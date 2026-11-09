<?php

namespace App\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DiamondDossierStockReportSummaryExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @var list<int>
     */
    private array $shapeTitleRows = [];

    /**
     * @var list<int>
     */
    private array $tableHeaderRows = [];

    /**
     * @var list<int>
     */
    private array $subtotalRows = [];

    private int $grandTotalRow = 0;

    /**
     * @param  list<array{
     *     shapeLabel: string,
     *     rows: list<array{
     *         crtMin: string,
     *         crtMax: string,
     *         reserved: array{pcs: int, crt: string},
     *         available: array{pcs: int, crt: string},
     *         actualAvailable: array{pcs: int, crt: string},
     *     }>,
     *     totals: array{
     *         reserved: array{pcs: int, crt: string},
     *         available: array{pcs: int, crt: string},
     *         actualAvailable: array{pcs: int, crt: string},
     *     },
     * }>  $sections
     * @param  array{
     *     reserved: array{pcs: int, crt: string},
     *     available: array{pcs: int, crt: string},
     *     actualAvailable: array{pcs: int, crt: string},
     * }  $grandTotals
     */
    public function __construct(
        private readonly array $sections,
        private readonly array $grandTotals,
    ) {}

    public function title(): string
    {
        return 'Summary';
    }

    /**
     * @return list<list<int|string>>
     */
    public function array(): array
    {
        $rows = [];
        $rowIndex = 1;

        foreach ($this->sections as $section) {
            $rows[] = [$section['shapeLabel']];
            $this->shapeTitleRows[] = $rowIndex;
            $rowIndex++;

            $rows[] = [
                'CRT Range',
                'Dialokasikan (pcs)',
                'Tersedia (pcs)',
                'Aktual Tersedia (pcs)',
            ];
            $this->tableHeaderRows[] = $rowIndex;
            $rowIndex++;

            foreach ($section['rows'] as $row) {
                $rows[] = [
                    $this->formatCrtRange($row['crtMin'], $row['crtMax']),
                    $row['reserved']['pcs'],
                    $row['available']['pcs'],
                    $row['actualAvailable']['pcs'],
                ];
                $rowIndex++;
            }

            $rows[] = [
                'Subtotal',
                $section['totals']['reserved']['pcs'],
                $section['totals']['available']['pcs'],
                $section['totals']['actualAvailable']['pcs'],
            ];
            $this->subtotalRows[] = $rowIndex;
            $rowIndex++;

            $rows[] = [''];
            $rowIndex++;
        }

        $rows[] = [
            'Grand total',
            $this->grandTotals['reserved']['pcs'],
            $this->grandTotals['available']['pcs'],
            $this->grandTotals['actualAvailable']['pcs'],
        ];
        $this->grandTotalRow = $rowIndex;

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $styles = [];

        foreach ($this->shapeTitleRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true, 'size' => 12],
            ];
        }

        foreach ($this->tableHeaderRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F7F8F9'],
                ],
            ];
        }

        foreach ($this->subtotalRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F4EA'],
                ],
            ];
        }

        if ($this->grandTotalRow > 0) {
            $styles[$this->grandTotalRow] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D1E7DD'],
                ],
            ];
        }

        return $styles;
    }

    private function formatCrtRange(string $crtMin, string $crtMax): string
    {
        return sprintf(
            '%s–%s',
            number_format((float) $crtMin, 3, ',', '.'),
            number_format((float) $crtMax, 3, ',', '.'),
        );
    }
}
