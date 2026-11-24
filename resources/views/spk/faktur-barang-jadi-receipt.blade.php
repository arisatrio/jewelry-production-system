<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e5e7eb; color: #000; font-family: Arial, Helvetica, sans-serif; }
        .spkPrintToolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: flex-end; gap: 0.5rem; padding: 0.75rem 1rem; background: #111827; color: #fff; }
        .spkPrintToolbar button { appearance: none; border: 1px solid #4b5563; border-radius: 0.25rem; background: #fff; color: #111827; font-size: 0.875rem; font-family: system-ui, -apple-system, sans-serif; padding: 0.4rem 0.85rem; cursor: pointer; }
        .spkPrintToolbar button.primary { background: #0070f2; border-color: #0070f2; color: #fff; }
        .spkReceiptPage {
            width: 297mm;
            min-height: 210mm;
            max-width: 297mm;
            margin: 1rem auto;
            padding: 10mm;
            background: #fff;
            box-shadow: 0 1px 4px rgb(0 0 0 / 18%);
        }
        .spkDocumentHeader { display: grid; grid-template-columns: 16% minmax(0, 1fr) 26%; align-items: stretch; width: 100%; font-size: 10pt; line-height: 1.15; border: 1px solid #000; }
        .spkDocumentHeaderLogo { display: flex; align-items: center; justify-content: center; padding: 4px; border-right: 1px solid #000; }
        .spkDocumentHeaderLogoImg { display: block; width: 100%; max-width: 70px; max-height: 52px; object-fit: contain; }
        .spkDocumentHeaderCenter { display: flex; flex-direction: column; border-right: 1px solid #000; }
        .spkDocumentHeaderFormTitle { display: flex; flex: 1 1 auto; align-items: center; justify-content: center; padding: 4px 6px; text-align: center; font-size: 14pt; font-weight: 700; letter-spacing: 0.03em; border-bottom: 1px solid #000; }
        .spkDocumentHeaderCompany { padding: 2px 6px; text-align: center; font-size: 9pt; font-weight: 700; }
        .spkDocumentHeaderMeta { display: flex; flex-direction: column; justify-content: center; gap: 1px; padding: 4px 8px; font-size: 9pt; line-height: 1.2; }
        .spkFbjReceiptMeta { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin: 8px 0 6px; font-size: 9pt; }
        .spkFbjReceiptMetaTable td { padding: 1px 0; vertical-align: top; }
        .spkFbjReceiptMetaTable td:first-child { padding-right: 4px; white-space: nowrap; }
        .spkFbjDetailTableWrap {
            width: 100%;
            max-width: 100%;
            border-right: 1px solid #000;
        }
        .spkFbjDetailTable {
            width: 100%;
            max-width: 100%;
            border: 0;
            border-collapse: collapse;
            font-size: 8pt;
            table-layout: fixed;
        }
        .spkFbjDetailTable th, .spkFbjDetailTable td { border: 1px solid #000; padding: 3px 4px; text-align: center; vertical-align: middle; word-wrap: break-word; }
        .spkFbjDetailTable th.spkFbjColNotes,
        .spkFbjDetailTable td.spkFbjColNotes {
            border-right: 1px solid #000;
        }
        .spkFbjDetailTable thead th { background: #f4c2a1; font-weight: 700; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .spkFbjDetailTable .spkFbjColLeft { text-align: left; }
        .spkFbjCategorySkuCode { display: block; margin-top: 2px; font-weight: 600; word-break: break-word; }
        .spkFbjProductionType { white-space: pre-line; }
        .spkFbjDetailTable .spkFbjColNo { width: 3%; }
        .spkFbjDetailTable .spkFbjColPrd { width: 9%; }
        .spkFbjDetailTable .spkFbjColCategory { width: 15%; }
        .spkFbjDetailTable .spkFbjColType { width: 7%; }
        .spkFbjDetailTable .spkFbjColGoldColor { width: 8%; }
        .spkFbjDetailTable .spkFbjColPcs { width: 3%; }
        .spkFbjDetailTable .spkFbjColCarat { width: 4%; }
        .spkFbjDetailTable .spkFbjColPerPcs { width: 5%; }
        .spkFbjDetailTable .spkFbjColShape { width: 4%; }
        .spkFbjDetailTable .spkFbjColGia { width: 9%; }
        .spkFbjDetailTable .spkFbjColDone { width: 7%; }
        .spkFbjDetailTable .spkFbjColImage { width: 7%; }
        .spkFbjDetailTable .spkFbjColGoldWt { width: 5%; }
        .spkFbjDetailTable .spkFbjColFinalWt { width: 5%; }
        .spkFbjDetailTable .spkFbjColNotes { width: 9%; }
        .spkFbjItemImage { display: block; max-width: 56px; max-height: 56px; margin: 0 auto; object-fit: contain; }
        .spkReceiptSignatures { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 14px; border: 1px solid #000; font-size: 9pt; break-inside: avoid; }
        .spkReceiptSignature { display: flex; flex-direction: column; text-align: center; border-right: 1px solid #000; }
        .spkReceiptSignature:last-child { border-right: none; }
        .spkReceiptSignatureTitle { padding: 4px 5px; border-bottom: 1px solid #000; background: #f3f4f6; font-weight: 700; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .spkReceiptSignatureSpace { height: 64px; }
        .spkReceiptSignatureLine { margin: 0 12px; padding: 4px 0; }
        .spkReceiptSignatureName { font-weight: 700; }
        .spkReceiptSignatureDate { padding: 4px 8px; border-top: 1px solid #000; text-align: left; }
        .spkReceiptFooter { margin-top: 12px; font-size: 8pt; color: #4b5563; }
        .spkPrintToolbarHint {
            flex: 1 1 auto;
            font-size: 0.75rem;
            color: #d1d5db;
            align-self: center;
        }
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm;
            }
            html, body { background: #fff; }
            .spkPrintToolbar { display: none !important; }
            .spkReceiptPage {
                width: 100%;
                max-width: 100%;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            /* Border kanan kadang terpotong saat PDF/cetak; garis inset menjaga ketebalan konsisten. */
            .spkDocumentHeader,
            .spkFbjDetailTableWrap,
            .spkReceiptSignatures {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                box-shadow: inset -1px 0 0 #000;
            }

            .spkFbjDetailTable th.spkFbjColNotes,
            .spkFbjDetailTable td.spkFbjColNotes {
                box-shadow: inset -1px 0 0 #000;
            }
        }
    </style>
</head>
<body>
    <div class="spkPrintToolbar">
        <span class="spkPrintToolbarHint">Kertas: A4 · Landscape (mendatar)</span>
        <button type="button" onclick="window.close()">Tutup</button>
        <button type="button" class="primary" onclick="window.print()">Cetak / PDF</button>
    </div>

    <main class="spkReceiptPage">
        @include('spk.partials.document-header', ['header' => $header])

        <div class="spkFbjReceiptMeta">
            <table class="spkFbjReceiptMetaTable">
                <tr>
                    <td>No. Form</td>
                    <td>: <strong>{{ $receiptNo }}</strong></td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td>: {{ $receiptDate }}</td>
                </tr>
                <tr>
                    <td>Dari</td>
                    <td>: {{ $receiptFrom !== '' ? $receiptFrom : '-' }}</td>
                </tr>
                <tr>
                    <td>Untuk</td>
                    <td>: {{ $receiptTo !== '' ? $receiptTo : '-' }}</td>
                </tr>
            </table>
            <div>Jumlah SPK: {{ count($groups) }}</div>
        </div>

        <div class="spkFbjDetailTableWrap">
        <table class="spkFbjDetailTable">
            <thead>
                <tr>
                    <th rowspan="2" class="spkFbjColNo">No</th>
                    <th rowspan="2" class="spkFbjColPrd">No PRD</th>
                    <th rowspan="2" class="spkFbjColCategory spkFbjColLeft">Item</th>
                    <th rowspan="2" class="spkFbjColType">Tipe Produksi</th>
                    <th rowspan="2" class="spkFbjColGoldColor">Warna Emas</th>
                    <th colspan="4">Detail</th>
                    <th rowspan="2" class="spkFbjColGia">No GIA</th>
                    <th rowspan="2" class="spkFbjColDone">Tanggal Selesai</th>
                    <th rowspan="2" class="spkFbjColImage">Gambar</th>
                    <th rowspan="2" class="spkFbjColGoldWt">Berat Emas</th>
                    <th rowspan="2" class="spkFbjColFinalWt">Berat Barang Jadi</th>
                    <th rowspan="2" class="spkFbjColNotes spkFbjColLeft">Keterangan</th>
                </tr>
                <tr>
                    <th class="spkFbjColPcs">Pcs</th>
                    <th class="spkFbjColCarat">Total Crt</th>
                    <th class="spkFbjColPerPcs">Crt per butir</th>
                    <th class="spkFbjColShape">Shape</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $groupIndex => $group)
                    @php
                        $detailRows = $group['detailRows'] ?? [];
                        $rowSpan = max(1, count($detailRows));
                    @endphp
                    @foreach ($detailRows as $index => $detail)
                        <tr>
                            @if ($index === 0)
                                <td rowspan="{{ $rowSpan }}">{{ $groupIndex + 1 }}</td>
                                <td rowspan="{{ $rowSpan }}" class="whitespace-nowrap">{{ $group['spkNo'] }}</td>
                                <td rowspan="{{ $rowSpan }}" class="spkFbjColLeft">
                                    {{ $group['categorySkuPrimary'] }}
                                    @if (($group['skuCode'] ?? '') !== '')
                                        <span class="spkFbjCategorySkuCode">{{ $group['skuCode'] }}</span>
                                    @endif
                                </td>
                                <td rowspan="{{ $rowSpan }}" class="spkFbjProductionType">{{ $group['productionType'] }}</td>
                                <td rowspan="{{ $rowSpan }}">{{ $group['goldColor'] }}</td>
                            @endif
                            <td>{{ $detail['pcs'] }}</td>
                            <td>{{ $detail['carat'] }}</td>
                            <td>{{ $detail['perPieceCarat'] }}</td>
                            <td>{{ $detail['shape'] }}</td>
                            <td>{{ $detail['gia'] !== '' ? $detail['gia'] : '—' }}</td>
                            @if ($index === 0)
                                <td rowspan="{{ $rowSpan }}">{{ $group['completionDate'] }}</td>
                                <td rowspan="{{ $rowSpan }}">
                                    @if (! empty($group['imageUrl']))
                                        <img src="{{ $group['imageUrl'] }}" alt="" class="spkFbjItemImage">
                                    @else
                                        —
                                    @endif
                                </td>
                                <td rowspan="{{ $rowSpan }}">{{ $group['goldWeight'] }}</td>
                                <td rowspan="{{ $rowSpan }}">{{ $group['finalWeight'] }}</td>
                                <td rowspan="{{ $rowSpan }}" class="spkFbjColLeft spkFbjColNotes">{{ $group['remarks'] !== '' ? $group['remarks'] : '—' }}</td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        </div>

        <div class="spkReceiptSignatures">
            @foreach ($signatures as $signature)
                <div class="spkReceiptSignature">
                    <div class="spkReceiptSignatureTitle">{{ $signature['title'] }}</div>
                    <div class="spkReceiptSignatureSpace"></div>
                    <div class="spkReceiptSignatureLine">
                        @if ($signature['name'] !== '')
                            <span class="spkReceiptSignatureName">{{ $signature['name'] }}</span>
                        @else
                            Nama &amp; Tanda Tangan
                        @endif
                    </div>
                    <div class="spkReceiptSignatureDate">Tanggal:</div>
                </div>
            @endforeach
        </div>

        <div class="spkReceiptFooter">Dicetak oleh {{ $printedBy }} pada {{ $printedAt }}</div>
    </main>
</body>
</html>