@php
    /** @var list<array<string, string>> $chains */
    /** @var list<array<string, string>> $components */
    $chains = is_array($chains ?? null) ? $chains : [];
    $components = is_array($components ?? null) ? $components : [];
    $blankTemplate = (bool) ($blankTemplate ?? false);
    $empty = $blankTemplate ? '' : '-';
@endphp

<section class="spkPrintSection spkPrintAccessoryPairSection">
    <div class="spkPrintAccessoryPairRow">
        <div class="spkPrintAccessoryCol">
            <h2 class="spkPrintSectionTitle">
                Rantai
                @unless ($blankTemplate)
                    <span>{{ count($chains) }} baris</span>
                @endunless
            </h2>
            <table class="spkPrintStoneTable spkPrintStoneTable--sm spkPrintAccessoryTable">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Jenis Item Chain</th>
                        <th>Warna</th>
                        <th>Ukuran (cm)</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($blankTemplate)
                        @for ($i = 0; $i < 2; $i++)
                            <tr>
                                @if ($i === 0)
                                    <td><span class="spkPrintHint">Bracelet / Pendant Chain</span></td>
                                    <td><span class="spkPrintHint">Jenis item chain</span></td>
                                    <td><span class="spkPrintHint">Warna emas</span></td>
                                    <td><span class="spkPrintHint">Ukuran (cm)</span></td>
                                @else
                                    <td>&nbsp;</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                @endif
                            </tr>
                        @endfor
                    @else
                        @forelse ($chains as $chain)
                            <tr>
                                <td>{{ $chain['category'] ?? $empty }}</td>
                                <td>{{ $chain['item'] ?? $empty }}</td>
                                <td>{{ $chain['color'] ?? $empty }}</td>
                                <td>{{ $chain['size'] ?? $empty }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">Tidak ada rantai pada SPK ini.</td>
                            </tr>
                        @endforelse
                    @endif
                </tbody>
            </table>
        </div>

        <div class="spkPrintAccessoryCol">
            <h2 class="spkPrintSectionTitle">
                Komponen
                @unless ($blankTemplate)
                    <span>{{ count($components) }} baris</span>
                @endunless
            </h2>
            <table class="spkPrintStoneTable spkPrintStoneTable--sm spkPrintAccessoryTable">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Jenis Item Komponen</th>
                        <th>Warna</th>
                        <th>Ukuran (cm)</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($blankTemplate)
                        @for ($i = 0; $i < 2; $i++)
                            <tr>
                                @if ($i === 0)
                                    <td><span class="spkPrintHint">Komponen / Stopper</span></td>
                                    <td><span class="spkPrintHint">Jenis item komponen</span></td>
                                    <td><span class="spkPrintHint">Warna emas</span></td>
                                    <td><span class="spkPrintHint">Ukuran (cm)</span></td>
                                @else
                                    <td>&nbsp;</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                @endif
                            </tr>
                        @endfor
                    @else
                        @forelse ($components as $component)
                            <tr>
                                <td>{{ $component['category'] ?? $empty }}</td>
                                <td>{{ $component['item'] ?? $empty }}</td>
                                <td>{{ $component['color'] ?? $empty }}</td>
                                <td>{{ $component['size'] ?? $empty }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">Tidak ada komponen pada SPK ini.</td>
                            </tr>
                        @endforelse
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</section>
