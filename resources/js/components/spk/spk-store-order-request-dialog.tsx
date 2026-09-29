import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useState } from 'react';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import {
    statusBadgeClass,
    targetDaysLeftHint,
} from '@/components/spk/spk-list-cells';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { storeOrderRequests } from '@/routes/spk';

type StoreOrderRequestRow = {
    rowId: number;
    docNo: string;
    transDate: string;
    estimatedDate: string;
    targetDaysLeft: number | null;
    store: string;
    customer: string;
    item: string;
    refSku: string | null;
    typeOrder: string | null;
    paymentStatus: string | null;
    notes: string | null;
    imageUrl: string | null;
    goldInfo: string | null;
    createdBy: string | null;
    spks: { spkNo: string; status: string }[];
};

type StoreOrderRequestTab = 'pending' | 'with_spk';

type StoreOrderRequestMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    tabCounts: Record<StoreOrderRequestTab, number>;
};

type StoreOrderRequestResult = {
    requestKey: string;
    rows: StoreOrderRequestRow[];
    meta: StoreOrderRequestMeta | null;
    failed: boolean;
};

type SpkStoreOrderRequestDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const SEARCH_DEBOUNCE_MS = 300;

const STORE_ORDER_REQUEST_TABS: { id: StoreOrderRequestTab; label: string }[] =
    [
        { id: 'pending', label: 'Belum Dibuat SPK' },
        { id: 'with_spk', label: 'Sudah Dibuat SPK' },
    ];

export function SpkStoreOrderRequestDialog({
    open,
    onOpenChange,
}: SpkStoreOrderRequestDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStatusListModal">
                <DialogHeader>
                    <DialogTitle>Permintaan Pesanan Toko</DialogTitle>
                    <DialogDescription>
                        Pesanan dari toko, dipisahkan berdasarkan status
                        pembuatan SPK.
                    </DialogDescription>
                </DialogHeader>
                {open ? <SpkStoreOrderRequestBody /> : null}
            </DialogContent>
        </Dialog>
    );
}

function SpkStoreOrderRequestBody() {
    const [tab, setTab] = useState<StoreOrderRequestTab>('pending');
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<StoreOrderRequestResult | null>(null);
    const requestKey = `${tab}|${page}|${search}`;
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

        if (tab !== 'pending') {
            query.tab = tab;
        }

        if (page > 1) {
            query.page = page;
        }

        if (search !== '') {
            query.search = search;
        }

        fetch(storeOrderRequests.url({ query }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = (await response.json()) as {
                    data?: StoreOrderRequestRow[];
                    meta?: StoreOrderRequestMeta;
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
    }, [tab, page, search, requestKey]);

    const selectTab = (nextTab: StoreOrderRequestTab) => {
        setTab(nextTab);
        setPage(1);
    };

    return (
        <>
            <div className="spkStatusListToolbar spkStoreOrderRequestToolbar">
                <div
                    className="spkSectionTabs spkStoreOrderRequestTabs"
                    role="tablist"
                    aria-label="Status pembuatan SPK"
                >
                    {STORE_ORDER_REQUEST_TABS.map((tabOption) => (
                        <button
                            key={tabOption.id}
                            type="button"
                            role="tab"
                            aria-selected={tab === tabOption.id}
                            className={[
                                'spkSectionTab',
                                tab === tabOption.id ? 'is-active' : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            onClick={() => selectTab(tabOption.id)}
                        >
                            <span className="spkSectionTabLabel">
                                {tabOption.label}
                                {meta !== null ? (
                                    <span className="spkStoreOrderRequestTabCount">
                                        {meta.tabCounts[
                                            tabOption.id
                                        ].toLocaleString('id-ID')}
                                    </span>
                                ) : null}
                            </span>
                        </button>
                    ))}
                </div>
                <div
                    className="spkTableHeaderSearch--finishing spkStatusListSearch"
                    role="search"
                >
                    <input
                        type="search"
                        className="spkTableHeaderSearchInput--finishing"
                        placeholder="Cari No Pesanan, No SPK, customer, item, toko..."
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        aria-label="Cari pesanan toko"
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
                        {tab === 'with_spk'
                            ? 'Tidak ada pesanan toko yang sudah dibuatkan SPK.'
                            : 'Tidak ada pesanan toko yang belum dibuatkan SPK.'}
                    </p>
                ) : (
                    <table className="spkAlertModalTable spkReceiptHistoryTable">
                        <thead>
                            <tr>
                                <th>No Pesanan</th>
                                <th>No SPK</th>
                                <th>Item</th>
                                <th>Tanggal</th>
                                <th>Dibuat Oleh</th>
                                <th className="spkTableColCenter">
                                    Target Delivery
                                </th>
                                <th className="spkTableColCenter">
                                    Status SPK
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.rowId}>
                                    <td className="whitespace-nowrap">
                                        <div className="flex flex-col">
                                            <span className="spkAlertModalTableIdentifier">
                                                {row.docNo}
                                            </span>
                                            {row.customer !== '-' ? (
                                                <span className="text-sm">
                                                    {row.customer}
                                                </span>
                                            ) : null}
                                            {row.typeOrder ||
                                            row.paymentStatus ? (
                                                <span className="spkAlertModalTableSubText">
                                                    {[
                                                        row.typeOrder,
                                                        row.paymentStatus,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            ) : null}
                                        </div>
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {row.spks.length > 0 ? (
                                            <div className="flex flex-col gap-1">
                                                {row.spks.map((spk) => (
                                                    <span key={spk.spkNo}>
                                                        {spk.spkNo}
                                                    </span>
                                                ))}
                                            </div>
                                        ) : null}
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
                                                    <span className="spkAlertModalTableSubText whitespace-pre-line">
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
                                        {row.spks.length > 0 ? (
                                            <div className="flex flex-col items-center gap-1">
                                                {row.spks.map((spk) => (
                                                    <span
                                                        key={spk.spkNo}
                                                        className={`spkTableBadge ${statusBadgeClass(spk.status)}`}
                                                    >
                                                        {spk.status}
                                                    </span>
                                                ))}
                                            </div>
                                        ) : null}
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
                        Total {meta.total.toLocaleString('id-ID')} pesanan
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
