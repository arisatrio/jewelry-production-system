<?php

namespace App\Support;

use App\Models\MsChain;
use App\Models\Production;
use App\Models\TrxChain;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TrxChainStockImporter
{
    public const DEFAULT_CSV_PATH = 'data/STOCK CHAIN  26 - Detail Transaksi Rantai (1).csv';

    private const IMPORT_NOTE = 'Impor CSV detail transaksi rantai';

    /**
     * @var list<string>
     */
    private const CSV_HEADER = [
        'Kategori Rantai',
        'Nama Master Item',
        'Kode Model',
        'Panjang',
        'Warna Emas',
        'Tgl Masuk',
        'Pcs',
        'Berat (gr)',
        'Harga Beli',
        'Status Availability',
        'Tgl Terpakai',
        'No PRD',
        'PLU',
    ];

    public function __construct(
        private readonly ChainUnitNumberGenerator $unitNumbers,
    ) {}

    /**
     * @return array{
     *     created: int,
     *     skipped: int,
     *     missing_master: int,
     *     missing_spk: int,
     *     purged: int
     * }
     */
    public function importFromPath(
        string $path,
        string $actor = 'system',
        bool $replacePreviousImport = false,
    ): array {
        if (! is_readable($path)) {
            throw new RuntimeException("Chain stock CSV is not readable: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Chain stock CSV could not be opened: {$path}");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw new RuntimeException('Chain stock CSV is empty.');
        }

        $header = array_map(fn (string $column): string => trim($column), $header);
        $this->assertHeader($header);

        $created = 0;
        $skipped = 0;
        $missingMaster = 0;
        $missingSpk = 0;
        $purged = 0;
        $now = now();

        /** @var array<string, int> $modelCodeSequence */
        $modelCodeSequence = [];

        try {
            DB::connection('third')->transaction(function () use (
                $handle,
                $header,
                $actor,
                $now,
                $replacePreviousImport,
                &$created,
                &$skipped,
                &$missingMaster,
                &$missingSpk,
                &$purged,
                &$modelCodeSequence,
            ): void {
                if ($replacePreviousImport) {
                    $purged = $this->purgePreviouslyImported($actor);
                }

                while (($row = fgetcsv($handle)) !== false) {
                    if ($row === [null] || $row === []) {
                        continue;
                    }

                    $record = $this->mapRow($header, $row);

                    if ($record === null) {
                        $skipped++;

                        continue;
                    }

                    $itemCode = $this->allocateItemCode(
                        $record['model_code'],
                        $modelCodeSequence,
                    );

                    $master = MsChain::query()
                        ->notDeleted()
                        ->where('chain_category', $record['chain_category'])
                        ->where('item_name', $record['master_item_name'])
                        ->first();

                    if ($master === null) {
                        $missingMaster++;

                        continue;
                    }

                    $spkId = null;

                    if ($record['prd_no'] !== null) {
                        $production = Production::query()
                            ->where('is_deleted', 0)
                            ->where('spk_no', $record['prd_no'])
                            ->first();

                        if ($production === null) {
                            $missingSpk++;
                        } else {
                            $spkId = (int) $production->row_id;
                        }
                    }

                    $length = number_format((float) $master->length, 2, '.', '');
                    $name = filled($master->item_name)
                        ? trim((string) $master->item_name)
                        : MsChain::composeItemName($master->item, $master->item_code, $length);

                    TrxChain::query()->create([
                        'unit_no' => $this->unitNumbers->generate(),
                        'mschain_id' => (int) $master->row_id,
                        'source_type' => TrxChain::SOURCE_PURCHASE,
                        'parent_row_id' => null,
                        'name' => $name,
                        'item_code' => $itemCode,
                        'chain_length' => $length,
                        'color' => $record['color'],
                        'weight' => $record['weight'],
                        'purchase_price' => $record['purchase_price'],
                        'supplier' => $record['supplier'],
                        'entry_date' => $record['entry_date'],
                        'out_date' => $record['out_date'],
                        'spk_id' => $spkId,
                        'is_used' => $record['is_used'],
                        'notes' => $record['notes'],
                        'is_deleted' => 0,
                        'created_date' => $now,
                        'created_by' => $actor,
                        'modified_date' => $now,
                        'modified_by' => $actor,
                    ]);

                    $created++;
                }
            });
        } finally {
            fclose($handle);
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'missing_master' => $missingMaster,
            'missing_spk' => $missingSpk,
            'purged' => $purged,
        ];
    }

    public function importDefault(string $actor = 'system', bool $replacePreviousImport = false): array
    {
        return $this->importFromPath(
            database_path(self::DEFAULT_CSV_PATH),
            $actor,
            $replacePreviousImport,
        );
    }

    public function purgePreviouslyImported(string $actor): int
    {
        $now = now();

        return TrxChain::query()
            ->notDeleted()
            ->where(function ($query): void {
                $query->where('notes', 'like', '%'.self::IMPORT_NOTE.'%')
                    ->orWhere('supplier', 'Import');
            })
            ->update([
                'is_deleted' => 1,
                'deleted_date' => $now,
                'deleted_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
    }

    /**
     * @param  array<string, int>  $modelCodeSequence
     */
    public function allocateItemCode(string $modelCode, array &$modelCodeSequence): string
    {
        $sequence = ($modelCodeSequence[$modelCode] ?? 0) + 1;
        $modelCodeSequence[$modelCode] = $sequence;

        if ($sequence === 1) {
            return $modelCode;
        }

        return $modelCode.'-'.$sequence;
    }

    public function parseDateFromCsv(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        } catch (\Throwable) {
            throw new InvalidArgumentException("Invalid date: {$value}");
        }
    }

    public function parseWeightFromCsv(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("Invalid weight: {$value}");
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    public function parsePurchasePriceFromCsv(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', '', $value) ?? $value;
        $normalized = str_ireplace(['Rp', 'IDR'], '', $normalized);
        $normalized = ltrim($normalized, '$');

        if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d+)?$/', $normalized) === 1) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("Invalid purchase price: {$value}");
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    /**
     * @param  list<string>  $header
     */
    private function assertHeader(array $header): void
    {
        $expectedWithSupplier = [...self::CSV_HEADER, 'Supplier'];

        if ($header !== self::CSV_HEADER && $header !== $expectedWithSupplier) {
            throw new RuntimeException(
                'Chain stock CSV header must be: '.implode(', ', $expectedWithSupplier),
            );
        }
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $row
     * @return array{
     *     chain_category: string,
     *     master_item_name: string,
     *     model_code: string,
     *     color: string|null,
     *     entry_date: string|null,
     *     weight: string|null,
     *     purchase_price: string|null,
     *     out_date: string|null,
     *     is_used: int,
     *     prd_no: string|null,
     *     notes: string|null,
     *     supplier: string|null
     * }|null
     */
    private function mapRow(array $header, array $row): ?array
    {
        /** @var array<string, string> $data */
        $data = [];

        foreach ($header as $index => $column) {
            $data[$column] = trim((string) ($row[$index] ?? ''));
        }

        if (
            $data['Kategori Rantai'] === ''
            || $data['Nama Master Item'] === ''
            || $data['Kode Model'] === ''
        ) {
            return null;
        }

        $status = strtoupper($data['Status Availability']);
        $isUsed = $status === 'USED';

        $entryDate = $data['Tgl Masuk'] !== ''
            ? $this->parseDateFromCsv($data['Tgl Masuk'])
            : null;

        $outDate = $data['Tgl Terpakai'] !== ''
            ? $this->parseDateFromCsv($data['Tgl Terpakai'])
            : null;

        if ($isUsed && $outDate === null) {
            $outDate = $entryDate ?? now()->format('Y-m-d');
        }

        $notes = [self::IMPORT_NOTE];

        if (! $isUsed && $entryDate === null) {
            $entryDate = now()->format('Y-m-d');
            $notes[] = 'Status READY tanpa tanggal masuk di sumber';
        }

        if ($isUsed && $entryDate === null) {
            $entryDate = $outDate;
            $notes[] = 'Status USED tanpa tanggal masuk di sumber';
        }

        if ($isUsed && $data['Tgl Terpakai'] === '') {
            $notes[] = 'Status USED tanpa tanggal terpakai di sumber';
        }

        if ($data['PLU'] !== '') {
            $notes[] = 'PLU: '.$data['PLU'];
        }

        if ($data['No PRD'] !== '' && $data['No PRD'] !== '0') {
            $notes[] = 'No PRD: '.$data['No PRD'];
        }

        $weight = $data['Berat (gr)'] !== ''
            ? $this->parseWeightFromCsv($data['Berat (gr)'])
            : null;

        $purchasePrice = $data['Harga Beli'] !== ''
            ? $this->parsePurchasePriceFromCsv($data['Harga Beli'])
            : null;

        $color = $data['Warna Emas'] !== '' ? $data['Warna Emas'] : null;

        $prdNo = $data['No PRD'] !== '' && $data['No PRD'] !== '0'
            ? $data['No PRD']
            : null;

        $supplier = ($data['Supplier'] ?? '') !== ''
            ? $data['Supplier']
            : null;

        return [
            'chain_category' => $data['Kategori Rantai'],
            'master_item_name' => $data['Nama Master Item'],
            'model_code' => $data['Kode Model'],
            'color' => $color,
            'entry_date' => $entryDate,
            'weight' => $weight,
            'purchase_price' => $purchasePrice,
            'out_date' => $isUsed ? $outDate : null,
            'is_used' => $isUsed ? 1 : 0,
            'prd_no' => $prdNo,
            'notes' => implode(' · ', $notes),
            'supplier' => $supplier,
        ];
    }
}
