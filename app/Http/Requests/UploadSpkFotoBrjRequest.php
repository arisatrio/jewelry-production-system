<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadSpkFotoBrjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'foto' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Pilih file gambar foto barang jadi.',
            'foto.mimes' => 'Format gambar harus JPG, PNG, WEBP, atau GIF.',
            'foto.max' => 'Ukuran gambar maksimal 10 MB.',
        ];
    }
}
