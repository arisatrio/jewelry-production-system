<?php

namespace App\Support;

class MsItemVarianceStoneCalculator
{
    /**
     * Calculate total carat from pcs × carat per pcs.
     */
    public static function totalCarat(?int $pcs, int|float|string|null $caratPerPcs): ?string
    {
        if ($pcs === null || $caratPerPcs === null || $caratPerPcs === '') {
            return null;
        }

        return number_format($pcs * (float) $caratPerPcs, 3, '.', '');
    }
}
