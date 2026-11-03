<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdvanceSpkStoneFulfillmentRequest;
use App\Http\Requests\CheckSpkStoneStockRequest;
use App\Http\Requests\StoreSpkStoneDossierAllocationRequest;
use App\Http\Requests\StoreSpkStoneMicroAllocationRequest;
use App\Models\SpkStone;
use App\Support\SpkStoneAllocationService;
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

    public function storeDossierAllocation(
        StoreSpkStoneDossierAllocationRequest $request,
        SpkStone $spkStone,
        SpkStoneAllocationService $allocations,
    ): RedirectResponse {
        abort_if($spkStone->is_deleted === 1, 404);

        $allocations->allocateDossier(
            $spkStone,
            (int) $request->validated('diamond_id'),
            $this->actorName($request),
            filled($request->validated('reserved_crt') ?? null)
                ? (float) $request->validated('reserved_crt')
                : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Batu dossier berhasil dialokasikan ke SPK.',
        ]);

        return back();
    }

    public function storeMicroAllocation(
        StoreSpkStoneMicroAllocationRequest $request,
        SpkStone $spkStone,
        SpkStoneAllocationService $allocations,
    ): RedirectResponse {
        abort_if($spkStone->is_deleted === 1, 404);

        $validated = $request->validated();

        $allocations->allocateMicro(
            $spkStone,
            (int) $validated['msstone_id'],
            $this->actorName($request),
            (float) $validated['reserved_pcs'],
            filled($validated['reserved_crt'] ?? null)
                ? (float) $validated['reserved_crt']
                : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Stok batu mikro berhasil dialokasikan ke SPK.',
        ]);

        return back();
    }

    public function revokeAllocationItem(
        SpkStone $spkStone,
        int $allocationRowId,
        SpkStoneAllocationService $allocations,
        Request $request,
    ): RedirectResponse {
        abort_if($spkStone->is_deleted === 1, 404);

        $allocations->revokeAllocation(
            $spkStone,
            $allocationRowId,
            $this->actorName($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Alokasi batu dihapus.',
        ]);

        return back();
    }

    public function revokeAllocations(
        SpkStone $spkStone,
        SpkStoneAllocationService $allocations,
        Request $request,
    ): RedirectResponse {
        abort_if($spkStone->is_deleted === 1, 404);

        $allocations->revokeAll($spkStone, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Alokasi batu dibatalkan.',
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
