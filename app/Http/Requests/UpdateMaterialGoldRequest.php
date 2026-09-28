<?php

namespace App\Http\Requests;

use App\Models\MaterialGold;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialGoldRequest extends FormRequest
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
        /** @var MaterialGold $materialGold */
        $materialGold = $this->route('materialGold');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(MaterialGold::class, 'name')
                    ->where(fn ($query) => $query->where('is_deleted', 0))
                    ->ignore($materialGold->row_id, 'row_id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama bahan emas wajib diisi.',
            'name.unique' => 'Nama bahan emas sudah digunakan.',
        ];
    }
}
