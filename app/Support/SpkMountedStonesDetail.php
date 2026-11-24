<?php

namespace App\Support;

use App\Models\DiamondMounting;
use App\Models\Production;

class SpkMountedStonesDetail
{
    public function __construct(
        private DiamondMountingStoneSynchronizer $stoneSynchronizer,
    ) {}

    /**
     * @return array{
     *     pasangBatu: array{id: int, docNo: string, showUrl: string, statusLabel: string}|null,
     *     mounted: list<array{kode: string, shape: string, pcs: int|float|null, crt: string|null, size: string}>,
     *     mountedTotals: array{pcs: int, carat: float},
     *     dossierCertificateByDocNo: array<string, string>,
     *     dossierGiaCertificateQueue: list<string>,
     *     dossierDiamondDocNos: list<string>
     * }
     */
    public function forProduction(Production $production): array
    {
        $document = DiamondMounting::query()
            ->notDeleted()
            ->where('spk_id', (int) $production->row_id)
            ->orderByDesc('row_id')
            ->first();

        if ($document === null) {
            return [
                'pasangBatu' => null,
                'mounted' => [],
                'mountedTotals' => [
                    'pcs' => 0,
                    'carat' => 0.0,
                ],
                'dossierCertificateByDocNo' => [],
                'dossierGiaCertificateQueue' => [],
                'dossierDiamondDocNos' => [],
            ];
        }

        $stones = $this->stoneSynchronizer->detailPayloadFor($document);
        $mounted = $this->stoneSynchronizer->mountedDetailFor($document);
        $diamondLines = $stones['diamonds'] ?? [];
        $dossierCertificates = $this->dossierCertificatesFromDetail($diamondLines);
        $dossierDiamondDocNos = $this->dossierDiamondDocNosFromDetail($diamondLines);

        return [
            'pasangBatu' => [
                'id' => (int) $document->row_id,
                'docNo' => (string) $document->doc_no,
                'showUrl' => route('pasang-batu.show', $document),
                'statusLabel' => $document->statusLabel(),
            ],
            'mounted' => $mounted,
            'mountedTotals' => $this->sumMountedTotals($mounted),
            'dossierCertificateByDocNo' => $dossierCertificates['byDocNo'],
            'dossierGiaCertificateQueue' => $dossierCertificates['queue'],
            'dossierDiamondDocNos' => $dossierDiamondDocNos,
        ];
    }

    /**
     * @param  list<array{kode?: string}>  $diamondLines
     * @return list<string>
     */
    private function dossierDiamondDocNosFromDetail(array $diamondLines): array
    {
        $codes = [];

        foreach ($diamondLines as $line) {
            $kode = trim((string) ($line['kode'] ?? ''));

            if ($kode === '' || in_array($kode, ['—', '-'], true)) {
                continue;
            }

            $codes[] = $kode;
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  list<array{kode?: string, sertifikat?: string}>  $diamondLines
     * @return array{byDocNo: array<string, string>, queue: list<string>}
     */
    private function dossierCertificatesFromDetail(array $diamondLines): array
    {
        $byDocNo = [];
        $queue = [];

        foreach ($diamondLines as $line) {
            $kode = trim((string) ($line['kode'] ?? ''));

            if ($kode === '' || in_array($kode, ['—', '-'], true)) {
                continue;
            }

            $certificate = trim((string) ($line['sertifikat'] ?? ''));

            if ($certificate === '' || in_array($certificate, ['—', '-'], true)) {
                $queue[] = '';
            } else {
                $byDocNo[$kode] = $certificate;
                $queue[] = $certificate;
            }
        }

        return [
            'byDocNo' => $byDocNo,
            'queue' => $queue,
        ];
    }

    /**
     * @param  list<array{kode?: string, pcs?: int|float|string|null, crt?: string|null}>  $rows
     * @return array{pcs: int, carat: float}
     */
    private function sumMountedTotals(array $rows): array
    {
        $pcs = 0;
        $carat = 0.0;

        foreach ($rows as $row) {
            $linePcs = (int) round((float) str_replace(',', '.', (string) ($row['pcs'] ?? '0')));

            if ($linePcs > 0) {
                $pcs += $linePcs;
            }

            $crtText = $row['crt'] ?? null;

            if (is_string($crtText) && trim($crtText) !== '') {
                $crt = (float) str_replace(',', '.', $crtText);

                if (is_finite($crt)) {
                    $carat += $crt;
                }
            }
        }

        return [
            'pcs' => $pcs,
            'carat' => round($carat, 3),
        ];
    }
}
