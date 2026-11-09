<?php

namespace App\Http\Requests;

use App\Models\SpkDiamond;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpkDiamondRequest extends FormRequest
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
            'spk_row_id' => ['required', 'integer', 'min:1'],
            'doc_no' => [
                'required',
                'string',
                'max:50',
                Rule::unique(SpkDiamond::class, 'doc_no')->where('is_deleted', 0),
            ],
            'tanggal' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doc_no.required' => 'No SPK Pasang Batu wajib diisi.',
            'doc_no.unique' => 'No SPK Pasang Batu sudah dipakai.',
            'tanggal.required' => 'Tanggal wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('doc_no') && is_string($this->doc_no)) {
            $this->merge([
                'doc_no' => trim($this->doc_no),
            ]);
        }
    }
}
