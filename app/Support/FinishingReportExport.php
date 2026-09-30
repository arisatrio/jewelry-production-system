<?php

namespace App\Support;

use DateTimeInterface;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithFreezePane;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * @phpstan-type FinishingReportRow array{
 *     docNo: string|null,
 *     sendCraftsmanDate: DateTimeInterface|null,
 *     receivedCraftsmanDate: DateTimeInterface|null,
 *     craftsmanName: string|null,
 *     spkNo: string|null,
 *     item: string|null,
 *     itemCategory: string|null,
 *     startWeight: float|null,
 *     submitMaterial: float|null,
 *     finishWeight: float|null,
 *     resultMaterial: float|null,
 *     shrink: float|null,
 *     shrinkRatio: float|null,
 *     qcStatus: string|null,
 *     qcNotes: string|null,
 *     workDuration: string|null,
 *     notes: string|null
 * }
 *
 * @implements WithMapping<FinishingReportRow>
 */
class FinishingReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithFreezePane, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const DATE_TIME_FORMAT = 'dd-mmm-yyyy hh:mm';

    private const WEIGHT_FORMAT = '0.00';

    /**
     * Negative shrink means the item gained weight, shown as "+" like the index table.
     */
    private const SHRINK_FORMAT = '0.00;+0.00;0.00';

    private const SHRINK_PERCENT_FORMAT = '0.00%;+0.00%;0.00%';

    /**
     * @param  array<int, FinishingReportRow>  $rows
     */
    public function __construct(private readonly array $rows) {}

    /**
     * @return array<int, FinishingReportRow>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'No Document',
            'Tanggal Serah',
            'Tanggal Terima',
            'Pengrajin',
            'No SPK',
            'Item',
            'Kategori',
            'Berat Awal (g)',
            'Bahan (g)',
            'Berat Akhir (g)',
            'Bahan Sisa (g)',
            'Susut (g)',
            'Persentase Susut (%)',
            'QC',
            'Catatan QC',
            'Waktu Pengerjaan',
            'Notes',
        ];
    }

    /**
     * @param  FinishingReportRow  $row
     * @return list<string|float|null>
     */
    public function map(mixed $row): array
    {
        return [
            $row['docNo'],
            $this->toExcelDate($row['sendCraftsmanDate']),
            $this->toExcelDate($row['receivedCraftsmanDate']),
            $row['craftsmanName'],
            $row['spkNo'],
            $row['item'],
            $row['itemCategory'],
            $row['startWeight'],
            $row['submitMaterial'],
            $row['finishWeight'],
            $row['resultMaterial'],
            $row['shrink'],
            $row['shrinkRatio'],
            $row['qcStatus'],
            $row['qcNotes'],
            $row['workDuration'],
            $row['notes'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'B' => self::DATE_TIME_FORMAT,
            'C' => self::DATE_TIME_FORMAT,
            'H' => self::WEIGHT_FORMAT,
            'I' => self::WEIGHT_FORMAT,
            'J' => self::WEIGHT_FORMAT,
            'K' => self::WEIGHT_FORMAT,
            'L' => self::SHRINK_FORMAT,
            'M' => self::SHRINK_PERCENT_FORMAT,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'F' => 45,
            'O' => 35,
            'Q' => 45,
        ];
    }

    public function freezePane(): string
    {
        return 'A2';
    }

    public function title(): string
    {
        return 'Laporan Finishing';
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'A:Q' => [
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ],
            'F' => ['alignment' => ['wrapText' => true]],
            'O' => ['alignment' => ['wrapText' => true]],
            'Q' => ['alignment' => ['wrapText' => true]],
        ];
    }

    private function toExcelDate(?DateTimeInterface $date): ?float
    {
        return $date === null ? null : (float) Date::dateTimeToExcel($date);
    }
}
