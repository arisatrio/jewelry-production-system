<?php

namespace App\Http\Requests;

use App\Models\MsShape;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDiamondCrtMatrixRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.shape_id' => [
                'required',
                'integer',
                Rule::exists(MsShape::class, 'row_id')->where('is_deleted', 0),
            ],
            'rows.*.crt_min' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:99999.999'],
            'rows.*.crt_max' => ['required', 'numeric', 'decimal:0,3', 'max:99999.999', 'gte:rows.*.crt_min'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Matrix CRT minimal berisi satu range.',
            'rows.*.shape_id.required' => 'Shape wajib dipilih.',
            'rows.*.shape_id.exists' => 'Shape tidak valid.',
            'rows.*.crt_min.required' => 'CRT min wajib diisi.',
            'rows.*.crt_min.numeric' => 'CRT min harus berupa angka.',
            'rows.*.crt_min.decimal' => 'CRT min maksimal 3 angka desimal.',
            'rows.*.crt_min.min' => 'CRT min tidak boleh negatif.',
            'rows.*.crt_max.required' => 'CRT max wajib diisi.',
            'rows.*.crt_max.numeric' => 'CRT max harus berupa angka.',
            'rows.*.crt_max.decimal' => 'CRT max maksimal 3 angka desimal.',
            'rows.*.crt_max.gte' => 'CRT max harus lebih besar atau sama dengan CRT min.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var list<array{shape_id: int|string, crt_min: float|string, crt_max: float|string}> $rows */
            $rows = $this->input('rows', []);
            $rangesByShape = [];

            foreach ($rows as $index => $row) {
                $rangesByShape[(int) $row['shape_id']][] = [
                    'index' => $index,
                    'min' => (float) $row['crt_min'],
                    'max' => (float) $row['crt_max'],
                ];
            }

            foreach ($rangesByShape as $ranges) {
                usort($ranges, fn (array $left, array $right): int => $left['min'] <=> $right['min']);

                for ($position = 1; $position < count($ranges); $position++) {
                    $previous = $ranges[$position - 1];
                    $current = $ranges[$position];

                    if ($current['min'] <= $previous['max']) {
                        $validator->errors()->add(
                            "rows.{$current['index']}.crt_min",
                            'Range bertumpuk dengan range lain pada shape yang sama.',
                        );
                    }
                }
            }
        });
    }
}
