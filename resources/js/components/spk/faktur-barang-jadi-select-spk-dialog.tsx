import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useEffect, useState } from 'react';
import { SpkTableLastProcessCell } from '@/components/spk/spk-list-cells';
import { SpkTableItemCell } from '@/components/spk/spk-table-item-cell';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { spks as searchFakturBarangJadiSpks } from '@/routes/faktur-barang-jadi/select';

export type FakturBarangJadiSelectedSpk = {
    rowId: number;
    spkNo: string;
    status: string;
    location: string | null;
    spkImageUrl: string | null;
    typeSkuLabel: string | null;
    itemDescription: string | null;
    skuAssigned: boolean;
    prosesTerakhir: string;
    prosesTerakhirDate: string;
    prosesTerakhirDisplay?: string | null;
    prosesTerakhirCompleted?: boolean | null;
    fotoBrjImageUrl: string | null;
    hasFotoBrj: boolean;
    lastWeight: string | null;
};

type FakturBarangJadiSpkOption = FakturBarangJadiSelectedSpk;

type SelectMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
};

type FakturBarangJadiSelectSpkDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    excludeSpkIds?: number[];
    receiptId?: number;
    onSelected: (row: FakturBarangJadiSelectedSpk) => void;
};

const SEARCH_DEBOUNCE_MS = 300;

export function FakturBarangJadiSelectSpkDialog({
    open,
    onOpenChange,
    excludeSpkIds = [],
    receiptId,
    onSelected,
}: FakturBarangJadiSelectSpkDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="spkAlertModal spkStatusListModal sm:max-w-[min(72rem,calc(100vw-2rem))]"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>Pilih SPK</DialogTitle>
                    <DialogDescription>
                        Pilih satu SPK selesai. Setelah itu isi berat akhir dan
                        unggah atau konfirmasi foto barang jadi.
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <FakturBarangJadiSelectSpkBody
                        excludeKey={excludeSpkIds.join(',')}
                        receiptId={receiptId}
                        onCancel={() => onOpenChange(false)}
                        onSelected={(row) => {
                            onSelected(row);
                            onOpenChange(false);
                        }}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function FakturBarangJadiSelectSpkBody({
    excludeKey,
    receiptId,
    onCancel,
    onSelected,
}: {
    excludeKey: string;
    receiptId?: number;
    onCancel: () => void;
    onSelected: (row: FakturBarangJadiSelectedSpk) => void;
}) {
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [rows, setRows] = useState<FakturBarangJadiSpkOption[]>([]);
    const [meta, setMeta] = useState<SelectMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [selectedRow, setSelectedRow] =
        useState<FakturBarangJadiSpkOption | null>(null);

    const requestKey = `${excludeKey}|${receiptId ?? ''}|${page}|${search}`;

    useEffect(() => {
        if (searchInput.trim() !== search) {
            const timeoutId = window.setTimeout(() => {
                setSearch(searchInput.trim());
                setPage(1);
            }, SEARCH_DEBOUNCE_MS);

            return () => window.clearTimeout(timeoutId);
        }
    }, [searchInput, search]);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);

        fetch(
            searchFakturBarangJadiSpks.url({
                query: {
                    page: page > 1 ? page : undefined,
                    search: search !== '' ? search : undefined,
                    exclude: excludeKey !== '' ? excludeKey : undefined,
                    receipt_id:
                        receiptId !== undefined && receiptId > 0
                            ? receiptId
                            : undefined,
                    faktur_belum: 1,
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
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json() as Promise<{
                    data: FakturBarangJadiSpkOption[];
                    meta: SelectMeta;
                }>;
            })
            .then((payload) => {
                setRows(payload.data);
                setMeta(payload.meta);
                setFailed(false);
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                setRows([]);
                setMeta(null);
                setFailed(true);
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [excludeKey, page, receiptId, search, requestKey]);

    return (
        <>
            <MessageStrip design="Information" hideCloseButton>
                Tambahkan SPK satu per satu. Foto barang jadi wajib sebelum SPK
                masuk daftar faktur.
            </MessageStrip>

            <div className="spkStatusListToolbar mt-3">
                <div
                    className="spkTableHeaderSearch--finishing spkStatusListSearch"
                    role="search"
                >
                    <input
                        type="search"
                        className="spkTableHeaderSearchInput--finishing"
                        placeholder="Cari No. SPK, item, pelanggan..."
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        aria-label="Cari SPK"
                    />
                    <Icon
                        name={searchIcon}
                        className="spkTableHeaderSearchIcon--finishing"
                    />
                </div>
            </div>

            <div
                className={`spkAlertModalBody spkStatusListBody${loading ? ' is-loading' : ''}`}
            >
                {loading && rows.length === 0 ? (
                    <p className="spkAlertModalEmpty">Memuat data...</p>
                ) : failed ? (
                    <p className="spkAlertModalEmpty">
                        Gagal memuat data. Silakan coba lagi.
                    </p>
                ) : rows.length === 0 ? (
                    <p className="spkAlertModalEmpty">
                        Tidak ada SPK yang memenuhi syarat.
                    </p>
                ) : (
                    <table className="spkAlertModalTable">
                        <thead>
                            <tr>
                                <th>No. SPK</th>
                                <th>Item</th>
                                <th className="spkTableColCenter">Status</th>
                                <th className="spkTableColCenter">
                                    Proses Terakhir
                                </th>
                                <th className="spkTableColCenter">Foto BRJ</th>
                                <th className="spkTableColCenter">Lokasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const isSelected =
                                    selectedRow?.rowId === row.rowId;

                                return (
                                    <tr
                                        key={row.rowId}
                                        tabIndex={0}
                                        aria-selected={isSelected}
                                        className={`spkAlertModalRow${isSelected ? ' is-selected' : ''}`}
                                        onClick={() => setSelectedRow(row)}
                                        onDoubleClick={() => onSelected(row)}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                onSelected(row);
                                            } else if (event.key === ' ') {
                                                event.preventDefault();
                                                setSelectedRow(row);
                                            }
                                        }}
                                    >
                                        <td className="font-medium whitespace-nowrap">
                                            {row.spkNo}
                                        </td>
                                        <td>
                                            <SpkTableItemCell
                                                spkNo={row.spkNo}
                                                spkImageUrl={row.spkImageUrl}
                                                typeSkuLabel={row.typeSkuLabel}
                                                itemDescription={
                                                    row.itemDescription
                                                }
                                                skuAssigned={row.skuAssigned}
                                            />
                                        </td>
                                        <td className="spkTableColCenter">
                                            <span className="spkTableBadge spkTableBadge--done">
                                                {row.status}
                                            </span>
                                        </td>
                                        <td className="spkTableColCenter">
                                            <SpkTableLastProcessCell
                                                row={{
                                                    prosesTerakhir:
                                                        row.prosesTerakhir,
                                                    prosesTerakhirDate:
                                                        row.prosesTerakhirDate,
                                                    prosesTerakhirDisplay:
                                                        row.prosesTerakhirDisplay,
                                                    prosesTerakhirCompleted:
                                                        row.prosesTerakhirCompleted,
                                                    status: row.status,
                                                }}
                                            />
                                        </td>
                                        <td className="spkTableColCenter">
                                            <span
                                                className={
                                                    row.hasFotoBrj
                                                        ? 'spkTableBadge spkTableBadge--done'
                                                        : 'spkTableBadge spkTableBadge--fakturPending'
                                                }
                                            >
                                                {row.hasFotoBrj
                                                    ? 'Sudah ada'
                                                    : 'Belum ada'}
                                            </span>
                                        </td>
                                        <td className="spkTableColCenter">
                                            {row.location ?? '—'}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>

            {meta && meta.lastPage > 1 ? (
                <div className="spkStatusListPagination">
                    <button
                        type="button"
                        className="spkPageBtn"
                        disabled={page <= 1}
                        onClick={() => setPage(page - 1)}
                    >
                        Sebelumnya
                    </button>
                    <span>
                        Halaman {meta.currentPage} / {meta.lastPage}
                    </span>
                    <button
                        type="button"
                        className="spkPageBtn"
                        disabled={page >= meta.lastPage}
                        onClick={() => setPage(page + 1)}
                    >
                        Berikutnya
                    </button>
                </div>
            ) : null}

            {selectedRow !== null ? (
                <MessageStrip design="Information" hideCloseButton>
                    <strong>{selectedRow.spkNo}</strong> dipilih — lanjut ke
                    foto barang jadi.
                </MessageStrip>
            ) : null}

            <DialogFooter className="gap-2 sm:justify-end">
                <Button design="Transparent" type="Button" onClick={onCancel}>
                    Batal
                </Button>
                <Button
                    design="Emphasized"
                    type="Button"
                    disabled={selectedRow === null}
                    onClick={() => {
                        if (selectedRow !== null) {
                            onSelected(selectedRow);
                        }
                    }}
                >
                    Lanjut
                </Button>
            </DialogFooter>
        </>
    );
}