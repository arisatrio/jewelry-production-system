<?php

namespace App\Http\Requests;

use App\Support\SpkProcessMapper;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpkProcessSlaRequest extends FormRequest
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
        $processKeys = array_column(app(SpkProcessMapper::class)->tabs(), 'key');

        return [
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.process_key' => [
                'required',
                'string',
                Rule::in($processKeys),
            ],
            'targets.*.working_days' => [
                'required',
                'integer',
                'min:0',
                'max:365',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'targets.required' => 'Daftar target SLA wajib diisi.',
            'targets.*.process_key.required' => 'Proses wajib dipilih.',
            'targets.*.process_key.in' => 'Proses tidak valid.',
            'targets.*.working_days.required' => 'Target hari kerja wajib diisi.',
            'targets.*.working_days.integer' => 'Target hari kerja harus berupa angka.',
            'targets.*.working_days.min' => 'Target hari kerja minimal 0.',
            'targets.*.working_days.max' => 'Target hari kerja maksimal 365.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var list<array{process_key?: string}> $targets */
            $targets = $this->input('targets', []);
            $keys = array_column($targets, 'process_key');
            $expected = array_column(app(SpkProcessMapper::class)->tabs(), 'key');

            if (count($keys) !== count(array_unique($keys))) {
                $validator->errors()->add('targets', 'Setiap proses hanya boleh muncul sekali.');
            }

            $missing = array_values(array_diff($expected, $keys));

            if ($missing !== []) {
                $validator->errors()->add(
                    'targets',
                    'Semua proses produksi wajib memiliki target SLA.',
                );
            }
        });
    }
}
