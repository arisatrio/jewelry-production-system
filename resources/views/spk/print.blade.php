<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Form SPK — Print' }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
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
            font: inherit;
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

        .spkPrintPage {
            display: flex;
            flex-direction: column;
            width: 210mm;
            min-height: 297mm;
            margin: 1rem auto;
            padding: 12mm;
            background: #fff;
            box-shadow: 0 1px 4px rgb(0 0 0 / 18%);
        }

        .spkPrintSheet {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            flex: 0 0 auto;
        }

        .spkPrintFlexSpacer {
            flex: 1 1 auto;
            min-height: 0;
        }

        .spkPrintSheet > thead {
            display: table-header-group;
        }

        .spkPrintSheet > tbody {
            display: table-row-group;
        }

        .spkPrintSheet > tfoot {
            display: table-footer-group;
        }

        .spkPrintSheet > thead > tr > td,
        .spkPrintSheet > tbody > tr > td,
        .spkPrintSheet > tfoot > tr > td {
            padding: 0;
            border: none;
            vertical-align: top;
        }

        .spkPrintSheetSpacer {
            height: 0;
            line-height: 0;
            font-size: 0;
            overflow: hidden;
        }

        .spkPrintFrame {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            width: 100%;
        }

        .spkPrintFrame--header {
            margin-bottom: 4px;
        }

        .spkPrintFrame--body {
            flex: 1 1 auto;
        }

        .spkPrintPage--priority {
            border: 5px solid #c00000;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .spkPrintPage--priority .spkPrintSheet {
            border: none;
        }

        .spkPrintPage--priority .spkPrintFrame {
            border: none;
            padding: 0;
        }

        .spkPrintPriorityPageBorder {
            display: none;
        }

        .spkPrintPriorityBanner {
            display: none;
        }

        .spkPrintPage--priority .spkPrintPriorityBanner--flow {
            display: block;
            width: max-content;
            max-width: calc(100% - 10px);
            margin: -12mm 0 8px -12mm;
            padding: 5px 14px;
            background: #c00000;
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            font-weight: 700;
            letter-spacing: 0.04em;
            line-height: 1.2;
            text-transform: uppercase;
            position: relative;
            z-index: 2;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .spkPrintPriorityBanner--fixed {
            display: none;
        }

        .spkPrintPage--priority .spkDocumentHeader {
            width: auto;
            margin: 0;
            border-top-width: 1px;
        }

        .spkPrintPage--priority .spkPrintFrame--header {
            padding-top: 0;
        }

        .spkPrintPage--priority .spkPrintBody {
            margin-top: 8px;
            padding: 0;
        }

        .spkDocumentHeader {
            display: grid;
            grid-template-columns: 16% minmax(0, 1fr) 26%;
            align-items: stretch;
            width: 100%;
            margin: 0;
            background: #fff;
            color: #000;
            font-size: 10pt;
            line-height: 1.15;
            border: 1px solid #000;
        }

        .spkDocumentHeaderLogo,
        .spkDocumentHeaderCenter,
        .spkDocumentHeaderMeta {
            min-width: 0;
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
            justify-content: stretch;
            gap: 0;
            padding: 0;
            border-right: 1px solid #000;
        }

        .spkDocumentHeaderFormTitle {
            display: flex;
            flex: 1 1 auto;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin: 0;
            padding: 4px 6px;
            color: #000;
            text-align: center;
            font-size: 16pt;
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
        }

        .spkDocumentHeaderCompany {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin: 0;
            padding: 2px 6px;
            color: #000;
            text-align: center;
            font-size: 9pt;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: 0.01em;
            border-bottom: none;
        }

        .spkDocumentHeaderMeta {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1px;
            padding: 4px 8px;
            font-size: 9pt;
            line-height: 1.2;
            text-align: left;
        }

        .spkDocumentHeaderMeta > div {
            margin: 0;
            padding: 0;
            border-bottom: none;
        }

        .spkPrintBody {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            margin-top: 4px;
            min-height: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 9pt;
            line-height: 1.35;
        }

        .spkPrintSection {
            margin: 0 0 6px;
        }

        .spkPrintSectionTitle {
            margin: 0 0 4px;
            font-size: 10.5pt;
            font-weight: 700;
            color: #111;
        }

        .spkPrintSectionHeading {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            margin: 0 0 6px;
        }

        .spkPrintSectionHeading .spkPrintSectionTitle {
            margin: 0;
        }

        .spkPrintSpkNo {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: flex-end;
            gap: 4px;
            margin: 0;
            text-align: right;
            font-size: 9pt;
            line-height: 1.2;
        }

        .spkPrintSpkNo span {
            color: #555;
            font-weight: 600;
            font-size: 8pt;
        }

        .spkPrintSpkNo strong {
            font-size: 12pt;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .spkPrintSpkNo strong:empty {
            display: inline-block;
            min-width: 28mm;
            min-height: 14pt;
        }

        .spkPrintMetaTable--info {
            table-layout: fixed;
            width: 100%;
        }

        .spkPrintMetaTable--info th,
        .spkPrintMetaTable--info td {
            padding: 1px 4px;
            font-size: 8pt;
            line-height: 1.2;
            vertical-align: middle;
        }

        .spkPrintMetaTable--info th {
            width: 28%;
        }

        .spkPrintMetaTable--info td:nth-child(2) {
            width: auto;
        }

        .spkPrintMetaTable--info .spkPrintQrCell {
            width: 120px;
            max-width: 120px;
            padding: 4px;
            overflow: hidden;
            vertical-align: middle;
            text-align: center;
            background: #fff;
        }

        .spkPrintSection--info .spkPrintSectionHeading {
            margin: 0 0 3px;
        }

        .spkPrintSection--info {
            margin: 0 0 4px;
        }

        .spkPrintQrBox {
            display: block;
            box-sizing: border-box;
            width: 100%;
            max-width: 110px;
            margin: 0 auto;
            padding: 0;
            background: #fff;
        }

        .spkPrintQr {
            display: block;
            width: 100%;
            max-width: 110px;
            aspect-ratio: 1 / 1;
            margin: 0 auto;
            overflow: hidden;
        }

        .spkPrintQr svg {
            display: block;
            width: 100%;
            height: 100%;
            margin: 0;
        }

        .spkPrintQrPlaceholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 110px;
            aspect-ratio: 1 / 1;
            margin: 0 auto;
            border: 1px dashed #ccc;
            color: #999;
            font-size: 9pt;
            font-weight: 600;
        }

        .spkPrintQrPlaceholder--hint {
            padding: 6px;
            font-weight: 400;
            text-align: center;
        }

        .spkPrintHint {
            display: block;
            color: #6b7280;
            font-size: 7pt;
            font-style: italic;
            font-weight: 400;
            line-height: 1.3;
        }

        .spkPrintImagePlaceholder .spkPrintHint {
            max-width: 70%;
            font-size: 8pt;
            text-align: center;
        }

        .spkPrintSubTitle {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            margin: 10px 0 4px;
            font-size: 9.5pt;
            font-weight: 700;
        }

        .spkPrintSubTitle span {
            font-size: 8.5pt;
            font-weight: 400;
            color: #555;
        }

        .spkPrintStonesSection {
            width: 100%;
            max-width: 100%;
        }

        .spkPrintStonesSection .spkPrintSectionTitle {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
        }

        .spkPrintStonesSection .spkPrintSectionTitle span {
            font-size: 8.5pt;
            font-weight: 400;
            color: #555;
        }

        .spkPrintDetailGrid {
            display: grid;
            grid-template-columns: minmax(0, 8fr) minmax(0, 4fr);
            gap: 10px;
            align-items: stretch;
            width: 100%;
            height: 100mm;
        }

        .spkPrintImageCol {
            display: flex;
            flex-direction: column;
            min-width: 0;
            height: 100mm;
            max-height: 100mm;
        }

        .spkPrintFieldsCol {
            display: flex;
            flex-direction: column;
            min-width: 0;
            height: 100mm;
            max-height: 100mm;
            overflow: hidden;
        }

        .spkPrintImageFrame {
            position: relative;
            display: flex;
            flex: 1 1 auto;
            align-items: stretch;
            width: 100%;
            height: 100mm;
            max-height: 100mm;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #fff;
            line-height: 0;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }

        .spkPrintImage {
            display: block;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            object-fit: contain;
            object-position: center center;
            border: none;
            background: transparent;
            vertical-align: top;
        }

        .spkPrintImagePlaceholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            color: #666;
            font-size: 9pt;
            font-weight: 600;
            background: #f7f7f7;
        }

        .spkPrintImageSize {
            flex: 0 0 auto;
            margin: 0;
            padding: 0;
            text-align: center;
            font-size: 6pt;
            font-weight: 400;
            color: #9ca3af;
            line-height: 1.2;
            background: transparent;
        }

        .spkPrintNotesSection {
            width: 100%;
            margin-bottom: 6px;
            text-align: left;
        }

        .spkPrintNotes {
            width: 100%;
            min-height: 40px;
            padding: 2px 6px;
            border: 1px solid #ccc;
            white-space: pre-line;
            word-break: break-word;
            font-size: 8pt;
            line-height: 1.2;
            text-align: left;
            text-indent: 0;
        }

        .spkPrintNotes .spkPrintHint {
            display: inline;
            text-align: left;
        }

        .spkPrintBottom {
            width: 100%;
            flex: 0 0 auto;
            padding-top: 4px;
            padding-bottom: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 9pt;
            line-height: 1.3;
        }

        .spkPrintMetaTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .spkPrintMetaTable th,
        .spkPrintMetaTable td {
            border: 1px solid #ccc;
            padding: 2px 4px;
            vertical-align: top;
            text-align: left;
            font-size: 8.5pt;
        }

        .spkPrintMetaTable th {
            width: 38%;
            background: #f3f4f6;
            font-weight: 700;
            color: #333;
        }

        .spkPrintMetaTable--item {
            flex: 1 1 auto;
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .spkPrintMetaTable--item td {
            width: 100%;
            padding: 6px 6px;
            background: #fff;
            vertical-align: top;
            box-sizing: border-box;
        }

        .spkPrintFieldStack {
            display: flex;
            flex-direction: column;
            gap: 0;
            min-width: 0;
        }

        .spkPrintFieldLabel,
        .spkPrintUkuranLabel {
            color: #555;
            font-size: 7pt;
            font-weight: 400;
            line-height: 1.1;
            margin-bottom: 6px;
            word-break: break-word;
        }

        .spkPrintFieldValue,
        .spkPrintFieldSku,
        .spkPrintUkuranValue {
            color: #111;
            font-size: 8.5pt;
            font-weight: 400;
            line-height: 1.15;
            word-break: break-word;
        }

        .spkPrintFieldValue:empty {
            display: block;
            min-height: 0;
        }

        .spkPrintUkuran {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            gap: 0;
            margin-top: 0;
        }

        .spkPrintUkuranItem {
            display: flex;
            flex: 1 1 0;
            flex-direction: column;
            gap: 0;
            min-width: 0;
        }

        .spkPrintUkuranSep {
            display: none;
        }

        .spkPrintUkuranItem .spkPrintHint {
            display: inline;
            white-space: normal;
        }

        .spkPrintStoneTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .spkPrintStoneTable th,
        .spkPrintStoneTable td {
            border: 1px solid #ccc;
            padding: 2px 3px;
            text-align: center;
            vertical-align: middle;
            font-size: 8pt;
        }

        .spkPrintStoneTable--sm th,
        .spkPrintStoneTable--sm td {
            padding: 1px 3px;
        }

        .spkPrintStoneTable thead th {
            background: #f3f4f6;
            font-weight: 700;
        }

        .spkPrintStoneTable .spkPrintStoneTableTotal td {
            background: #f3f4f6;
            font-weight: 700;
        }

        .spkPrintApproval {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: stretch;
            margin-top: 0;
            border: 1px solid #000;
            background: #fff;
        }

        .spkPrintApprovalCol {
            min-width: 0;
            padding: 4px 6px;
        }

        .spkPrintApprovalCol + .spkPrintApprovalCol {
            border-left: 1px solid #000;
        }

        .spkPrintApprovalTitle {
            margin: 0 0 4px;
            font-size: 9pt;
            font-weight: 700;
        }

        .spkPrintApprovalMeta {
            display: flex;
            flex-direction: column;
            gap: 1px;
            font-size: 8.5pt;
        }

        .spkPrintApprovalMeta > div {
            display: grid;
            grid-template-columns: 3.5rem minmax(0, 1fr);
            gap: 4px;
            align-items: baseline;
        }

        .spkPrintApprovalMeta span {
            color: #555;
        }

        @media print {
            html,
            body {
                background: #fff;
            }

            .spkPrintToolbar {
                display: none !important;
            }

            .spkPrintPage {
                display: flex;
                flex-direction: column;
                width: auto;
                min-height: 281mm;
                height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .spkPrintFlexSpacer {
                flex: 1 1 auto;
                min-height: 0;
            }

            .spkPrintPage--priority {
                border: none !important;
                padding: 0;
            }

            .spkPrintBottom {
                flex: 0 0 auto;
                padding: 4px 8px 0;
                break-inside: avoid;
                page-break-inside: avoid;
                text-align: left;
            }

            .spkPrintNotes,
            .spkPrintNotesSection {
                text-align: left;
            }

            .spkPrintPage--priority .spkPrintBottom {
                padding-bottom: 6px;
            }

            .spkPrintSheet > thead {
                display: table-header-group;
            }

            .spkPrintSheet > tbody {
                display: table-row-group;
            }

            .spkPrintSheet > tfoot {
                display: table-footer-group;
            }

            .spkPrintSheet > thead > tr > td {
                padding: 4px 8px 6px;
            }

            .spkPrintSheet > tbody > tr > td {
                padding: 0 8px;
            }

            .spkPrintSheet > tfoot > tr > td {
                padding: 0 8px;
                height: 4px;
                line-height: 4px;
                font-size: 0;
                border: none;
                vertical-align: top;
            }

            .spkPrintSheetSpacer {
                height: 4px;
            }

            .spkPrintPage--priority .spkPrintSheet > thead > tr > td {
                padding-top: 36px;
            }

            .spkPrintPage--priority .spkPrintSheet {
                border: none !important;
            }

            .spkPrintPage--priority .spkPrintFrame {
                border: none !important;
            }

            .spkPrintPriorityPageBorder {
                display: block !important;
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                border: 5px solid #c00000;
                pointer-events: none;
                z-index: 10000;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .spkPrintPriorityBanner--flow {
                display: none !important;
            }

            .spkPrintPriorityBanner--fixed {
                display: block !important;
                position: fixed;
                top: 0;
                left: 0;
                z-index: 10001;
                width: max-content;
                max-width: calc(100% - 10px);
                margin: 0;
                padding: 6px 14px;
                background: #c00000 !important;
                color: #fff !important;
                border: none !important;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 11pt;
                font-weight: 700;
                letter-spacing: 0.04em;
                line-height: 1.2;
                text-transform: uppercase;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .spkPrintFrame--header {
                margin-bottom: 0;
                padding-top: 0;
            }

            .spkPrintPage--priority .spkPrintFrame--header {
                padding-top: 0;
                margin-bottom: 0;
            }

            .spkPrintPage--priority .spkDocumentHeader {
                margin-top: 0;
            }

            .spkPrintStoneTable thead {
                display: table-header-group;
            }

            .spkPrintStoneTable tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .spkPrintStoneTable .spkPrintStoneTableTotal {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            @page {
                size: A4;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>
    <div class="spkPrintToolbar">
        <button type="button" onclick="window.close()">Tutup</button>
        <button type="button" class="primary" onclick="window.print()">Cetak / PDF</button>
    </div>

    @php
        $priorityRaw = (string) ($document['info']['priority'] ?? '');
        $priorityValue = strtoupper(trim(in_array($priorityRaw, ['-', '—'], true) ? '' : $priorityRaw));
        $isPriority = $priorityValue === 'YES';
    @endphp

    @if ($isPriority)
        <div class="spkPrintPriorityPageBorder" aria-hidden="true"></div>
        <div class="spkPrintPriorityBanner spkPrintPriorityBanner--fixed">
            PRIORITAS PRODUKSI
        </div>
    @endif

    <main class="spkPrintPage{{ $isPriority ? ' spkPrintPage--priority' : '' }}">
        <table class="spkPrintSheet">
            <thead>
                <tr>
                    <td>
                        @if ($isPriority)
                            <div class="spkPrintPriorityBanner spkPrintPriorityBanner--flow">
                                PRIORITAS PRODUKSI
                            </div>
                        @endif

                        <div class="spkPrintFrame spkPrintFrame--header">
                            @include('spk.partials.document-header', ['header' => $header])
                        </div>
                    </td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="spkPrintFrame spkPrintFrame--body">
                            <section class="spkPrintBody">
                                @include('spk.partials.print-body', ['document' => $document])
                            </section>
                        </div>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td class="spkPrintSheetSpacer" aria-hidden="true">&nbsp;</td>
                </tr>
            </tfoot>
        </table>

        <div class="spkPrintFlexSpacer" aria-hidden="true"></div>

        @include('spk.partials.print-bottom', ['document' => $document])
    </main>

    <script src="{{ url('/js/qrcode-generator.js') }}"></script>
    <script>
        (function () {
            var el = document.getElementById('spkPrintQr');
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
