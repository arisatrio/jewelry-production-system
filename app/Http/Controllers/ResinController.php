<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResinRequest;
use App\Http\Requests\UpdateResinRequest;
use App\Models\MsShape;
use App\Models\Production;
use App\Models\Resin;
use App\Models\ResinStone;
use App\Support\GoogleCloudStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ResinController extends Controller
{
    /**
     * Display a listing of resin documents.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $resins = Resin::query()
            ->notDeleted()
            ->withCount([
                'stones as stone_count' => fn ($query) => $query->notDeleted(),
            ])
            ->with([
                'production' => fn ($query) => $query
                    ->notDeleted()
                    ->select(['row_id', 'spk_no', 'item_name', 'customer_name']),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('doc_no', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('production', function ($productionQuery) use ($search): void {
                            $productionQuery->notDeleted()
                                ->where(function ($productionInner) use ($search): void {
                                    $productionInner->where('spk_no', 'like', "%{$search}%")
                                        ->orWhere('item_name', 'like', "%{$search}%")
                                        ->orWhere('customer_name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Resin $resin): array => $this->toListItem($resin));

        return Inertia::render('resin/index', [
            'resins' => $resins,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resin document.
     */
    public function create(): Response
    {
        return Inertia::render('resin/create', [
            'shapeOptions' => $this->shapeOptions(),
            'form' => [
                'transDate' => now()->format('Y-m-d'),
                'spkId' => null,
                'spkNo' => '',
                'itemName' => '',
                'customerName' => '',
                'fileUpload' => null,
                'fileUrl' => null,
                'stones' => [],
            ],
        ]);
    }

    /**
     * Search SPKs for the resin form selector.
     */
    public function searchSpks(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();
        $limit = max(1, min($request->integer('limit', 25), 50));

        $query = Production::query()
            ->notDeleted()
            ->whereNotNull('spk_no')
            ->orderByDesc('row_id')
            ->limit($limit)
            ->select([
                'row_id',
                'spk_no',
                'item_name',
                'customer_name',
            ]);

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($innerQuery) use ($like): void {
                $innerQuery->where('spk_no', 'like', $like)
                    ->orWhere('item_name', 'like', $like)
                    ->orWhere('customer_name', 'like', $like);
            });
        }

        return response()->json([
            'status' => true,
            'data' => $query->get()->map(fn (Production $production): array => [
                'rowId' => (int) $production->row_id,
                'spkNo' => (string) $production->spk_no,
                'customer' => filled($production->customer_name)
                    ? (string) $production->customer_name
                    : '—',
                'item' => filled($production->item_name)
                    ? (string) $production->item_name
                    : '—',
            ])->values()->all(),
        ]);
    }

    /**
     * Store a newly created resin document.
     */
    public function store(
        StoreResinRequest $request,
        GoogleCloudStorageService $gcs,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $this->actorName($request);
        $file = $request->file('file');

        DB::connection('third')->transaction(function () use ($validated, $actor, $file, $gcs): void {
            $resin = Resin::query()->create([
                'doc_no' => $this->nextDocNo(),
                'trans_date' => $validated['trans_date'],
                'spk_id' => $validated['spk_id'],
                'file_upload' => $file instanceof UploadedFile
                    ? $this->storeFile($file, $gcs)
                    : null,
                'status' => Resin::STATUS_OPEN,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $this->storeStones($resin, $validated['stones'] ?? [], $actor);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen resin berhasil ditambahkan.',
        ]);

        return to_route('resin.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): never
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resin document.
     */
    public function edit(Resin $resin): Response
    {
        abort_if($resin->is_deleted === 1, 404);

        $resin->load([
            'production' => fn ($query) => $query
                ->notDeleted()
                ->select(['row_id', 'spk_no', 'item_name', 'customer_name']),
            'stones' => fn ($query) => $query
                ->notDeleted()
                ->with(['shape' => fn ($shapeQuery) => $shapeQuery->notDeleted()])
                ->orderBy('line_id'),
        ]);

        return Inertia::render('resin/edit', [
            'shapeOptions' => $this->shapeOptions(),
            'resinItem' => $this->toFormItem($resin),
        ]);
    }

    /**
     * Update the specified resin document.
     */
    public function update(
        UpdateResinRequest $request,
        Resin $resin,
        GoogleCloudStorageService $gcs,
    ): RedirectResponse {
        abort_if($resin->is_deleted === 1, 404);

        $validated = $request->validated();
        $actor = $this->actorName($request);
        $file = $request->file('file');

        DB::connection('third')->transaction(function () use ($resin, $validated, $actor, $file, $gcs): void {
            $payload = [
                'doc_no' => $validated['doc_no'],
                'trans_date' => $validated['trans_date'],
                'spk_id' => $validated['spk_id'],
                'modified_date' => now(),
                'modified_by' => $actor,
            ];

            if ($file instanceof UploadedFile) {
                $payload['file_upload'] = $this->storeFile($file, $gcs);
            }

            $resin->update($payload);

            $resin->stones()
                ->notDeleted()
                ->update([
                    'is_deleted' => 1,
                    'deleted_date' => now(),
                    'deleted_by' => $actor,
                    'modified_date' => now(),
                    'modified_by' => $actor,
                ]);

            $this->storeStones($resin, $validated['stones'] ?? [], $actor);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen resin berhasil diperbarui.',
        ]);

        return to_route('resin.index');
    }

    /**
     * Soft-delete the specified resin document.
     */
    public function destroy(Request $request, Resin $resin): RedirectResponse
    {
        abort_if($resin->is_deleted === 1, 404);

        $actor = $this->actorName($request);

        DB::connection('third')->transaction(function () use ($resin, $actor): void {
            $resin->update([
                'is_deleted' => 1,
                'deleted_date' => now(),
                'deleted_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $resin->stones()
                ->notDeleted()
                ->update([
                    'is_deleted' => 1,
                    'deleted_date' => now(),
                    'deleted_by' => $actor,
                    'modified_date' => now(),
                    'modified_by' => $actor,
                ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen resin berhasil dihapus.',
        ]);

        return to_route('resin.index');
    }

    /**
     * @param  list<array{
     *     shape_id?: int|null,
     *     pcs?: int|null,
     *     carat?: int|null,
     *     size?: string|null
     * }>  $stones
     */
    private function storeStones(Resin $resin, array $stones, string $actor): void
    {
        foreach ($stones as $stone) {
            $hasValue = filled($stone['shape_id'] ?? null)
                || filled($stone['pcs'] ?? null)
                || filled($stone['carat'] ?? null)
                || filled($stone['size'] ?? null);

            if (! $hasValue) {
                continue;
            }

            $resin->stones()->create([
                'shape_id' => $stone['shape_id'] ?? null,
                'pcs' => $stone['pcs'] ?? null,
                'carat' => $stone['carat'] ?? null,
                'size' => $stone['size'] ?? null,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);
        }
    }

    /**
     * Upload file ke GCS; simpan nama file pendek (kompatibel varchar(50)).
     */
    private function storeFile(UploadedFile $file, GoogleCloudStorageService $gcs): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension()));
        $filename = time().'_'.Str::lower(Str::random(10)).($extension !== '' ? '.'.$extension : '');
        $folder = (string) config('gcs.folder', 'produksi');

        $gcs->uploadFile($file, $folder, $filename);

        return $filename;
    }

    /**
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     status: string|null,
     *     spkNo: string|null,
     *     itemName: string|null,
     *     customerName: string|null,
     *     stoneCount: int,
     *     fileUpload: string|null
     * }
     */
    private function toListItem(Resin $resin): array
    {
        return [
            'id' => (int) $resin->row_id,
            'docNo' => $resin->doc_no,
            'transDate' => $resin->trans_date?->format('Y-m-d'),
            'status' => $resin->status,
            'spkNo' => $resin->production?->spk_no,
            'itemName' => $resin->production?->item_name,
            'customerName' => $resin->production?->customer_name,
            'stoneCount' => (int) ($resin->stone_count ?? 0),
            'fileUpload' => $resin->file_upload,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     status: string|null,
     *     spkId: int|null,
     *     spkNo: string|null,
     *     itemName: string|null,
     *     customerName: string|null,
     *     fileUpload: string|null,
     *     fileUrl: string|null,
     *     stones: list<array{
     *         shapeId: int|null,
     *         shapeName: string|null,
     *         pcs: int|null,
     *         carat: int|null,
     *         size: string
     *     }>
     * }
     */
    private function toFormItem(Resin $resin): array
    {
        return [
            'id' => (int) $resin->row_id,
            'docNo' => $resin->doc_no,
            'transDate' => $resin->trans_date?->format('Y-m-d'),
            'status' => $resin->status,
            'spkId' => $resin->spk_id,
            'spkNo' => $resin->production?->spk_no,
            'itemName' => $resin->production?->item_name,
            'customerName' => $resin->production?->customer_name,
            'fileUpload' => $resin->file_upload,
            'fileUrl' => $this->fileUrl($resin->file_upload),
            'stones' => $resin->stones
                ->filter(fn (ResinStone $stone): bool => $stone->is_deleted === 0)
                ->map(fn (ResinStone $stone): array => [
                    'shapeId' => $stone->shape_id,
                    'shapeName' => filled($stone->shape?->name)
                        ? (string) $stone->shape->name
                        : (filled($stone->shape?->code) ? (string) $stone->shape->code : null),
                    'pcs' => $stone->pcs,
                    'carat' => $stone->carat,
                    'size' => $stone->size !== null
                        ? number_format((float) $stone->size, 2, '.', '')
                        : '',
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function shapeOptions(): array
    {
        return MsShape::query()
            ->notDeleted()
            ->orderBy('name')
            ->get(['row_id', 'name', 'code'])
            ->map(fn (MsShape $shape): array => [
                'id' => (int) $shape->row_id,
                'name' => filled($shape->name)
                    ? (string) $shape->name
                    : (filled($shape->code) ? (string) $shape->code : (string) $shape->row_id),
            ])
            ->values()
            ->all();
    }

    private function fileUrl(?string $fileName): ?string
    {
        if (! filled($fileName)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', (string) $fileName));

        if ($path === '' || $path === '-') {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
        ) {
            return $path;
        }

        if (str_contains($path, '/')) {
            return '/storage/'.ltrim($path, '/');
        }

        return rtrim((string) config('spk.production_image_base_url'), '/').'/'.$path;
    }

    private function nextDocNo(): string
    {
        $latest = Resin::query()
            ->notDeleted()
            ->where('doc_no', 'like', 'RES%')
            ->orderByDesc('row_id')
            ->value('doc_no');

        $nextNumber = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $nextNumber = ((int) ($matches[1] ?? 0)) + 1;
        }

        return sprintf('RES%07d', $nextNumber);
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }
}
