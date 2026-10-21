import { useCallback, useEffect, useState } from 'react';
import { Button } from '@ui5/webcomponents-react/Button';
import { Dialog } from '@ui5/webcomponents-react/Dialog';
import { Input } from '@ui5/webcomponents-react/Input';
import { Text } from '@ui5/webcomponents-react/Text';
import { spkDiamonds as searchSpkDiamonds } from '@/routes/pasang-batu/select';

export type PasangBatuSelectedSpkDiamond = {
    id: number;
    noSpkDiamond: string;
    spkNo: string | null;
    tanggal: string | null;
    keterangan: string | null;
    itemName: string | null;
    prefill: {
        spkId: number;
        spkNo: string | null;
        weightFrame: string | null;
        weightStone: string | null;
        keterangan: string | null;
        settingStones: Array<{
            stoneId: number;
            pcs: string;
            crt: string;
            notes: string;
        }>;
        diamonds: Array<{ diamondId: number }>;
        settingAwalLines: Array<Record<string, unknown>>;
    };
};

type DialogRow = PasangBatuSelectedSpkDiamond;

type PasangBatuSelectSpkDiamondDialogProps = {
    open: boolean;
    onClose: () => void;
    onSelect: (row: PasangBatuSelectedSpkDiamond) => void;
};

export function PasangBatuSelectSpkDiamondDialog({
    open,
    onClose,
    onSelect,
}: PasangBatuSelectSpkDiamondDialogProps) {
    const [search, setSearch] = useState('');
    const [rows, setRows] = useState<DialogRow[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async (query: string) => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(
                searchSpkDiamonds.url({ query: { search: query, limit: 25 } }),
                {
                    headers: { Accept: 'application/json' },
                },
            );

            if (!response.ok) {
                throw new Error('Gagal memuat SPK Diamond.');
            }

            const payload = (await response.json()) as {
                data: DialogRow[];
            };

            setRows(payload.data ?? []);
        } catch {
            setError('Gagal memuat daftar SPK Diamond.');
            setRows([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        void load(search);
    }, [open, search, load]);

    return (
        <Dialog
            open={open}
            headerText="Pilih SPK Diamond"
            onAfterClose={onClose}
            footer={
                <div className="spkDialogFooter">
                    <Button design="Transparent" onClick={onClose}>
                        Tutup
                    </Button>
                </div>
            }
        >
            <div className="spkDialogBody">
                <Text>
                    Cari dan pilih SPK Diamond yang sudah dikirim ke produksi.
                </Text>
                <Input
                    accessibleName="Cari SPK Diamond"
                    placeholder="No SPK Diamond / No SPK produksi..."
                    value={search}
                    onInput={(event) =>
                        setSearch(event.target.value ?? '')
                    }
                />
                {error ? <Text className="spkFioriError">{error}</Text> : null}
                {loading ? (
                    <Text>Memuat...</Text>
                ) : rows.length === 0 ? (
                    <Text>Tidak ada SPK Diamond ditemukan.</Text>
                ) : (
                    <table className="spkCoranMaterialTable">
                        <thead>
                            <tr>
                                <th>No SPK Diamond</th>
                                <th>SPK Produksi</th>
                                <th>Item</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.id}>
                                    <td>{row.noSpkDiamond}</td>
                                    <td>{row.spkNo ?? '—'}</td>
                                    <td>{row.itemName ?? '—'}</td>
                                    <td>
                                        <Button
                                            design="Emphasized"
                                            type="Button"
                                            onClick={() => {
                                                onSelect(row);
                                                onClose();
                                            }}
                                        >
                                            Pilih
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </Dialog>
    );
}
