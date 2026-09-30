<?php

namespace App\Support;

use DateTimeInterface;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithFreezePane;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
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
class FinishingReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithCustomStartCell, WithEvents, WithFreezePane, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const LAST_COLUMN = 'Q';

    private const HEADING_ROW = 6;

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
    public function __construct(
        private readonly array $rows,
        private readonly string $craftsmanLabel,
        private readonly DateTimeInterface $dateFrom,
        private readonly DateTimeInterface $dateTo,
        private readonly DateTimeInterface $exportedAt,
    ) {}

    public function startCell(): string
    {
        return 'A'.self::HEADING_ROW;
    }

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
        return 'A'.(self::HEADING_ROW + 1);
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
            self::HEADING_ROW => [
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

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->getSheet()->getDelegate();

                $this->writeReportHeader($sheet);
                $this->writeSummaryRow($sheet);
            },
        ];
    }

    private function writeReportHeader(Worksheet $sheet): void
    {
        $lines = [
            'Laporan Finishing',
            "Pengrajin : {$this->craftsmanLabel}",
            'Tanggal : '.$this->dateFrom->format('d-M-Y').' s/d '.$this->dateTo->format('d-M-Y'),
            'Tanggal Export : '.$this->exportedAt->format('d-M-Y H:i'),
        ];

        foreach ($lines as $index => $line) {
            $row = $index + 1;

            $sheet->setCellValue("A{$row}", $line);
            $sheet->mergeCells("A{$row}:".self::LAST_COLUMN.$row);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    }

    private function writeSummaryRow(Worksheet $sheet): void
    {
        $firstDataRow = self::HEADING_ROW + 1;
        $lastDataRow = self::HEADING_ROW + count($this->rows);
        $summaryRow = $lastDataRow + 1;
        $hasRows = $this->rows !== [];

        $sum = fn (string $column): string|int => $hasRows
            ? "=SUM({$column}{$firstDataRow}:{$column}{$lastDataRow})"
            : 0;

        $sheet->setCellValue("A{$summaryRow}", 'Total ('.count($this->rows).' dokumen)');
        $sheet->mergeCells("A{$summaryRow}:G{$summaryRow}");

        foreach (['H', 'I', 'J', 'K', 'L'] as $column) {
            $sheet->setCellValue("{$column}{$summaryRow}", $sum($column));
        }

        $sheet->setCellValue(
            "M{$summaryRow}",
            "=IF((H{$summaryRow}+I{$summaryRow})=0,0,L{$summaryRow}/(H{$summaryRow}+I{$summaryRow}))",
        );

        $sheet->getStyle("H{$summaryRow}:K{$summaryRow}")->getNumberFormat()->setFormatCode(self::WEIGHT_FORMAT);
        $sheet->getStyle("L{$summaryRow}")->getNumberFormat()->setFormatCode(self::SHRINK_FORMAT);
        $sheet->getStyle("M{$summaryRow}")->getNumberFormat()->setFormatCode(self::SHRINK_PERCENT_FORMAT);

        $sheet->getStyle("A{$summaryRow}:".self::LAST_COLUMN.$summaryRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    private function toExcelDate(?DateTimeInterface $date): ?float
    {
        return $date === null ? null : (float) Date::dateTimeToExcel($date);
    }
}
