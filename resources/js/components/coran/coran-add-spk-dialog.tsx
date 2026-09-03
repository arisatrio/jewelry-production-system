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
import { spks as searchCoranSpks } from '@/routes/coran/select';

export type CoranAddedSpk = {
    spk_id: string;
    spk_no: string;
    spk_type: string;
    order_type_label: string;
    type_code: string;
    product_item_name: string;
    sku_code: string;
    item_description: string;
    satuan: string;
};

type SpkSelectorOption = {
    rowId: number;
    spkNo: string;
    customer: string;
    item: string;
    goldColor: string;
    qty: number;
    spkType: string | null;
    orderTypeLabel: string | null;
    skuCode: string | null;
    typeCode: string | null;
    productItemName: string | null;
    itemDescription: string | null;
    satuan: string;
};

type CoranAddSpkDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    excludeSpkIds: number[];
    onAdded: (detail: CoranAddedSpk) => void;
};

export function CoranAddSpkDialog({
    open,
    onOpenChange,
    excludeSpkIds,
    onAdded,
}: CoranAddSpkDialogProps) {
    const [search, setSearch] = useState('');
    const [rows, setRows] = useState<SpkSelectorOption[]>([]);
    const [loadingList, setLoadingList] = useState(false);
    const [selectedRow, setSelectedRow] = useState<SpkSelectorOption | null>(
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
                searchCoranSpks.url({
                    query: {
                        search: search || undefined,
                        exclude:
                            excludeSpkIds.length > 0
                                ? excludeSpkIds.join(',')
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
                        throw new Error('Gagal memuat daftar SPK');
                    }

                    const payload = (await response.json()) as {
                        data: SpkSelectorOption[];
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
    }, [open, search, excludeSpkIds]);

    const handleAdd = () => {
        if (!selectedRow) {
            setError('SPK wajib dipilih.');

            return;
        }

        onAdded({
            spk_id: String(selectedRow.rowId),
            spk_no: selectedRow.spkNo,
            spk_type: selectedRow.spkType ?? '',
            order_type_label: selectedRow.orderTypeLabel ?? '',
            type_code: selectedRow.typeCode ?? '',
            product_item_name: selectedRow.productItemName ?? '',
            sku_code: selectedRow.skuCode ?? '',
            item_description: selectedRow.itemDescription ?? '',
            satuan: selectedRow.satuan ?? '',
        });
        onOpenChange(false);
    };

    const selectRow = (row: SpkSelectorOption) => {
        setSelectedRow(row);
        setError(null);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="jewelCadAddSpkDialog">
                <DialogHeader>
                    <DialogTitle>Pilih SPK</DialogTitle>
                    <DialogDescription>
                        Cari dan pilih SPK yang akan ditambahkan ke dokumen
                        coran. Berat dan status diisi di tabel setelah SPK
                        ditambahkan.
                    </DialogDescription>
                </DialogHeader>

                {error ? <Text className="spkFioriError">{error}</Text> : null}

                <div className="jewelCadAddSpkSelect">
                    <UiInput
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari no. SPK, customer, atau item..."
                        autoFocus
                    />

                    <div className="spkCreateDialogTableWrap">
                        <table className="spkCreateDialogTable">
                            <thead>
                                <tr>
                                    <th>No. SPK</th>
                                    <th>Customer</th>
                                    <th>Item</th>
                                    <th>Bahan Emas</th>
                                    <th>Qty</th>
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
                                            Tidak ada SPK ditemukan.
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
                                                <td>{row.spkNo}</td>
                                                <td>{row.customer}</td>
                                                <td>{row.item}</td>
                                                <td>{row.goldColor || '—'}</td>
                                                <td>{row.qty ?? '—'}</td>
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
                        onClick={handleAdd}
                    >
                        Tambah
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
