<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveFakturBarangJadiReceiptRequest extends FormRequest
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
            'diterima_oleh' => [
                'required',
                'string',
                'max:150',
                Rule::exists(Employee::class, 'nama_lengkap')
                    ->where('status', Employee::STATUS_ACTIVE)
                    ->where('is_deleted', 0),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'diterima_oleh.required' => 'Pilih nama penerima faktur.',
        ];
    }
}
