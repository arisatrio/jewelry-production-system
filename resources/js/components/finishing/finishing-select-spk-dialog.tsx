import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useEffect, useState } from 'react';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import {
    SpkPaymentStatusBadge,
    SpkTableDescriptionCell,
    SpkTableLastProcessCell,
    SpkTableStatusCell,
    displayListDate,
    isListRowIncomplete,
    targetDaysLeftHint,
    tipeProduksiBadgeClass,
} from '@/components/spk/spk-list-cells';
import type { SpkIndexRow } from '@/components/spk/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { list as spkSelectList } from '@/routes/spk/select';

export type FinishingSelectedSpk = {
    spk_id: string;
    spk_no: string;
    spk_type: string;
    order_type_label: string;
    type_code: string;
    product_item_name: string;
    sku_code: string;
    item_description: string;
    satuan: string;
    lastWeight: string | null;
};

type SpkSelectRow = SpkIndexRow & {
    lastWeight: string | null;
    orderTypeLabel: string | null;
    satuan: string;
};

type SelectListMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
};

type SelectListResult = {
    requestKey: string;
    rows: SpkSelectRow[];
    meta: SelectListMeta | null;
    failed: boolean;
};

type FinishingSelectSpkDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    excludeSpkIds?: number[];
    onSelected: (spk: FinishingSelectedSpk) => void;
};

const SEARCH_DEBOUNCE_MS = 300;

function SelectedSpkAlert({ row }: { row: SpkSelectRow }) {
    const lastProcess = row.prosesTerakhir.trim();
    const lastProcessDate = (row.prosesTerakhirDate ?? '').trim();
    const lastProcessLabel =
        lastProcess === ''
            ? 'belum diproses'
            : lastProcessDate !== ''
              ? `${lastProcess} (${lastProcessDate})`
              : lastProcess;

    return (
        <MessageStrip
            design={row.lastWeight !== null ? 'Information' : 'Critical'}
            hideCloseButton
            className="finishingSelectSpkAlert"
        >
            <strong>{row.produksiNo}</strong> dipilih · Proses terakhir:{' '}
            {lastProcessLabel}.{' '}
            {row.lastWeight !== null
                ? `Berat terakhir ${row.lastWeight} g akan diisi sebagai Berat Awal.`
                : 'SPK belum memiliki berat terakhir, Berat Awal perlu diisi manual.'}
        </MessageStrip>
    );
}

function toSelectedSpk(row: SpkSelectRow): FinishingSelectedSpk {
    return {
        spk_id: String(row.rowId),
        spk_no: row.produksiNo,
        spk_type: row.tipeProduksi ?? '',
        order_type_label: row.orderTypeLabel ?? '',
        type_code: row.typeCode ?? '',
        product_item_name: row.productItemName ?? '',
        sku_code: row.skuCode ?? '',
        item_description: row.itemDescription ?? '',
        satuan: row.satuan ?? '',
        lastWeight: row.lastWeight ?? null,
    };
}

export function FinishingSelectSpkDialog({
    open,
    onOpenChange,
    excludeSpkIds = [],
    onSelected,
}: FinishingSelectSpkDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStatusListModal">
                <DialogHeader>
                    <DialogTitle>Pilih SPK</DialogTitle>
                    <DialogDescription>
                        Cari dan pilih satu SPK untuk dokumen finishing.
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <FinishingSelectSpkBody
                        excludeKey={excludeSpkIds.join(',')}
                        onCancel={() => onOpenChange(false)}
                        onSelected={(row) => {
                            onSelected(toSelectedSpk(row));
                            onOpenChange(false);
                        }}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function FinishingSelectSpkBody({
    excludeKey,
    onCancel,
    onSelected,
}: {
    excludeKey: string;
    onCancel: () => void;
    onSelected: (row: SpkSelectRow) => void;
}) {
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<SelectListResult | null>(null);
    const [selectedRow, setSelectedRow] = useState<SpkSelectRow | null>(null);
    const requestKey = `${excludeKey}|${page}|${search}`;
    const loading = result?.requestKey !== requestKey;
    const rows = result?.rows ?? [];
    const meta = result?.meta ?? null;

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            if (searchInput.trim() !== search) {
                setSearch(searchInput.trim());
                setPage(1);
            }
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timeoutId);
    }, [searchInput, search]);

    useEffect(() => {
        const controller = new AbortController();

        fetch(
            spkSelectList.url({
                query: {
                    page: page > 1 ? page : undefined,
                    search: search !== '' ? search : undefined,
                    exclude: excludeKey !== '' ? excludeKey : undefined,
                },
            }),
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = (await response.json()) as {
                    data?: SpkSelectRow[];
                    meta?: SelectListMeta;
                };

                setResult({
                    requestKey,
                    rows: Array.isArray(payload.data) ? payload.data : [],
                    meta: payload.meta ?? null,
                    failed: false,
                });
            })
            .catch(() => {
                if (controller.signal.aborted) {
                    return;
                }

                setResult({
                    requestKey,
                    rows: [],
                    meta: null,
                    failed: true,
                });
            });

        return () => controller.abort();
    }, [excludeKey, page, search, requestKey]);

    return (
        <>
            <div className="spkStatusListToolbar">
                <div
                    className="spkTableHeaderSearch--finishing spkStatusListSearch"
                    role="search"
                >
                    <input
                        type="search"
                        className="spkTableHeaderSearchInput--finishing"
                        placeholder="Cari No SPK, pesanan, item..."
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        aria-label="Cari SPK"
                        autoFocus
                    />
                    <Icon
                        name={searchIcon}
                        className="spkTableHeaderSearchIcon--finishing"
                    />
                </div>
            </div>

            <div
                className={`spkAlertModalBody spkStatusListBody${loading && result !== null ? 'is-loading' : ''}`}
            >
                {loading && result === null ? (
                    <p className="spkAlertModalEmpty">Memuat data...</p>
                ) : result?.failed ? (
                    <p className="spkAlertModalEmpty">
                        Gagal memuat data. Silakan coba lagi.
                    </p>
                ) : rows.length === 0 ? (
                    <p className="spkAlertModalEmpty">
                        Tidak ada SPK ditemukan.
                    </p>
                ) : (
                    <table className="spkAlertModalTable">
                        <thead>
                            <tr>
                                <th className="spkStatusListColDoc">ID</th>
                                <th>Item</th>
                                <th>Dibuat Oleh</th>
                                <th>Tanggal</th>
                                <th className="spkTableColCenter">
                                    Target Selesai
                                </th>
                                <th className="spkTableColCenter">
                                    Proses Terakhir
                                </th>
                                <th className="spkTableColCenter">
                                    Berat Terakhir
                                </th>
                                <th className="spkTableColCenter">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const targetHint =
                                    row.targetDaysLeft !== null &&
                                    isListRowIncomplete(row.status)
                                        ? targetDaysLeftHint(row.targetDaysLeft)
                                        : null;
                                const isSelected =
                                    selectedRow?.rowId === row.rowId;

                                return (
                                    <tr
                                        key={row.rowId}
                                        tabIndex={0}
                                        aria-selected={isSelected}
                                        className={`spkAlertModalRow${isSelected ? 'is-selected' : ''}`}
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
                                        <td className="spkStatusListColDoc">
                                            <div className="flex flex-col items-start gap-1">
                                                <span className="spkAlertModalTableIdentifier">
                                                    {row.produksiNo}
                                                </span>
                                                <div className="flex items-center gap-1">
                                                    <span
                                                        className={`spkTableBadge ${tipeProduksiBadgeClass(row.tipeProduksi)}`}
                                                    >
                                                        {row.tipeProduksi}
                                                    </span>
                                                    <SpkPaymentStatusBadge
                                                        status={
                                                            row.paymentStatus
                                                        }
                                                    />
                                                </div>
                                                {row.orderReference ? (
                                                    <span className="spkTableDocMeta">
                                                        {row.orderReference}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="spkStatusListColItem">
                                            <div className="flex items-start gap-3">
                                                <SpkItemThumbnail
                                                    imageUrl={row.spkImageUrl}
                                                    spkNo={row.produksiNo}
                                                />
                                                <SpkTableDescriptionCell
                                                    row={row}
                                                />
                                            </div>
                                        </td>
                                        <td>{row.createdBy ?? '—'}</td>
                                        <td>
                                            {displayListDate(row.createdDate)}
                                        </td>
                                        <td className="spkTableColCenter">
                                            <div className="flex flex-col">
                                                <span>
                                                    {displayListDate(
                                                        row.estimatedDelivery,
                                                    )}
                                                </span>
                                                {targetHint ? (
                                                    <span
                                                        className={`text-xs ${targetHint.className}`}
                                                    >
                                                        {targetHint.label}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="spkTableColCenter">
                                            <SpkTableLastProcessCell
                                                row={row}
                                            />
                                        </td>
                                        <td className="spkTableColCenter">
                                            {row.lastWeight !== null
                                                ? `${row.lastWeight} g`
                                                : '—'}
                                        </td>
                                        <td className="spkTableColCenter">
                                            <SpkTableStatusCell row={row} />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>

            {meta !== null && meta.total > 0 ? (
                <div className="spkTableFooter spkStatusListFooter">
                    <div className="spkTableTotal">
                        Total {meta.total.toLocaleString('id-ID')} SPK
                    </div>
                    <div className="spkPagination">
                        <button
                            type="button"
                            className="spkPageBtn"
                            disabled={loading || meta.currentPage <= 1}
                            onClick={() => setPage(meta.currentPage - 1)}
                        >
                            Sebelumnya
                        </button>
                        <button type="button" className="spkPageBtn is-active">
                            {meta.currentPage}
                        </button>
                        <button
                            type="button"
                            className="spkPageBtn"
                            disabled={
                                loading || meta.currentPage >= meta.lastPage
                            }
                            onClick={() => setPage(meta.currentPage + 1)}
                        >
                            Berikutnya
                        </button>
                    </div>
                </div>
            ) : null}

            {selectedRow !== null ? (
                <SelectedSpkAlert row={selectedRow} />
            ) : null}

            <DialogFooter>
                <Button design="Default" type="Button" onClick={onCancel}>
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
                    Pilih
                </Button>
            </DialogFooter>
        </>
    );
}
