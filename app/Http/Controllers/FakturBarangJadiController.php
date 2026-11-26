<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachFakturBarangJadiSpkRequest;
use App\Http\Requests\ImportFakturBarangJadiRequest;
use App\Http\Requests\PrintFakturBarangJadiRequest;
use App\Http\Requests\ReceiveFakturBarangJadiReceiptRequest;
use App\Http\Requests\UploadSpkFotoBrjRequest;
use App\Models\Employee;
use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use App\Support\FakturBarangJadiImporter;
use App\Support\FakturBarangJadiImportTemplateExport;
use App\Support\FakturBarangJadiPrintPresenter;
use App\Support\FakturBarangJadiReceiptService;
use App\Support\FakturBarangJadiWorkflowStatus;
use App\Support\SerahTerimaFakturBarangJadiDocNumberGenerator;
use App\Support\SpkDashboardAnalytics;
use App\Support\SpkItemImageUrl;
use App\Support\SpkProcessMapper;
use App\Support\SpkProductionListItemPresentation;
use App\Support\SpkService;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FakturBarangJadiController extends Controller
{
    private const INDEX_SORT_COLUMNS = [
        'id' => 'id',
        'tanggal' => 'tanggal',
        'doc_no' => 'doc_no',
        'jumlah_spk' => 'jumlah_spk',
        'created' => 'created_at',
    ];

    /**
     * @var list<string>
     */
    private const RECEIPT_LOCATIONS = ['Head Office', 'Workshop', 'Store'];

    /**
     * Form buat faktur barang jadi.
     */
    public function create(FakturBarangJadiReceiptService $receipts): Response
    {
        return Inertia::render('faktur-barang-jadi/create', [
            'fakturBarangJadiTableAvailable' => $receipts->tableAvailable(),
            'fakturBarangJadiFotoBrjAvailable' => Schema::connection('third')->hasColumn('spk', 'foto_brj'),
            'receiptEmployeeOptions' => Inertia::once(fn (): array => self::receiptEmployeeOptions()),
        ]);
    }

    /**
     * Cari SPK selesai untuk modal pilih SPK pada form faktur.
     */
    public function searchSpks(Request $request, FakturBarangJadiReceiptService $receipts): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = max(1, min($request->integer('per_page', 20), 50));
        $excludeInput = $request->input('exclude', []);
        $exclude = collect(is_array($excludeInput) ? $excludeInput : explode(',', (string) $excludeInput))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
        $fakturBelumOnly = $request->boolean('faktur_belum', true);
        $receiptId = max(0, $request->integer('receipt_id'));
        $exceptReceiptId = $receiptId > 0 ? $receiptId : null;
        $blockedIds = $receipts->spkRowIdsOnOpenReceipts($exceptReceiptId);
        $fotoBrjColumn = Schema::connection('third')->hasColumn('spk', 'foto_brj');
        $lastWeightColumn = Schema::connection('third')->hasColumn('spk', 'last_weight');
        $analytics = new SpkDashboardAnalytics;

        $page = Production::query()
            ->with(['sku', 'categoryPrefix'])
            ->notDeleted()
            ->where('spk_type', '!=', SpkService::REPARATION_TYPE)
            ->tap(fn (Builder $query) => $analytics->constrainToCompletedProduction($query))
            ->when($fakturBelumOnly, function (Builder $query) use ($blockedIds): void {
                $issuedIds = array_keys($blockedIds);

                if ($issuedIds !== []) {
                    $query->whereIntegerNotInRaw('row_id', $issuedIds);
                }
            })
            ->when($exclude !== [], fn (Builder $query) => $query->whereIntegerNotInRaw('row_id', $exclude))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('spk_no', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('plu', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage);

        $statusLabels = SpkDashboardAnalytics::BACKLOG_STATUS_LABELS;
        $productions = $page->getCollection();
        $lastProcessDates = SpkDashboardAnalytics::lastProcessDatesFor($productions);
        $processMapper = app(SpkProcessMapper::class);

        return response()->json([
            'data' => $productions
                ->map(function (Production $production) use ($blockedIds, $fotoBrjColumn, $lastWeightColumn, $statusLabels, $lastProcessDates, $processMapper): array {
                    $rowId = (int) $production->row_id;
                    $kind = SpkDashboardAnalytics::completedProductionKind($rowId);
                    $status = $kind !== null
                        ? ($statusLabels[$kind] ?? 'DONE')
                        : '—';
                    $fotoBrj = $fotoBrjColumn ? $production->foto_brj : null;
                    $item = SpkProductionListItemPresentation::forProduction($production);
                    $lastProcessBadge = $processMapper->resolveLastProcessBadge(
                        $rowId,
                        $production->last_process,
                    );

                    return [
                        'rowId' => $rowId,
                        'spkNo' => (string) $production->spk_no,
                        'status' => $status,
                        'location' => $production->location,
                        'spkImageUrl' => $item['spkImageUrl'],
                        'typeSkuLabel' => $item['typeSkuLabel'],
                        'itemDescription' => $item['itemDescription'],
                        'skuAssigned' => $item['skuAssigned'],
                        'prosesTerakhir' => trim((string) ($production->last_process ?? '')),
                        'prosesTerakhirDate' => $lastProcessDates[$rowId] ?? '',
                        'prosesTerakhirDisplay' => $lastProcessBadge['label'] ?? null,
                        'prosesTerakhirCompleted' => $lastProcessBadge['completed'] ?? null,
                        'fotoBrjImageUrl' => $fotoBrjColumn
                            ? SpkItemImageUrl::fromFotoBrj($fotoBrj)
                            : null,
                        'hasFotoBrj' => $fotoBrjColumn ? filled($fotoBrj) : true,
                        'lastWeight' => $lastWeightColumn && filled($production->last_weight)
                            ? number_format((float) $production->last_weight, 2, '.', '')
                            : null,
                        'fakturBarangJadiIssued' => isset($blockedIds[$rowId]),
                    ];
                })
                ->values(),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Daftar faktur barang jadi yang sudah dibuat.
     */
    public function index(Request $request, FakturBarangJadiReceiptService $receipts): Response
    {
        $tableAvailable = $receipts->tableAvailable();
        $indexFilters = $this->resolveReceiptIndexFilters($request);

        $emptyReceipts = [
            'data' => [],
            'total' => 0,
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $indexFilters['per_page'],
        ];

        $shared = [
            'filters' => $indexFilters['filters'],
            'defaultFilters' => [
                'status' => [],
                'dari' => [],
                'untuk' => [],
            ],
            'filterOptions' => $this->receiptIndexFilterOptions(),
            'fakturBarangJadiTableAvailable' => $tableAvailable,
            'receiptEmployeeOptions' => Inertia::once(fn (): array => self::receiptEmployeeOptions()),
        ];

        if (! $tableAvailable) {
            return Inertia::render('faktur-barang-jadi/index', [
                ...$shared,
                'receipts' => $emptyReceipts,
            ]);
        }

        $receiptPage = SerahTerimaFakturBarangJadi::query()
            ->tap(fn (Builder $query): Builder => $this->applyReceiptIndexSearch($query, $indexFilters['search']))
            ->tap(fn (Builder $query): Builder => $this->applyReceiptIndexFilters(
                $query,
                $indexFilters,
            ))
            ->tap(fn (Builder $query): Builder => $this->applyReceiptIndexSort(
                $query,
                $indexFilters['sort'],
                $indexFilters['direction'],
            ))
            ->paginate($indexFilters['per_page'])
            ->withQueryString();

        return Inertia::render('faktur-barang-jadi/index', [
            ...$shared,
            'receipts' => $receiptPage->through(
                fn (SerahTerimaFakturBarangJadi $receipt): array => $this->serializeReceiptRow($receipt),
            ),
        ]);
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new FakturBarangJadiImportTemplateExport([
                [
                    '',
                    now()->toDateString(),
                    'Workshop',
                    'Store',
                    'NO-SPK-CONTOH',
                    'PLU-CONTOH',
                    '4.66',
                    '',
                    '',
                    '',
                ],
            ]),
            'template-import-faktur-barang-jadi.xlsx',
        );
    }

    public function import(
        ImportFakturBarangJadiRequest $request,
        FakturBarangJadiImporter $importer,
    ): RedirectResponse {
        $result = $importer->import(
            $request->file('file'),
            $this->actorName($request),
        );

        if ($result['importedReceipts'] > 0) {
            $receiptLabel = $result['importedReceipts'] === 1
                ? '1 faktur'
                : "{$result['importedReceipts']} faktur";

            $spkLabel = $result['importedSpk'] === 1
                ? '1 SPK'
                : "{$result['importedSpk']} SPK";

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Import berhasil: {$receiptLabel}, {$spkLabel} tercatat.",
            ]);
        }

        if ($result['errors'] !== []) {
            $preview = collect($result['errors'])
                ->take(3)
                ->map(function (array $error): string {
                    $messages = implode(' ', $error['messages']);

                    return "Baris {$error['row']}: {$messages}";
                })
                ->implode(' ');

            $remaining = count($result['errors']) - 3;
            $suffix = $remaining > 0 ? " (+{$remaining} baris lainnya)" : '';

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Import gagal. {$preview}{$suffix}",
            ]);
        }

        return back();
    }

    /**
     * Detail faktur barang jadi beserta daftar SPK.
     */
    public function show(
        SerahTerimaFakturBarangJadi $serahTerimaFakturBarangJadi,
        FakturBarangJadiReceiptService $receipts,
        FakturBarangJadiPrintPresenter $printPresenter,
    ): Response {
        if (! $receipts->tableAvailable()) {
            abort(404);
        }

        return Inertia::render('faktur-barang-jadi/show', [
            'receipt' => $this->serializeReceiptDetail($serahTerimaFakturBarangJadi),
            'printGroups' => $printPresenter->groupsForReceipt($serahTerimaFakturBarangJadi),
            'fakturBarangJadiFotoBrjAvailable' => Schema::connection('third')->hasColumn('spk', 'foto_brj'),
        ]);
    }

    public function attachSpk(
        AttachFakturBarangJadiSpkRequest $request,
        SerahTerimaFakturBarangJadi $serahTerimaFakturBarangJadi,
        FakturBarangJadiReceiptService $receipts,
    ): RedirectResponse {
        if (! $receipts->tableAvailable()) {
            throw new HttpException(422, 'Penyimpanan faktur barang jadi belum tersedia di database.');
        }

        /** @var list<int> $ids */
        $ids = array_map(intval(...), $request->validated('ids'));

        $productions = Production::query()
            ->with(['sku', 'categoryPrefix'])
            ->notDeleted()
            ->whereIn('row_id', $ids)
            ->where('spk_type', '!=', SpkService::REPARATION_TYPE)
            ->get()
            ->sortBy(fn (Production $production): int|false => array_search((int) $production->row_id, $ids, true))
            ->values();

        abort_if($productions->isEmpty(), 404);

        DB::connection('third')->transaction(function () use (
            $request,
            $serahTerimaFakturBarangJadi,
            $receipts,
            $productions,
        ): void {
            $receipts->attachProductionsToReceipt(
                $serahTerimaFakturBarangJadi,
                $productions,
                $this->actorName($request),
            );
        });

        $count = $productions->count();
        $label = $count === 1 ? '1 SPK' : "{$count} SPK";

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$label} ditambahkan ke faktur {$serahTerimaFakturBarangJadi->doc_no}.",
        ]);

        return redirect()->route('faktur-barang-jadi.show', $serahTerimaFakturBarangJadi);
    }

    public function detachSpk(
        Request $request,
        SerahTerimaFakturBarangJadi $serahTerimaFakturBarangJadi,
        int $spk_row_id,
        FakturBarangJadiReceiptService $receipts,
    ): RedirectResponse {
        if (! $receipts->tableAvailable()) {
            throw new HttpException(422, 'Penyimpanan faktur barang jadi belum tersedia di database.');
        }

        $production = Production::query()
            ->notDeleted()
            ->where('row_id', $spk_row_id)
            ->firstOrFail();

        DB::connection('third')->transaction(function () use (
            $request,
            $serahTerimaFakturBarangJadi,
            $receipts,
            $production,
        ): void {
            $receipts->detachProductionFromReceipt(
                $serahTerimaFakturBarangJadi,
                $production,
                $this->actorName($request),
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "SPK {$production->spk_no} dikeluarkan dari faktur {$serahTerimaFakturBarangJadi->doc_no}.",
        ]);

        return redirect()->route('faktur-barang-jadi.show', $serahTerimaFakturBarangJadi);
    }

    /**
     * Riwayat faktur barang jadi untuk modal di halaman index.
     */
    public function receiptHistory(Request $request, FakturBarangJadiReceiptService $receipts): JsonResponse
    {
        if (! $receipts->tableAvailable()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'currentPage' => 1,
                    'lastPage' => 1,
                    'perPage' => 20,
                    'total' => 0,
                ],
            ]);
        }

        $search = $request->string('search')->trim()->toString();

        $page = SerahTerimaFakturBarangJadi::query()
            ->tap(fn (Builder $query): Builder => $this->applyReceiptIndexSearch($query, $search))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn (SerahTerimaFakturBarangJadi $receipt): array => $this->serializeReceiptRow($receipt))
                ->values(),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Simpan faktur barang jadi dari halaman index.
     */
    public function storeReceipt(
        PrintFakturBarangJadiRequest $request,
        SerahTerimaFakturBarangJadiDocNumberGenerator $docNumberGenerator,
        FakturBarangJadiReceiptService $receipts,
    ): JsonResponse {
        if (! $receipts->tableAvailable()) {
            throw ValidationException::withMessages([
                'receipt' => ['Penyimpanan faktur barang jadi belum tersedia di database.'],
            ]);
        }

        /** @var list<int> $ids */
        $ids = array_map(intval(...), $request->validated('ids'));

        $productions = Production::query()
            ->with(['sku', 'categoryPrefix'])
            ->notDeleted()
            ->whereIn('row_id', $ids)
            ->where('spk_type', '!=', SpkService::REPARATION_TYPE)
            ->get()
            ->sortBy(fn (Production $production): int|false => array_search((int) $production->row_id, $ids, true))
            ->values();

        abort_if($productions->isEmpty(), 404);

        $receipts->assertEligibleForReceipt(
            $productions,
            $this->nullableTrimmed($request->validated('dari')),
            $this->actorName($request),
        );

        $receiptDate = Carbon::createFromFormat(
            'Y-m-d',
            $request->validated('tanggal') ?? now()->toDateString(),
        )->startOfDay();

        $actor = $this->actorName($request);
        $toLocation = $this->nullableTrimmed($request->validated('untuk'));

        $receipts->relocateProductionsTo($productions, $toLocation, $actor);
        $productions->each->refresh();

        $items = $productions
            ->map(fn (Production $production): array => $receipts->receiptItem($production))
            ->all();

        $receipt = DB::connection('third')->transaction(function () use (
            $docNumberGenerator,
            $receiptDate,
            $request,
            $items,
            $actor,
            $toLocation,
        ): SerahTerimaFakturBarangJadi {
            $payload = [
                'doc_no' => $docNumberGenerator->generate(
                    $receiptDate,
                    $this->nullableTrimmed($request->validated('dari')),
                    $toLocation,
                ),
                'tanggal' => $receiptDate->toDateString(),
                'dari' => $this->nullableTrimmed($request->validated('dari')),
                'untuk' => $toLocation,
                'diserahkan_oleh' => $this->nullableTrimmed($request->validated('diserahkan_oleh')),
                'diterima_oleh' => null,
                'diketahui_oleh' => $this->nullableTrimmed($request->validated('diketahui_oleh')),
                'jumlah_spk' => count($items),
                'spk_row_ids' => array_column($items, 'spkRowId'),
                'items' => $items,
                'created_by' => $actor,
            ];

            if (FakturBarangJadiWorkflowStatus::columnAvailable()) {
                $payload['workflow_status'] = FakturBarangJadiWorkflowStatus::CREATED;
            }

            return SerahTerimaFakturBarangJadi::query()->create($payload);
        });

        $receipts->syncSendToMerchDates($productions, $receiptDate, $actor);

        return response()->json([
            'id' => $receipt->id,
            'docNo' => $receipt->doc_no,
            'printUrl' => route('faktur-barang-jadi.print.receipt', $receipt),
            'workflowStatus' => FakturBarangJadiWorkflowStatus::columnAvailable()
                ? FakturBarangJadiWorkflowStatus::CREATED
                : null,
        ], 201);
    }

    /**
     * Halaman print faktur barang jadi.
     */
    public function printReceipt(
        SerahTerimaFakturBarangJadi $serahTerimaFakturBarangJadi,
        FakturBarangJadiPrintPresenter $printPresenter,
    ): View {
        $header = [
            ...$this->receiptDocumentHeader(),
            'formTitle' => 'Faktur Barang Jadi',
        ];

        return view('spk.faktur-barang-jadi-receipt', [
            'title' => "Faktur Barang Jadi {$serahTerimaFakturBarangJadi->doc_no} — Print",
            'header' => $header,
            'receiptNo' => $serahTerimaFakturBarangJadi->doc_no,
            'printedAt' => ($serahTerimaFakturBarangJadi->created_at ?? now())->format('d-M-Y H:i'),
            'printedBy' => $serahTerimaFakturBarangJadi->created_by ?? '-',
            'receiptDate' => $serahTerimaFakturBarangJadi->tanggal->format('d-M-Y'),
            'receiptFrom' => (string) $serahTerimaFakturBarangJadi->dari,
            'receiptTo' => (string) $serahTerimaFakturBarangJadi->untuk,
            'signatures' => [
                ['title' => 'Diserahkan oleh', 'name' => (string) $serahTerimaFakturBarangJadi->diserahkan_oleh],
                ['title' => 'Diketahui oleh', 'name' => (string) $serahTerimaFakturBarangJadi->diketahui_oleh],
                ['title' => 'Diterima oleh', 'name' => (string) $serahTerimaFakturBarangJadi->diterima_oleh],
            ],
            'groups' => $printPresenter->groupsForReceipt($serahTerimaFakturBarangJadi),
        ]);
    }

    /**
     * Tandai faktur sebagai dikirim ke tujuan.
     */
    public function sendReceipt(
        Request $request,
        SerahTerimaFakturBarangJadi $serahTerimaFakturBarangJadi,
        FakturBarangJadiReceiptService $receipts,
    ): JsonResponse {
        if (! $receipts->tableAvailable()) {
            throw new HttpException(422, 'Penyimpanan faktur barang jadi belum tersedia di database.');
        }

        if (! FakturBarangJadiWorkflowStatus::columnAvailable()) {
            throw new HttpException(422, 'Status faktur belum tersedia di database.');
        }

        $current = FakturBarangJadiWorkflowStatus::normalize($serahTerimaFakturBarangJadi->workflow_status);

        if ($current !== FakturBarangJadiWorkflowStatus::CREATED) {
            throw new HttpException(422, 'Hanya faktur berstatus dibuat yang dapat dikirim.');
        }

        $serahTerimaFakturBarangJadi->forceFill([
            'workflow_status' => FakturBarangJadiWorkflowStatus::SENT,
        ])->save();

        return response()->json([
            'message' => "Faktur {$serahTerimaFakturBarangJadi->doc_no} ditandai dikirim.",
            'workflowStatus' => FakturBarangJadiWorkflowStatus::SENT,
        ]);
    }

    /**
     * Tandai faktur sebagai diterima di lokasi tujuan.
     */
    public function receiveReceipt(
        ReceiveFakturBarangJadiReceiptRequest $request,
        SerahTerimaFakturBarangJadi $serahTerimaFakturBarangJadi,
        FakturBarangJadiReceiptService $receipts,
    ): JsonResponse {
        if (! $receipts->tableAvailable()) {
            throw new HttpException(422, 'Penyimpanan faktur barang jadi belum tersedia di database.');
        }

        if (! FakturBarangJadiWorkflowStatus::columnAvailable()) {
            throw new HttpException(422, 'Status faktur belum tersedia di database.');
        }

        $current = FakturBarangJadiWorkflowStatus::normalize($serahTerimaFakturBarangJadi->workflow_status);

        if ($current !== FakturBarangJadiWorkflowStatus::SENT) {
            throw new HttpException(422, 'Hanya faktur yang sudah dikirim yang dapat diterima.');
        }

        /** @var list<int> $rowIds */
        $rowIds = array_values(array_filter(array_map(
            intval(...),
            $serahTerimaFakturBarangJadi->spk_row_ids ?? [],
        ), fn (int $id): bool => $id > 0));

        abort_if($rowIds === [], 404);

        $productions = Production::query()
            ->notDeleted()
            ->whereIn('row_id', $rowIds)
            ->get();

        abort_if($productions->isEmpty(), 404);

        $actor = $this->actorName($request);
        $toLocation = $serahTerimaFakturBarangJadi->untuk;

        DB::connection('third')->transaction(function () use (
            $request,
            $serahTerimaFakturBarangJadi,
            $receipts,
            $productions,
            $actor,
            $toLocation,
        ): void {
            $serahTerimaFakturBarangJadi->forceFill([
                'workflow_status' => FakturBarangJadiWorkflowStatus::RECEIVED,
                'diterima_oleh' => $this->nullableTrimmed($request->validated('diterima_oleh')),
            ])->save();

            $receipts->relocateProductionsTo($productions, $toLocation, $actor);
        });

        return response()->json([
            'message' => "Faktur {$serahTerimaFakturBarangJadi->doc_no} diterima. Lokasi SPK diperbarui ke {$toLocation}.",
            'workflowStatus' => FakturBarangJadiWorkflowStatus::RECEIVED,
        ]);
    }

    /**
     * API: daftar SPK yang faktur barang jadinya berstatus dikirim.
     */
    public function sentSpkList(Request $request, FakturBarangJadiReceiptService $receipts): JsonResponse
    {
        $emptyMeta = [
            'currentPage' => 1,
            'lastPage' => 1,
            'perPage' => 20,
            'total' => 0,
        ];

        if (! $receipts->tableAvailable() || ! FakturBarangJadiWorkflowStatus::columnAvailable()) {
            return response()->json([
                'data' => [],
                'meta' => $emptyMeta,
            ]);
        }

        $sentRowIds = $receipts->spkRowIdsWithLatestWorkflowStatus(FakturBarangJadiWorkflowStatus::SENT);

        if ($sentRowIds === []) {
            return response()->json([
                'data' => [],
                'meta' => $emptyMeta,
            ]);
        }

        $search = $request->string('search')->trim()->toString();
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $page = Production::query()
            ->with(['sku', 'categoryPrefix'])
            ->notDeleted()
            ->whereIn('row_id', $sentRowIds)
            ->where('spk_type', '!=', SpkService::REPARATION_TYPE)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('spk_no', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->when(
                            Schema::connection('third')->hasColumn('spk', 'plu'),
                            fn (Builder $builder) => $builder->orWhere('plu', 'like', "%{$search}%"),
                        )
                        ->when(
                            Schema::connection('third')->hasColumn('spk', 'location'),
                            fn (Builder $builder) => $builder->orWhere('location', 'like', "%{$search}%"),
                        );
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage);

        $fakturDetails = $receipts->latestFakturDetailsBySpkRowIds(
            $page->getCollection()->pluck('row_id')->map(intval(...))->all(),
        );

        $doneKinds = SpkDashboardAnalytics::completedProductionKinds(
            $page->getCollection()->pluck('row_id')->all(),
        );

        return response()->json([
            'data' => $page->getCollection()
                ->map(function (Production $production) use ($fakturDetails, $doneKinds): array {
                    $rowId = (int) $production->row_id;
                    $completed = $doneKinds[$rowId] ?? false;
                    $faktur = $fakturDetails[$rowId] ?? null;

                    return [
                        'rowId' => $rowId,
                        'spkNo' => filled($production->spk_no) ? (string) $production->spk_no : '-',
                        'spkType' => filled($production->spk_type) ? (string) $production->spk_type : '-',
                        'customerName' => filled($production->customer_name)
                            ? (string) $production->customer_name
                            : null,
                        'itemName' => filled($production->item_name) ? (string) $production->item_name : '-',
                        'productionStatus' => SpkDashboardAnalytics::backlogStatusLabel($production, $completed),
                        'location' => filled($production->location)
                            ? trim((string) $production->location)
                            : Production::DEFAULT_LOCATION,
                        'plu' => filled($production->plu) ? trim((string) $production->plu) : null,
                        'fakturBarangJadiStatus' => FakturBarangJadiWorkflowStatus::SENT,
                        'fakturBarangJadiStatusLabel' => FakturBarangJadiWorkflowStatus::label(FakturBarangJadiWorkflowStatus::SENT),
                        'faktur' => $faktur,
                    ];
                })
                ->values(),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Soft delete faktur dari modal riwayat.
     */
    public function destroyReceipt(
        Request $request,
        SerahTerimaFakturBarangJadi $serahTerimaFakturBarangJadi,
        FakturBarangJadiReceiptService $receipts,
    ): JsonResponse {
        if (! $receipts->tableAvailable()) {
            throw new HttpException(422, 'Penyimpanan faktur barang jadi belum tersedia di database.');
        }

        DB::connection('third')->transaction(function () use ($request, $serahTerimaFakturBarangJadi): void {
            $serahTerimaFakturBarangJadi->forceFill(['deleted_by' => $this->actorName($request)])->save();
            $serahTerimaFakturBarangJadi->delete();
        });

        return response()->json([
            'message' => "Faktur {$serahTerimaFakturBarangJadi->doc_no} berhasil dihapus.",
        ]);
    }

    /**
     * @return array{
     *     logoUrl: string,
     *     companyName: string,
     *     formTitle: string,
     *     docNo: string,
     *     issueNo: string,
     *     revision: string,
     *     issueDate: string
     * }
     */
    private function receiptDocumentHeader(): array
    {
        return [
            'logoUrl' => asset((string) config('spk.logo', 'images/logo.jpg')),
            'companyName' => (string) config('spk.company_name', 'Wanda House of Jewels'),
            'formTitle' => 'Faktur Barang Jadi',
            'docNo' => (string) config('spk.faktur_barang_jadi_receipt_document_no', 'WHOJ-PRD-FRM-010'),
            'issueNo' => (string) config('spk.issue_no', '01'),
            'revision' => (string) config('spk.faktur_barang_jadi_receipt_revision', '00'),
            'issueDate' => (string) config('spk.faktur_barang_jadi_receipt_issue_date', '12/11/2026'),
        ];
    }

    public function updateFotoBrj(
        UploadSpkFotoBrjRequest $request,
        Production $production,
        SpkService $spkService,
    ): RedirectResponse {
        if (! Schema::connection('third')->hasColumn('spk', 'foto_brj')) {
            throw new HttpException(422, 'Kolom foto barang jadi belum tersedia di database.');
        }

        if ((int) $production->is_deleted === 1) {
            abort(404);
        }

        if (SpkDashboardAnalytics::completedProductionKind((int) $production->row_id) === null) {
            throw new HttpException(422, 'Foto barang jadi hanya dapat diunggah untuk SPK selesai.');
        }

        $updates = [
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ];

        if ($request->hasFile('foto')) {
            $filename = $spkService->uploadFotoBrjFile(
                $request->file('foto'),
                (string) ($production->spk_no ?? ''),
            );

            $updates['foto_brj'] = $filename;
        }

        if (
            $request->filled('last_weight')
            && Schema::connection('third')->hasColumn('spk', 'last_weight')
        ) {
            $updates['last_weight'] = number_format((float) $request->input('last_weight'), 2, '.', '');
        }

        if (count($updates) <= 2) {
            throw new HttpException(422, 'Tidak ada data foto atau berat akhir untuk disimpan.');
        }

        $production->forceFill($updates)->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data barang jadi untuk SPK '.($production->spk_no ?? '').' disimpan.',
        ]);

        return back();
    }

    /**
     * @return array{
     *     search: string,
     *     sort: string,
     *     direction: string,
     *     per_page: int,
     *     status: list<string>,
     *     dari: list<string>,
     *     untuk: list<string>,
     *     date_from: string|null,
     *     date_to: string|null,
     *     filters: array{
     *         search: string,
     *         sort: string,
     *         direction: string,
     *         per_page: int,
     *         status: list<string>,
     *         dari: list<string>,
     *         untuk: list<string>,
     *         date_from: string|null,
     *         date_to: string|null
     *     }
     * }
     */
    private function resolveReceiptIndexFilters(Request $request): array
    {
        $search = $request->string('search')->trim()->toString();
        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, self::INDEX_SORT_COLUMNS) ? $sort : 'id';
        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        $perPage = $request->integer('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        $workflowStatuses = [
            FakturBarangJadiWorkflowStatus::CREATED,
            FakturBarangJadiWorkflowStatus::SENT,
            FakturBarangJadiWorkflowStatus::RECEIVED,
        ];

        $statusFilters = $this->resolveReceiptMultiFilter($request->input('status'), $workflowStatuses);
        $dariFilters = $this->resolveReceiptMultiFilter($request->input('dari'), self::RECEIPT_LOCATIONS);
        $untukFilters = $this->resolveReceiptMultiFilter($request->input('untuk'), self::RECEIPT_LOCATIONS);
        $dateFrom = $this->resolveReceiptIndexDate($request->string('date_from')->toString());
        $dateTo = $this->resolveReceiptIndexDate($request->string('date_to')->toString());

        if ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom) {
            $dateTo = $dateFrom;
        }

        $filters = [
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
            'status' => $statusFilters,
            'dari' => $dariFilters,
            'untuk' => $untukFilters,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        return [
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
            'status' => $statusFilters,
            'dari' => $dariFilters,
            'untuk' => $untukFilters,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'filters' => $filters,
        ];
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function resolveReceiptMultiFilter(mixed $value, array $allowed): array
    {
        return collect(is_array($value) ? $value : (filled($value) ? [$value] : []))
            ->filter(fn (mixed $item): bool => is_scalar($item))
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => in_array($item, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function resolveReceiptIndexDate(string $date): ?string
    {
        $trimmed = trim($date);

        if ($trimmed === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $trimmed);

        if ($parsed === false || $parsed->format('Y-m-d') !== $trimmed) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @return array{
     *     status: list<array{value: string, label: string}>,
     *     dari: list<array{value: string, label: string}>,
     *     untuk: list<array{value: string, label: string}>,
     *     sort: list<array{value: string, label: string}>,
     *     direction: list<array{value: string, label: string}>,
     *     per_page: list<array{value: string, label: string}>
     * }
     */
    private function receiptIndexFilterOptions(): array
    {
        return [
            'status' => collect(FakturBarangJadiWorkflowStatus::LABELS)
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
            'dari' => collect(self::RECEIPT_LOCATIONS)
                ->map(fn (string $location): array => [
                    'value' => $location,
                    'label' => $location,
                ])
                ->all(),
            'untuk' => collect(self::RECEIPT_LOCATIONS)
                ->map(fn (string $location): array => [
                    'value' => $location,
                    'label' => $location,
                ])
                ->all(),
            'sort' => [
                ['value' => 'id', 'label' => 'ID'],
                ['value' => 'tanggal', 'label' => 'Tanggal Faktur'],
                ['value' => 'doc_no', 'label' => 'No. Faktur'],
                ['value' => 'jumlah_spk', 'label' => 'Jumlah SPK'],
                ['value' => 'created', 'label' => 'Dibuat'],
            ],
            'direction' => [
                ['value' => 'asc', 'label' => 'A–Z / Lama ke baru'],
                ['value' => 'desc', 'label' => 'Z–A / Baru ke lama'],
            ],
            'per_page' => [
                ['value' => '10', 'label' => '10'],
                ['value' => '20', 'label' => '20'],
                ['value' => '50', 'label' => '50'],
                ['value' => '100', 'label' => '100'],
            ],
        ];
    }

    /**
     * @param  array{
     *     status: list<string>,
     *     dari: list<string>,
     *     untuk: list<string>,
     *     date_from: string|null,
     *     date_to: string|null
     * }  $indexFilters
     */
    private function applyReceiptIndexFilters(Builder $query, array $indexFilters): Builder
    {
        if (
            $indexFilters['status'] !== []
            && FakturBarangJadiWorkflowStatus::columnAvailable()
        ) {
            $query->whereIn('workflow_status', $indexFilters['status']);
        }

        if ($indexFilters['dari'] !== []) {
            $query->whereIn('dari', $indexFilters['dari']);
        }

        if ($indexFilters['untuk'] !== []) {
            $query->whereIn('untuk', $indexFilters['untuk']);
        }

        if ($indexFilters['date_from'] !== null) {
            $query->whereDate('tanggal', '>=', $indexFilters['date_from']);
        }

        if ($indexFilters['date_to'] !== null) {
            $query->whereDate('tanggal', '<=', $indexFilters['date_to']);
        }

        return $query;
    }

    private function applyReceiptIndexSort(Builder $query, string $sort, string $direction): Builder
    {
        $column = self::INDEX_SORT_COLUMNS[$sort] ?? 'id';
        $query->orderBy($column, $direction);

        if ($sort !== 'id') {
            $query->orderByDesc('id');
        }

        return $query;
    }

    private function applyReceiptIndexSearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search): void {
            foreach (['doc_no', 'dari', 'untuk', 'diserahkan_oleh', 'diketahui_oleh', 'diterima_oleh', 'created_by', 'items'] as $column) {
                $query->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    /**
     * @return array{
     *     id: int,
     *     docNo: string,
     *     tanggal: string,
     *     dari: string|null,
     *     untuk: string|null,
     *     jumlahSpk: int,
     *     spkNos: list<string>,
     *     diserahkanOleh: string|null,
     *     diketahuiOleh: string|null,
     *     diterimaOleh: string|null,
     *     workflowStatus: string,
     *     workflowStatusLabel: string,
     *     canSend: bool,
     *     canReceive: bool,
     *     createdBy: string|null,
     *     createdAt: string|null,
     *     printUrl: string
     * }
     */
    private function serializeReceiptRow(SerahTerimaFakturBarangJadi $receipt): array
    {
        $workflow = FakturBarangJadiWorkflowStatus::columnAvailable()
            ? FakturBarangJadiWorkflowStatus::normalize($receipt->workflow_status)
            : FakturBarangJadiWorkflowStatus::CREATED;

        return [
            'id' => $receipt->id,
            'docNo' => $receipt->doc_no,
            'tanggal' => $receipt->tanggal->format('d-M-Y'),
            'dari' => $receipt->dari,
            'untuk' => $receipt->untuk,
            'jumlahSpk' => $receipt->jumlah_spk,
            'spkNos' => array_values(array_column($receipt->items, 'spkNo')),
            'diserahkanOleh' => $receipt->diserahkan_oleh,
            'diketahuiOleh' => $receipt->diketahui_oleh,
            'diterimaOleh' => $receipt->diterima_oleh,
            'workflowStatus' => $workflow,
            'workflowStatusLabel' => FakturBarangJadiWorkflowStatus::label($workflow),
            'canSend' => $workflow === FakturBarangJadiWorkflowStatus::CREATED,
            'canReceive' => $workflow === FakturBarangJadiWorkflowStatus::SENT,
            'canEditSpk' => FakturBarangJadiWorkflowStatus::allowsSpkChanges($workflow),
            'createdBy' => $receipt->created_by,
            'createdAt' => $receipt->created_at?->format('d-M-Y H:i'),
            'printUrl' => route('faktur-barang-jadi.print.receipt', $receipt),
            'showUrl' => route('faktur-barang-jadi.show', $receipt),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     docNo: string,
     *     tanggal: string,
     *     dari: string|null,
     *     untuk: string|null,
     *     jumlahSpk: int,
     *     spkNos: list<string>,
     *     items: list<array{
     *         spkRowId: int,
     *         spkNo: string,
     *         type: string,
     *         status: string,
     *         item: string,
     *         plu: string|null,
     *         location: string
     *     }>,
     *     diserahkanOleh: string|null,
     *     diketahuiOleh: string|null,
     *     diterimaOleh: string|null,
     *     workflowStatus: string,
     *     workflowStatusLabel: string,
     *     canSend: bool,
     *     canReceive: bool,
     *     canEditSpk: bool,
     *     createdBy: string|null,
     *     createdAt: string|null,
     *     printUrl: string,
     *     showUrl: string
     * }
     */
    private function serializeReceiptDetail(SerahTerimaFakturBarangJadi $receipt): array
    {
        return [
            ...$this->serializeReceiptRow($receipt),
            'items' => array_values($receipt->items ?? []),
        ];
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }

    /**
     * @return list<string>
     */
    public static function receiptEmployeeOptions(): array
    {
        return Employee::query()
            ->where('is_deleted', 0)
            ->where('status', Employee::STATUS_ACTIVE)
            ->orderBy('nama_lengkap')
            ->pluck('nama_lengkap')
            ->map(fn (mixed $name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
