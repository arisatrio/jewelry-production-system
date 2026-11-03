<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyDiamondDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu batu dossier.',
            'ids.array' => 'Data batu dossier tidak valid.',
            'ids.min' => 'Pilih minimal satu batu dossier.',
            'ids.*.integer' => 'Data batu dossier tidak valid.',
            'ids.*.min' => 'Data batu dossier tidak valid.',
            'ids.*.distinct' => 'Terdapat batu dossier duplikat.',
        ];
    }

    /**
     * @return array<int, int>
     */
    public function diamondIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('ids');

        return array_values(array_map(intval(...), $ids));
    }
}
