<?php

namespace App\Http\Requests;

use App\Models\MsShape;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckSpkStoneStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shape_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists(MsShape::class, 'row_id')->where('is_deleted', 0),
            ],
            'pcs' => ['required', 'integer', 'min:1'],
            'carat_per_pcs' => ['nullable', 'numeric', 'min:0'],
            'size' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shape_id.required' => 'Bentuk batu wajib diisi.',
            'shape_id.exists' => 'Bentuk batu tidak valid.',
            'pcs.required' => 'Jumlah butir wajib diisi.',
            'pcs.min' => 'Jumlah butir minimal 1.',
        ];
    }
}
