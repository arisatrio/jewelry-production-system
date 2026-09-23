<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreStoneTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'transaction_kind' => trim((string) $this->input('transaction_kind')),
            'stone_id' => $this->filled('stone_id')
                ? $this->integer('stone_id')
                : null,
            'pcs' => $this->filled('pcs')
                ? str_replace(',', '.', trim((string) $this->input('pcs')))
                : null,
            'crt' => $this->filled('crt')
                ? str_replace(',', '.', trim((string) $this->input('crt')))
                : null,
            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transaction_kind' => [
                'required',
                'string',
                Rule::in(['addition', 'deduction']),
            ],
            'stone_id' => ['required', 'integer', 'min:1'],
            'pcs' => ['required', 'numeric', 'gt:0'],
            'crt' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! Schema::connection('third')->hasTable('msstone')) {
                    $validator->errors()->add('stone_id', 'Master batu tidak tersedia.');

                    return;
                }

                $stoneId = $this->integer('stone_id');

                if ($stoneId <= 0) {
                    return;
                }

                $exists = DB::connection('third')
                    ->table('msstone')
                    ->where('row_id', $stoneId)
                    ->where('is_deleted', 0)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add('stone_id', 'Batu tidak valid.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_kind.required' => 'Jenis transaksi wajib dipilih.',
            'transaction_kind.in' => 'Jenis transaksi tidak valid.',
            'stone_id.required' => 'Batu wajib dipilih.',
            'pcs.required' => 'Jumlah pcs wajib diisi.',
            'pcs.numeric' => 'Jumlah pcs harus berupa angka.',
            'pcs.gt' => 'Jumlah pcs harus lebih besar dari nol.',
            'crt.numeric' => 'CRT harus berupa angka.',
            'notes.max' => 'Catatan maksimal 100 karakter.',
        ];
    }
}
