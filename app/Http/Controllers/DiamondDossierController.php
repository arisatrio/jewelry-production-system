<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDestroyDiamondDossierRequest;
use App\Http\Requests\ImportDiamondDossierRequest;
use App\Http\Requests\StoreDiamondDossierRequest;
use App\Http\Requests\UpdateDiamondDossierRequest;
use App\Support\DiamondDossierImporter;
use App\Support\DiamondDossierImportTemplateExport;
use App\Support\DiamondDossierInventory;
use App\Support\SpkStoneAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DiamondDossierController extends Controller
{
    public function index(
        Request $request,
        DiamondDossierInventory $inventory,
    ): Response {
        $filters = Validator::make($request->only([
            'search',
            'status',
            'per_page',
            'diamond_type',
            'shape_id',
            'supplier',
            'sort',
            'direction',
        ]), [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                DiamondDossierInventory::STATUS_AVAILABLE,
                DiamondDossierInventory::STATUS_RESERVED,
                DiamondDossierInventory::STATUS_USED,
            ])],
            'per_page' => ['nullable', 'integer'],
            'diamond_type' => ['nullable', Rule::in(DiamondDossierInventory::diamondTypes())],
            'shape_id' => ['nullable', 'integer'],
            'supplier' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(DiamondDossierInventory::indexSorts())],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ])->validate();

        $search = trim((string) ($filters['search'] ?? ''));
        $status = filled($filters['status'] ?? null)
            ? (string) $filters['status']
            : null;
        $diamondType = filled($filters['diamond_type'] ?? null)
            ? (string) $filters['diamond_type']
            : null;
        $shapeId = filled($filters['shape_id'] ?? null)
            ? (int) $filters['shape_id']
            : null;
        $supplier = filled($filters['supplier'] ?? null)
            ? trim((string) $filters['supplier'])
            : null;
        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
        $sort = DiamondDossierInventory::resolveIndexSort(
            (string) ($filters['sort'] ?? 'entry_date'),
        );
        $direction = DiamondDossierInventory::resolveIndexDirection(
            (string) ($filters['direction'] ?? 'desc'),
        );

        return Inertia::render('inventory/batu-dossier/index', [
            'diamonds' => $inventory->paginate(
                $search,
                $status,
                $perPage,
                $diamondType,
                $shapeId,
                $supplier,
                $sort,
                $direction,
            ),
            'summary' => $inventory->summary(
                $search,
                $status,
                $diamondType,
                $shapeId,
                $supplier,
            ),
            'statusSummaries' => $inventory->statusSummaries(
                $search,
                $diamondType,
                $shapeId,
                $supplier,
            ),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
                'diamond_type' => $diamondType,
                'shape_id' => $shapeId,
                'supplier' => $supplier,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'filterOptions' => [
                'sort' => DiamondDossierInventory::indexSortOptions(),
                'direction' => [
                    ['value' => 'asc', 'label' => 'A–Z / terkecil'],
                    ['value' => 'desc', 'label' => 'Z–A / terbesar'],
                ],
            ],
            'shapeOptions' => $inventory->shapeOptions(),
            'diamondTypeOptions' => DiamondDossierInventory::diamondTypeOptions(),
            'supplierOptions' => $inventory->supplierOptions(),
            'defaults' => [
                'diamondType' => 'Dossier',
                'entryDate' => now()->toDateString(),
            ],
            'lastUpdatedAt' => $inventory->lastInventoryModifiedAt(),
        ]);
    }

    public function store(
        StoreDiamondDossierRequest $request,
        DiamondDossierInventory $inventory,
    ): RedirectResponse {
        $validated = $request->validated();
        $diamondId = $inventory->create($validated, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Batu dossier berhasil ditambahkan.',
        ]);

        return back();
    }

    public function importTemplate(DiamondDossierInventory $inventory): BinaryFileResponse
    {
        $sampleShape = 'RD';

        if (Schema::connection('third')->hasTable('msshape')) {
            $shape = DB::connection('third')
                ->table('msshape')
                ->select(['name', 'code'])
                ->when(
                    Schema::connection('third')->hasColumn('msshape', 'is_deleted'),
                    fn ($query) => $query->where('is_deleted', 0),
                )
                ->orderBy('name')
                ->first();

            if ($shape !== null) {
                $code = trim((string) ($shape->code ?? ''));
                $name = trim((string) ($shape->name ?? ''));
                $sampleShape = $code !== '' ? $code : ($name !== '' ? $name : $sampleShape);
            }
        }

        return Excel::download(
            new DiamondDossierImportTemplateExport([
                [
                    now()->toDateString(),
                    $sampleShape,
                    '0.500',
                    'D',
                    '',
                    'RJ',
                ],
            ]),
            'template-import-batu-dossier.xlsx',
        );
    }

    public function import(
        ImportDiamondDossierRequest $request,
        DiamondDossierImporter $importer,
    ): RedirectResponse {
        $result = $importer->import(
            $request->file('file'),
            $this->actorName($request),
        );

        if ($result['imported'] > 0) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $result['imported'] === 1
                    ? '1 batu dossier berhasil diimport.'
                    : "{$result['imported']} batu dossier berhasil diimport.",
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

    public function show(
        int $diamond,
        DiamondDossierInventory $inventory,
    ): Response {
        $detail = $inventory->find($diamond);

        abort_if($detail === null, 404);

        $canEdit = $inventory->editableBlockReason($diamond) === null;
        $allocationService = app(SpkStoneAllocationService::class);
        $canRevokeAllocation = $detail['status'] === DiamondDossierInventory::STATUS_RESERVED
            && $allocationService->tableAvailable()
            && $allocationService->diamondIsReserved($diamond);

        return Inertia::render('inventory/batu-dossier/show', [
            'diamond' => $detail,
            'canEdit' => $canEdit,
            'canRevokeAllocation' => $canRevokeAllocation,
            'shapeOptions' => $inventory->shapeOptions(),
            'diamondTypeOptions' => DiamondDossierInventory::diamondTypeOptions(),
            'form' => $canEdit ? $inventory->formPayload($diamond) : null,
        ]);
    }

    public function revokeAllocation(
        int $diamond,
        Request $request,
        SpkStoneAllocationService $allocations,
    ): RedirectResponse {
        $allocations->revokeDossierByDiamondId($diamond, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Alokasi batu dossier ke SPK dibatalkan.',
        ]);

        return back();
    }

    public function update(
        UpdateDiamondDossierRequest $request,
        int $diamond,
        DiamondDossierInventory $inventory,
    ): RedirectResponse {
        $inventory->update($diamond, $request->validated(), $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Batu dossier berhasil diperbarui.',
        ]);

        return back();
    }

    public function destroyMany(
        BulkDestroyDiamondDossierRequest $request,
        DiamondDossierInventory $inventory,
    ): RedirectResponse {
        $deletedCount = $inventory->softDeleteMany(
            $request->diamondIds(),
            $this->actorName($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $deletedCount === 1
                ? '1 batu dossier berhasil dihapus.'
                : "{$deletedCount} batu dossier berhasil dihapus.",
        ]);

        return back();
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }
}
