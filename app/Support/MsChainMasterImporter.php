<?php

namespace App\Support;

use App\Models\MsChain;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class MsChainMasterImporter
{
    public const DEFAULT_CSV_PATH = 'data/mschain_master_detail_ukuran.csv';

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importFromPath(string $path, string $actor = 'system'): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Chain master CSV is not readable: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Chain master CSV could not be opened: {$path}");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw new RuntimeException('Chain master CSV is empty.');
        }

        $header = array_map(fn (string $column): string => trim($column), $header);
        $this->assertHeader($header);

        try {
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $now = now();

            $result = DB::connection('third')->transaction(function () use (
                $handle,
                $header,
                $actor,
                $now,
                &$created,
                &$updated,
                &$skipped,
            ): array {
                while (($row = fgetcsv($handle)) !== false) {
                    if ($row === [null] || $row === []) {
                        continue;
                    }

                    $record = $this->mapRow($header, $row);

                    if ($record === null) {
                        $skipped++;

                        continue;
                    }

                    $existing = MsChain::query()
                        ->where('chain_category', $record['chain_category'])
                        ->where('item', $record['item'])
                        ->where('item_code', $record['item_code'])
                        ->where('length', $record['length'])
                        ->first();

                    if ($existing === null) {
                        MsChain::query()->create([
                            ...$record,
                            'is_deleted' => 0,
                            'created_date' => $now,
                            'created_by' => $actor,
                            'modified_date' => $now,
                            'modified_by' => $actor,
                        ]);
                        $created++;

                        continue;
                    }

                    $existing->fill([
                        'item_name' => $record['item_name'],
                        'description' => $record['description'],
                        'is_deleted' => 0,
                        'modified_date' => $now,
                        'modified_by' => $actor,
                        'deleted_date' => null,
                        'deleted_by' => null,
                    ]);

                    if ($existing->isDirty()) {
                        $existing->save();
                        $updated++;

                        continue;
                    }

                    $skipped++;
                }

                return [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                ];
            });
        } finally {
            fclose($handle);
        }

        return $result;
    }

    public function importDefault(string $actor = 'system'): array
    {
        return $this->importFromPath(database_path(self::DEFAULT_CSV_PATH), $actor);
    }

    public function parseLengthFromCsv(string $lengthText): string
    {
        $normalized = trim(str_replace(',', '.', $lengthText));

        if ($normalized === '') {
            throw new InvalidArgumentException('Chain length is empty.');
        }

        if (preg_match('/(\d+(?:\.\d+)?)/', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException("Chain length is invalid: {$lengthText}");
        }

        return number_format((float) $matches[1], 2, '.', '');
    }

    /**
     * @param  list<string>  $header
     */
    private function assertHeader(array $header): void
    {
        $expected = [
            'chain_category',
            'item',
            'item_code',
            'lenght',
            'item_name',
            'description',
        ];

        if ($header !== $expected) {
            throw new RuntimeException(
                'Chain master CSV header must be: '.implode(', ', $expected),
            );
        }
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $row
     * @return array{
     *     chain_category: string,
     *     item: string,
     *     item_code: string,
     *     length: string,
     *     item_name: string|null,
     *     description: string|null
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
            $data['chain_category'] === ''
            || $data['item'] === ''
            || $data['item_code'] === ''
            || $data['lenght'] === ''
        ) {
            return null;
        }

        return [
            'chain_category' => $data['chain_category'],
            'item' => $data['item'],
            'item_code' => $data['item_code'],
            'length' => $this->parseLengthFromCsv($data['lenght']),
            'item_name' => $data['item_name'] !== '' ? $data['item_name'] : null,
            'description' => $data['description'] !== '' ? $data['description'] : null,
        ];
    }
}
