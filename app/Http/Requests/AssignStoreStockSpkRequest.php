<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignStoreStockSpkRequest extends FormRequest
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
        $this->merge([
            'doc_no' => strtoupper(trim((string) $this->input('doc_no'))),
            'spk_no' => trim((string) $this->input('spk_no')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'doc_no' => ['required', 'string', 'max:50', 'regex:/^RS-[A-Z0-9]+$/'],
            'spk_no' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doc_no.required' => 'Nomor permintaan stok wajib diisi.',
            'doc_no.regex' => 'Nomor permintaan stok tidak valid.',
            'spk_no.required' => 'Nomor SPK wajib dipilih.',
        ];
    }
}
