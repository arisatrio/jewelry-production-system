<?php

namespace App\Support;

use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use Illuminate\Support\Carbon;

class FakturBarangJadiPrintPresenter
{
    public function __construct(
        private SpkMountedStonesDetail $mountedStonesDetail,
        private SpkProductionWeightDisplay $productionWeightDisplay,
    ) {}

    /**
     * @return list<array{
     *     spkNo: string,
     *     categorySkuPrimary: string,
     *     skuCode: string,
     *     productionType: string,
     *     goldColor: string,
     *     completionDate: string,
     *     imageUrl: string|null,
     *     goldWeight: string,
     *     finalWeight: string,
     *     remarks: string,
     *     detailRows: list<array{pcs: string, carat: string, perPieceCarat: string, shape: string, gia: string}>
     * }>
     */
    public function groupsForReceipt(SerahTerimaFakturBarangJadi $receipt): array
    {
        /** @var list<int> $rowIds */
        $rowIds = array_values(array_filter(array_map(
            intval(...),
            $receipt->spk_row_ids ?? [],
        ), fn (int $id): bool => $id > 0));

        if ($rowIds === []) {
            return [];
        }

        $order = array_flip($rowIds);

        $productions = Production::query()
            ->notDeleted()
            ->with(['sku', 'categoryPrefix'])
            ->whereIn('row_id', $rowIds)
            ->get()
            ->sortBy(fn (Production $production): int => $order[(int) $production->row_id] ?? PHP_INT_MAX)
            ->values();

        $lastProcessDates = SpkDashboardAnalytics::lastProcessDatesFor($productions);
        $receiptDate = $receipt->tanggal?->format('d-M-Y') ?? '';
        $stoneWeights = $this->productionWeightDisplay->totalStoneWeightBySpkIds(
            $productions
                ->map(fn (Production $production): int => (int) $production->row_id)
                ->all(),
        );

        return $productions
            ->map(fn (Production $production): array => $this->mapProductionGroup(
                $production,
                $lastProcessDates[(int) $production->row_id] ?? null,
                $receiptDate,
                $stoneWeights[(int) $production->row_id] ?? 0.0,
            ))
            ->all();
    }

    /**
     * @return array{
     *     spkNo: string,
     *     categorySkuPrimary: string,
     *     skuCode: string,
     *     productionType: string,
     *     goldColor: string,
     *     completionDate: string,
     *     imageUrl: string|null,
     *     goldWeight: string,
     *     finalWeight: string,
     *     remarks: string,
     *     detailRows: list<array{pcs: string, carat: string, perPieceCarat: string, shape: string, gia: string}>
     * }
     */
    private function mapProductionGroup(
        Production $production,
        ?string $lastProcessDate,
        string $receiptDateFallback,
        float $stoneWeightGrams,
    ): array {
        $detailRows = $this->buildDetailRows($production);

        if ($detailRows === []) {
            $detailRows[] = $this->emptyDetailRow();
        }

        $categorySku = $this->categorySkuFields($production);

        return [
            'spkNo' => filled($production->spk_no) ? (string) $production->spk_no : '-',
            'categorySkuPrimary' => $categorySku['primary'],
            'skuCode' => $categorySku['skuCode'],
            'productionType' => $this->productionTypeForPrint($production),
            'goldColor' => strtoupper(trim((string) ($production->gold_color ?? '-'))),
            'completionDate' => $this->formatCompletionDate($production, $lastProcessDate, $receiptDateFallback),
            'imageUrl' => SpkItemImageUrl::fromFotoBrj($production->foto_brj),
            'goldWeight' => $this->formatWeight(
                $this->productionWeightDisplay->emasWeightGrams($production, $stoneWeightGrams),
            ),
            'finalWeight' => $this->formatWeight(
                $this->productionWeightDisplay->finishedGoodsWeightGrams($production),
            ),
            'remarks' => $this->remarks($production),
            'detailRows' => $detailRows,
        ];
    }

    /**
     * @return list<array{pcs: string, carat: string, perPieceCarat: string, shape: string, gia: string}>
     */
    private function buildDetailRows(Production $production): array
    {
        $mounted = $this->mountedStonesDetail->forProduction($production)['mounted'] ?? [];

        if ($mounted === []) {
            return [];
        }

        return array_values(array_map(
            fn (array $line): array => $this->detailRowFromMountedLine($line),
            $mounted,
        ));
    }

    /**
     * @param  array{kode?: string, shape?: string, shapeCode?: string|null, pcs?: int|float|string|null, crt?: string|null, size?: string}  $line
     * @return array{pcs: string, carat: string, perPieceCarat: string, shape: string, gia: string}
     */
    private function detailRowFromMountedLine(array $line): array
    {
        $pcsTotal = max(1, $this->numericToInt((string) ($line['pcs'] ?? '1')));
        $crtTotal = $this->parseDecimal($line['crt'] ?? null) ?? 0.0;
        $perPiece = $pcsTotal > 0 ? $crtTotal / $pcsTotal : $crtTotal;
        $gia = $this->noGiaForMountedLine($line);

        return [
            'pcs' => (string) $pcsTotal,
            'carat' => $this->mountedTotalCrtDisplay($line['crt'] ?? null),
            'perPieceCarat' => $this->formatDecimal($perPiece, 3),
            'shape' => $this->shapeCode($line['shapeCode'] ?? null),
            'gia' => $gia,
        ];
    }

    private function mountedTotalCrtDisplay(mixed $crt): string
    {
        $parsed = $this->parseDecimal($crt);

        if ($parsed === null) {
            return '-';
        }

        return $this->formatDecimal($parsed, 3);
    }

    private function noGiaForMountedLine(array $line): string
    {
        if (! $this->isDossierMountedLineByCaratPerPcs($line)) {
            return '';
        }

        return $this->mountedKodeDiamond($line);
    }

    private function isDossierMountedLineByCaratPerPcs(array $line): bool
    {
        $pcs = max(1, $this->numericToInt((string) ($line['pcs'] ?? '1')));
        $crtTotal = $this->parseDecimal($line['crt'] ?? null) ?? 0.0;
        $caratPerPcs = $pcs > 0 ? $crtTotal / $pcs : $crtTotal;

        return $caratPerPcs >= SpkStoneStockChecker::DOSSIER_MIN_CARAT_PER_PCS;
    }

    /**
     * @param  array{kode?: string, diamond_code?: string, pcs?: int|float|string|null}  $line
     */
    private function mountedKodeDiamond(array $line): string
    {
        $kode = trim((string) ($line['kode'] ?? $line['diamond_code'] ?? ''));

        if ($kode === '' || in_array($kode, ['—', '-'], true)) {
            return '';
        }

        return $kode;
    }

    /**
     * @return array{pcs: string, carat: string, perPieceCarat: string, shape: string, gia: string}
     */
    private function emptyDetailRow(): array
    {
        return [
            'pcs' => '-',
            'carat' => '-',
            'perPieceCarat' => '-',
            'shape' => '-',
            'gia' => '',
        ];
    }

    /**
     * @return array{primary: string, skuCode: string}
     */
    private function categorySkuFields(Production $production): array
    {
        $production->loadMissing(['categoryPrefix', 'sku']);

        $categoryItem = trim((string) ($production->categoryPrefix?->category ?? ''));

        if ($categoryItem === '') {
            $categoryItem = trim((string) ($production->categoryPrefix?->prefix ?? ''));
        }

        $categoryItem = $categoryItem !== '' ? strtoupper($categoryItem) : '';

        $skuDescription = trim((string) ($production->sku?->item_original ?? ''));
        $skuCode = trim((string) ($production->sku?->sku_code ?? ''));

        if ($skuDescription === '' || $skuCode === '') {
            $notes = trim((string) ($production->notes ?? ''));
            $primaryParts = array_values(array_filter(
                [$categoryItem, $notes],
                fn (string $part): bool => $part !== '',
            ));

            return [
                'primary' => $primaryParts !== [] ? implode(' | ', $primaryParts) : '-',
                'skuCode' => '',
            ];
        }

        $primaryParts = array_values(array_filter(
            [$categoryItem, $skuDescription],
            fn (string $part): bool => $part !== '',
        ));

        return [
            'primary' => $primaryParts !== [] ? implode(' | ', $primaryParts) : '-',
            'skuCode' => $skuCode,
        ];
    }

    private function productionTypeForPrint(Production $production): string
    {
        $type = trim((string) ($production->spk_type ?? ''));

        if (strcasecmp($type, 'Pesanan') === 0) {
            $orderNo = trim((string) ($production->request_order_no ?? ''));

            if ($orderNo !== '') {
                return "Pesanan\n{$orderNo}";
            }

            return 'Pesanan';
        }

        return strtoupper($type !== '' ? $type : '-');
    }

    private function remarks(Production $production): string
    {
        $plu = trim((string) ($production->plu ?? ''));

        if ($plu !== '') {
            return $plu;
        }

        $notes = trim((string) ($production->notes ?? ''));

        return $notes !== '' ? $notes : '';
    }

    private function formatCompletionDate(
        Production $production,
        ?string $lastProcessDate,
        string $receiptDateFallback,
    ): string {
        if ($lastProcessDate !== null && trim($lastProcessDate) !== '') {
            try {
                return Carbon::createFromFormat('d-M-Y', $lastProcessDate)->format('d-M-Y');
            } catch (\Throwable) {
                // fall through
            }
        }

        if ($production->estimated_delivery_time !== null) {
            return $production->estimated_delivery_time->format('d-M-Y');
        }

        return $receiptDateFallback !== '' ? $receiptDateFallback : '-';
    }

    private function formatWeight(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '-') {
            return '-';
        }

        $numeric = (float) str_replace(',', '.', (string) $value);

        if (! is_finite($numeric)) {
            return '-';
        }

        return $this->formatDecimal($numeric, 2);
    }

    private function formatDecimal(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', '');
    }

    private function parseDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = (float) str_replace(',', '.', (string) $value);

        return is_finite($numeric) ? $numeric : null;
    }

    private function numericToInt(string $value): int
    {
        $numeric = (float) str_replace(',', '.', $value);

        return max(0, (int) round($numeric));
    }

    private function shapeCode(?string $code): string
    {
        $code = trim((string) ($code ?? ''));

        return $code !== '' ? strtoupper($code) : '-';
    }
}
