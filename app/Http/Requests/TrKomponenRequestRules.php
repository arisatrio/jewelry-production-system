<?php

namespace App\Http\Requests;

class TrKomponenRequestRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'entry_date' => ['nullable', 'date'],
            'out_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'jenis_komponen' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:100'],
            'item_code' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:100'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'spk_id' => ['nullable', 'integer', 'min:0'],
            'is_used' => ['nullable', 'integer', 'in:0,1'],
            'is_reserved' => ['nullable', 'integer', 'in:0,1'],
        ];
    }
}
