<?php

namespace App\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DiamondDossierImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  list<list<string|null>>  $sampleRows
     */
    public function __construct(
        private readonly array $sampleRows = [],
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Tanggal Masuk',
            'Kode/Bentuk',
            'CRT',
            'Warna',
            'GIA',
            'Supplier',
        ];
    }

    /**
     * @return list<list<string|null>>
     */
    public function array(): array
    {
        return $this->sampleRows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F4EA'],
                ],
            ],
        ];
    }
}
