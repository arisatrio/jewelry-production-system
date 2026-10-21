<?php

namespace App\Support;

use App\Models\DiamondMounting;
use App\Models\SpkDiamond;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkDiamondMountingBridge
{
    public function linkMounting(SpkDiamond $spkDiamond, DiamondMounting $mounting, string $actor): void
    {
        $now = now();

        $spkDiamond->update([
            'status' => SpkDiamond::STATUS_MOUNTING,
            'modified_date' => $now,
            'modified_by' => $actor,
        ]);

        if (Schema::connection('third')->hasTable('trstone')) {
            DB::connection('third')
                ->table('trstone')
                ->where('ref_row_id', $spkDiamond->id)
                ->where('transtype_id', SpkDiamondLedger::TRANSTYPE_OUT)
                ->where('is_deleted', 0)
                ->update([
                    'ref_row_id' => $mounting->row_id,
                    'transtype_id' => DiamondMountingStoneSynchronizer::TRANSTYPE_SETTING,
                    'modified_date' => $now,
                    'modified_by' => $actor,
                ]);
        }

        $diamondIds = $spkDiamond->diamonds()
            ->notDeleted()
            ->pluck('trdiamond_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($diamondIds !== [] && Schema::connection('third')->hasTable('trdiamond')) {
            DB::connection('third')
                ->table('trdiamond')
                ->whereIn('row_id', $diamondIds)
                ->where('is_deleted', 0)
                ->where('spk_diamond_id', $spkDiamond->id)
                ->update([
                    'diamondmounting_id' => $mounting->row_id,
                    'spk_diamond_id' => 0,
                    'is_used' => 1,
                    'out_date' => $now->toDateString(),
                    'modified_date' => $now,
                    'modified_by' => $actor,
                ]);
        }
    }

    public function markCompleted(SpkDiamond $spkDiamond, string $actor): void
    {
        $now = now();

        $spkDiamond->update([
            'status' => SpkDiamond::STATUS_DONE,
            'completed_at' => $now,
            'completed_by' => $actor,
            'modified_date' => $now,
            'modified_by' => $actor,
        ]);
    }

    /**
     * @return array{
     *     settingStones: list<array{stoneId: int, pcs: string, crt: string, notes: string}>,
     *     diamonds: list<array{diamondId: int}>,
     *     weightFrame: string|null,
     *     weightStone: string|null,
     *     keterangan: string|null,
     *     spkId: int,
     *     spkNo: string|null,
     *     noSpkDiamond: string,
     *     settingAwalLines: list<array<string, mixed>>
     * }
     */
    public function prefillForMounting(SpkDiamond $spkDiamond): array
    {
        $spkDiamond->load([
            'stones' => fn ($query) => $query->notDeleted()->with('stone'),
            'diamonds' => fn ($query) => $query->notDeleted(),
            'production',
        ]);

        $settingStones = $spkDiamond->stones->map(function ($line): array {
            return [
                'stoneId' => (int) $line->stone_id,
                'pcs' => $line->pcs !== null ? (string) $line->pcs : '',
                'crt' => $line->crt !== null ? number_format((float) $line->crt, 3, '.', '') : '',
                'notes' => (string) ($line->notes ?? ''),
            ];
        })->values()->all();

        $diamonds = $spkDiamond->diamonds->map(fn ($line): array => [
            'diamondId' => (int) $line->trdiamond_id,
        ])->values()->all();

        $diamondMeta = $this->dossierRowsFor(
            $spkDiamond->diamonds->pluck('trdiamond_id')->map(fn (mixed $id): int => (int) $id)->all(),
        );

        $settingAwalLines = $spkDiamond->stones->map(function ($line): array {
            $parcel = filled($line->stone?->parcel) ? (string) $line->stone->parcel : null;
            $name = filled($line->stone?->name) ? (string) $line->stone->name : 'Batu';

            return [
                'sourceType' => 'stone',
                'sourceLineId' => (int) $line->id,
                'stoneId' => (int) $line->stone_id,
                'shapeId' => filled($line->stone?->shape_id) ? (int) $line->stone->shape_id : null,
                'diamondCode' => $parcel ?? $name,
                'size' => filled($line->stone?->stone_size) ? (string) $line->stone->stone_size : null,
                'batu' => $name,
                'pcsAwal' => $line->pcs,
                'crtAwal' => $line->crt,
                'pcsTerpasang' => null,
                'crtTerpasang' => null,
                'pcsRetur' => null,
                'crtRetur' => null,
                'notes' => $line->notes,
            ];
        })->merge($spkDiamond->diamonds->map(function ($line) use ($diamondMeta): array {
            $meta = $diamondMeta[(int) $line->trdiamond_id] ?? null;
            $docNo = $meta['docNo'] ?? null;

            return [
                'sourceType' => 'diamond',
                'sourceLineId' => (int) $line->id,
                'trdiamondId' => (int) $line->trdiamond_id,
                'shapeId' => $meta['shapeId'] ?? null,
                'diamondCode' => $docNo,
                'batu' => filled($docNo) ? (string) $docNo : 'Dossier #'.$line->trdiamond_id,
                'pcsAwal' => 1,
                'crtAwal' => $meta['crt'] ?? null,
                'pcsTerpasang' => null,
                'crtTerpasang' => null,
                'pcsRetur' => null,
                'crtRetur' => null,
                'notes' => null,
            ];
        }))->values()->all();

        return [
            'settingStones' => $settingStones,
            'diamonds' => $diamonds,
            'weightFrame' => $spkDiamond->weight_frame !== null
                ? number_format((float) $spkDiamond->weight_frame, 2, '.', '')
                : null,
            'weightStone' => $spkDiamond->weight_stone !== null
                ? number_format((float) $spkDiamond->weight_stone, 3, '.', '')
                : null,
            'keterangan' => $spkDiamond->keterangan,
            'spkId' => (int) $spkDiamond->spk_id,
            'spkNo' => $spkDiamond->spk_no ?? $spkDiamond->production?->spk_no,
            'noSpkDiamond' => (string) $spkDiamond->no_spk_diamond,
            'settingAwalLines' => $settingAwalLines,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function applySettingAwalOutcomes(
        SpkDiamond $spkDiamond,
        DiamondMounting $mounting,
        array $lines,
        string $actor,
    ): void {
        if ($lines === []) {
            return;
        }

        $now = now();
        $spkDiamond->load([
            'stones' => fn ($query) => $query->notDeleted(),
        ]);

        foreach ($lines as $line) {
            $sourceType = strtolower(trim((string) ($line['source_type'] ?? '')));
            $pcsTerpasang = $this->toFloat($line['pcs_terpasang'] ?? null);
            $crtTerpasang = $this->toFloat($line['crt_terpasang'] ?? null);
            $pcsRetur = $this->toFloat($line['pcs_retur'] ?? null);

            if ($sourceType === 'stone') {
                $sourceLineId = (int) ($line['source_line_id'] ?? 0);
                $stoneLine = $spkDiamond->stones->firstWhere('id', $sourceLineId);

                if ($stoneLine === null || ! filled($stoneLine->trstone_row_id)) {
                    continue;
                }

                if ($pcsTerpasang === null && ($pcsRetur === null || $pcsRetur <= 0)) {
                    continue;
                }

                $pcs = $pcsTerpasang ?? 0.0;

                DB::connection('third')
                    ->table('trstone')
                    ->where('row_id', (int) $stoneLine->trstone_row_id)
                    ->where('is_deleted', 0)
                    ->update([
                        'ref_row_id' => $mounting->row_id,
                        'transtype_id' => DiamondMountingStoneSynchronizer::TRANSTYPE_SETTING,
                        'pcs' => number_format($pcs, 0, '.', ''),
                        'crt' => $crtTerpasang !== null
                            ? number_format($crtTerpasang, 3, '.', '')
                            : null,
                        'modified_date' => $now,
                        'modified_by' => $actor,
                    ]);
            }

            if ($sourceType === 'diamond' && Schema::connection('third')->hasTable('trdiamond')) {
                $trdiamondId = (int) ($line['trdiamond_id'] ?? 0);

                if ($trdiamondId <= 0 || $pcsRetur === null || $pcsRetur <= 0) {
                    continue;
                }

                DB::connection('third')
                    ->table('trdiamond')
                    ->where('row_id', $trdiamondId)
                    ->where('is_deleted', 0)
                    ->update([
                        'diamondmounting_id' => 0,
                        'spk_diamond_id' => $spkDiamond->id,
                        'is_used' => 1,
                        'modified_date' => $now,
                        'modified_by' => $actor,
                    ]);
            }
        }
    }

    /**
     * @param  list<int>  $diamondIds
     * @return array<int, array{docNo: string|null, shapeId: int|null, crt: string|null}>
     */
    private function dossierRowsFor(array $diamondIds): array
    {
        $diamondIds = array_values(array_filter($diamondIds, fn (int $id): bool => $id > 0));

        if ($diamondIds === [] || ! Schema::connection('third')->hasTable('trdiamond')) {
            return [];
        }

        return DB::connection('third')
            ->table('trdiamond')
            ->whereIn('row_id', $diamondIds)
            ->get(['row_id', 'doc_no', 'shape_id', 'crt'])
            ->mapWithKeys(function (object $row): array {
                $id = (int) $row->row_id;

                return [
                    $id => [
                        'docNo' => filled($row->doc_no) ? (string) $row->doc_no : null,
                        'shapeId' => filled($row->shape_id) ? (int) $row->shape_id : null,
                        'crt' => filled($row->crt)
                            ? number_format((float) $row->crt, 3, '.', '')
                            : null,
                    ],
                ];
            })
            ->all();
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) str_replace(',', '.', (string) $value);

        return is_finite($number) ? $number : null;
    }
}
