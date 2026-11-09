<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoldMaterialTransactionRequest;
use App\Support\GoldMaterialLedger;
use App\Support\GoldMaterialTransactionReport;
use App\Support\GoldMaterialTransactionWorkbookExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GoldMaterialTransactionController extends Controller
{
    public function index(Request $request, GoldMaterialLedger $ledger): Response
    {
        $filters = $this->validatedFilters($request, includePerPage: true);
        $search = $filters['search'];
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];
        $eventType = $filters['event_type'];
        $perPage = $filters['per_page'];
        $sort = $filters['sort'];
        $direction = $filters['direction'];
        $activePeriod = $ledger->activePeriod();

        return Inertia::render('inventory/transaksi-bahan-emas/index', [
            'transactions' => $activePeriod === null
                ? $this->emptyPaginator($perPage)
                : $ledger->paginate(
                    $activePeriod['id'],
                    $search,
                    $dateFrom,
                    $dateTo,
                    $eventType,
                    $perPage,
                    $sort,
                    $direction,
                ),
            'total' => $activePeriod === null
                ? '0.00'
                : $ledger->total(
                    $activePeriod['id'],
                    $search,
                    $dateFrom,
                    $dateTo,
                    $eventType,
                ),
            'activePeriod' => $activePeriod,
            'materialOptions' => $ledger->materialOptions($activePeriod['id'] ?? null),
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'event_type' => $eventType,
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'filterOptions' => [
                'sort' => GoldMaterialLedger::transactionSortOptions(),
                'direction' => GoldMaterialLedger::directionSortOptions(),
            ],
        ]);
    }

    public function export(
        Request $request,
        GoldMaterialLedger $ledger,
        GoldMaterialTransactionReport $report,
    ): BinaryFileResponse {
        $filters = $this->validatedFilters($request, includePerPage: false);
        $activePeriod = $ledger->activePeriod();

        $rows = $activePeriod === null
            ? []
            : $report->rows(
                $activePeriod['id'],
                $filters['search'],
                $filters['date_from'],
                $filters['date_to'],
                $filters['event_type'],
            );

        $fileNameParts = array_filter([
            'Laporan_Trans_Material_Gold',
            $activePeriod !== null
                ? Str::slug($activePeriod['label'])
                : null,
            $filters['event_type'] !== null
                ? Str::slug(match ($filters['event_type']) {
                    GoldMaterialLedger::TRANSACTION_EVENT_ENTRY => 'Penambahan',
                    GoldMaterialLedger::TRANSACTION_EVENT_OUT => 'Pemakaian',
                    default => $filters['event_type'],
                })
                : null,
            $filters['date_from'] !== null && $filters['date_to'] !== null
                ? "{$filters['date_from']}_{$filters['date_to']}"
                : ($filters['date_from'] ?? $filters['date_to']),
            $filters['search'] !== ''
                ? Str::slug(Str::limit($filters['search'], 40, ''))
                : null,
            now()->format('YmdHis'),
        ]);

        return Excel::download(
            new GoldMaterialTransactionWorkbookExport($rows),
            implode('_', $fileNameParts).'.xlsx',
        );
    }

    public function store(
        StoreGoldMaterialTransactionRequest $request,
        GoldMaterialLedger $ledger,
    ): RedirectResponse {
        $activePeriod = $ledger->activePeriod();

        if ($activePeriod === null) {
            throw ValidationException::withMessages([
                'period' => 'Periode bahan emas aktif tidak tersedia.',
            ]);
        }

        $validated = $request->validated();

        $ledger->store(
            $activePeriod['id'],
            (string) $validated['transaction_kind'],
            (int) $validated['materialgold_id'],
            (string) $validated['weight'],
            filled($validated['notes'] ?? null) ? (string) $validated['notes'] : null,
            $request->user()?->name ?? 'system',
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['transaction_kind'] === 'addition'
                ? 'Penambahan bahan emas berhasil disimpan.'
                : 'Pengurangan bahan emas berhasil disimpan.',
        ]);

        return back();
    }

    /**
     * @return array{
     *     search: string,
     *     date_from: string|null,
     *     date_to: string|null,
     *     event_type: string|null,
     *     per_page: int,
     *     sort: string,
     *     direction: string
     * }
     */
    private function validatedFilters(Request $request, bool $includePerPage): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'event_type' => ['nullable', Rule::in([
                GoldMaterialLedger::TRANSACTION_EVENT_ENTRY,
                GoldMaterialLedger::TRANSACTION_EVENT_OUT,
            ])],
            'sort' => ['nullable', Rule::in(GoldMaterialLedger::transactionSorts())],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];

        if ($includePerPage) {
            $rules['per_page'] = ['nullable', 'integer'];
        }

        $filters = Validator::make($request->only(array_keys($rules)), $rules)->validate();

        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;

        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'date_from' => filled($filters['date_from'] ?? null)
                ? (string) $filters['date_from']
                : null,
            'date_to' => filled($filters['date_to'] ?? null)
                ? (string) $filters['date_to']
                : null,
            'event_type' => filled($filters['event_type'] ?? null)
                ? (string) $filters['event_type']
                : null,
            'per_page' => $perPage,
            'sort' => GoldMaterialLedger::resolveSort(
                (string) ($filters['sort'] ?? GoldMaterialLedger::DEFAULT_TRANSACTION_SORT),
                GoldMaterialLedger::transactionSorts(),
                GoldMaterialLedger::DEFAULT_TRANSACTION_SORT,
            ),
            'direction' => GoldMaterialLedger::resolveDirection(
                (string) ($filters['direction'] ?? GoldMaterialLedger::DEFAULT_TRANSACTION_DIRECTION),
            ),
        ];
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
