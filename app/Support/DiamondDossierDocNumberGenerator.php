<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiamondDossierDocNumberGenerator
{
    /**
     * Generate next dossier stock code in legacy format D0007649.
     */
    public function generate(): string
    {
        if (! Schema::connection('third')->hasTable('trdiamond')) {
            return 'D0000001';
        }

        $candidates = DB::connection('third')
            ->table('trdiamond')
            ->where('doc_no', 'like', 'D%')
            ->orderByDesc('doc_no')
            ->limit(100)
            ->pluck('doc_no');

        $max = 0;

        foreach ($candidates as $docNo) {
            if (! is_string($docNo)) {
                continue;
            }

            if (preg_match('/^D(\d+)$/', $docNo, $matches) !== 1) {
                continue;
            }

            $max = max($max, (int) $matches[1]);
        }

        return sprintf('D%07d', $max + 1);
    }
}
