<?php

namespace App\Http\Requests;

use App\Models\FinishingHandmade;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinishingShrinkAllowanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $cells = $this->input('cells');

        if (! is_array($cells)) {
            return;
        }

        $normalized = [];

        foreach ($cells as $cell) {
            if (is_array($cell) && array_key_exists('allowance_percent', $cell)) {
                $cell['allowance_percent'] = str_replace(
                    ',',
                    '.',
                    trim((string) $cell['allowance_percent']),
                );
            }

            $normalized[] = $cell;
        }

        $this->merge([
            'cells' => $normalized,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cells' => ['required', 'array', 'min:1'],
            'cells.*.work_type' => [
                'required',
                'string',
                Rule::in(FinishingHandmade::workTypeOptions()),
            ],
            'cells.*.item_category' => [
                'required',
                'string',
                Rule::in(FinishingHandmade::itemCategoryOptions()),
            ],
            'cells.*.allowance_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'decimal:0,2',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cells.required' => 'Matrix jatah susut wajib diisi.',
            'cells.*.work_type.required' => 'Jenis pekerjaan wajib diisi.',
            'cells.*.work_type.in' => 'Jenis pekerjaan tidak valid.',
            'cells.*.item_category.required' => 'Kategori barang wajib diisi.',
            'cells.*.item_category.in' => 'Kategori barang tidak valid.',
            'cells.*.allowance_percent.required' => 'Jatah susut wajib diisi.',
            'cells.*.allowance_percent.numeric' => 'Jatah susut harus berupa angka.',
            'cells.*.allowance_percent.min' => 'Jatah susut minimal 0%.',
            'cells.*.allowance_percent.max' => 'Jatah susut maksimal 100%.',
            'cells.*.allowance_percent.decimal' => 'Jatah susut maksimal 2 angka di belakang koma.',
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var list<array{work_type?: string, item_category?: string}> $cells */
                $cells = $this->input('cells', []);
                $keys = [];

                foreach ($cells as $cell) {
                    $workType = (string) ($cell['work_type'] ?? '');
                    $itemCategory = (string) ($cell['item_category'] ?? '');

                    if ($workType === '' || $itemCategory === '') {
                        continue;
                    }

                    $keys[] = $workType.'|'.$itemCategory;
                }

                if (count($keys) !== count(array_unique($keys))) {
                    $validator->errors()->add('cells', 'Setiap kombinasi jenis pekerjaan dan kategori barang hanya boleh muncul sekali.');
                }

                $expected = [];

                foreach (FinishingHandmade::workTypeOptions() as $workType) {
                    foreach (FinishingHandmade::itemCategoryOptions() as $itemCategory) {
                        $expected[] = $workType.'|'.$itemCategory;
                    }
                }

                $missing = array_diff($expected, $keys);

                if ($missing !== []) {
                    $validator->errors()->add(
                        'cells',
                        'Semua jenis pekerjaan dan kategori barang wajib memiliki jatah susut.',
                    );
                }
            },
        ];
    }
}
