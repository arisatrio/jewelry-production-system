<?php

namespace App\Http\Requests;

use App\Models\MsPosition;
use App\Models\MsShape;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMsItemVarianceStoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'shape_id' => $this->filled('shape_id') ? $this->integer('shape_id') : null,
            'position_id' => $this->filled('position_id') ? $this->integer('position_id') : null,
            'position_nama' => $this->filled('position_nama')
                ? trim((string) $this->input('position_nama'))
                : null,
            'pcs' => $this->filled('pcs') ? $this->integer('pcs') : null,
            'carat_per_pcs' => $this->filled('carat_per_pcs') ? $this->input('carat_per_pcs') : null,
            'size' => $this->filled('size')
                ? str_replace(['×', 'X'], 'x', trim((string) $this->input('size')))
                : null,
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
            'shape_id' => [
                'nullable',
                'integer',
                Rule::exists(MsShape::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0),
                ),
            ],
            'position_id' => [
                'nullable',
                'integer',
                Rule::exists(MsPosition::class, 'id'),
            ],
            'position_nama' => [
                'nullable',
                'string',
                'max:255',
            ],
            'pcs' => ['nullable', 'integer', 'min:0'],
            'carat_per_pcs' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'size' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^\d+([.,]\d+)?([xX×]\d+([.,]\d+)?)?$/u',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shape_id.exists' => 'Shape tidak valid.',
            'position_id.exists' => 'Posisi tidak valid.',
            'size.regex' => 'Ukuran batu harus angka (mm) atau format PxL (contoh: 3.50x2.10).',
        ];
    }
}
