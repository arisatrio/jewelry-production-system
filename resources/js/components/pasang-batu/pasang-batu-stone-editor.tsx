import { Button } from '@ui5/webcomponents-react/Button';
import { Text } from '@ui5/webcomponents-react/Text';
import {
    SpkDiamondDetailCertificateLink,
    SpkDiamondDetailStoneLabelLink,
    type SpkDiamondDetailStoneRow,
} from '@/components/spk/spk-stone-allocated-display-cells';
import { formatDecimal3Id } from '@/components/spk/spk-stone-decimal-format';

export type PasangBatuStoneOption = {
    value: string;
    label: string;
    stock?: string;
    description?: string;
};

export type PasangBatuDiamondOption = PasangBatuStoneOption & {
    code: string | null;
    diamondType: string | null;
    shape: string | null;
    certificate: string | null;
    crt: string | null;
};

export type SettingStoneLine = {
    key: string;
    stone_id: string;
    pcs: string;
    crt: string;
    notes: string;
};

export type DiamondLine = {
    key: string;
    batch_key: string;
    diamond_id: string;
};

export type PasangBatuMountedDisposition = 'pending' | 'terpasang' | 'return';

export type MountedStoneLine = {
    key: string;
    batch_key: string;
    diamond_code: string;
    shape_id: string;
    pcs: string;
    crt: string;
    size: string;
    disposition: PasangBatuMountedDisposition;
};

type PasangBatuStoneEditorProps = {
    stoneOptions: PasangBatuStoneOption[];
    shapeOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    settingStones: SettingStoneLine[];
    returnStones: SettingStoneLine[];
    diamonds: DiamondLine[];
    mountedStones: MountedStoneLine[];
    errors: Record<string, string>;
    onSettingChange: (lines: SettingStoneLine[]) => void;
    onReturnChange: (lines: SettingStoneLine[]) => void;
    onDiamondsChange: (lines: DiamondLine[]) => void;
    onMountedChange: (lines: MountedStoneLine[]) => void;
};

export function newPasangBatuLineKey(prefix: string): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

function emptyDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const text = String(value).trim();

    return text !== '' ? text : '—';
}

function parseQuantity(value: string): number | null {
    const trimmed = value.trim().replace(',', '.');

    if (trimmed === '') {
        return null;
    }

    const parsed = Number.parseFloat(trimmed);

    return Number.isFinite(parsed) ? parsed : null;
}

function crtPerPcsFrom(pcs: string, crt: string): string | null {
    const pcsValue = parseQuantity(pcs);
    const crtValue = parseQuantity(crt);

    if (pcsValue === null || pcsValue <= 0 || crtValue === null) {
        return null;
    }

    return (crtValue / pcsValue).toFixed(3);
}

function formatTotalCarat(value: number): string {
    return value.toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

function resolveStoneLabel(
    stoneOptions: PasangBatuStoneOption[],
    stoneId: string,
): string {
    if (stoneId.trim() === '') {
        return '—';
    }

    return (
        stoneOptions.find((option) => option.value === stoneId)?.label ??
        `Batu #${stoneId}`
    );
}

function resolveShapeLabel(
    shapeOptions: PasangBatuStoneOption[],
    shapeId: string,
    fallback?: string,
): string {
    if (shapeId.trim() !== '') {
        return (
            shapeOptions.find((option) => option.value === shapeId)?.label ??
            fallback ??
            '—'
        );
    }

    return fallback?.trim() !== '' ? (fallback ?? '—') : '—';
}

function resolveDiamondMeta(
    diamondOptions: PasangBatuDiamondOption[],
    diamondId: string,
): PasangBatuDiamondOption | null {
    if (diamondId.trim() === '') {
        return null;
    }

    return diamondOptions.find((option) => option.value === diamondId) ?? null;
}

type MicroBatchRow = {
    kind: 'micro';
    line: SettingStoneLine;
    disposition: 'terpasang' | 'return';
};

type DossierBatchRow = {
    kind: 'dossier';
    diamond: DiamondLine;
    mounted: MountedStoneLine;
};

type BatchRow = MicroBatchRow | DossierBatchRow;

function buildBatchRows(
    settingStones: SettingStoneLine[],
    returnStones: SettingStoneLine[],
    diamonds: DiamondLine[],
    mountedStones: MountedStoneLine[],
): BatchRow[] {
    const microRows: MicroBatchRow[] = [
        ...settingStones.map(
            (line): MicroBatchRow => ({
                kind: 'micro',
                line,
                disposition: 'terpasang',
            }),
        ),
        ...returnStones.map(
            (line): MicroBatchRow => ({
                kind: 'micro',
                line,
                disposition: 'return',
            }),
        ),
    ];

    const mountedByBatch = new Map(
        mountedStones.map((line) => [line.batch_key, line]),
    );

    const dossierRows: DossierBatchRow[] = diamonds
        .map((diamond) => {
            const mounted = mountedByBatch.get(diamond.batch_key);

            if (!mounted) {
                return null;
            }

            return {
                kind: 'dossier',
                diamond,
                mounted,
            };
        })
        .filter((row): row is DossierBatchRow => row !== null);

    return [...microRows, ...dossierRows];
}

function batchRowTotals(rows: BatchRow[]): { pcs: number; carat: number } {
    let pcs = 0;
    let carat = 0;

    for (const row of rows) {
        if (row.kind === 'micro') {
            const linePcs = parseQuantity(row.line.pcs);
            const lineCrt = parseQuantity(row.line.crt);

            if (linePcs !== null && linePcs > 0) {
                pcs += linePcs;
            }

            if (lineCrt !== null && lineCrt > 0) {
                carat += lineCrt;
            }

            continue;
        }

        if (row.mounted.disposition !== 'terpasang') {
            continue;
        }

        const pcsText =
            row.mounted.pcs.trim() !== '' ? row.mounted.pcs.trim() : '1';
        const crtText = row.mounted.crt.trim();
        const linePcs = parseQuantity(pcsText);
        const lineCrt = parseQuantity(crtText);

        if (linePcs !== null && linePcs > 0) {
            pcs += linePcs;
        }

        if (lineCrt !== null && lineCrt > 0) {
            carat += lineCrt;
        }
    }

    return { pcs, carat };
}

function toDossierDisplayRow(
    row: DossierBatchRow,
    diamondOptions: PasangBatuDiamondOption[],
    shapeOptions: PasangBatuStoneOption[],
): SpkDiamondDetailStoneRow {
    const meta = resolveDiamondMeta(diamondOptions, row.diamond.diamond_id);
    const shape =
        meta?.shape ??
        resolveShapeLabel(shapeOptions, row.mounted.shape_id, undefined);
    const pcs =
        row.mounted.pcs.trim() !== '' ? row.mounted.pcs.trim() : '1';
    const crt =
        row.mounted.crt.trim() !== ''
            ? row.mounted.crt.trim()
            : (meta?.crt?.trim() ?? '');
    const certificate =
        meta?.certificate?.trim() ||
        row.mounted.diamond_code.trim() ||
        null;

    return {
        source: 'dossier',
        sourceLabel: 'Dossier',
        certificate,
        shape: shape === '—' ? '-' : shape,
        label: meta?.label ?? row.mounted.diamond_code,
        diamondId:
            row.diamond.diamond_id.trim() !== ''
                ? Number(row.diamond.diamond_id)
                : null,
        msStoneId: null,
        crtPerPcs: crtPerPcsFrom(pcs, crt),
        reservedPcs: pcs,
        reservedCrt: crt !== '' ? crt : null,
    };
}

export function PasangBatuStoneEditor({
    stoneOptions,
    shapeOptions,
    diamondOptions,
    settingStones,
    returnStones,
    diamonds,
    mountedStones,
    errors,
    onSettingChange,
    onReturnChange,
    onDiamondsChange,
    onMountedChange,
}: PasangBatuStoneEditorProps) {
    const rows = buildBatchRows(
        settingStones,
        returnStones,
        diamonds,
        mountedStones,
    );
    const totals = batchRowTotals(rows);

    const setMicroDisposition = (
        line: SettingStoneLine,
        from: 'terpasang' | 'return',
        to: 'terpasang' | 'return',
    ) => {
        if (from === to) {
            return;
        }

        if (from === 'terpasang') {
            onSettingChange(
                settingStones.filter((item) => item.key !== line.key),
            );
            onReturnChange([...returnStones, line]);

            return;
        }

        onReturnChange(returnStones.filter((item) => item.key !== line.key));
        onSettingChange([...settingStones, line]);
    };

    const setDossierDisposition = (
        mounted: MountedStoneLine,
        disposition: 'terpasang' | 'return',
    ) => {
        if (mounted.disposition === disposition) {
            return;
        }

        onMountedChange(
            mountedStones.map((item) =>
                item.key === mounted.key ? { ...item, disposition } : item,
            ),
        );
    };

    const renderActionButtons = (
        disposition: PasangBatuMountedDisposition | 'terpasang' | 'return',
        onTerpasang: () => void,
        onReturn: () => void,
    ) => (
        <div className="spkPasangBatuBatchActions">
            <Button
                design={
                    disposition === 'terpasang' ? 'Emphasized' : 'Transparent'
                }
                type="Button"
                onClick={onTerpasang}
            >
                Terpasang
            </Button>
            <Button
                design={disposition === 'return' ? 'Attention' : 'Transparent'}
                type="Button"
                onClick={onReturn}
            >
                Return
            </Button>
        </div>
    );

    return (
        <section className="spkShowSection spkBatuPanelSection spkPasangBatuBatchSection">
            <div className="spkBatuPanelSectionHeader">
                <h3 className="spkFioriDetailBlockTitle">Batu dialokasikan</h3>
                <Text className="spkFioriDetailBlockMeta">
                    {rows.length.toLocaleString('id-ID')} baris · klik{' '}
                    <strong>Terpasang</strong> agar dossier masuk section Batu
                    terpasang saat simpan
                </Text>
            </div>

            <div className="spkStoneTableWrap">
                <table className="spkStoneTable spkStoneAllocatedFlatTable spkStoneTable--withStatus">
                    <thead>
                        <tr>
                            <th>Tipe</th>
                            <th>No. sertifikat</th>
                            <th>Bentuk</th>
                            <th>Nama/Kode batu</th>
                            <th>CRT per butir</th>
                            <th>Jumlah butir (pcs)</th>
                            <th>Total CRT</th>
                            <th className="spkStoneTableActionCol">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={8}>
                                    <div className="spkStoneEmpty">
                                        <Text>
                                            Tidak ada baris batu. Pilih SPK
                                            Diamond untuk mengisi daftar.
                                        </Text>
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => {
                                if (row.kind === 'micro') {
                                    const crtPerPcs = crtPerPcsFrom(
                                        row.line.pcs,
                                        row.line.crt,
                                    );
                                    const microLabel = resolveStoneLabel(
                                        stoneOptions,
                                        row.line.stone_id,
                                    );
                                    const labelWithNotes =
                                        row.line.notes.trim() !== ''
                                            ? `${microLabel} (${row.line.notes})`
                                            : microLabel;

                                    return (
                                        <tr key={`micro-${row.line.key}`}>
                                            <td>Mikro</td>
                                            <td>—</td>
                                            <td>—</td>
                                            <td>{emptyDash(labelWithNotes)}</td>
                                            <td className="tabular-nums">
                                                {crtPerPcs !== null
                                                    ? formatDecimal3Id(
                                                          crtPerPcs,
                                                      )
                                                    : '-'}
                                            </td>
                                            <td className="tabular-nums">
                                                {emptyDash(row.line.pcs)}
                                            </td>
                                            <td className="tabular-nums">
                                                {formatDecimal3Id(
                                                    row.line.crt,
                                                )}
                                            </td>
                                            <td className="spkStoneTableActionCol">
                                                {renderActionButtons(
                                                    row.disposition,
                                                    () =>
                                                        setMicroDisposition(
                                                            row.line,
                                                            row.disposition,
                                                            'terpasang',
                                                        ),
                                                    () =>
                                                        setMicroDisposition(
                                                            row.line,
                                                            row.disposition,
                                                            'return',
                                                        ),
                                                )}
                                            </td>
                                        </tr>
                                    );
                                }

                                const detailRow = toDossierDisplayRow(
                                    row,
                                    diamondOptions,
                                    shapeOptions,
                                );

                                return (
                                    <tr key={`dossier-${row.diamond.key}`}>
                                        <td>{detailRow.sourceLabel}</td>
                                        <td>
                                            <SpkDiamondDetailCertificateLink
                                                certificate={
                                                    detailRow.certificate
                                                }
                                            />
                                        </td>
                                        <td>{emptyDash(detailRow.shape)}</td>
                                        <td>
                                            <SpkDiamondDetailStoneLabelLink
                                                row={detailRow}
                                            />
                                        </td>
                                        <td className="tabular-nums">
                                            {detailRow.crtPerPcs !== null
                                                ? formatDecimal3Id(
                                                      detailRow.crtPerPcs,
                                                  )
                                                : '-'}
                                        </td>
                                        <td className="tabular-nums">
                                            {emptyDash(detailRow.reservedPcs)}
                                        </td>
                                        <td className="tabular-nums">
                                            {formatDecimal3Id(
                                                detailRow.reservedCrt,
                                            )}
                                        </td>
                                        <td className="spkStoneTableActionCol">
                                            {renderActionButtons(
                                                row.mounted.disposition,
                                                () =>
                                                    setDossierDisposition(
                                                        row.mounted,
                                                        'terpasang',
                                                    ),
                                                () =>
                                                    setDossierDisposition(
                                                        row.mounted,
                                                        'return',
                                                    ),
                                            )}
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                    {rows.length > 0 ? (
                        <tfoot>
                            <tr className="spkStoneTableTotal spkStoneTableTotal--allocated">
                                <td colSpan={5}>Total batu dialokasikan</td>
                                <td className="spkStoneTableTotalValue">
                                    {totals.pcs.toLocaleString('id-ID')}
                                </td>
                                <td className="spkStoneTableTotalValue">
                                    {formatTotalCarat(totals.carat)}
                                </td>
                                <td />
                            </tr>
                        </tfoot>
                    ) : null}
                </table>
            </div>

            {errors.setting_stones ||
            errors.return_stones ||
            errors.diamonds ||
            errors.mounted_stones ? (
                <Text className="spkFioriError">
                    Periksa kembali data batu sebelum menyimpan.
                </Text>
            ) : null}
        </section>
    );
}
