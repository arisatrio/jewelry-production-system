<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdvanceSpkComponentFulfillmentRequest;
use App\Models\SpkComponent;
use App\Support\ComponentStockService;
use App\Support\GoldColorOptions;
use App\Support\SpkComponentFulfillment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SpkComponentStockController extends Controller
{
    /**
     * Unit komponen siap pakai untuk alokasi ke SPK (modal alokasi di detail SPK).
     */
    public function allocationUnits(
        SpkComponent $spkKomponen,
        ComponentStockService $chainStock,
        SpkComponentFulfillment $fulfillment,
    ): JsonResponse {
        abort_if($spkKomponen->is_deleted === 1, 404);

        $spkKomponen->loadMissing(['msComponent', 'production']);

        if ($spkKomponen->mscomponent_id === null || $spkKomponen->msComponent === null) {
            return response()->json([
                'status' => true,
                'data' => [
                    'allocation' => $fulfillment->resolve($spkKomponen),
                    'units' => [],
                    'colorOptions' => GoldColorOptions::all(),
                    'suggestedColor' => filled($spkKomponen->color)
                        ? (string) $spkKomponen->color
                        : null,
                ],
            ]);
        }

        abort_if($spkKomponen->msComponent->is_deleted === 1, 404);

        $colorOptions = array_values(array_unique(array_merge(
            GoldColorOptions::all(),
            $chainStock->distinctInStockColors($spkKomponen->msComponent),
        )));
        sort($colorOptions);

        return response()->json([
            'status' => true,
            'data' => [
                'allocation' => $fulfillment->resolve($spkKomponen),
                'units' => $chainStock->listInStockUnitsForSpkAllocation($spkKomponen),
                'colorOptions' => $colorOptions,
                'suggestedColor' => filled($spkKomponen->color)
                    ? (string) $spkKomponen->color
                    : null,
            ],
        ]);
    }

    /**
     * Batalkan alokasi unit komponen pada baris SPK (kembalikan ke stok).
     */
    public function revokeAllocation(
        SpkComponent $spkKomponen,
        SpkComponentFulfillment $fulfillment,
        Request $request,
    ): RedirectResponse {
        abort_if($spkKomponen->is_deleted === 1, 404);

        $fulfillment->revokeAllocation($spkKomponen, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Alokasi komponen dibatalkan.',
        ]);

        return back();
    }

    /**
     * Lanjutkan status fulfillment komponen (kirim / terima workshop / terpasang).
     */
    public function advanceFulfillment(
        AdvanceSpkComponentFulfillmentRequest $request,
        SpkComponent $spkKomponen,
        SpkComponentFulfillment $fulfillment,
    ): RedirectResponse {
        abort_if($spkKomponen->is_deleted === 1, 404);

        $allocation = $fulfillment->advance(
            $spkKomponen,
            (string) $request->validated('action'),
            $this->actorName($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Status komponen diperbarui: '.$allocation['label'],
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
