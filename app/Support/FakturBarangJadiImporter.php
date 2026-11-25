<?php

namespace App\Support;

use App\Http\Requests\PrintFakturBarangJadiRequest;
use App\Models\Employee;
use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class FakturBarangJadiImporter
{
    private const MAX_SPK_PER_RECEIPT = 100;

    public const DEFAULT_IMPORT_WORKFLOW = FakturBarangJadiWorkflowStatus::RECEIVED;

    /**
     * @var list<string>
     */
    private const EXPECTED_HEADERS = [
        'no faktur',
        'tanggal',
        'dari',
        'untuk',
        'no spk',
        'plu',
        'berat akhir barang jadi (emas + batu)',
        'diserahkan oleh',
        'diketahui oleh',
        'diterima oleh',
    ];

    public function __construct(
        private readonly FakturBarangJadiReceiptService $receipts,
        private readonly SerahTerimaFakturBarangJadiDocNumberGenerator $docNumberGenerator,
    ) {}

    /**
     * @return array{
     *     importedReceipts: int,
     *     importedSpk: int,
     *     errors: list<array{row: int, messages: list<string>}>
     * }
     */
    public function import(UploadedFile $file, string $actor): array
    {
        if (! $this->receipts->tableAvailable()) {
            throw ValidationException::withMessages([
                'file' => 'Penyimpanan faktur barang jadi belum tersedia di database.',
            ]);
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = (int) $worksheet->getHighestDataRow();

        if ($highestRow < 1) {
            throw ValidationException::withMessages([
                'file' => 'File Excel kosong.',
            ]);
        }

        $this->assertHeaderRow($worksheet->rangeToArray('A1:J1', null, true, false)[0] ?? []);

        $parsedRows = [];
        $errors = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $cells = $worksheet->rangeToArray("A{$rowNumber}:J{$rowNumber}", null, true, false)[0] ?? [];

            if ($this->isBlankRow($cells)) {
                continue;
            }

            $rowErrors = $this->validateRowCells($cells, $rowNumber);

            if ($rowErrors !== []) {
                $errors[] = [
                    'row' => $rowNumber,
                    'messages' => $rowErrors,
                ];

                continue;
            }

            $parsedRows[] = [
                'row' => $rowNumber,
                'data' => $this->mapRow($cells),
            ];
        }

        if ($parsedRows === [] && $errors === []) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada baris data untuk diimport.',
            ]);
        }

        if ($errors !== []) {
            return [
                'importedReceipts' => 0,
                'importedSpk' => 0,
                'errors' => $errors,
            ];
        }

        $groups = $this->buildGroups($parsedRows, $errors);

        if ($errors !== []) {
            return [
                'importedReceipts' => 0,
                'importedSpk' => 0,
                'errors' => $errors,
            ];
        }

        if ($groups === []) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada faktur yang dapat diimport.',
            ]);
        }

        $importedReceipts = 0;
        $importedSpk = 0;

        DB::connection('third')->transaction(function () use ($groups, $actor, &$importedReceipts, &$importedSpk): void {
            foreach ($groups as $group) {
                $this->receipts->createReceiptFromImport($group, $actor, $this->docNumberGenerator);
                $importedReceipts++;
                $importedSpk += $group['productions']->count();
            }
        });

        return [
            'importedReceipts' => $importedReceipts,
            'importedSpk' => $importedSpk,
            'errors' => [],
        ];
    }

    /**
     * @param  list<array{row: int, data: array<string, mixed>}>  $parsedRows
     * @param  list<array{row: int, messages: list<string>}>  $errors
     * @return list<array{
     *     doc_no: string|null,
     *     tanggal: string,
     *     dari: string,
     *     untuk: string,
     *     workflow: string,
     *     diserahkan_oleh: string|null,
     *     diketahui_oleh: string|null,
     *     diterima_oleh: string|null,
     *     productions: Collection<int, Production>,
     * }>
     */
    private function buildGroups(array $parsedRows, array &$errors): array
    {
        /** @var array<string, list<array{row: int, data: array<string, mixed>}>>  $buckets */
        $buckets = [];
        $spkAssignments = [];

        foreach ($parsedRows as $parsedRow) {
            $data = $parsedRow['data'];
            $key = $this->groupKey($data);
            $buckets[$key][] = $parsedRow;

            $spkNo = (string) $data['spk_no'];

            if (isset($spkAssignments[$spkNo])) {
                $errors[] = [
                    'row' => $parsedRow['row'],
                    'messages' => [
                        "No SPK {$spkNo} muncul lebih dari sekali di file (baris {$spkAssignments[$spkNo]}).",
                    ],
                ];

                continue;
            }

            $spkAssignments[$spkNo] = $parsedRow['row'];
        }

        if ($errors !== []) {
            return [];
        }

        $blockedIds = $this->receipts->spkRowIdsOnOpenReceipts();
        $groups = [];

        foreach ($buckets as $bucket) {
            $header = $bucket[0]['data'];
            $headerRow = $bucket[0]['row'];

            foreach ($bucket as $entry) {
                if (! $this->headersMatch($header, $entry['data'])) {
                    $errors[] = [
                        'row' => $entry['row'],
                        'messages' => [
                            'Kolom faktur (No Faktur, Tanggal, Dari, Untuk, tanda tangan) harus sama untuk SPK dalam satu faktur.',
                        ],
                    ];
                }
            }

            if ($errors !== []) {
                continue;
            }

            $spkNos = array_map(
                fn (array $entry): string => (string) $entry['data']['spk_no'],
                $bucket,
            );

            $productions = Production::query()
                ->notDeleted()
                ->whereIn('spk_no', $spkNos)
                ->where('spk_type', '!=', SpkService::REPARATION_TYPE)
                ->get()
                ->keyBy(fn (Production $production): string => (string) $production->spk_no);

            foreach ($bucket as $entry) {
                $spkNo = (string) $entry['data']['spk_no'];

                if (! $productions->has($spkNo)) {
                    $errors[] = [
                        'row' => $entry['row'],
                        'messages' => ["No SPK {$spkNo} tidak ditemukan atau bukan tipe yang dapat difakturkan."],
                    ];
                }
            }

            if ($errors !== []) {
                continue;
            }

            foreach ($bucket as $entry) {
                $production = $productions->get((string) $entry['data']['spk_no']);
                $rowId = (int) $production->row_id;

                if (isset($blockedIds[$rowId])) {
                    $errors[] = [
                        'row' => $entry['row'],
                        'messages' => ["SPK {$production->spk_no} sudah tercantum pada faktur barang jadi aktif."],
                    ];

                    continue;
                }

                $from = (string) $header['dari'];
                $currentLocation = trim((string) ($production->location ?? Production::DEFAULT_LOCATION));

                if (strcasecmp($currentLocation, $from) !== 0) {
                    $errors[] = [
                        'row' => $entry['row'],
                        'messages' => ["SPK {$production->spk_no} tidak berada di lokasi {$from}."],
                    ];
                }
            }

            if ($errors !== []) {
                continue;
            }

            if (count($bucket) > self::MAX_SPK_PER_RECEIPT) {
                $errors[] = [
                    'row' => $headerRow,
                    'messages' => ['Satu faktur maksimal '.self::MAX_SPK_PER_RECEIPT.' SPK.'],
                ];

                continue;
            }

            $docNo = $header['doc_no'];

            if (filled($docNo)) {
                $exists = SerahTerimaFakturBarangJadi::query()
                    ->withTrashed()
                    ->where('doc_no', $docNo)
                    ->exists();

                if ($exists) {
                    $errors[] = [
                        'row' => $headerRow,
                        'messages' => ["No Faktur {$docNo} sudah digunakan."],
                    ];

                    continue;
                }
            }

            $orderedProductions = collect($bucket)
                ->map(fn (array $entry): Production => $productions->get((string) $entry['data']['spk_no']))
                ->values();

            $pluByRowId = [];
            $lastWeightByRowId = [];

            foreach ($bucket as $entry) {
                $plu = $entry['data']['plu'] ?? null;

                if (filled($plu)) {
                    $production = $productions->get((string) $entry['data']['spk_no']);
                    $pluByRowId[(int) $production->row_id] = trim((string) $plu);
                }

                $lastWeight = $entry['data']['last_weight'] ?? null;

                if (filled($lastWeight)) {
                    $production = $productions->get((string) $entry['data']['spk_no']);
                    $lastWeightByRowId[(int) $production->row_id] = (string) $lastWeight;
                }
            }

            $groups[] = [
                'doc_no' => filled($docNo) ? (string) $docNo : null,
                'tanggal' => (string) $header['tanggal'],
                'dari' => (string) $header['dari'],
                'untuk' => (string) $header['untuk'],
                'workflow' => (string) $header['workflow'],
                'diserahkan_oleh' => $header['diserahkan_oleh'],
                'diketahui_oleh' => $header['diketahui_oleh'],
                'diterima_oleh' => $header['diterima_oleh'],
                'productions' => $orderedProductions,
                'plu_by_row_id' => $pluByRowId,
                'last_weight_by_row_id' => $lastWeightByRowId,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function headersMatch(array $left, array $right): bool
    {
        foreach (['doc_no', 'tanggal', 'dari', 'untuk', 'diserahkan_oleh', 'diketahui_oleh', 'diterima_oleh'] as $field) {
            if (($left[$field] ?? null) !== ($right[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function groupKey(array $data): string
    {
        $docNo = trim((string) ($data['doc_no'] ?? ''));

        if ($docNo !== '') {
            return 'doc:'.$docNo;
        }

        return 'hdr:'.implode('|', [
            (string) $data['tanggal'],
            (string) $data['dari'],
            (string) $data['untuk'],
            (string) ($data['diserahkan_oleh'] ?? ''),
            (string) ($data['diketahui_oleh'] ?? ''),
            (string) ($data['diterima_oleh'] ?? ''),
        ]);
    }

    /**
     * @param  list<mixed>  $headerCells
     */
    private function assertHeaderRow(array $headerCells): void
    {
        $normalized = [];

        foreach (self::EXPECTED_HEADERS as $index => $expected) {
            $value = $this->cellString($headerCells[$index] ?? null);
            $normalized[] = $this->normalizeHeader($value);
        }

        if ($normalized !== self::EXPECTED_HEADERS) {
            throw ValidationException::withMessages([
                'file' => 'Format kolom tidak sesuai template. Unduh template terbaru lalu isi ulang file Excel.',
            ]);
        }
    }

    /**
     * @param  list<mixed>  $cells
     * @return list<string>
     */
    private function validateRowCells(array $cells, int $rowNumber): array
    {
        $mapped = $this->mapRow($cells);
        $messages = [];

        if ($mapped['tanggal'] === null) {
            $messages[] = 'Tanggal wajib diisi dengan format yang valid (contoh: 2026-11-20 atau 20-11-2026).';
        }

        if (! in_array($mapped['dari'], PrintFakturBarangJadiRequest::LOCATIONS, true)) {
            $messages[] = 'Dari harus Head Office, Workshop, atau Store.';
        }

        if (! in_array($mapped['untuk'], PrintFakturBarangJadiRequest::LOCATIONS, true)) {
            $messages[] = 'Untuk harus Head Office, Workshop, atau Store.';
        }

        if (
            in_array($mapped['dari'], PrintFakturBarangJadiRequest::LOCATIONS, true)
            && in_array($mapped['untuk'], PrintFakturBarangJadiRequest::LOCATIONS, true)
            && strcasecmp((string) $mapped['dari'], (string) $mapped['untuk']) === 0
        ) {
            $messages[] = 'Untuk tidak boleh sama dengan Dari.';
        }

        if ($mapped['spk_no'] === '') {
            $messages[] = 'No SPK wajib diisi.';
        }

        if (filled($mapped['plu']) && mb_strlen((string) $mapped['plu']) > 50) {
            $messages[] = 'PLU maksimal 50 karakter.';
        }

        if ($mapped['last_weight'] === null) {
            $weightRaw = $this->cellString($cells[6] ?? null);

            if ($weightRaw === '') {
                $messages[] = 'Berat Akhir Barang Jadi (Emas + Batu) wajib diisi.';
            } else {
                $messages[] = 'Berat Akhir Barang Jadi (Emas + Batu) harus berupa angka (maks. 2 desimal).';
            }
        }

        foreach (['diserahkan_oleh' => 'Diserahkan Oleh', 'diketahui_oleh' => 'Diketahui Oleh', 'diterima_oleh' => 'Diterima Oleh'] as $field => $label) {
            $value = $mapped[$field];

            if (! filled($value)) {
                continue;
            }

            $validator = Validator::make(
                [$field => $value],
                [
                    $field => [
                        'string',
                        'max:150',
                        Rule::exists(Employee::class, 'nama_lengkap')
                            ->where('status', Employee::STATUS_ACTIVE)
                            ->where('is_deleted', 0),
                    ],
                ],
            );

            if ($validator->fails()) {
                $messages[] = "{$label} tidak dikenali sebagai karyawan aktif.";
            }
        }

        return $messages;
    }

    /**
     * @param  list<mixed>  $cells
     * @return array{
     *     doc_no: string|null,
     *     tanggal: string|null,
     *     dari: string,
     *     untuk: string,
     *     spk_no: string,
     *     plu: string|null,
     *     last_weight: string|null,
     *     workflow: string,
     *     diserahkan_oleh: string|null,
     *     diketahui_oleh: string|null,
     *     diterima_oleh: string|null,
     * }
     */
    private function mapRow(array $cells): array
    {
        return [
            'doc_no' => $this->nullableCellString($cells[0] ?? null),
            'tanggal' => $this->parseDate($cells[1] ?? null),
            'dari' => $this->cellString($cells[2] ?? null),
            'untuk' => $this->cellString($cells[3] ?? null),
            'spk_no' => $this->cellString($cells[4] ?? null),
            'plu' => $this->nullableCellString($cells[5] ?? null),
            'last_weight' => $this->parseLastWeight($cells[6] ?? null),
            'workflow' => self::DEFAULT_IMPORT_WORKFLOW,
            'diserahkan_oleh' => $this->nullableCellString($cells[7] ?? null),
            'diketahui_oleh' => $this->nullableCellString($cells[8] ?? null),
            'diterima_oleh' => $this->nullableCellString($cells[9] ?? null),
        ];
    }

    private function parseLastWeight(mixed $value): ?string
    {
        $string = $this->cellString($value);

        if ($string === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $string);

        $validator = Validator::make(
            ['last_weight' => $normalized],
            ['last_weight' => ['numeric', 'min:0', 'decimal:0,2']],
        );

        if ($validator->fails()) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($this->cellString($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = str_replace('  ', ' ', $normalized);
        $normalized = preg_replace('/\s*\(opsional\)\s*$/u', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function cellString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        return trim((string) $value);
    }

    private function nullableCellString(mixed $value): ?string
    {
        $string = $this->cellString($value);

        return $string === '' ? null : $string;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return CarbonImmutable::instance(
                    ExcelDate::excelToDateTimeObject((float) $value),
                )->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $string = $this->cellString($value);

        if ($string === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'd-M-Y', 'd M Y'];

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $string);

                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return CarbonImmutable::parse($string)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
