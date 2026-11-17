<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrintFakturBarangJadiRequest;
use App\Http\Requests\ReceiveFakturBarangJadiReceiptRequest;
use App\Models\Employee;
use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use App\Support\FakturBarangJadiReceiptService;
use App\Support\FakturBarangJadiWorkflowStatus;
use App\Support\SerahTerimaFakturBarangJadiDocNumberGenerator;
use App\Support\SpkDashboardAnalytics;
use App\Support\SpkService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FakturBarangJadiController extends Controller
{
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
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    foreach (['doc_no', 'dari', 'untuk', 'diserahkan_oleh', 'diketahui_oleh', 'diterima_oleh', 'created_by', 'items'] as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()
                ->map(function (SerahTerimaFakturBarangJadi $receipt): array {
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
                        'createdBy' => $receipt->created_by,
                        'createdAt' => $receipt->created_at?->format('d-M-Y H:i'),
                        'printUrl' => route('faktur-barang-jadi.print.receipt', $receipt),
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
     * Simpan faktur barang jadi dari halaman index.
     */
    public function storeReceipt(
        PrintFakturBarangJadiRequest $request,
        SerahTerimaFakturBarangJadiDocNumberGenerator $docNumberGenerator,
        FakturBarangJadiReceiptService $receipts,
    ): JsonResponse {
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

        $receipts->assertEligibleForReceipt(
            $productions,
            $this->nullableTrimmed($request->validated('dari')),
        );

        $receiptDate = Carbon::createFromFormat(
            'Y-m-d',
            $request->validated('tanggal') ?? now()->toDateString(),
        )->startOfDay();

        $items = $productions
            ->map(fn (Production $production): array => $receipts->receiptItem($production))
            ->all();

        $actor = $this->actorName($request);
        $toLocation = $this->nullableTrimmed($request->validated('untuk'));

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
    public function printReceipt(SerahTerimaFakturBarangJadi $serahTerimaFakturBarangJadi): View
    {
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
            'rows' => $serahTerimaFakturBarangJadi->items,
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
