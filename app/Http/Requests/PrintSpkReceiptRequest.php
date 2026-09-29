<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintSpkReceiptRequest extends FormRequest
{
    public const LOCATIONS = ['Head Office', 'Workshop', 'Store'];

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
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'dari' => ['nullable', 'string', Rule::in(self::LOCATIONS)],
            'untuk' => ['nullable', 'string', Rule::in(self::LOCATIONS), 'different:dari'],
            'diserahkan_oleh' => $this->activeEmployeeRules(),
            'diketahui_oleh' => $this->activeEmployeeRules(),
            'diterima_oleh' => $this->activeEmployeeRules(),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function activeEmployeeRules(): array
    {
        return [
            'nullable',
            'string',
            'max:150',
            Rule::exists(Employee::class, 'nama_lengkap')
                ->where('status', Employee::STATUS_ACTIVE)
                ->where('is_deleted', 0),
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
            'ids.max' => 'Maksimal 100 SPK per tanda terima.',
            'dari.in' => 'Pilihan Dari tidak valid.',
            'untuk.in' => 'Pilihan Untuk tidak valid.',
            'untuk.different' => 'Untuk tidak boleh sama dengan Dari.',
        ];
    }
}
