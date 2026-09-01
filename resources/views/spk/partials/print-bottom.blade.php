@php
    /** @var array $document */
    $blankTemplate = (bool) ($blankTemplate ?? false);
    $notes = $blankTemplate ? '' : trim((string) ($document['notes'] ?? ''));
    $approval = $document['approval'] ?? [];
@endphp

<div class="spkPrintBottom">
    <section class="spkPrintSection spkPrintCorFields" aria-label="Form cor">
        <table class="spkPrintMetaTable spkPrintMetaTable--cor">
            <tbody>
                <tr>
                    <th>Tanggal Cor</th>
                    <td>&nbsp;</td>
                    <th>No Form Cor</th>
                    <td>&nbsp;</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="spkPrintSection spkPrintNotesSection">
        <h2 class="spkPrintSectionTitle">Catatan</h2>
        <div class="spkPrintNotes">@if ($blankTemplate)<span class="spkPrintHint">Catatan produksi tambahan (opsional)</span>@else{{ $notes !== '' && $notes !== '-' ? $notes : '-' }}@endif</div>
    </section>

    <footer class="spkPrintApproval" aria-label="Persetujuan">
        @foreach ($approval as $column)
            <div class="spkPrintApprovalCol">
                <div class="spkPrintApprovalTitle">{{ $column['title'] ?? '-' }}</div>
                <div class="spkPrintApprovalMeta">
                    <div><span>Nama</span><strong>{{ $blankTemplate ? '' : ($column['name'] ?? '-') }}</strong></div>
                    <div><span>Tanggal</span><strong>{{ $blankTemplate ? '' : ($column['date'] ?? '-') }}</strong></div>
                    @if ($blankTemplate)
                        <span class="spkPrintHint">Nama dan tanggal petugas yang setujui kolom ini di aplikasi</span>
                    @endif
                </div>
            </div>
        @endforeach
    </footer>
</div>
