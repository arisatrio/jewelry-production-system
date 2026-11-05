<?php

namespace App\Http\Requests;

use App\Support\StoneLedger;
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
            'crt' => ['required', 'numeric', 'gt:0'],
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
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('transaction_kind') !== 'deduction') {
                    return;
                }

                $ledger = app(StoneLedger::class);
                $activePeriod = $ledger->activePeriod();

                if ($activePeriod === null) {
                    return;
                }

                $stoneId = $this->integer('stone_id');
                $requestedPcs = (float) $this->input('pcs');
                $availablePcs = $ledger->availablePcsForStone(
                    $activePeriod['id'],
                    $stoneId,
                );

                if ($requestedPcs > $availablePcs + 0.0005) {
                    $validator->errors()->add(
                        'pcs',
                        'Jumlah pcs melebihi stok tersedia ('.$this->formatAvailablePcs($availablePcs).').',
                    );
                }
            },
        ];
    }

    private function formatAvailablePcs(float $value): string
    {
        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        return number_format($value, 2, ',', '.');
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
            'crt.required' => 'CRT wajib diisi.',
            'crt.numeric' => 'CRT harus berupa angka.',
            'crt.gt' => 'CRT harus lebih besar dari nol.',
            'notes.max' => 'Catatan maksimal 100 karakter.',
        ];
    }
}
