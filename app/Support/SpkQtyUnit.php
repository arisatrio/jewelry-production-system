<?php

namespace App\Support;

class SpkQtyUnit
{
    public const UNIT_PCS = 'Pcs';

    public const UNIT_PAIR = 'Pasang';

    public const UNIT_HALF_PAIR = 'Setengah Pasang';

    /**
     * @return list<string>
     */
    public static function units(): array
    {
        return [
            self::UNIT_PCS,
            self::UNIT_PAIR,
            self::UNIT_HALF_PAIR,
        ];
    }

    /**
     * @return list<array{value: string, label: string, qty: int, satuan: string}>
     */
    public static function options(): array
    {
        return [
            [
                'value' => self::optionValue(1, self::UNIT_PCS),
                'label' => '1 Pcs',
                'qty' => 1,
                'satuan' => self::UNIT_PCS,
            ],
            [
                'value' => self::optionValue(1, self::UNIT_PAIR),
                'label' => '1 Pasang',
                'qty' => 1,
                'satuan' => self::UNIT_PAIR,
            ],
            [
                'value' => self::optionValue(1, self::UNIT_HALF_PAIR),
                'label' => '1/2 Pasang',
                'qty' => 1,
                'satuan' => self::UNIT_HALF_PAIR,
            ],
        ];
    }

    public static function optionValue(int $qty, string $satuan): string
    {
        return $qty.'|'.trim($satuan);
    }

    /**
     * @return array{qty: int, satuan: string}|null
     */
    public static function parseOptionValue(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach (self::options() as $option) {
            if ($option['value'] === $value) {
                return [
                    'qty' => $option['qty'],
                    'satuan' => $option['satuan'],
                ];
            }
        }

        $parts = explode('|', $value, 2);

        if (count($parts) !== 2) {
            return null;
        }

        $qty = (int) $parts[0];
        $satuan = trim($parts[1]);

        if ($qty < 1 || $satuan === '') {
            return null;
        }

        return [
            'qty' => $qty,
            'satuan' => $satuan,
        ];
    }

    public static function optionValueFor(?int $qty, ?string $satuan): string
    {
        $normalizedQty = max(1, (int) ($qty ?? 1));
        $normalizedSatuan = filled($satuan) ? trim((string) $satuan) : self::UNIT_PCS;

        $candidate = self::optionValue($normalizedQty, $normalizedSatuan);

        foreach (self::options() as $option) {
            if ($option['value'] === $candidate) {
                return $candidate;
            }
        }

        return $candidate;
    }

    public static function label(?int $qty, ?string $satuan): string
    {
        if ($qty === null) {
            return '-';
        }

        $normalizedSatuan = filled($satuan) ? trim((string) $satuan) : self::UNIT_PCS;
        $candidate = self::optionValue(max(1, $qty), $normalizedSatuan);

        foreach (self::options() as $option) {
            if ($option['value'] === $candidate) {
                return $option['label'];
            }
        }

        return trim($qty.' '.$normalizedSatuan);
    }

    public static function storageLabel(?int $qty, ?string $satuan): string
    {
        if ($qty === null) {
            return '-';
        }

        $normalizedSatuan = filled($satuan) ? trim((string) $satuan) : self::UNIT_PCS;

        return trim($qty.' '.$normalizedSatuan);
    }
}
