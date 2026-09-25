<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateCoranStatusRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
            'action' => [
                'required',
                'string',
                Rule::in(['submit', 'manager_approve', 'complete', 'delete']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu dokumen coran.',
            'ids.min' => 'Pilih minimal satu dokumen coran.',
            'action.in' => 'Aksi status tidak valid.',
        ];
    }
}
