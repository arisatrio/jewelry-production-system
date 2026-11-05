<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSpkStonePurchaseStatusRequest;
use App\Models\SpkStone;
use App\Support\SpkStoneFulfillment;
use App\Support\SpkStoneOrderingList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class SpkStonePurchaseController extends Controller
{
    public function index(Request $request, SpkStoneOrderingList $orderingList): Response
    {
        $filters = Validator::make($request->only([
            'search',
            'per_page',
            'page',
        ]), [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page = (int) ($filters['page'] ?? 1);

        return Inertia::render('inventory/pembelian-batu/index', [
            'stones' => $orderingList->paginate($search, $perPage, $page),
            'listAvailable' => $orderingList->listAvailable(),
            'filters' => [
                'search' => $search,
                'per_page' => in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25,
            ],
        ]);
    }

    public function updateStatus(
        UpdateSpkStonePurchaseStatusRequest $request,
        SpkStone $spkStone,
        SpkStoneFulfillment $fulfillment,
    ): RedirectResponse {
        abort_if($spkStone->is_deleted === 1, 404);

        $allocation = $fulfillment->updatePurchaseStatus(
            $spkStone,
            (string) $request->validated('status'),
            $this->actorName($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Status batu diperbarui: '.$allocation['label'],
        ]);

        return back();
    }

    public function destroy(
        Request $request,
        SpkStone $spkStone,
        SpkStoneFulfillment $fulfillment,
    ): RedirectResponse {
        abort_if($spkStone->is_deleted === 1, 404);

        $fulfillment->cancelPurchase($spkStone, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pembelian batu dihapus dari daftar.',
        ]);

        return back();
    }

    public function restock(
        Request $request,
        SpkStone $spkStone,
        SpkStoneFulfillment $fulfillment,
    ): RedirectResponse {
        abort_if($spkStone->is_deleted === 1, 404);

        $fulfillment->restockPurchase($spkStone, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Restok batu berhasil. Stok mikro ditambahkan dan baris dihapus dari pembelian.',
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
