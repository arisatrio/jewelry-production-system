<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class SpkDiamondDocumentPrintPresenter
{
    /**
     * @param  array{
     *     docNo: string,
     *     tanggal: string,
     *     spkNo: string|null,
     *     typeSkuLabel: string|null,
     *     itemDescription: string|null,
     *     createdBy: string|null,
     *     createdDate: string|null,
     *     items: list<array<string, mixed>>,
     *     totals: array{pcs: int, carat: float}
     * }  $detail
     * @return array{
     *     docNo: string,
     *     tanggal: string,
     *     spkNo: string,
     *     sku: string,
     *     skuDescription: string,
     *     createdBy: string,
     *     createdDate: string,
     *     items: list<array{
     *         sourceLabel: string,
     *         certificate: string,
     *         shape: string,
     *         label: string,
     *         crtPerPcs: string,
     *         reservedPcs: string,
     *         reservedCrt: string
     *     }>,
     *     totals: array{pcs: string, carat: string}
     * }
     */
    public function fromDetailPayload(array $detail): array
    {
        $items = [];

        foreach ($detail['items'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = [
                'sourceLabel' => $this->display((string) ($item['sourceLabel'] ?? '')),
                'certificate' => $this->display(filled($item['certificate'] ?? null) ? (string) $item['certificate'] : null),
                'shape' => $this->display((string) ($item['shape'] ?? '')),
                'label' => $this->display((string) ($item['label'] ?? '')),
                'crtPerPcs' => $this->formatDecimal3($item['crtPerPcs'] ?? null),
                'reservedPcs' => $this->display(isset($item['reservedPcs']) ? (string) $item['reservedPcs'] : null),
                'reservedCrt' => $this->formatDecimal3($item['reservedCrt'] ?? null),
            ];
        }

        return [
            'docNo' => (string) $detail['docNo'],
            'tanggal' => $this->formatDocumentDate((string) $detail['tanggal']),
            'spkNo' => $this->display($detail['spkNo'] ?? null),
            'sku' => $this->display($detail['typeSkuLabel'] ?? null),
            'skuDescription' => $this->display($detail['itemDescription'] ?? null),
            'createdBy' => $this->display($detail['createdBy'] ?? null),
            'createdDate' => $this->formatCreatedDate($detail['createdDate'] ?? null),
            'items' => $items,
            'totals' => [
                'pcs' => number_format((int) ($detail['totals']['pcs'] ?? 0), 0, ',', '.'),
                'carat' => $this->formatDecimal3((string) ($detail['totals']['carat'] ?? '0')),
            ],
        ];
    }

    private function display(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : '—';
    }

    private function formatDecimal3(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        $text = trim((string) $value);

        if ($text === '') {
            return '—';
        }

        $normalized = (float) str_replace(',', '.', $text);

        if (! is_finite($normalized)) {
            return '—';
        }

        return number_format($normalized, 3, ',', '.');
    }

    private function formatDocumentDate(string $isoDate): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $isoDate)->format('d-M-Y');
        } catch (\Throwable) {
            return $this->display($isoDate);
        }
    }

    private function formatCreatedDate(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('d-M-Y H:i');
        } catch (\Throwable) {
            return $this->display($value);
        }
    }
}
