<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e5e7eb; color: #000; font-family: 'Times New Roman', Times, serif; }
        .spkPrintToolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: flex-end; gap: 0.5rem; padding: 0.75rem 1rem; background: #111827; color: #fff; }
        .spkPrintToolbar button { appearance: none; border: 1px solid #4b5563; border-radius: 0.25rem; background: #fff; color: #111827; font-size: 0.875rem; font-family: system-ui, -apple-system, sans-serif; padding: 0.4rem 0.85rem; cursor: pointer; }
        .spkPrintToolbar button.primary { background: #0070f2; border-color: #0070f2; color: #fff; }
        .spkReceiptPage { width: 210mm; min-height: 297mm; margin: 1rem auto; padding: 12mm; background: #fff; box-shadow: 0 1px 4px rgb(0 0 0 / 18%); }
        .spkDocumentHeader { display: grid; grid-template-columns: 16% minmax(0, 1fr) 26%; align-items: stretch; width: 100%; font-size: 10pt; line-height: 1.15; border: 1px solid #000; }
        .spkDocumentHeaderLogo { display: flex; align-items: center; justify-content: center; padding: 4px; border-right: 1px solid #000; }
        .spkDocumentHeaderLogoImg { display: block; width: 100%; max-width: 70px; max-height: 52px; object-fit: contain; }
        .spkDocumentHeaderCenter { display: flex; flex-direction: column; border-right: 1px solid #000; }
        .spkDocumentHeaderFormTitle { display: flex; flex: 1 1 auto; align-items: center; justify-content: center; padding: 4px 6px; text-align: center; font-size: 16pt; font-weight: 700; letter-spacing: 0.03em; border-bottom: 1px solid #000; }
        .spkDocumentHeaderCompany { padding: 2px 6px; text-align: center; font-size: 9pt; font-weight: 700; }
        .spkDocumentHeaderMeta { display: flex; flex-direction: column; justify-content: center; gap: 1px; padding: 4px 8px; font-size: 9pt; line-height: 1.2; }
        .spkReceiptInfo { margin: 10px 0 8px; font-size: 10pt; }
        .spkReceiptInfoTable { border-collapse: collapse; width: 100%; }
        .spkReceiptInfoTable td { padding: 2px 0; vertical-align: top; }
        .spkReceiptInfoTable td:first-child { width: 148px; font-weight: 700; }
        .spkReceiptTable { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-top: 8px; }
        .spkReceiptTable th, .spkReceiptTable td { padding: 4px 5px; border: 1px solid #000; text-align: left; vertical-align: top; }
        .spkReceiptTable th { background: #f3f4f6; font-weight: 700; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .spkReceiptTable thead { display: table-header-group; }
        .spkReceiptTable tr { break-inside: avoid; }
        .spkReceiptColNo { width: 1%; white-space: nowrap; text-align: center !important; }
        .spkReceiptColCenter { text-align: center !important; white-space: nowrap; }
        .spkReceiptColNumeric { text-align: right !important; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .spkReceiptTable tfoot td { font-weight: 700; background: #f9fafb; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .spkReceiptFooter { margin-top: 16px; font-size: 8.5pt; color: #4b5563; }
        @page { size: A4; margin: 8mm; }
        @media print {
            html, body { background: #fff; }
            .spkPrintToolbar { display: none !important; }
            .spkReceiptPage { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="spkPrintToolbar">
        <button type="button" onclick="window.close()">Tutup</button>
        <button type="button" class="primary" onclick="window.print()">Cetak / PDF</button>
    </div>

    <main class="spkReceiptPage">
        @include('spk.partials.document-header', ['header' => $header])

        <div class="spkReceiptInfo">
            <table class="spkReceiptInfoTable">
                <tr>
                    <td>No. SPK Pasang Batu</td>
                    <td>: <strong>{{ $document['docNo'] }}</strong></td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td>: {{ $document['tanggal'] }}</td>
                </tr>
                <tr>
                    <td>No. SPK</td>
                    <td>: {{ $document['spkNo'] }}</td>
                </tr>
                <tr>
                    <td>SKU</td>
                    <td>: {{ $document['sku'] }}</td>
                </tr>
                <tr>
                    <td>SKU description</td>
                    <td>: {{ $document['skuDescription'] }}</td>
                </tr>
                <tr>
                    <td>Dibuat oleh</td>
                    <td>: {{ $document['createdBy'] }}</td>
                </tr>
                <tr>
                    <td>Dibuat tanggal</td>
                    <td>: {{ $document['createdDate'] }}</td>
                </tr>
            </table>
        </div>

        <table class="spkReceiptTable">
            <thead>
                <tr>
                    <th class="spkReceiptColNo">No</th>
                    <th>Tipe</th>
                    <th>No. sertifikat</th>
                    <th>Bentuk</th>
                    <th>Nama/Kode batu</th>
                    <th class="spkReceiptColNumeric">CRT per butir</th>
                    <th class="spkReceiptColNumeric">Jumlah butir (pcs)</th>
                    <th class="spkReceiptColNumeric">Total CRT</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($document['items'] as $item)
                    <tr>
                        <td class="spkReceiptColNo">{{ $loop->iteration }}</td>
                        <td>{{ $item['sourceLabel'] }}</td>
                        <td>{{ $item['certificate'] }}</td>
                        <td>{{ $item['shape'] }}</td>
                        <td>{{ $item['label'] }}</td>
                        <td class="spkReceiptColNumeric">{{ $item['crtPerPcs'] }}</td>
                        <td class="spkReceiptColNumeric">{{ $item['reservedPcs'] }}</td>
                        <td class="spkReceiptColNumeric">{{ $item['reservedCrt'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="spkReceiptColCenter">Tidak ada baris batu pada dokumen ini.</td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($document['items']) > 0)
                <tfoot>
                    <tr>
                        <td colspan="6">Total batu dialokasikan</td>
                        <td class="spkReceiptColNumeric">{{ $document['totals']['pcs'] }}</td>
                        <td class="spkReceiptColNumeric">{{ $document['totals']['carat'] }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="spkReceiptFooter">Dicetak oleh {{ $printedBy }} pada {{ $printedAt }}</div>
    </main>
</body>
</html>