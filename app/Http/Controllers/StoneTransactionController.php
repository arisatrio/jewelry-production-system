<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoneTransactionRequest;
use App\Support\StoneLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StoneTransactionController extends Controller
{
    public function index(Request $request, StoneLedger $ledger): Response
    {
        $filters = Validator::make($request->only([
            'search',
            'date_from',
            'date_to',
            'per_page',
        ]), [
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer'],
        ])->validate();

        $search = trim((string) ($filters['search'] ?? ''));
        $dateFrom = filled($filters['date_from'] ?? null)
            ? (string) $filters['date_from']
            : null;
        $dateTo = filled($filters['date_to'] ?? null)
            ? (string) $filters['date_to']
            : null;
        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $activePeriod = $ledger->activePeriod();

        return Inertia::render('inventory/transaksi-batu/index', [
            'transactions' => $activePeriod === null
                ? $this->emptyPaginator($perPage)
                : $ledger->paginate(
                    $activePeriod['id'],
                    $search,
                    $dateFrom,
                    $dateTo,
                    $perPage,
                ),
            'total' => $activePeriod === null
                ? '0'
                : $ledger->total(
                    $activePeriod['id'],
                    $search,
                    $dateFrom,
                    $dateTo,
                ),
            'activePeriod' => $activePeriod,
            'stoneOptions' => $ledger->stoneOptions($activePeriod['id'] ?? null),
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(
        StoreStoneTransactionRequest $request,
        StoneLedger $ledger,
    ): RedirectResponse {
        $activePeriod = $ledger->activePeriod();

        if ($activePeriod === null) {
            throw ValidationException::withMessages([
                'period' => 'Periode batu aktif tidak tersedia.',
            ]);
        }

        $validated = $request->validated();

        $ledger->store(
            $activePeriod['id'],
            (string) $validated['transaction_kind'],
            (int) $validated['stone_id'],
            (string) $validated['pcs'],
            filled($validated['crt'] ?? null) ? (string) $validated['crt'] : null,
            filled($validated['notes'] ?? null) ? (string) $validated['notes'] : null,
            $request->user()?->name ?? 'system',
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['transaction_kind'] === 'addition'
                ? 'Penambahan batu berhasil disimpan.'
                : 'Pengurangan batu berhasil disimpan.',
        ]);

        return back();
    }

    /**
     * @return array{
     *     data: array<int, mixed>,
     *     current_page: int,
     *     last_page: int,
     *     per_page: int,
     *     total: int
     * }
     */
    private function emptyPaginator(int $perPage): array
    {
        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];
    }
}
