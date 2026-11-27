import { Button } from '@ui5/webcomponents-react/Button';
import { Text } from '@ui5/webcomponents-react/Text';
import {
    displayCell,
    displayCrt,
    displayStoneWithNotes,
    StoneBatchTable,
} from '@/components/pasang-batu/pasang-batu-stone-tables';
import { pasangBatuStonesFromFormState } from '@/components/pasang-batu/pasang-batu-stone-form-preview';

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

function resolveDiamondMeta(
    diamondOptions: PasangBatuDiamondOption[],
    diamondId: string,
): PasangBatuDiamondOption | null {
    if (diamondId.trim() === '') {
        return null;
    }

    return diamondOptions.find((option) => option.value === diamondId) ?? null;
}

function microStoneDisplay(
    line: SettingStoneLine,
    stoneOptions: PasangBatuStoneOption[],
) {
    return displayStoneWithNotes(
        resolveStoneLabel(stoneOptions, line.stone_id),
        line.notes.trim() !== '' ? line.notes : null,
    );
}

function dispositionButtons(
    disposition: PasangBatuMountedDisposition | 'terpasang' | 'return',
    onTerpasang: () => void,
    onReturn: () => void,
) {
    return (
        <div className="spkPasangBatuBatchActions spkPasangBatuBatchActions--compact">
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
    onDiamondsChange: _onDiamondsChange,
    onMountedChange,
}: PasangBatuStoneEditorProps) {
    void _onDiamondsChange;
    const batchPreview = pasangBatuStonesFromFormState({
        settingStones,
        returnStones,
        diamonds,
        mountedStones,
        stoneOptions,
        shapeOptions,
        diamondOptions,
    });

    const mountedByBatch = new Map(
        mountedStones.map((line) => [line.batch_key, line]),
    );

    const dossierRows = diamonds
        .map((diamond) => {
            const mounted = mountedByBatch.get(diamond.batch_key);

            if (!mounted) {
                return null;
            }

            return { diamond, mounted };
        })
        .filter(
            (
                row,
            ): row is {
                diamond: DiamondLine;
                mounted: MountedStoneLine;
            } => row !== null,
        );

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

    const hasAnyRows =
        settingStones.length > 0 ||
        returnStones.length > 0 ||
        dossierRows.length > 0;

    const settingRows = settingStones.map((line) => [
        microStoneDisplay(line, stoneOptions),
        displayCell(line.pcs),
        displayCrt(line.crt),
        dispositionButtons(
            'terpasang',
            () => setMicroDisposition(line, 'terpasang', 'terpasang'),
            () => setMicroDisposition(line, 'terpasang', 'return'),
        ),
    ]);

    const returnRows = returnStones.map((line) => [
        microStoneDisplay(line, stoneOptions),
        displayCell(line.pcs),
        displayCrt(line.crt),
        dispositionButtons(
            'return',
            () => setMicroDisposition(line, 'return', 'terpasang'),
            () => setMicroDisposition(line, 'return', 'return'),
        ),
    ]);

    const diamondRows = dossierRows.map(({ diamond, mounted }) => {
        const meta = resolveDiamondMeta(diamondOptions, diamond.diamond_id);

        return [
            displayCell(meta?.code ?? meta?.label),
            displayCell(meta?.label),
            displayCell(meta?.shape),
            displayCell(meta?.certificate),
            displayCrt(meta?.crt ?? mounted.crt),
            dispositionButtons(
                mounted.disposition === 'return'
                    ? 'return'
                    : mounted.disposition === 'terpasang'
                      ? 'terpasang'
                      : 'pending',
                () => setDossierDisposition(mounted, 'terpasang'),
                () => setDossierDisposition(mounted, 'return'),
            ),
        ];
    });

    const mountedRows = batchPreview.mounted.map((row) => [
        displayCell(row.kode),
        displayCell(row.shape),
        displayCell(row.pcs),
        displayCrt(row.crt),
        displayCell(row.size),
    ]);

    return (
        <section className="spkShowSection spkBatuPanelSection spkPasangBatuBatchSection">
            <p className="spkPasangBatuBatchNote">
                Tandai <strong>Terpasang</strong> pada baris dossier agar masuk
                panel Batu terpasang (sama seperti tampilan detail SPK).
            </p>

            {!hasAnyRows ? (
                <div className="spkStoneEmpty spkPasangBatuBatchEmpty">
                    <Text>
                        Tidak ada batu. Pilih SPK Diamond untuk mengisi daftar.
                    </Text>
                </div>
            ) : (
                <div className="spkProcessInfoItem is-coran-materials">
                    <div className="spkCoranDetailLabel">
                        Detail Batch Pasang Batu
                    </div>
                    <div className="spkStoneBatchGrid">
                        <StoneBatchTable
                            title="Setting Batu"
                            columns={['Batu', 'Pcs', 'Crt', 'Aksi']}
                            rows={settingRows}
                        />
                        <StoneBatchTable
                            title="Retur Batu"
                            columns={['Batu', 'Pcs', 'Crt', 'Aksi']}
                            rows={returnRows}
                        />
                        <StoneBatchTable
                            title="Diamond"
                            columns={[
                                'Kode',
                                'Diamond',
                                'Bentuk',
                                'Sertifikat',
                                'Crt',
                                'Aksi',
                            ]}
                            rows={diamondRows}
                        />
                        <StoneBatchTable
                            title="Batu terpasang"
                            columns={[
                                'Kode Diamond',
                                'Shape',
                                'Pcs',
                                'Crt',
                                'Size',
                            ]}
                            rows={mountedRows}
                        />
                    </div>
                </div>
            )}

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