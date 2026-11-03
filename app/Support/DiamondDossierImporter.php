<?php

namespace App\Support;

use App\Http\Requests\StoreDiamondDossierRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class DiamondDossierImporter
{
    private const EXPECTED_HEADERS = [
        'tanggal masuk',
        'kode/bentuk',
        'crt',
        'warna',
        'gia',
        'supplier',
    ];

    public function __construct(
        private readonly DiamondDossierInventory $inventory,
    ) {}

    /**
     * @return array{
     *     imported: int,
     *     errors: list<array{row: int, messages: list<string>}>
     * }
     */
    public function import(UploadedFile $file, string $actor): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = (int) $worksheet->getHighestDataRow();

        if ($highestRow < 1) {
            throw ValidationException::withMessages([
                'file' => 'File Excel kosong.',
            ]);
        }

        $this->assertHeaderRow($worksheet->rangeToArray('A1:F1', null, true, false)[0] ?? []);

        $parsedRows = [];
        $errors = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $cells = $worksheet->rangeToArray("A{$rowNumber}:F{$rowNumber}", null, true, false)[0] ?? [];

            if ($this->isBlankRow($cells)) {
                continue;
            }

            $rowErrors = $this->validateRow($cells, $rowNumber);

            if ($rowErrors !== []) {
                $errors[] = [
                    'row' => $rowNumber,
                    'messages' => $rowErrors,
                ];

                continue;
            }

            $parsedRows[] = [
                'row' => $rowNumber,
                'attributes' => $this->mapRowToAttributes($cells),
            ];
        }

        if ($parsedRows === [] && $errors === []) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada baris data untuk diimport.',
            ]);
        }

        if ($errors !== []) {
            return [
                'imported' => 0,
                'errors' => $errors,
            ];
        }

        $imported = 0;

        foreach ($parsedRows as $parsedRow) {
            $this->inventory->create($parsedRow['attributes'], $actor);
            $imported++;
        }

        return [
            'imported' => $imported,
            'errors' => [],
        ];
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
    private function validateRow(array $cells, int $rowNumber): array
    {
        $attributes = $this->mapRowToAttributes($cells);

        if ($attributes['shape_id'] === null) {
            return ['Kode/Bentuk tidak dikenali. Gunakan kode atau nama bentuk dari master.'];
        }

        $validator = Validator::make(
            $attributes,
            StoreDiamondDossierRequest::fieldRules(),
            StoreDiamondDossierRequest::validationMessages(),
        );

        $validator->after(function ($validator) use ($attributes): void {
            if ($attributes['shape_id'] === null) {
                return;
            }

            $shapeExists = collect($this->inventory->shapeOptions())
                ->contains(fn (array $option): bool => (int) $option['value'] === (int) $attributes['shape_id']);

            if (! $shapeExists) {
                $validator->errors()->add('shape_id', 'Bentuk tidak valid.');
            }

            StoreDiamondDossierRequest::assertCertificateUniqueForDossier(
                $validator,
                (string) $attributes['diamond_type'],
                $attributes['certificate'] ?? null,
            );
        });

        if ($validator->fails()) {
            return collect($validator->errors()->all())
                ->map(fn (string $message): string => $message)
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * @param  list<mixed>  $cells
     * @return array{
     *     doc_no?: null,
     *     diamond_type: string,
     *     shape_id: int|null,
     *     color: string,
     *     crt: string,
     *     certificate: string|null,
     *     supplier: string|null,
     *     entry_date: string|null
     * }
     */
    private function mapRowToAttributes(array $cells): array
    {
        $shapeInput = $this->cellString($cells[1] ?? null);
        $crt = $this->normalizeCrt($cells[2] ?? null);

        return [
            'diamond_type' => DiamondDossierInventory::diamondTypes()[0] ?? 'Dossier',
            'shape_id' => $this->inventory->resolveShapeId($shapeInput),
            'color' => $this->cellString($cells[3] ?? null),
            'crt' => $crt,
            'certificate' => $this->nullableCellString($cells[4] ?? null),
            'supplier' => $this->nullableCellString($cells[5] ?? null),
            'entry_date' => $this->parseEntryDate($cells[0] ?? null),
        ];
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

        return str_replace('  ', ' ', $normalized);
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

    private function normalizeCrt(mixed $value): string
    {
        $normalized = str_replace(',', '.', $this->cellString($value));
        $normalized = preg_replace('/\.(?=.*\.)/', '', $normalized) ?? $normalized;

        return $normalized;
    }

    private function parseEntryDate(mixed $value): ?string
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
