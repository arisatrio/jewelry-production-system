import acceptIcon from '@ui5/webcomponents-icons/dist/accept.js';
import undoIcon from '@ui5/webcomponents-icons/dist/undo.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { displayCrt } from '@/components/pasang-batu/pasang-batu-stone-tables';

function displayCell(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const text = String(value).trim();

    return text !== '' ? text : '—';
}

export type SettingAwalLine = {
    key: string;
    sourceType: 'stone' | 'diamond';
    sourceLineId: number;
    stoneId?: number;
    trdiamondId?: number;
    shapeId?: number;
    diamondCode?: string | null;
    size?: string | null;
    batu: string;
    pcsAwal: number | string | null;
    crtAwal: number | string | null;
    pcsTerpasang: number | string | null;
    crtTerpasang: number | string | null;
    pcsRetur: number | string | null;
    crtRetur: number | string | null;
    notes?: string | null;
};

function toNumber(value: number | string | null | undefined): number {
    if (value === null || value === undefined) {
        return 0;
    }

    const parsed = Number(String(value).replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
}

function updateLine(
    lines: SettingAwalLine[],
    key: string,
    patch: Partial<SettingAwalLine>,
): SettingAwalLine[] {
    return lines.map((line) =>
        line.key === key ? { ...line, ...patch } : line,
    );
}

export function PasangBatuSettingAwalPanel({
    lines,
    onChange,
}: {
    lines: SettingAwalLine[];
    onChange: (lines: SettingAwalLine[]) => void;
}) {
    return (
        <div className="spkStoneBatchPanel">
            <div className="spkCoranDetailLabel">Setting awal (dari SPK Diamond)</div>
            <table className="spkCoranMaterialTable spkStoneBatchTable is-batu-pcs-crt">
                <thead>
                    <tr>
                        <th scope="col">Batu</th>
                        <th scope="col">Awal Pcs</th>
                        <th scope="col">Awal Crt</th>
                        <th scope="col">Terpasang</th>
                        <th scope="col">Retur</th>
                        <th scope="col" className="spkTableActionCol">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {lines.length === 0 ? (
                        <tr>
                            <td colSpan={6} className="spkCoranBreakdownEmpty">
                                Pilih SPK Diamond untuk memuat setting awal.
                            </td>
                        </tr>
                    ) : (
                        lines.map((line) => {
                            const pcsAwal = toNumber(line.pcsAwal);
                            const crtAwal = toNumber(line.crtAwal);
                            const pcsTerpasang = toNumber(line.pcsTerpasang);
                            const pcsRetur = toNumber(line.pcsRetur);

                            return (
                                <tr key={line.key} className="spkCoranLineRow">
                                    <td>{displayCell(line.batu)}</td>
                                    <td>{displayCell(line.pcsAwal)}</td>
                                    <td>{displayCrt(line.crtAwal)}</td>
                                    <td>{displayCell(line.pcsTerpasang || null)}</td>
                                    <td>{displayCell(line.pcsRetur || null)}</td>
                                    <td className="spkTableActionCol">
                                        <div className="spkInlineActionGroup">
                                            <Button
                                                design="Transparent"
                                                icon={acceptIcon}
                                                type="Button"
                                                accessibleName="Tandai terpasang"
                                                tooltip="Terpasang"
                                                onClick={() => {
                                                    const remainingPcs = Math.max(
                                                        0,
                                                        pcsAwal - pcsRetur,
                                                    );
                                                    const remainingCrt = Math.max(
                                                        0,
                                                        crtAwal -
                                                            toNumber(
                                                                line.crtRetur,
                                                            ),
                                                    );
                                                    onChange(
                                                        updateLine(
                                                            lines,
                                                            line.key,
                                                            {
                                                                pcsTerpasang:
                                                                    remainingPcs,
                                                                crtTerpasang:
                                                                    remainingCrt,
                                                            },
                                                        ),
                                                    );
                                                }}
                                            />
                                            <Button
                                                design="Transparent"
                                                icon={undoIcon}
                                                type="Button"
                                                accessibleName="Tandai retur"
                                                tooltip="Retur"
                                                onClick={() => {
                                                    const remainingPcs = Math.max(
                                                        0,
                                                        pcsAwal - pcsTerpasang,
                                                    );
                                                    const remainingCrt = Math.max(
                                                        0,
                                                        crtAwal -
                                                            toNumber(
                                                                line.crtTerpasang,
                                                            ),
                                                    );
                                                    onChange(
                                                        updateLine(
                                                            lines,
                                                            line.key,
                                                            {
                                                                pcsRetur:
                                                                    remainingPcs,
                                                                crtRetur:
                                                                    remainingCrt,
                                                            },
                                                        ),
                                                    );
                                                }}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            );
                        })
                    )}
                </tbody>
            </table>
        </div>
    );
}
