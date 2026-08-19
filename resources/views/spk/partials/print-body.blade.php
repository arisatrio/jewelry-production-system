@php
    /** @var array $document */
    $blankTemplate = (bool) ($blankTemplate ?? false);
    $info = $document['info'] ?? [];
    $item = $document['item'] ?? [];
    $stones = $document['stones'] ?? [];
    $detailUrl = trim((string) ($document['detailUrl'] ?? ''));
    $empty = $blankTemplate ? '' : '-';
    $totalButir = 0;
    $totalCarat = 0.0;
    foreach ($stones as $stone) {
        $totalButir += (int) preg_replace('/[^\d-]/', '', (string) ($stone['pcs'] ?? '0'));
        $totalCarat += (float) str_replace(',', '.', (string) ($stone['totalCarat'] ?? '0'));
    }

    $spkType = ($info['spkType'] ?? $empty) !== '' ? (string) ($info['spkType'] ?? $empty) : $empty;

    $requestOrderNo = ($info['requestOrderNo'] ?? $empty) !== '' ? (string) ($info['requestOrderNo'] ?? $empty) : $empty;
    $customerName = ($info['customerName'] ?? $empty) !== '' ? (string) ($info['customerName'] ?? $empty) : $empty;
    $pesananLabel = $requestOrderNo === $empty && $customerName === $empty
        ? $empty
        : $requestOrderNo.' ('.$customerName.')';

    $showRefSpk = $blankTemplate || (($info['refSpkNo'] ?? '-') !== '-');
@endphp

<section class="spkPrintSection spkPrintSection--info">
    <div class="spkPrintSectionHeading">
        <h2 class="spkPrintSectionTitle">Informasi Produksi</h2>
        <div class="spkPrintSpkNo">
            <span>No. SPK</span>
            @if ($blankTemplate)
                <strong class="spkPrintHint">Nomor SPK otomatis, format tahun/PRD/urut (contoh: 2026/PRD/01601)</strong>
            @else
                <strong>{{ $info['spkNo'] ?? $empty }}</strong>
            @endif
        </div>
    </div>
    @php
        $infoRowCount = 5 + ($showRefSpk ? 1 : 0);
    @endphp
    <table class="spkPrintMetaTable spkPrintMetaTable--info">
        <tbody>
            <tr>
                <th>Tipe Produksi</th>
                <td>
                    @if ($blankTemplate)
                        <span class="spkPrintHint">Jenis SPK: Stock, Pesanan, Exchange, Refund, atau Reparasi</span>
                    @else
                        {{ $spkType }}
                    @endif
                </td>
                <td class="spkPrintQrCell" rowspan="{{ $infoRowCount }}">
                    <div class="spkPrintQrBox">
                        @if ($detailUrl !== '')
                            <div
                                id="spkPrintQr"
                                class="spkPrintQr"
                                data-value="{{ $detailUrl }}"
                                role="img"
                                aria-label="QR code menuju detail SPK {{ $info['spkNo'] ?? '' }}"
                            ></div>
                        @elseif ($blankTemplate)
                            <div class="spkPrintQrPlaceholder spkPrintQrPlaceholder--hint">
                                <span class="spkPrintHint">Berisi QR code yang jika di-scan akan menampilkan detail SPK di aplikasi</span>
                            </div>
                        @else
                            <div class="spkPrintQrPlaceholder">QR</div>
                        @endif
                    </div>
                </td>
            </tr>
            <tr>
                <th>Pesanan</th>
                <td>
                    @if ($blankTemplate)
                        <span class="spkPrintHint">Nomor request order dan nama customer, contoh: DP-0009303 (Vera). Kosong jika tipe Stock</span>
                    @else
                        {{ $pesananLabel }}
                    @endif
                </td>
            </tr>
            @if ($showRefSpk)
                <tr>
                    <th>SPK Referensi</th>
                    <td>
                        @if ($blankTemplate)
                            <span class="spkPrintHint">Nomor SPK sumber untuk Exchange, Refund, atau Reparasi</span>
                        @else
                            {{ $info['refSpkNo'] ?? '-' }}
                        @endif
                    </td>
                </tr>
            @endif
            <tr>
                <th>Tanggal Permintaan</th>
                <td>
                    @if ($blankTemplate)
                        <span class="spkPrintHint">Tanggal permintaan produksi (dd/mm/yyyy)</span>
                    @else
                        {{ $info['orderDate'] ?? $empty }}
                    @endif
                </td>
            </tr>
            <tr>
                <th>Tanggal Estimasi Selesai</th>
                <td>
                    @if ($blankTemplate)
                        <span class="spkPrintHint">Tanggal estimasi selesai (dd/mm/yyyy)</span>
                    @else
                        {{ $info['estimatedDelivery'] ?? $info['workEstimated'] ?? $empty }}
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</section>

<section class="spkPrintSection">
    <h2 class="spkPrintSectionTitle">Detail Item</h2>
    <div class="spkPrintDetailGrid">
        <div class="spkPrintImageCol">
            <div class="spkPrintImageFrame">
                @if(! empty($item['imageUrl']))
                    <img src="{{ $item['imageUrl'] }}" alt="Gambar item" class="spkPrintImage">
                @else
                    <div class="spkPrintImagePlaceholder">
                        @if ($blankTemplate)
                            <span class="spkPrintHint">Gambar desain item 1:1 dari SKU atau unggahan SPK</span>
                        @else
                            Gambar item
                        @endif
                    </div>
                @endif
            </div>
            {{-- <div class="spkPrintImageSize">Ukuran: 6 × 10,6 cm (tinggi × lebar)</div> --}}
        </div>

        <div class="spkPrintFieldsCol">
            <table class="spkPrintMetaTable spkPrintMetaTable--item">
                <tbody>
                    <tr>
                        <td>
                            <div class="spkPrintFieldStack">
                                <span class="spkPrintFieldLabel">Tipe Item | SKU</span>
                                @php
                                    $typeCode = trim((string) ($item['typeCode'] ?? ''));
                                    $productItemName = trim((string) ($item['productItemName'] ?? ''));
                                    $skuCode = trim((string) ($item['skuCode'] ?? ''));
                                    $typeProductLine = collect([$typeCode, $productItemName])
                                        ->filter(fn (string $part): bool => $part !== '' && $part !== '-')
                                        ->implode(' | ');
                                    $hasStructuredType = $typeProductLine !== '' || ($skuCode !== '' && $skuCode !== '-');
                                @endphp
                                @if ($hasStructuredType)
                                    @if ($typeProductLine !== '')
                                        <span class="spkPrintFieldValue">{{ $typeProductLine }}</span>
                                    @endif
                                    @if ($skuCode !== '' && $skuCode !== '-')
                                        <span class="spkPrintFieldSku">{{ $skuCode }}</span>
                                    @endif
                                @else
                                    @if ($blankTemplate)
                                        <span class="spkPrintHint">Baris 1: kode tipe | nama item. Baris 2: kode SKU dengan font lebih kecil</span>
                                    @else
                                        <span class="spkPrintFieldValue">{{ $item['typeVariant'] ?? '-' }}</span>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="spkPrintFieldStack">
                                <span class="spkPrintFieldLabel">Deskripsi Item</span>
                                <span class="spkPrintFieldValue">
                                    @if ($blankTemplate)
                                        <span class="spkPrintHint">Deskripsi item dari komponen SKU, contoh: Rose Gold Bangle Netizen Asimetris Heart Diamond Dossier 0.3</span>
                                    @else
                                        @php
                                            $itemDescription = trim((string) ($item['description'] ?? ''));
                                        @endphp
                                        {{ $itemDescription !== '' && $itemDescription !== '-' ? $itemDescription : '-' }}
                                    @endif
                                </span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="spkPrintFieldStack">
                                <span class="spkPrintFieldLabel">Status Order</span>
                                <span class="spkPrintFieldValue">
                                    @if ($blankTemplate)
                                        <span class="spkPrintHint">Otomatis New Order jika SKU belum pernah dibuat SPK, atau Repeat Order plus urutan (contoh: Repeat Order 003)</span>
                                    @else
                                        {{ $item['statusOrderLabel'] ?? '-' }}
                                    @endif
                                </span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="spkPrintFieldStack">
                                <span class="spkPrintFieldLabel">Qty</span>
                                <span class="spkPrintFieldValue">
                                    @if ($blankTemplate)
                                        <span class="spkPrintHint">Jumlah item beserta satuan (Pcs atau Pasang)</span>
                                    @else
                                        {{ $item['qty'] ?? '-' }}
                                    @endif
                                </span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="spkPrintFieldStack">
                                <span class="spkPrintFieldLabel">Ukuran</span>
                                <div class="spkPrintUkuran">
                                    <div class="spkPrintUkuranItem">
                                        <span class="spkPrintUkuranLabel">Diameter (mm)</span>
                                        <strong class="spkPrintUkuranValue">
                                            @if ($blankTemplate)
                                                <span class="spkPrintHint">Diameter mm</span>
                                            @else
                                                {{ $item['diameter'] ?? '-' }}
                                            @endif
                                        </strong>
                                    </div>
                                    <div class="spkPrintUkuranItem">
                                        <span class="spkPrintUkuranLabel">Dimensi (mm)</span>
                                        <strong class="spkPrintUkuranValue">
                                            @if ($blankTemplate)
                                                <span class="spkPrintHint">PxL mm</span>
                                            @else
                                                {{ $item['dimensi'] ?? '-' }}
                                            @endif
                                        </strong>
                                    </div>
                                    <div class="spkPrintUkuranItem">
                                        <span class="spkPrintUkuranLabel">Ring Size</span>
                                        <strong class="spkPrintUkuranValue">
                                            @if ($blankTemplate)
                                                <span class="spkPrintHint">Size 12 HK</span>
                                            @else
                                                {{ $item['ringSize'] ?? '-' }}
                                            @endif
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="spkPrintUkuran">
                                <div class="spkPrintUkuranItem">
                                    <span class="spkPrintUkuranLabel">Berat Emas (g)</span>
                                    <strong class="spkPrintUkuranValue">
                                        @if ($blankTemplate)
                                            <span class="spkPrintHint">Berat dalam gram</span>
                                        @else
                                            {{ $item['goldWeight'] ?? '-' }}
                                        @endif
                                    </strong>
                                </div>
                                <div class="spkPrintUkuranItem">
                                    <span class="spkPrintUkuranLabel">Warna Emas</span>
                                    <strong class="spkPrintUkuranValue">
                                        @if ($blankTemplate)
                                            <span class="spkPrintHint">White / Yellow / Rose / Two Tones</span>
                                        @else
                                            {{ $item['goldColor'] ?? '-' }}
                                        @endif
                                    </strong>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="spkPrintFieldStack">
                                <span class="spkPrintFieldLabel">File JewelCAD 3D</span>
                                <span class="spkPrintFieldValue">
                                    @if ($blankTemplate)
                                        <span class="spkPrintHint">Nama atau kode file JewelCAD 3D</span>
                                    @else
                                        {{ $item['jwcad3d'] ?? '-' }}
                                    @endif
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="spkPrintSection spkPrintStonesSection">
    <h2 class="spkPrintSectionTitle">
        Daftar Batu
        @unless ($blankTemplate)
            <span>{{ count($stones) }} item</span>
        @endunless
    </h2>
    <table class="spkPrintStoneTable spkPrintStoneTable--sm">
        <thead>
            <tr>
                <th>Posisi</th>
                <th>Bentuk</th>
                <th>Diameter / Dimensi PxL (mm)</th>
                <th>Carat per Butir (pcs)</th>
                <th>Jumlah Butir (pcs)</th>
                <th>Total Carat</th>
            </tr>
        </thead>
        <tbody>
            @if ($blankTemplate)
                @for ($i = 0; $i < 3; $i++)
                    <tr>
                        @if ($i === 0)
                            <td><span class="spkPrintHint">Posisi batu pada item</span></td>
                            <td><span class="spkPrintHint">Nama bentuk batu, tanpa kode</span></td>
                            <td><span class="spkPrintHint">Diameter atau PxL dalam mm</span></td>
                            <td><span class="spkPrintHint">Carat per 1 butir</span></td>
                            <td><span class="spkPrintHint">Jumlah butir</span></td>
                            <td><span class="spkPrintHint">Carat x jumlah butir</span></td>
                        @else
                            <td>&nbsp;</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        @endif
                    </tr>
                @endfor
                <tr class="spkPrintStoneTableTotal">
                    <td colspan="4">Total</td>
                    <td><span class="spkPrintHint">Total butir</span></td>
                    <td><span class="spkPrintHint">Total carat</span></td>
                </tr>
            @else
                @forelse ($stones as $stone)
                    <tr>
                        <td>{{ $stone['positionName'] ?? '-' }}</td>
                        <td>{{ $stone['shapeName'] ?? '-' }}</td>
                        <td>{{ $stone['size'] ?? '-' }}</td>
                        <td>{{ $stone['caratPerPcs'] ?? '-' }}</td>
                        <td>{{ $stone['pcs'] ?? '-' }}</td>
                        <td>{{ $stone['totalCarat'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Tidak ada batu pada varian ini.</td>
                    </tr>
                @endforelse
                @if (count($stones) > 0)
                    <tr class="spkPrintStoneTableTotal">
                        <td colspan="4">Total</td>
                        <td>{{ $totalButir }}</td>
                        <td>{{ number_format($totalCarat, 3, ',', '.') }}</td>
                    </tr>
                @endif
            @endif
        </tbody>
    </table>
</section>
