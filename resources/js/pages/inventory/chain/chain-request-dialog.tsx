import { Link } from '@inertiajs/react';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    requests as chainRequests,
    show as chainShow,
} from '@/routes/inventory/chains';
import { show as spkShow } from '@/routes/spk';

type ChainRequestTab = 'pending' | 'fulfilled';

type ChainRequestRow = {
    lineId: number;
    spkId: number;
    spkNo: string;
    spkStatus: string | null;
    chainCategory: string | null;
    chainItem: string | null;
    chainColor: string | null;
    chainSize: string | null;
    msChainId: number | null;
    masterItemName: string | null;
    masterItemCode: string | null;
    createdDate: string | null;
};

type ChainRequestMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    tabCounts: Record<ChainRequestTab, number>;
};

type ChainRequestResult = {
    requestKey: string;
    rows: ChainRequestRow[];
    meta: ChainRequestMeta | null;
    failed: boolean;
};

type ChainRequestDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const SEARCH_DEBOUNCE_MS = 300;

const CHAIN_REQUEST_TABS: { id: ChainRequestTab; label: string }[] = [
    { id: 'pending', label: 'Belum keluar stok' },
    { id: 'fulfilled', label: 'Sudah keluar stok' },
];

function chainLabel(row: ChainRequestRow): string {
    if (row.masterItemName) {
        return row.masterItemName;
    }

    return [row.chainCategory, row.chainItem, row.chainSize]
        .filter(Boolean)
        .join(' · ');
}

export function ChainRequestDialog({
    open,
    onOpenChange,
}: ChainRequestDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModalContent sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>Permintaan Rantai</DialogTitle>
                    <DialogDescription>
                        Kebutuhan rantai dari SPK (spk_chains) yang perlu
                        dikeluarkan dari stok.
                    </DialogDescription>
                </DialogHeader>

                {open ? <ChainRequestDialogBody /> : null}
            </DialogContent>
        </Dialog>
    );
}

function ChainRequestDialogBody() {
    const [tab, setTab] = useState<ChainRequestTab>('pending');
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<ChainRequestResult | null>(null);
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

        fetch(chainRequests.url({ query }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = (await response.json()) as {
                    data?: ChainRequestRow[];
                    meta?: ChainRequestMeta;
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

    const selectTab = (nextTab: ChainRequestTab) => {
        setTab(nextTab);
        setPage(1);
    };

    return (
        <>
            <div className="spkStatusListToolbar spkStoreOrderRequestToolbar">
                <div
                    className="spkSectionTabs spkStoreOrderRequestTabs"
                    role="tablist"
                    aria-label="Status permintaan rantai"
                >
                    {CHAIN_REQUEST_TABS.map((tabOption) => (
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
                        placeholder="Cari No SPK, item, master chain..."
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        aria-label="Cari permintaan rantai"
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
                        {tab === 'fulfilled'
                            ? 'Tidak ada permintaan rantai yang sudah keluar stok.'
                            : 'Tidak ada permintaan rantai yang menunggu stok.'}
                    </p>
                ) : (
                    <table className="spkAlertModalTable spkReceiptHistoryTable">
                        <thead>
                            <tr>
                                <th>No SPK</th>
                                <th>Rantai</th>
                                <th>Warna</th>
                                <th className="spkTableColCenter">Panjang</th>
                                <th>Master</th>
                                <th className="spkTableColCenter">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.lineId}>
                                    <td className="spkAlertModalTableIdentifier">
                                        <Link
                                            href={spkShow.url(row.spkId)}
                                            className="spkProduksiLink"
                                        >
                                            {row.spkNo}
                                        </Link>
                                    </td>
                                    <td>{chainLabel(row)}</td>
                                    <td>{row.chainColor ?? '—'}</td>
                                    <td className="spkTableColCenter tabular-nums">
                                        {row.chainSize
                                            ? `${row.chainSize} cm`
                                            : '—'}
                                    </td>
                                    <td>
                                        {row.msChainId !== null ? (
                                            <Link
                                                href={chainShow.url(
                                                    row.msChainId,
                                                )}
                                                className="spkProduksiLink"
                                            >
                                                {row.masterItemCode ??
                                                    row.masterItemName ??
                                                    'Detail master'}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="spkTableColCenter">
                                        {row.createdDate ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {meta !== null && meta.lastPage > 1 ? (
                <div className="spkTableFooter">
                    <div className="spkTableTotal">
                        Halaman {meta.currentPage} / {meta.lastPage}
                    </div>
                    <div className="spkPagination">
                        <button
                            type="button"
                            className="spkPageBtn"
                            disabled={meta.currentPage <= 1}
                            onClick={() => setPage(meta.currentPage - 1)}
                        >
                            Sebelumnya
                        </button>
                        <button
                            type="button"
                            className="spkPageBtn is-active"
                        >
                            {meta.currentPage}
                        </button>
                        <button
                            type="button"
                            className="spkPageBtn"
                            disabled={meta.currentPage >= meta.lastPage}
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
