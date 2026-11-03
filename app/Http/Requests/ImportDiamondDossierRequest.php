<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportDiamondDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:5120',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File Excel wajib diunggah.',
            'file.file' => 'File Excel tidak valid.',
            'file.mimes' => 'File harus berformat .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
