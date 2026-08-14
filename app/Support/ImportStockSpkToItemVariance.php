<?php

namespace App\Support;

use App\Models\MsItemVariance;
use App\Models\MsItemVarianceStone;
use App\Models\Production;
use App\Models\SpkStone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportStockSpkToItemVariance
{
    public const ACTOR = 'system-import';

    public const YEAR = 2026;

    /**
     * @param  list<int>|null  $onlyRowIds
     * @return array{
     *     created: int,
     *     linked: int,
     *     skipped: int,
     *     errors: list<array{spk_no: string|null, message: string}>
     * }
     */
    public function handle(bool $dryRun = false, ?int $limit = null, ?array $onlyRowIds = null): array
    {
        $created = 0;
        $linked = 0;
        $skipped = 0;
        $errors = [];

        $query = $this->eligibleQuery();

        if ($onlyRowIds !== null) {
            $query->whereIn('row_id', $onlyRowIds);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $productions = $query
            ->with([
                'item',
                'stones' => fn ($stones) => $stones->notDeleted()->orderBy('line_id'),
            ])
            ->orderBy('row_id')
            ->get();

        foreach ($productions as $production) {
            try {
                $result = $this->importOne($production, $dryRun);
            } catch (Throwable $exception) {
                $errors[] = [
                    'spk_no' => $production->spk_no,
                    'message' => $exception->getMessage(),
                ];

                continue;
            }

            if ($result === 'created') {
                $created++;
                $linked++;
            } else {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'linked' => $linked,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @return Builder<Production>
     */
    public function eligibleQuery(): Builder
    {
        $year = (string) self::YEAR;

        return Production::query()
            ->notDeleted()
            ->whereRaw('LOWER(spk_type) = ?', ['stock'])
            ->where(function (Builder $query): void {
                $query->whereNull('item_variance_id')
                    ->orWhereDoesntHave('itemVariance');
            })
            ->whereNotNull('item_id')
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->where(function (Builder $query) use ($year): void {
                $query->whereYear('order_date', self::YEAR)
                    ->orWhere('spk_no', 'like', $year.'/%')
                    ->orWhereYear('created_date', self::YEAR);
            });
    }

    /**
     * @return 'created'|'skipped'
     */
    private function importOne(Production $production, bool $dryRun): string
    {
        $description = trim((string) $production->description);

        if ($description === '' || $production->item_id === null) {
            return 'skipped';
        }

        if ($dryRun) {
            return 'created';
        }

        return DB::connection('third')->transaction(function () use ($production, $description): string {
            $production = Production::query()
                ->notDeleted()
                ->where('row_id', $production->row_id)
                ->with('item')
                ->lockForUpdate()
                ->first();

            if ($production === null) {
                return 'skipped';
            }

            if ($production->item_variance_id !== null) {
                $varianceExists = MsItemVariance::query()
                    ->where('row_id', $production->item_variance_id)
                    ->exists();

                if ($varianceExists) {
                    return 'skipped';
                }
            }

            $itemTypeName = filled($production->item?->name)
                ? (string) $production->item->name
                : (filled($production->item_name) ? (string) $production->item_name : null);

            $name = $this->varianceNameFromDescription($description, $itemTypeName);
            $name = mb_substr($name, 0, 100);
            $now = now();
            $ukuran = $this->ukuranFromSpk($production->diameter_length_ringsize);

            $variance = MsItemVariance::query()->create([
                'item_id' => $production->item_id,
                'name' => $name,
                'description' => $description,
                'diameter' => $ukuran['diameter'],
                'dimensi' => $ukuran['dimensi'],
                'ring_size' => $ukuran['ring_size'],
                'diameter_length_ringsize' => $ukuran['diameter_length_ringsize'],
                'gold_weight' => $production->gold_weight,
                'gold_color' => filled($production->gold_color)
                    ? (string) $production->gold_color
                    : null,
                'jwcad_3d' => filled($production->jwcad_3d)
                    ? trim((string) $production->jwcad_3d)
                    : null,
                'image' => filled($production->file_name)
                    ? trim((string) $production->file_name)
                    : null,
                'is_deleted' => 0,
                'created_date' => $now,
                'created_by' => self::ACTOR,
                'modified_date' => $now,
                'modified_by' => self::ACTOR,
            ]);

            $stones = SpkStone::query()
                ->notDeleted()
                ->where('row_id', $production->row_id)
                ->orderBy('line_id')
                ->get();

            foreach ($stones as $stone) {
                $this->createVarianceStone($variance, $stone, $now);
            }

            $production->update([
                'item_variance_id' => $variance->row_id,
                'modified_date' => $now,
                'modified_by' => self::ACTOR,
            ]);

            return 'created';
        });
    }

    /**
     * Hapus prefix nama tipe item di depan deskripsi untuk nama varian.
     * Contoh: "Earring Cuff xxxx" + tipe "Earring" → "Cuff xxxx".
     */
    public function varianceNameFromDescription(string $description, ?string $itemTypeName): string
    {
        $name = trim($description);
        $itemTypeName = filled($itemTypeName) ? trim($itemTypeName) : '';

        if ($name === '' || $itemTypeName === '') {
            return $name;
        }

        $pattern = '/^'.preg_quote($itemTypeName, '/').'\s+/iu';
        $stripped = preg_replace($pattern, '', $name, 1);

        if (! is_string($stripped)) {
            return $name;
        }

        $stripped = trim($stripped);

        return $stripped !== '' ? $stripped : $name;
    }

    private function createVarianceStone(
        MsItemVariance $variance,
        SpkStone $stone,
        mixed $now,
    ): void {
        $pcs = $stone->pcs;
        $totalCarat = $stone->carat;
        $caratPerPcs = null;

        if ($pcs !== null && $pcs > 0 && $totalCarat !== null && $totalCarat !== '') {
            $caratPerPcs = number_format(((float) $totalCarat) / $pcs, 3, '.', '');
        }

        MsItemVarianceStone::query()->create([
            'item_variance_id' => $variance->row_id,
            'shape_id' => $stone->shape_id,
            'pcs' => $pcs,
            'carat_per_pcs' => $caratPerPcs,
            'total_carat' => filled($totalCarat) ? $totalCarat : null,
            'size' => $stone->size,
            'is_deleted' => 0,
            'created_date' => $now,
            'created_by' => self::ACTOR,
            'modified_date' => $now,
            'modified_by' => self::ACTOR,
        ]);
    }

    /**
     * @return array{
     *     diameter: string|null,
     *     dimensi: string|null,
     *     ring_size: string|null,
     *     diameter_length_ringsize: string|null
     * }
     */
    private function ukuranFromSpk(?string $label): array
    {
        $label = filled($label) ? trim($label) : null;

        if ($label === null) {
            return [
                'diameter' => null,
                'dimensi' => null,
                'ring_size' => null,
                'diameter_length_ringsize' => null,
            ];
        }

        if (substr_count($label, '/') >= 2) {
            $parts = preg_split('/\s*\/\s*/', $label, 3) ?: [];
            $parts = array_pad(
                array_map(
                    static fn (string $part): string => trim($part),
                    $parts,
                ),
                3,
                '',
            );

            $diameter = $parts[0] !== '' ? $parts[0] : null;
            $dimensi = $parts[1] !== '' ? $parts[1] : null;
            $ringSize = $parts[2] !== '' ? $parts[2] : null;

            return [
                'diameter' => $diameter,
                'dimensi' => $dimensi,
                'ring_size' => $ringSize,
                'diameter_length_ringsize' => implode(' / ', [
                    $diameter ?? '',
                    $dimensi ?? '',
                    $ringSize ?? '',
                ]),
            ];
        }

        return [
            'diameter' => null,
            'dimensi' => $label,
            'ring_size' => null,
            'diameter_length_ringsize' => $label,
        ];
    }
}
