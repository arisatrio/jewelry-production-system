<?php

namespace App\Support;

use App\Models\FinishingHandmade;

class FinishingNotesWorkTypeBackfill
{
    /**
     * Frasa catatan lama, dari yang paling panjang, ke jenis pekerjaan finishing.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'PASANG GANTI CHAIN' => 'Pasang / Ganti Chain',
        'PASANG CHAIN' => 'Pasang / Ganti Chain',
        'GANTI CHAIN' => 'Pasang / Ganti Chain',
        'SETTING STOPPER' => 'Setting Stopper',
        'SETTING STOPER' => 'Setting Stopper',
        'SET STOPPER' => 'Setting Stopper',
        'SET STOPER' => 'Setting Stopper',
        'SETTING ENGSEL' => 'Setting Engsel',
        'SET ENGSEL' => 'Setting Engsel',
        'PASANG BATU' => 'Pasang Batu',
        'FINISHING KOMPONEN' => 'Finishing Komponen',
        'FINISHING RANGKA' => 'Finishing Rangka',
        'FINIHSING 1' => 'Finishing 1',
        'FINISHING 1' => 'Finishing 1',
        'FINISHING 2' => 'Finishing 2',
        'FINISHING 3' => 'Finishing 3',
        'POLES DOFF PERMUKAAN' => 'Poles / Doff / Permukaan',
        'REPAIR BOLONG' => 'Repair Bolong',
        'REP BOLONG' => 'Repair Bolong',
        'TAMBAL BOLONG' => 'Repair Bolong',
        'BENERIN BOLONG' => 'Repair Bolong',
        'REPAIR KONTRUKSI' => 'Repair Bentuk / Konstruksi',
        'REPAIR KONSTRUKSI' => 'Repair Bentuk / Konstruksi',
        'REPAIR BENTUK' => 'Repair Bentuk / Konstruksi',
        'REP BENTUK' => 'Repair Bentuk / Konstruksi',
        'REP GANTI KUKU' => 'Repair / Ganti Kuku',
        'REPAIR KUKU' => 'Repair / Ganti Kuku',
        'GANTI KUKU' => 'Repair / Ganti Kuku',
        'REP KUKU' => 'Repair / Ganti Kuku',
        'KURANGI BERAT' => 'Repair Berat / Ketebalan',
        'TAMBAH BERAT' => 'Repair Berat / Ketebalan',
        'RESIZE' => 'Resize Ukuran (HK)',
        'DOFF' => 'Poles / Doff / Permukaan',
        'POLES' => 'Poles / Doff / Permukaan',
    ];

    public function __construct(private FinishingShrinkAllowanceSettings $allowances) {}

    public function run(): int
    {
        $updated = 0;

        FinishingHandmade::query()
            ->notDeleted()
            ->where(function ($query): void {
                $query->whereNull('work_type')->orWhere('work_type', '');
            })
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->chunkById(200, function ($documents) use (&$updated): void {
                foreach ($documents as $document) {
                    if ($this->apply($document)) {
                        $updated++;
                    }
                }
            }, 'row_id');

        return $updated;
    }

    public function apply(FinishingHandmade $document): bool
    {
        if ($document->is_deleted === 1 || filled($document->work_type)) {
            return false;
        }

        $resolved = $this->resolve($document->notes);

        if ($resolved === null) {
            return false;
        }

        $updates = [
            'work_category' => $resolved['work_category'],
            'work_type' => $resolved['work_type'],
            'modified_date' => now(),
            'modified_by' => 'system',
        ];

        $itemCategory = trim((string) $document->item_category);

        if ($itemCategory !== '') {
            $percent = $this->allowances->percentFor($resolved['work_type'], $itemCategory);

            if ($percent !== null) {
                $updates['shrink_tolerance'] = $percent;
            }
        }

        $document->forceFill($updates)->save();

        return true;
    }

    /**
     * @return array{work_category: string, work_type: string}|null
     */
    public function resolve(?string $notes): ?array
    {
        $normalized = $this->normalize($notes);

        if ($normalized === '') {
            return null;
        }

        $matchedTypes = [];
        $startsWithAlias = false;

        foreach (self::ALIASES as $phrase => $workType) {
            if (! $this->containsPhrase($normalized, $phrase)) {
                continue;
            }

            $matchedTypes[$workType] = true;

            if ($normalized === $phrase || str_starts_with($normalized, $phrase.' ')) {
                $startsWithAlias = true;
            }
        }

        if (count($matchedTypes) !== 1 || ! $startsWithAlias) {
            return null;
        }

        $workType = (string) array_key_first($matchedTypes);
        $workCategory = $this->categoryFor($workType);

        if ($workCategory === null) {
            return null;
        }

        return [
            'work_category' => $workCategory,
            'work_type' => $workType,
        ];
    }

    private function categoryFor(string $workType): ?string
    {
        foreach (FinishingHandmade::workTypesByCategory() as $workCategory => $workTypes) {
            if (in_array($workType, $workTypes, true)) {
                return $workCategory;
            }
        }

        return null;
    }

    private function normalize(?string $notes): string
    {
        $notes = mb_strtoupper(trim((string) $notes));
        $notes = str_replace(['.', ',', '/', '&', '(', ')', '-', '_'], ' ', $notes);

        return trim((string) preg_replace('/\s+/', ' ', $notes));
    }

    private function containsPhrase(string $normalized, string $phrase): bool
    {
        return preg_match('/(?:^| )'.preg_quote($phrase, '/').'(?: |$)/', $normalized) === 1;
    }
}
