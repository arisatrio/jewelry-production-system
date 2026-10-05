import { Link } from '@inertiajs/react';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import { useEffect, useState } from 'react';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import { targetDaysLeftHint } from '@/components/spk/spk-list-cells';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { create as spkCreate, storeStockRequests } from '@/routes/spk';

type StoreStockRequestRow = {
    rowId: number;
    docNo: string;
    transDate: string;
    transDateIso: string | null;
    estimatedDate: string;
    estimatedDateIso: string | null;
    targetDaysLeft: number | null;
    store: string;
    item: string;
    refSku: string | null;
    typeOrder: string | null;
    status: string | null;
    approvedAt: string | null;
    notes: string | null;
    imageUrl: string | null;
    goldInfo: string | null;
    goldWeight: string | null;
    createdBy: string | null;
};

type StoreStockRequestMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
};

type StoreStockRequestResult = {
    requestKey: string;
    rows: StoreStockRequestRow[];
    meta: StoreStockRequestMeta | null;
    failed: boolean;
};

type SpkStoreStockRequestDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const SEARCH_DEBOUNCE_MS = 300;

function createSpkUrl(row: StoreStockRequestRow): string {
    const query: Record<string, string> = {};

    if (row.transDateIso) {
        query.order_date = row.transDateIso;
    }

    if (row.estimatedDateIso) {
        query.estimated_delivery_time = row.estimatedDateIso;
    }

    if (row.refSku) {
        query.sku = row.refSku;
    }

    if (row.goldWeight) {
        query.gold_weight = row.goldWeight;
    }

    if (row.docNo !== '' && row.docNo !== '-') {
        query.request_stock_no = row.docNo;
    }

    if (row.notes) {
        query.store_notes = row.notes;
    }

    return spkCreate.url({ query });
}

export function SpkStoreStockRequestDialog({
    open,
    onOpenChange,
}: SpkStoreStockRequestDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStatusListModal">
                <DialogHeader>
                    <DialogTitle>Permintaan Stok Toko</DialogTitle>
                    <DialogDescription>
                        Permintaan stok dari toko yang sudah di-approve.
                    </DialogDescription>
                </DialogHeader>
                {open ? <SpkStoreStockRequestBody /> : null}
            </DialogContent>
        </Dialog>
    );
}

function SpkStoreStockRequestBody() {
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<StoreStockRequestResult | null>(null);
    const requestKey = `${page}|${search}`;
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
        const query: Record<string, string | number> = {};

        if (page > 1) {
            query.page = page;
        }

        if (search !== '') {
            query.search = search;
        }

        fetch(storeStockRequests.url({ query }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = (await response.json()) as {
                    data?: StoreStockRequestRow[];
                    meta?: StoreStockRequestMeta;
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

                setResult({ requestKey, rows: [], meta: null, failed: true });
            });

        return () => controller.abort();
    }, [page, search, requestKey]);

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
                        placeholder="Cari No Request, item, SKU, store..."
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        aria-label="Cari request stok"
                    />
                    <Icon
                        name={searchIcon}
                        className="spkTableHeaderSearchIcon--finishing"
                    />
                </div>
            </div>

            <div
                className={[
                    'spkAlertModalBody spkStatusListBody',
                    loading && result !== null ? 'is-loading' : '',
                ]
                    .filter(Boolean)
                    .join(' ')}
            >
                {loading && result === null ? (
                    <p className="spkAlertModalEmpty">Memuat data...</p>
                ) : result?.failed ? (
                    <p className="spkAlertModalEmpty">
                        Gagal memuat data. Silakan coba lagi.
                    </p>
                ) : rows.length === 0 ? (
                    <p className="spkAlertModalEmpty">
                        Tidak ada request stok approved dari Store.
                    </p>
                ) : (
                    <table className="spkAlertModalTable spkReceiptHistoryTable">
                        <thead>
                            <tr>
                                <th>No Request</th>
                                <th>Item</th>
                                <th>Tanggal</th>
                                <th>Dibuat Oleh</th>
                                <th className="spkTableColCenter">
                                    Target Delivery
                                </th>
                                <th className="spkTableColCenter">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.rowId}>
                                    <td className="spkAlertModalTableIdentifier whitespace-nowrap">
                                        <div className="flex flex-col items-start gap-1.5">
                                            <span>{row.docNo}</span>
                                            <Link
                                                href={createSpkUrl(row)}
                                                className="spkCreateBtn"
                                            >
                                                <Icon
                                                    name={addIcon}
                                                    mode="Decorative"
                                                />
                                                Buat SPK
                                            </Link>
                                        </div>
                                    </td>
                                    <td className="spkReceiptHistoryColWrap">
                                        <div className="flex items-start gap-3">
                                            <SpkItemThumbnail
                                                imageUrl={row.imageUrl}
                                                spkNo={row.docNo}
                                            />
                                            <div className="flex flex-col">
                                                <span>{row.item}</span>
                                                {row.refSku ? (
                                                    <span className="spkAlertModalTableSubText">
                                                        {row.refSku}
                                                    </span>
                                                ) : null}
                                                {row.goldInfo ? (
                                                    <span className="spkAlertModalTableSubText">
                                                        {row.goldInfo}
                                                    </span>
                                                ) : null}
                                                {row.notes ? (
                                                    <span className="spkAlertModalTableSubText">
                                                        {row.notes}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {row.transDate}
                                    </td>
                                    <td>{row.createdBy ?? '—'}</td>
                                    <td className="spkTableColCenter whitespace-nowrap">
                                        <div className="flex flex-col">
                                            <span>{row.estimatedDate}</span>
                                            {row.targetDaysLeft !== null ? (
                                                <TargetDaysLeft
                                                    daysLeft={
                                                        row.targetDaysLeft
                                                    }
                                                />
                                            ) : null}
                                        </div>
                                    </td>
                                    <td className="spkTableColCenter">
                                        <div className="flex flex-col">
                                            <span>{row.status ?? '—'}</span>
                                            {row.approvedAt ? (
                                                <span className="spkAlertModalTableSubText whitespace-nowrap">
                                                    {row.approvedAt}
                                                </span>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {meta !== null && meta.total > 0 ? (
                <div className="spkTableFooter spkStatusListFooter">
                    <div className="spkTableTotal">
                        Total {meta.total.toLocaleString('id-ID')} request
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
        </>
    );
}

function TargetDaysLeft({ daysLeft }: { daysLeft: number }) {
    const hint = targetDaysLeftHint(daysLeft);

    return <span className={`text-xs ${hint.className}`}>{hint.label}</span>;
}
