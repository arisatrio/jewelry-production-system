<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdvanceSpkChainFulfillmentRequest;
use App\Models\SpkChain;
use App\Support\ChainStockService;
use App\Support\GoldColorOptions;
use App\Support\SpkChainFulfillment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SpkChainStockController extends Controller
{
    /**
     * Unit chain siap pakai untuk alokasi ke SPK (modal alokasi di detail SPK).
     */
    public function allocationUnits(
        Request $request,
        SpkChain $spkChain,
        ChainStockService $chainStock,
        SpkChainFulfillment $fulfillment,
    ): JsonResponse {
        abort_if($spkChain->is_deleted === 1, 404);

        $spkChain->loadMissing(['msChain', 'production']);

        $color = trim((string) $request->query('color', ''));
        $colorFilter = $color !== '' ? $color : null;

        if ($spkChain->mschain_id === null || $spkChain->msChain === null) {
            return response()->json([
                'status' => true,
                'data' => [
                    'allocation' => $fulfillment->resolve($spkChain),
                    'units' => [],
                    'colorOptions' => GoldColorOptions::all(),
                    'suggestedColor' => filled($spkChain->color)
                        ? (string) $spkChain->color
                        : null,
                ],
            ]);
        }

        abort_if($spkChain->msChain->is_deleted === 1, 404);

        $colorOptions = array_values(array_unique(array_merge(
            GoldColorOptions::all(),
            $chainStock->distinctInStockColors($spkChain->msChain),
        )));
        sort($colorOptions);

        return response()->json([
            'status' => true,
            'data' => [
                'allocation' => $fulfillment->resolve($spkChain),
                'units' => $chainStock->listInStockUnits(
                    $spkChain->msChain,
                    color: $colorFilter,
                ),
                'colorOptions' => $colorOptions,
                'suggestedColor' => filled($spkChain->color)
                    ? (string) $spkChain->color
                    : null,
            ],
        ]);
    }

    /**
     * Lanjutkan status fulfillment rantai (kirim / terima workshop / terpasang).
     */
    public function advanceFulfillment(
        AdvanceSpkChainFulfillmentRequest $request,
        SpkChain $spkChain,
        SpkChainFulfillment $fulfillment,
    ): RedirectResponse {
        abort_if($spkChain->is_deleted === 1, 404);

        $allocation = $fulfillment->advance(
            $spkChain,
            (string) $request->validated('action'),
            $this->actorName($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Status rantai diperbarui: '.$allocation['label'],
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
