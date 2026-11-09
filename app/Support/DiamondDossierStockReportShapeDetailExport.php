<?php

namespace App\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DiamondDossierStockReportShapeDetailExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<array{
     *     id: int,
     *     code: string|null,
     *     diamondType: string|null,
     *     shape: string|null,
     *     color: string|null,
     *     crt: string|null,
     *     certificate: string|null,
     *     supplier: string|null,
     *     entryDate: string|null,
     *     outDate: string|null,
     *     status: string,
     *     reservedSpkNo: string|null,
     *     spkNo: string|null,
     *     mountingDocumentNo: string|null,
     *     mountingDocumentId: int|null,
     *     createdBy: string|null,
     *     createdDate: string|null,
     *     modifiedBy: string|null,
     *     modifiedDate: string|null
     * }>  $rows
     */
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $rows,
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Tipe',
            'Bentuk',
            'Warna',
            'CRT',
            'No. Sertifikat',
            'Supplier',
            'Tanggal masuk',
            'Tanggal keluar',
            'Status',
            'No. Pasang Batu',
            'No. SPK',
            'Dibuat oleh',
            'Diubah oleh',
        ];
    }

    /**
     * @return list<list<int|string|null>>
     */
    public function array(): array
    {
        return array_map(
            fn (array $row): array => [
                $row['code'],
                $row['diamondType'],
                $row['shape'],
                $row['color'],
                $row['crt'],
                $row['certificate'],
                $row['supplier'],
                $row['entryDate'],
                $row['outDate'],
                DiamondDossierInventory::inventoryStatusLabel(
                    (string) $row['status'],
                    $row['reservedSpkNo'],
                ),
                $row['mountingDocumentNo'],
                $row['spkNo'],
                $row['createdBy'],
                $row['modifiedBy'],
            ],
            $this->rows,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F7F8F9'],
                ],
            ],
        ];
    }
}
