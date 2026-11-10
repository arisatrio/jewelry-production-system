import { useEffect, useState } from 'react';
import { Button } from '@ui5/webcomponents-react/Button';
import { Text } from '@ui5/webcomponents-react/Text';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input as UiInput } from '@/components/ui/input';
import { spkDiamonds as searchPasangBatuSpkDiamonds } from '@/routes/pasang-batu/select';

export type PasangBatuSelectedSpkDiamond = {
    rowId: number;
    docNo: string;
};

type SpkDiamondSelectorOption = {
    rowId: number;
    docNo: string;
    spkNo: string | null;
    itemName: string;
    statusLabel: string;
    lineCount: number;
};

type PasangBatuSelectSpkDiamondDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    excludeSpkDiamondIds?: number[];
    allowMountingId?: number | null;
    onSelected: (selection: PasangBatuSelectedSpkDiamond) => void;
};

export function PasangBatuSelectSpkDiamondDialog({
    open,
    onOpenChange,
    excludeSpkDiamondIds = [],
    allowMountingId = null,
    onSelected,
}: PasangBatuSelectSpkDiamondDialogProps) {
    const [search, setSearch] = useState('');
    const [rows, setRows] = useState<SpkDiamondSelectorOption[]>([]);
    const [loadingList, setLoadingList] = useState(false);
    const [selectedRow, setSelectedRow] = useState<SpkDiamondSelectorOption | null>(
        null,
    );
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) {
            setSearch('');
            setRows([]);
            setLoadingList(false);
            setSelectedRow(null);
            setError(null);
        }
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setLoadingList(true);

            void fetch(
                searchPasangBatuSpkDiamonds.url({
                    query: {
                        search: search || undefined,
                        exclude:
                            excludeSpkDiamondIds.length > 0
                                ? excludeSpkDiamondIds.join(',')
                                : undefined,
                        allowMountingId:
                            allowMountingId !== null && allowMountingId > 0
                                ? allowMountingId
                                : undefined,
                        limit: 25,
                    },
                }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            )
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error('Gagal memuat daftar SPK Diamond');
                    }

                    const payload = (await response.json()) as {
                        data: SpkDiamondSelectorOption[];
                    };

                    setRows(payload.data ?? []);
                })
                .catch((fetchError: unknown) => {
                    if (
                        fetchError instanceof DOMException &&
                        fetchError.name === 'AbortError'
                    ) {
                        return;
                    }

                    setRows([]);
                })
                .finally(() => setLoadingList(false));
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [open, search, excludeSpkDiamondIds, allowMountingId]);

    const handleSelect = () => {
        if (!selectedRow) {
            setError('SPK Diamond wajib dipilih.');

            return;
        }

        onSelected({
            rowId: selectedRow.rowId,
            docNo: selectedRow.docNo,
        });
        onOpenChange(false);
    };

    const selectRow = (row: SpkDiamondSelectorOption) => {
        setSelectedRow(row);
        setError(null);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="jewelCadAddSpkDialog">
                <DialogHeader>
                    <DialogTitle>Pilih SPK Diamond</DialogTitle>
                    <DialogDescription>
                        Cari dan pilih dokumen SPK Diamond (status Diterima
                        Workshop) untuk mengisi batu setting dan dossier.
                    </DialogDescription>
                </DialogHeader>

                {error ? <Text className="spkFioriError">{error}</Text> : null}

                <div className="jewelCadAddSpkSelect">
                    <UiInput
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari no. SPK Diamond, SPK produksi, atau item..."
                        autoFocus
                    />

                    <div className="spkCreateDialogTableWrap">
                        <table className="spkCreateDialogTable">
                            <thead>
                                <tr>
                                    <th>No. SPK Diamond</th>
                                    <th>No. SPK</th>
                                    <th>Item</th>
                                    <th>Status</th>
                                    <th>Baris</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loadingList ? (
                                    <tr>
                                        <td colSpan={5}>Memuat data...</td>
                                    </tr>
                                ) : rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={5}>
                                            Tidak ada SPK Diamond ditemukan.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row) => {
                                        const isSelected =
                                            selectedRow?.rowId === row.rowId;

                                        return (
                                            <tr
                                                key={row.rowId}
                                                tabIndex={0}
                                                className={
                                                    isSelected
                                                        ? 'is-selected'
                                                        : undefined
                                                }
                                                onClick={() => selectRow(row)}
                                                onKeyDown={(event) => {
                                                    if (
                                                        event.key === 'Enter' ||
                                                        event.key === ' '
                                                    ) {
                                                        event.preventDefault();
                                                        selectRow(row);
                                                    }
                                                }}
                                            >
                                                <td>{row.docNo}</td>
                                                <td>{row.spkNo ?? '—'}</td>
                                                <td>{row.itemName}</td>
                                                <td>{row.statusLabel}</td>
                                                <td>{row.lineCount}</td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        design="Default"
                        type="Button"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button
                        design="Emphasized"
                        type="Button"
                        disabled={!selectedRow}
                        onClick={handleSelect}
                    >
                        Pilih
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
