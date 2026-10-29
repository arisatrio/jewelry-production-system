<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdvanceSpkStoneFulfillmentRequest;
use App\Http\Requests\CheckSpkStoneStockRequest;
use App\Models\SpkStone;
use App\Support\SpkStoneFulfillment;
use App\Support\SpkStoneStockChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

    /**
     * Tandai baris batu SPK sedang dipesan.
     */
    public function advanceFulfillment(
        AdvanceSpkStoneFulfillmentRequest $request,
        SpkStone $spkStone,
        SpkStoneFulfillment $fulfillment,
    ): RedirectResponse {
        abort_if($spkStone->is_deleted === 1, 404);

        $allocation = $fulfillment->advance(
            $spkStone,
            (string) $request->validated('action'),
            $this->actorName($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Status batu diperbarui: '.$allocation['label'],
        ]);

        return back();
    }

    private function actorName(Request $request): string
    {
        $user = $request->user();

        if ($user === null) {
            return 'system';
        }

        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : (string) $user->email;
    }
}
