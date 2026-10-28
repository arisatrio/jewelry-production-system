<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintSpkChainReceiptRequest extends FormRequest
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
            'spk_row_id' => ['required', 'integer', 'min:1'],
            'chain_line_ids' => ['nullable', 'array', 'max:50'],
            'chain_line_ids.*' => ['integer', 'distinct', 'min:1'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'dari' => ['nullable', 'string', Rule::in(self::LOCATIONS)],
            'untuk' => ['nullable', 'string', Rule::in(self::LOCATIONS), 'different:dari'],
            'diserahkan_oleh' => $this->activeEmployeeRules(),
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
}
