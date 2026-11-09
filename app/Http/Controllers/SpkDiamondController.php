<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrintSpkDiamondReceiptRequest;
use App\Models\Employee;
use App\Models\SerahTerimaSpkDiamond;
use App\Models\SpkDiamond;
use App\Support\SerahTerimaSpkDiamondDocNumberGenerator;
use App\Support\SpkDiamondDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class SpkDiamondController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const INDEX_SORT_COLUMNS = [
        'doc_no' => 'ID',
        'tanggal' => 'Tanggal',
        'spk_no' => 'No. SPK',
        'item_name' => 'Item',
        'status' => 'Status',
        'diamondmounting_id' => 'No Pasang Batu',
        'created_by' => 'Dibuat oleh',
        'created_date' => 'Dibuat tanggal',
    ];

    /**
     * Daftar dokumen SPK Pasang Batu.
     */
    public function index(Request $request, SpkDiamondDocumentService $documents): Response
    {
        $search = $request->string('search')->trim()->toString();
        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, self::INDEX_SORT_COLUMNS) ? $sort : 'tanggal';
        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        $dateFrom = $this->resolveIndexDate($request->string('date_from')->toString());
        $dateTo = $this->resolveIndexDate($request->string('date_to')->toString());
        $perPage = $request->integer('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;

        if ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom) {
            $dateTo = $dateFrom;
        }

        $documentPage = $documents->paginateForIndex(
            $search,
            $dateFrom,
            $dateTo,
            $sort,
            $direction,
            $perPage,
        );

        return Inertia::render('spk-diamond/index', [
            'documents' => $documentPage,
            'tableAvailable' => $documents->tableAvailable(),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'per_page' => collect([10, 25, 50, 100])
                    ->map(fn (int $size): array => [
                        'value' => (string) $size,
                        'label' => (string) $size,
                    ])
                    ->all(),
                'sort' => collect(self::INDEX_SORT_COLUMNS)
                    ->map(fn (string $label, string $value): array => [
                        'value' => $value,
                        'label' => $label,
                    ])
                    ->values()
                    ->all(),
                'direction' => [
                    ['value' => 'desc', 'label' => 'Menurun'],
                    ['value' => 'asc', 'label' => 'Menaik'],
                ],
            ],
            'receiptEmployeeOptions' => Inertia::once(fn (): array => $this->receiptEmployeeOptions()),
        ]);
    }

    /**
     * Detail dokumen SPK Pasang Batu.
     */
    public function show(SpkDiamond $spkDiamond, SpkDiamondDocumentService $documents): Response
    {
        if (! $documents->tableAvailable()) {
            abort(404);
        }

        return Inertia::render('spk-diamond/show', [
            'document' => $documents->detailPayload($spkDiamond),
        ]);
    }

    /**
     * Riwayat tanda terima SPK Pasang Batu untuk modal di halaman index.
     */
    public function receiptHistory(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        $receipts = SerahTerimaSpkDiamond::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    foreach (['doc_no', 'dari', 'untuk', 'diserahkan_oleh', 'diketahui_oleh', 'diterima_oleh', 'created_by', 'items'] as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $receipts->getCollection()
                ->map(fn (SerahTerimaSpkDiamond $receipt): array => [
                    'id' => $receipt->id,
                    'docNo' => $receipt->doc_no,
                    'tanggal' => $receipt->tanggal->format('d-M-Y'),
                    'dari' => $receipt->dari,
                    'untuk' => $receipt->untuk,
                    'jumlahSpk' => $receipt->jumlah_dokumen,
                    'spkNos' => array_values(array_column($receipt->items, 'docNo')),
                    'diserahkanOleh' => $receipt->diserahkan_oleh,
                    'diketahuiOleh' => $receipt->diketahui_oleh,
                    'diterimaOleh' => $receipt->diterima_oleh,
                    'createdBy' => $receipt->created_by,
                    'createdAt' => $receipt->created_at?->format('d-M-Y H:i'),
                    'printUrl' => route('spk-diamond.print.receipt', $receipt),
                ])
                ->values(),
            'meta' => [
                'currentPage' => $receipts->currentPage(),
                'lastPage' => $receipts->lastPage(),
                'perPage' => $receipts->perPage(),
                'total' => $receipts->total(),
            ],
        ]);
    }

    /**
     * Simpan tanda terima SPK Pasang Batu dari halaman index.
     */
    public function storeReceipt(
        PrintSpkDiamondReceiptRequest $request,
        SerahTerimaSpkDiamondDocNumberGenerator $docNumberGenerator,
    ): JsonResponse {
        /** @var list<int> $ids */
        $ids = array_map(intval(...), $request->validated('ids'));

        $documents = SpkDiamond::query()
            ->with(['production'])
            ->where('is_deleted', 0)
            ->whereIn('row_id', $ids)
            ->get()
            ->sortBy(fn (SpkDiamond $document): int|false => array_search((int) $document->row_id, $ids, true))
            ->values();

        abort_if($documents->isEmpty(), 404);

        $receiptDate = Carbon::createFromFormat(
            'Y-m-d',
            $request->validated('tanggal') ?? now()->toDateString(),
        )->startOfDay();

        $items = $documents->map(fn (SpkDiamond $document): array => [
            'spkDiamondRowId' => (int) $document->row_id,
            'docNo' => $document->doc_no ?? '-',
            'spkNo' => filled($document->spk_no) ? (string) $document->spk_no : '-',
            'item' => $document->production !== null && filled($document->production->item_name)
                ? (string) $document->production->item_name
                : '-',
            'lineCount' => (int) $document->jumlah_baris,
            'tanggal' => $document->tanggal->format('d-M-Y'),
        ])->all();

        $receipt = DB::connection('third')->transaction(fn (): SerahTerimaSpkDiamond => SerahTerimaSpkDiamond::query()->create([
            'doc_no' => $docNumberGenerator->generate($receiptDate),
            'tanggal' => $receiptDate->toDateString(),
            'dari' => $this->nullableTrimmed($request->validated('dari')),
            'untuk' => $this->nullableTrimmed($request->validated('untuk')),
            'diserahkan_oleh' => $this->nullableTrimmed($request->validated('diserahkan_oleh')),
            'diterima_oleh' => $this->nullableTrimmed($request->validated('diterima_oleh')),
            'diketahui_oleh' => $this->nullableTrimmed($request->validated('diketahui_oleh')),
            'jumlah_dokumen' => count($items),
            'spk_diamond_row_ids' => array_column($items, 'spkDiamondRowId'),
            'items' => $items,
            'created_by' => $this->actorName($request),
        ]));

        return response()->json([
            'id' => $receipt->id,
            'docNo' => $receipt->doc_no,
            'printUrl' => route('spk-diamond.print.receipt', $receipt),
        ], 201);
    }

    /**
     * Halaman print tanda terima SPK Pasang Batu.
     */
    public function printReceipt(SerahTerimaSpkDiamond $serahTerimaSpkDiamond): View
    {
        $header = [
            ...$this->receiptDocumentHeader(),
            'formTitle' => 'Tanda Terima SPK Pasang Batu',
        ];

        return view('spk.spk-diamond-receipt', [
            'title' => "Tanda Terima SPK Pasang Batu {$serahTerimaSpkDiamond->doc_no} — Print",
            'header' => $header,
            'receiptNo' => $serahTerimaSpkDiamond->doc_no,
            'printedAt' => ($serahTerimaSpkDiamond->created_at ?? now())->format('d-M-Y H:i'),
            'printedBy' => $serahTerimaSpkDiamond->created_by ?? '-',
            'receiptDate' => $serahTerimaSpkDiamond->tanggal->format('d-M-Y'),
            'receiptFrom' => (string) $serahTerimaSpkDiamond->dari,
            'receiptTo' => (string) $serahTerimaSpkDiamond->untuk,
            'signatures' => [
                ['title' => 'Diserahkan oleh', 'name' => (string) $serahTerimaSpkDiamond->diserahkan_oleh],
                ['title' => 'Diketahui oleh', 'name' => (string) $serahTerimaSpkDiamond->diketahui_oleh],
                ['title' => 'Diterima oleh', 'name' => (string) $serahTerimaSpkDiamond->diterima_oleh],
            ],
            'rows' => $serahTerimaSpkDiamond->items,
        ]);
    }

    /**
     * Soft delete tanda terima dari modal riwayat.
     */
    public function destroyReceipt(Request $request, SerahTerimaSpkDiamond $serahTerimaSpkDiamond): JsonResponse
    {
        DB::connection('third')->transaction(function () use ($request, $serahTerimaSpkDiamond): void {
            $serahTerimaSpkDiamond->forceFill(['deleted_by' => $this->actorName($request)])->save();
            $serahTerimaSpkDiamond->delete();
        });

        return response()->json([
            'message' => "Tanda terima {$serahTerimaSpkDiamond->doc_no} berhasil dihapus.",
        ]);
    }

    private function resolveIndexDate(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $parsed = date_create_immutable($trimmed);

        if ($parsed === false) {
            return null;
        }

        return $parsed->format('Y-m-d');
    }

    /**
     * @return list<string>
     */
    private function receiptEmployeeOptions(): array
    {
        return Employee::query()
            ->active()
            ->whereNotNull('nama_lengkap')
            ->where('nama_lengkap', '!=', '')
            ->orderBy('nama_lengkap')
            ->pluck('nama_lengkap')
            ->map(fn (mixed $name): string => trim((string) $name))
            ->unique()
            ->values()
            ->all();
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
            'formTitle' => 'Tanda Terima SPK Pasang Batu',
            'docNo' => (string) config('spk.spk_diamond_receipt_document_no', 'WHOJ-PRD-FRM-009'),
            'issueNo' => (string) config('spk.issue_no', '01'),
            'revision' => (string) config('spk.spk_diamond_receipt_revision', '00'),
            'issueDate' => (string) config('spk.spk_diamond_receipt_issue_date', '05/11/2026'),
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
}
