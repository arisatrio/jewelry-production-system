<?php

namespace App\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DiamondDossierTransactionLogExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  list<array{
     *     eventDate: string|null,
     *     eventLabel: string,
     *     code: string|null,
     *     shape: string|null,
     *     crt: string|null,
     *     color: string|null,
     *     certificate: string|null,
     *     supplier: string|null,
     *     mountingDocumentNo: string|null,
     *     spkNo: string|null,
     *     actor: string|null,
     *     actorAt: string|null
     * }>  $rows
     */
    public function __construct(
        private readonly array $rows,
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Tanggal',
            'Jenis',
            'Kode',
            'Bentuk',
            'CRT',
            'Warna',
            'No. Sertifikat',
            'Supplier',
            'No. Pasang Batu',
            'No. SPK',
            'Oleh',
            'Diubah pada',
        ];
    }

    /**
     * @return list<list<string|null>>
     */
    public function array(): array
    {
        return array_map(
            fn (array $row): array => [
                $row['eventDate'],
                $row['eventLabel'],
                $row['code'],
                $row['shape'],
                $row['crt'],
                $row['color'],
                $row['certificate'],
                $row['supplier'],
                $row['mountingDocumentNo'],
                $row['spkNo'],
                $row['actor'],
                $row['actorAt'],
            ],
            $this->rows,
        );
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
