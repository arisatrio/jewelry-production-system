<?php

namespace App\Http\Requests;

use App\Support\SpkChainFulfillment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceSpkChainFulfillmentRequest extends FormRequest
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
            'action' => [
                'required',
                'string',
                Rule::in([
                    SpkChainFulfillment::ACTION_SEND_TO_WORKSHOP,
                    SpkChainFulfillment::ACTION_RECEIVE_WORKSHOP,
                    SpkChainFulfillment::ACTION_MARK_INSTALLED,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Aksi wajib dipilih.',
            'action.in' => 'Aksi tidak valid.',
        ];
    }
}
