<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachFakturBarangJadiSpkRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu SPK.',
            'ids.min' => 'Pilih minimal satu SPK.',
            'ids.max' => 'Maksimal 50 SPK per penambahan.',
        ];
    }
}
