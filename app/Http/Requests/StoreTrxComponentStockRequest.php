<?php

namespace App\Http\Requests;

use App\Support\ComponentStockService;
use App\Support\GoldColorOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrxComponentStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('supplier')) {
            $merge['supplier'] = trim((string) $this->input('supplier'));
        }

        if ($this->has('color')) {
            $color = trim((string) $this->input('color'));
            $merge['color'] = $color === '' ? null : $color;
        }

        if ($this->has('weight')) {
            $merge['weight'] = str_replace(',', '.', trim((string) $this->input('weight')));
        }

        if ($this->has('purchase_price')) {
            $purchasePrice = str_replace(',', '.', trim((string) $this->input('purchase_price')));
            $merge['purchase_price'] = $purchasePrice === '' ? null : $purchasePrice;
        }

        if ($this->has('component_size')) {
            $componentSize = str_replace(',', '.', trim((string) $this->input('component_size')));
            $merge['component_size'] = $componentSize === '' ? null : $componentSize;
        }

        $entryDate = trim((string) $this->input('entry_date', ''));
        $merge['entry_date'] = $entryDate === '' ? now()->toDateString() : $entryDate;

        $pcsRaw = trim((string) $this->input('pcs', '1'));
        $merge['pcs'] = $pcsRaw === '' ? 1 : $pcsRaw;

        if ($this->has('notes')) {
            $notes = trim((string) $this->input('notes'));
            $merge['notes'] = $notes === '' ? null : $notes;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'supplier' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'purchase_price' => ['nullable', 'numeric', 'gte:0', 'decimal:0,2'],
            'component_size' => ['nullable', 'numeric', 'gte:0', 'decimal:0,2', 'max:9999999999999.99'],
            'color' => ['required', 'string', Rule::in(GoldColorOptions::all())],
            'pcs' => ['required', 'integer', 'min:1', 'max:'.ComponentStockService::MAX_RECEIVE_PCS],
            'notes' => ['nullable', 'string', 'max:5000'],
            'return_to' => ['nullable', 'string', Rule::in(['index', 'show'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entry_date.required' => 'Tanggal masuk wajib diisi.',
            'entry_date.date' => 'Tanggal masuk tidak valid.',
            'supplier.required' => 'Supplier wajib diisi.',
            'weight.required' => 'Berat wajib diisi.',
            'weight.gt' => 'Berat harus lebih dari 0.',
            'purchase_price.gte' => 'Harga beli (USD) tidak boleh negatif.',
            'component_size.numeric' => 'Ukuran harus berupa angka.',
            'component_size.gte' => 'Ukuran tidak boleh negatif.',
            'color.required' => 'Warna wajib dipilih.',
            'color.in' => 'Warna tidak valid.',
            'pcs.required' => 'Jumlah pcs wajib diisi.',
            'pcs.integer' => 'Jumlah pcs harus bilangan bulat.',
            'pcs.min' => 'Jumlah pcs minimal 1.',
            'pcs.max' => 'Jumlah pcs maksimal '.ComponentStockService::MAX_RECEIVE_PCS.'.',
        ];
    }
}
