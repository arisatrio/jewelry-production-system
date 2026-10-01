<?php

namespace App\Http\Controllers;

use App\Models\SpkStone;
use App\Support\SpkStoneStockChecker;
use Illuminate\Http\JsonResponse;

class SpkStoneStockController extends Controller
{
    /**
     * Daftar stok batu (dossier / mikro) yang cocok dengan satu baris batu SPK (modal / lazy load).
     */
    public function show(SpkStone $spkStone, SpkStoneStockChecker $stockChecker): JsonResponse
    {
        abort_if($spkStone->is_deleted === 1, 404);

        $spkStone->loadMissing('shape');

        return response()->json([
            'status' => true,
            'data' => $stockChecker->matchingStones($spkStone),
        ]);
    }
}
