<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #e5e7eb;
            color: #000;
            font-family: 'Times New Roman', Times, serif;
        }
        .spkPrintToolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #111827;
            color: #fff;
        }
        .spkPrintToolbar button {
            appearance: none;
            border: 1px solid #4b5563;
            border-radius: 0.25rem;
            background: #fff;
            color: #111827;
            font-size: 0.875rem;
            font-family: system-ui, -apple-system, sans-serif;
            padding: 0.4rem 0.85rem;
            cursor: pointer;
        }
        .spkPrintToolbar button.primary {
            background: #0070f2;
            border-color: #0070f2;
            color: #fff;
        }
        .spkReceiptPage {
            width: 210mm;
            min-height: 297mm;
            margin: 1rem auto;
            padding: 12mm;
            background: #fff;
            box-shadow: 0 1px 4px rgb(0 0 0 / 18%);
        }
        .spkDocumentHeader {
            display: grid;
            grid-template-columns: 16% minmax(0, 1fr) 26%;
            align-items: stretch;
            width: 100%;
            font-size: 10pt;
            line-height: 1.15;
            border: 1px solid #000;
        }
        .spkDocumentHeaderLogo {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            border-right: 1px solid #000;
        }
        .spkDocumentHeaderLogoImg {
            display: block;
            width: 100%;
            max-width: 70px;
            max-height: 52px;
            object-fit: contain;
        }
        .spkDocumentHeaderCenter {
            display: flex;
            flex-direction: column;
            border-right: 1px solid #000;
        }
        .spkDocumentHeaderFormTitle {
            display: flex;
            flex: 1 1 auto;
            align-items: center;
            justify-content: center;
            padding: 4px 6px;
            text-align: center;
            font-size: 16pt;
            font-weight: 700;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #000;
        }
        .spkDocumentHeaderCompany {
            padding: 2px 6px;
            text-align: center;
            font-size: 9pt;
            font-weight: 700;
        }
        .spkDocumentHeaderMeta {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1px;
            padding: 4px 8px;
            font-size: 9pt;
            line-height: 1.2;
        }
        .spkReceiptInfo {
            margin: 8px 0 2px;
            font-size: 10pt;
            line-height: normal;
        }
        .spkChainReceiptQrFooter {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            margin-top: 12px;
            break-inside: avoid;
        }
        .spkChainReceiptQrBox {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            width: 96px;
        }
        .spkChainReceiptQr {
            display: block;
            width: 88px;
            height: 88px;
            overflow: hidden;
        }
        .spkChainReceiptQr svg {
            display: block;
            width: 100%;
            height: 100%;
        }
        .spkChainReceiptQrCaption {
            margin: 0;
            max-width: 96px;
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 7.5pt;
            font-weight: 600;
            line-height: 1.2;
            text-align: center;
        }
        .spkReceiptInfoTable {
            width: auto;
            border-collapse: collapse;
            border-spacing: 0;
            line-height: normal;
        }
        .spkReceiptInfoTable td {
            padding: 0;
            vertical-align: top;
            line-height: normal;
        }
        .spkReceiptInfoTable td:first-child { width: 72px; }
        .spkReceiptInfoTableRow--count td {
            padding-top: 4px;
        }
        .spkReceiptTable {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        .spkReceiptTable th,
        .spkReceiptTable td {
            padding: 4px 4px;
            border: 1px solid #000;
            text-align: left;
            vertical-align: top;
        }
        .spkReceiptTable th {
            background: #f3f4f6;
            font-weight: 700;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .spkReceiptColNo {
            width: 1%;
            white-space: nowrap;
            text-align: center !important;
        }
        .spkReceiptColCenter {
            text-align: center !important;
            white-space: nowrap;
        }
        .spkReceiptSignatures {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 16px;
            border: 1px solid #000;
            font-size: 10pt;
            break-inside: avoid;
        }
        .spkReceiptSignature {
            display: flex;
            flex-direction: column;
            text-align: center;
            border-right: 1px solid #000;
        }
        .spkReceiptSignature:last-child { border-right: none; }
        .spkReceiptSignatureTitle {
            padding: 4px 5px;
            border-bottom: 1px solid #000;
            background: #f3f4f6;
            font-weight: 700;
        }
        .spkReceiptSignatureSpace { height: 72px; }
        .spkReceiptSignatureLine { margin: 0 12px; padding: 4px 0; }
        .spkReceiptSignatureName { font-weight: 700; }
        .spkReceiptSignatureDate {
            padding: 4px 8px;
            border-top: 1px solid #000;
            text-align: left;
        }
        .spkReceiptFooter {
            margin-top: 16px;
            font-size: 8.5pt;
            color: #4b5563;
        }
        @page { size: A4; margin: 8mm; }
        @media print {
            html, body { background: #fff; }
            .spkPrintToolbar { display: none !important; }
            .spkReceiptPage {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
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
                <tr class="spkReceiptInfoTableRow--count">
                    <td colspan="2">Jumlah komponen: {{ $rowCount }}</td>
                </tr>
            </table>
        </div>

        <table class="spkReceiptTable">
            <thead>
                <tr>
                    <th class="spkReceiptColNo">No</th>
                    <th>No SPK</th>
                    <th>Kategori</th>
                    <th>Jenis Item Komponen</th>
                    <th>Warna</th>
                    <th class="spkReceiptColCenter">Ukuran</th>
                    <th class="spkReceiptColCenter">No. Unit</th>
                    <th class="spkReceiptColCenter">Berat</th>
                    <th>Customer</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="spkReceiptColNo">{{ $loop->iteration }}</td>
                        <td>{{ $row['spkNo'] }}</td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['item'] }}</td>
                        <td>{{ $row['color'] }}</td>
                        <td class="spkReceiptColCenter">{{ $row['componentSize'] ?? '-' }}</td>
                        <td class="spkReceiptColCenter">{{ $row['unitNo'] }}</td>
                        <td class="spkReceiptColCenter">
                            {{ ($row['weight'] ?? '-') !== '-' ? $row['weight'].' g' : '-' }}
                        </td>
                        <td>{{ ($row['customer'] ?? '') !== '' ? $row['customer'] : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

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
                    <div class="spkReceiptSignatureDate">
                        Tanggal: {{ $signature['date'] ?? $receiptDate }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="spkReceiptFooter">Dicetak oleh {{ $printedBy }} pada {{ $printedAt }}</div>

        @if (filled($qrUrl ?? ''))
            <footer class="spkChainReceiptQrFooter" aria-label="QR terima komponen">
                <div class="spkChainReceiptQrBox">
                    <div
                        id="spkComponentReceiptQr"
                        class="spkChainReceiptQr"
                        data-value="{{ $qrUrl }}"
                        role="img"
                        aria-label="QR code terima komponen {{ $receiptNo }}"
                    ></div>
                    <p class="spkChainReceiptQrCaption">
                        <i><small>*scan ketika menerima barang</small></i>
                    </p>
                </div>
            </footer>
        @endif
    </main>

    <script src="{{ url('/js/qrcode-generator.js') }}"></script>
    <script>
        (function () {
            var el = document.getElementById('spkComponentReceiptQr');
            if (!el || typeof qrcode !== 'function') {
                return;
            }

            var value = el.getAttribute('data-value') || '';
            if (!value) {
                return;
            }

            var qr = qrcode(0, 'M');
            qr.addData(value);
            qr.make();
            el.innerHTML = qr.createSvgTag({
                scalable: true,
                margin: 2,
            });
        })();
    </script>
</body>
</html>
