<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdvanceSpkChainFulfillmentRequest;
use App\Models\SpkChain;
use App\Support\ChainStockService;
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
        SpkChain $spkChain,
        ChainStockService $chainStock,
        SpkChainFulfillment $fulfillment,
    ): JsonResponse {
        abort_if($spkChain->is_deleted === 1, 404);

        $spkChain->loadMissing(['msChain', 'production']);

        if ($spkChain->mschain_id === null || $spkChain->msChain === null) {
            return response()->json([
                'status' => true,
                'data' => [
                    'allocation' => $fulfillment->resolve($spkChain),
                    'units' => [],
                ],
            ]);
        }

        abort_if($spkChain->msChain->is_deleted === 1, 404);

        return response()->json([
            'status' => true,
            'data' => [
                'allocation' => $fulfillment->resolve($spkChain),
                'units' => $chainStock->listInStockUnits($spkChain->msChain),
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
