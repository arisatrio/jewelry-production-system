<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckSpkStoneStockRequest;
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

    /**
     * Cek stok batu dari input form SPK (create/edit) sebelum baris batu disimpan.
     */
    public function check(CheckSpkStoneStockRequest $request, SpkStoneStockChecker $stockChecker): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'status' => true,
            'data' => $stockChecker->matchingFromAttributes(
                (int) $validated['shape_id'],
                (int) $validated['pcs'],
                (float) ($validated['carat_per_pcs'] ?? 0),
                filled($validated['size'] ?? null) ? (string) $validated['size'] : null,
            ),
        ]);
    }
}
