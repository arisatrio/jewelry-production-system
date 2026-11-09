<?php

namespace App\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GoldMaterialTransactionWorkbookExport implements FromArray, ShouldAutoSize, WithCustomStartCell, WithEvents, WithStyles, WithTitle
{
    private const DATA_START_ROW = 3;

    private const LAST_COLUMN = 'AL';

    /**
     * @var list<string>
     */
    private const GROUP_HEADERS = [
        'Finishing/Handmade',
        'Poles Rangka',
        'Poles Barang Jadi',
        'Pasang Batu (Diamond Mounting)',
        'Reparasi Finishing Handmade',
        'Reparasi Pasang Batu',
        'Reparasi Poles Chrome',
    ];

    /**
     * @var list<string>
     */
    private const SUB_HEADERS = [
        'Tanggal',
        'Pengrajin',
        'Berat Awal',
        'Rangka Jadi',
        'Susut',
    ];

    /**
     * @param  list<array{spkNo: string, cells: list<list<string>>, totalShrink: string}>  $rows
     */
    public function __construct(
        private readonly array $rows,
    ) {}

    public function startCell(): string
    {
        return 'A'.self::DATA_START_ROW;
    }

    public function title(): string
    {
        return 'Worksheet';
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return array_map(
            function (array $row, int $index): array {
                $flat = [
                    (string) ($index + 1),
                    $row['spkNo'],
                ];

                foreach ($row['cells'] as $block) {
                    foreach ($block as $value) {
                        $flat[] = $value;
                    }
                }

                $flat[] = $row['totalShrink'];

                return $flat;
            },
            $this->rows,
            array_keys($this->rows),
        );
    }

    public function styles(Worksheet $sheet): array
    {
        $lastDataRow = max(
            self::DATA_START_ROW,
            self::DATA_START_ROW + count($this->rows) - 1,
        );

        return [
            1 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F4EA'],
                ],
            ],
            2 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F4EA'],
                ],
            ],
            sprintf('A%d:%s%d', self::DATA_START_ROW, self::LAST_COLUMN, $lastDataRow) => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->setCellValue('A1', 'No');
                $sheet->setCellValue('B1', 'No. PRD');
                $sheet->setCellValue(self::LAST_COLUMN.'1', 'Total Susut');

                $columnIndex = 3;

                foreach (self::GROUP_HEADERS as $groupHeader) {
                    $startColumn = $this->columnLetter($columnIndex);
                    $endColumn = $this->columnLetter($columnIndex + 4);
                    $sheet->mergeCells("{$startColumn}1:{$endColumn}1");
                    $sheet->setCellValue("{$startColumn}1", $groupHeader);
                    $columnIndex += 5;
                }

                $columnIndex = 3;

                foreach (self::GROUP_HEADERS as $_groupHeader) {
                    foreach (self::SUB_HEADERS as $subHeader) {
                        $column = $this->columnLetter($columnIndex);
                        $sheet->setCellValue("{$column}2", $subHeader);
                        $columnIndex++;
                    }
                }

                $sheet->freezePane('A'.self::DATA_START_ROW);
            },
        ];
    }

    private function columnLetter(int $index): string
    {
        $letters = '';

        while ($index > 0) {
            $modulo = ($index - 1) % 26;
            $letters = chr(65 + $modulo).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }
}
