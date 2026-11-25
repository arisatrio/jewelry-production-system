<?php

namespace App\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FakturBarangJadiImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
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
            'No Faktur (opsional)',
            'Tanggal',
            'Dari',
            'Untuk',
            'No SPK',
            'PLU (opsional)',
            'Berat Akhir Barang Jadi (Emas + Batu)',
            'Diserahkan Oleh (opsional)',
            'Diketahui Oleh (opsional)',
            'Diterima Oleh (opsional)',
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
                    'startColor' => ['rgb' => 'E8F0FA'],
                ],
            ],
        ];
    }
}
