<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreGoldMaterialTransactionRequest extends FormRequest
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
            'transaction_kind' => trim((string) $this->input('transaction_kind')),
            'materialgold_id' => $this->filled('materialgold_id')
                ? $this->integer('materialgold_id')
                : null,
            'weight' => $this->filled('weight')
                ? str_replace(',', '.', trim((string) $this->input('weight')))
                : null,
            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
            'materialgold_id' => ['required', 'integer', 'min:1'],
            'weight' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
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
                if (! Schema::connection('third')->hasTable('msmaterialgold')) {
                    $validator->errors()->add('materialgold_id', 'Master bahan emas tidak tersedia.');

                    return;
                }

                $materialId = $this->integer('materialgold_id');

                if ($materialId <= 0) {
                    return;
                }

                $exists = DB::connection('third')
                    ->table('msmaterialgold')
                    ->where('row_id', $materialId)
                    ->where('is_deleted', 0)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add('materialgold_id', 'Bahan emas tidak valid.');
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
            'materialgold_id.required' => 'Bahan emas wajib dipilih.',
            'weight.required' => 'Berat wajib diisi.',
            'weight.numeric' => 'Berat harus berupa angka.',
            'weight.gt' => 'Berat harus lebih besar dari nol.',
            'weight.decimal' => 'Berat hanya boleh memiliki maksimal 2 angka desimal.',
            'notes.max' => 'Catatan maksimal 100 karakter.',
        ];
    }
}
