<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiamondDossierInventory
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_USED = 'used';

    /**
     * @return LengthAwarePaginator<int, array<string, int|string|null>>
     */
    public function paginate(string $search, ?string $status, int $perPage): LengthAwarePaginator
    {
        return $this->filteredQuery($search, $status)
            ->orderByDesc('diamond.row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (object $diamond): array => $this->toRow($diamond));
    }

    /**
     * @return array{count: int, crt: string}
     */
    public function summary(string $search, ?string $status): array
    {
        $summary = $this->filteredQuery($search, $status)
            ->cloneWithout(['columns', 'orders'])
            ->selectRaw('COUNT(*) as diamond_count, SUM(COALESCE(diamond.crt, 0)) as total_crt')
            ->first();

        return [
            'count' => (int) ($summary->diamond_count ?? 0),
            'crt' => number_format((float) ($summary->total_crt ?? 0), 3, '.', ''),
        ];
    }

    public function availableCountInCrtRange(int $shapeId, string $crtMin, string $crtMax): int
    {
        return $this->availableInCrtRangeQuery($shapeId, $crtMin, $crtMax)->count();
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    public function availableInCrtRange(int $shapeId, string $crtMin, string $crtMax): array
    {
        return array_values($this->availableInCrtRangeQuery($shapeId, $crtMin, $crtMax)
            ->orderBy('diamond.crt')
            ->orderBy('diamond.row_id')
            ->get()
            ->map(fn (object $diamond): array => $this->toRow($diamond))
            ->all());
    }

    /**
     * Available dossier diamonds plus the ones already assigned to the given mounting document.
     *
     * @return list<array{value: string, label: string, description: string, code: string|null, diamondType: string|null, shape: string|null, certificate: string|null, crt: string|null}>
     */
    public function mountingOptions(?int $mountingId = null): array
    {
        return array_values($this->selectableForMountingQuery($mountingId)
            ->orderBy('diamond.doc_no')
            ->orderBy('diamond.row_id')
            ->get()
            ->map(fn (object $diamond): array => $this->toMountingOption($diamond))
            ->all());
    }

    /**
     * @param  list<int>  $diamondIds
     * @return list<int>
     */
    public function selectableIdsForMounting(array $diamondIds, ?int $mountingId = null): array
    {
        if ($diamondIds === []) {
            return [];
        }

        return array_values($this->selectableForMountingQuery($mountingId)
            ->whereIn('diamond.row_id', $diamondIds)
            ->pluck('diamond.row_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * Links the selected dossier diamonds to the mounting document and releases the ones no longer selected.
     *
     * @param  list<int>  $diamondIds
     *
     * @throws ValidationException
     */
    public function assignToMounting(int $mountingId, array $diamondIds, string $actor): void
    {
        $now = now();
        $diamondIds = array_values(array_unique($diamondIds));

        $assignedIds = DB::connection('third')
            ->table('trdiamond')
            ->where('diamondmounting_id', $mountingId)
            ->where('is_deleted', 0)
            ->pluck('row_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $releasedIds = array_values(array_diff($assignedIds, $diamondIds));
        $newIds = array_values(array_diff($diamondIds, $assignedIds));

        if ($releasedIds !== []) {
            DB::connection('third')
                ->table('trdiamond')
                ->whereIn('row_id', $releasedIds)
                ->update([
                    'diamondmounting_id' => 0,
                    'is_used' => 0,
                    'out_date' => null,
                    'modified_date' => $now,
                    'modified_by' => $actor,
                ]);
        }

        if ($newIds === []) {
            return;
        }

        $linkedCount = DB::connection('third')
            ->table('trdiamond as diamond')
            ->whereIn('diamond.row_id', $newIds)
            ->where('diamond.is_deleted', 0)
            ->where(fn (Builder $query) => $this->whereAvailable($query))
            ->update([
                'diamond.diamondmounting_id' => $mountingId,
                'diamond.is_used' => 1,
                'diamond.out_date' => $now->toDateString(),
                'diamond.modified_date' => $now,
                'diamond.modified_by' => $actor,
            ]);

        if ($linkedCount !== count($newIds)) {
            throw ValidationException::withMessages([
                'diamonds' => 'Sebagian Batu Dossier yang dipilih sudah dipakai dokumen lain. Muat ulang halaman lalu pilih ulang.',
            ]);
        }
    }

    private function selectableForMountingQuery(?int $mountingId): Builder
    {
        return $this->filteredQuery('', null)
            ->where(function (Builder $query) use ($mountingId): void {
                $query->where(fn (Builder $availableQuery) => $this->whereAvailable($availableQuery));

                if ($mountingId !== null) {
                    $query->orWhere('diamond.diamondmounting_id', $mountingId);
                }
            });
    }

    /**
     * @return array{value: string, label: string, description: string, code: string|null, diamondType: string|null, shape: string|null, certificate: string|null, crt: string|null}
     */
    private function toMountingOption(object $diamond): array
    {
        $row = $this->toRow($diamond);
        $code = $row['code'] ?? '#'.$row['id'];
        $crtLabel = $row['crt'] !== null ? $row['crt'].' ct' : null;

        return [
            'value' => (string) $row['id'],
            'label' => implode(' · ', array_filter([$code, $row['shape'], $crtLabel])),
            'description' => implode(' · ', array_filter([$row['diamondType'], $row['certificate']])),
            'code' => $row['code'],
            'diamondType' => $row['diamondType'],
            'shape' => $row['shape'],
            'certificate' => $row['certificate'],
            'crt' => $row['crt'],
        ];
    }

    private function availableInCrtRangeQuery(int $shapeId, string $crtMin, string $crtMax): Builder
    {
        return $this->filteredQuery('', self::STATUS_AVAILABLE)
            ->where('diamond.shape_id', $shapeId)
            ->whereBetween('diamond.crt', [$crtMin, $crtMax]);
    }

    /**
     * @return array{id: int, code: string|null, diamondType: string|null, shape: string|null, color: string|null, crt: string|null, certificate: string|null, supplier: string|null, entryDate: string|null, outDate: string|null, status: string, mountingDocumentNo: string|null, createdBy: string|null, createdDate: string|null}
     */
    private function toRow(object $diamond): array
    {
        return [
            'id' => (int) $diamond->row_id,
            'code' => $this->nullableString($diamond->doc_no),
            'diamondType' => $this->nullableString($diamond->diamond_type),
            'shape' => $this->nullableString($diamond->shape_name),
            'color' => $this->nullableString($diamond->color),
            'crt' => filled($diamond->crt)
                ? number_format((float) $diamond->crt, 3, '.', '')
                : null,
            'certificate' => $this->nullableString($diamond->certificate),
            'supplier' => $this->nullableString($diamond->supplier),
            'entryDate' => $this->nullableString($diamond->entry_date),
            'outDate' => $this->nullableString($diamond->out_date),
            'status' => (int) $diamond->is_used === 1
                ? self::STATUS_USED
                : self::STATUS_AVAILABLE,
            'mountingDocumentNo' => $this->nullableString($diamond->mounting_doc_no),
            'createdBy' => $this->nullableString($diamond->created_by),
            'createdDate' => $this->nullableString($diamond->created_date),
        ];
    }

    private function whereAvailable(Builder $query): Builder
    {
        return $query->where('diamond.is_used', '!=', 1)
            ->orWhereNull('diamond.is_used');
    }

    private function filteredQuery(string $search, ?string $status): Builder
    {
        return DB::connection('third')
            ->table('trdiamond as diamond')
            ->leftJoin('msshape as shape', 'shape.row_id', '=', 'diamond.shape_id')
            ->leftJoin('diamondmounting as mounting_document', function ($join): void {
                $join->on('mounting_document.row_id', '=', 'diamond.diamondmounting_id')
                    ->where('mounting_document.is_deleted', 0);
            })
            ->where('diamond.is_deleted', 0)
            ->when($status === self::STATUS_AVAILABLE, fn (Builder $query) => $query
                ->where(fn (Builder $innerQuery) => $this->whereAvailable($innerQuery)))
            ->when($status === self::STATUS_USED, fn (Builder $query) => $query
                ->where('diamond.is_used', 1))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $innerQuery) use ($like): void {
                    $innerQuery->where('diamond.doc_no', 'like', $like)
                        ->orWhere('diamond.diamond_type', 'like', $like)
                        ->orWhere('diamond.certificate', 'like', $like)
                        ->orWhere('diamond.color', 'like', $like)
                        ->orWhere('diamond.supplier', 'like', $like)
                        ->orWhere('shape.name', 'like', $like)
                        ->orWhere('mounting_document.doc_no', 'like', $like);
                });
            })
            ->select([
                'diamond.row_id',
                'diamond.doc_no',
                'diamond.diamond_type',
                'shape.name as shape_name',
                'diamond.color',
                'diamond.crt',
                'diamond.certificate',
                'diamond.supplier',
                'diamond.entry_date',
                'diamond.out_date',
                'diamond.is_used',
                'mounting_document.doc_no as mounting_doc_no',
                'diamond.created_by',
                'diamond.created_date',
            ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
