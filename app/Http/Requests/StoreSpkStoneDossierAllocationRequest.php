<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpkStoneDossierAllocationRequest extends FormRequest
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
            'diamond_id' => ['required', 'integer', 'min:1'],
            'reserved_pcs' => ['sometimes', 'numeric', 'in:1'],
            'reserved_crt' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
