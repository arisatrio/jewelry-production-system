<?php

namespace App\Support;

use App\Models\SpkDiamond;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SpkDiamondLedger
{
    public const TRANSTYPE_OUT = 18;

    public function __construct(private StoneLedger $stoneLedger) {}

    /**
     * @throws ValidationException
     */
    public function applySubmit(SpkDiamond $document, string $actor): void
    {
        $period = $this->stoneLedger->activePeriod();

        if ($period === null) {
            throw ValidationException::withMessages([
                'status' => 'Periode batu aktif tidak ditemukan.',
            ]);
        }

        $periodId = $period['id'];
        $now = now();

        $document->load([
            'stones' => fn ($query) => $query->notDeleted(),
            'diamonds' => fn ($query) => $query->notDeleted(),
        ]);

        $stockError = app(SpkDiamondSettingStockValidator::class)->firstErrorForLines(
            $document->stones
                ->map(fn ($line): array => [
                    'stone_id' => $line->stone_id,
                    'pcs' => $line->pcs,
                ])
                ->all(),
        );

        if ($stockError !== null) {
            throw ValidationException::withMessages([
                'setting_stones' => $stockError,
            ]);
        }

        foreach ($document->stones as $line) {
            $stoneId = (int) $line->stone_id;
            $pcs = (float) ($line->pcs ?? 0);

            if ($stoneId <= 0 || $pcs <= 0) {
                continue;
            }

            $rowId = DB::connection('third')->table('trstone')->insertGetId([
                'period_id' => $periodId,
                'transtype_id' => self::TRANSTYPE_OUT,
                'ref_row_id' => $document->id,
                'stone_id' => $stoneId,
                'spk_id' => $document->spk_id,
                'pcs' => $pcs,
                'crt' => filled($line->crt)
                    ? number_format((float) $line->crt, 3, '.', '')
                    : null,
                'txt' => null,
                'notes' => filled($line->notes) ? (string) $line->notes : 'SPK Diamond',
                'is_used' => 0,
                'is_deleted' => 0,
                'created_date' => $now,
                'created_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
                'deleted_date' => null,
                'deleted_by' => null,
            ]);

            $line->update([
                'trstone_row_id' => $rowId,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
        }

        $diamondIds = $document->diamonds
            ->pluck('trdiamond_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($diamondIds === []) {
            return;
        }

        if (! Schema::connection('third')->hasColumn('trdiamond', 'spk_diamond_id')) {
            throw ValidationException::withMessages([
                'diamonds' => 'Kolom reservasi diamond belum tersedia.',
            ]);
        }

        $inventory = app(DiamondDossierInventory::class);
        $selectable = $inventory->selectableIdsForMounting($diamondIds, null);

        if (count($selectable) !== count($diamondIds)) {
            throw ValidationException::withMessages([
                'diamonds' => 'Sebagian Batu Dossier sudah dipakai. Muat ulang halaman lalu pilih ulang.',
            ]);
        }

        DB::connection('third')
            ->table('trdiamond')
            ->whereIn('row_id', $diamondIds)
            ->where('is_deleted', 0)
            ->update([
                'spk_diamond_id' => $document->id,
                'is_used' => 1,
                'out_date' => $now->toDateString(),
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
    }
}
