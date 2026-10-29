import { Head, Link, router } from '@inertiajs/react';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import navBackIcon from '@ui5/webcomponents-icons/dist/nav-back.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useMemo, useState } from 'react';
import { ActiveFilterSummary } from '@/components/active-filter-summary';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { sourceTypeLabel } from '@/pages/inventory/component/component-source-type';
import { formatUsd } from '@/pages/inventory/component/format-usd';
import { index as chainsIndex, show } from '@/routes/inventory/components';
import { index as chainTransactionsIndex } from '@/routes/inventory/component-transactions';

type ChainTransactionRow = {
    id: number;
    unitNo: string | null;
    msComponentId: number;
    componentCategory: string | null;
    masterItem: string | null;
    masterItemCode: string | null;
    name: string;
    sourceType: string;
    componentSize: string;
    weight: string | null;
    color: string | null;
    supplier: string | null;
    purchasePrice: string | null;
    entryDate: string | null;
    outDate: string | null;
    spkNo: string | null;
    notes: string | null;
    status: 'in_stock' | 'issued';
    createdBy: string | null;
    createdDate: string | null;
};

type TransactionsPaginator = {
    data: ChainTransactionRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type ChainTransactionsIndexProps = {
    transactions: TransactionsPaginator;
    filters: {
        search: string;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;
const DEFAULT_PER_PAGE = 50;

const PER_PAGE_FILTER_OPTIONS = PAGE_SIZE_OPTIONS.map((size) => ({
    value: String(size),
    label: String(size),
}));

export default function ChainTransactionsIndex({
    transactions,
    filters,
}: ChainTransactionsIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [syncedSearch, setSyncedSearch] = useState(filters.search);
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [entriesDraft, setEntriesDraft] = useState(String(filters.per_page));

    if (syncedSearch !== filters.search) {
        setSyncedSearch(filters.search);
        setSearchQuery(filters.search);
    }

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
    }) => {
        router.get(
            chainTransactionsIndex.url({
                query: {
                    search: (params.search ?? filters.search) || undefined,
                    per_page: params.per_page ?? filters.per_page,
                    page: params.page ?? 1,
                },
            }),
            {},
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            visit({ search: searchQuery, page: 1 });
        }, 300);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce search only
    }, [searchQuery, filters.search]);

    const totalPages = useMemo(
        () => Math.max(1, transactions.last_page),
        [transactions.last_page],
    );

    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasEntriesDraftChanges =
        Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom =
        entriesDraft !== String(DEFAULT_PER_PAGE);

    const applyEntries = () => {
        setEntriesMenuOpen(false);
        visit({
            page: 1,
            per_page: Number(entriesDraft),
        });
    };

    const clearEntries = () => {
        setEntriesDraft(String(DEFAULT_PER_PAGE));
        setEntriesMenuOpen(false);
        visit({
            page: 1,
            per_page: DEFAULT_PER_PAGE,
        });
    };

    return (
        <>
            <Head title="Transaksi Komponen" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Transaksi Komponen
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Semua unit stok trxchain di seluruh master
                                    chain
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <div
                                className="spkTableHeaderSearch--finishing"
                                role="search"
                            >
                                <input
                                    className="spkTableHeaderSearchInput--finishing"
                                    type="search"
                                    placeholder="Cari"
                                    value={searchQuery}
                                    autoComplete="off"
                                    aria-label="Cari log transaksi chain"
                                    onChange={(event) =>
                                        setSearchQuery(event.target.value)
                                    }
                                />
                                <Icon
                                    className="spkTableHeaderSearchIcon--finishing"
                                    name={searchIcon}
                                />
                            </div>
                            <div
                                className="spkTableHeaderActions--finishing"
                                role="group"
                                aria-label="Aksi tabel"
                            >
                                <DropdownMenu
                                    open={entriesMenuOpen}
                                    onOpenChange={(open) => {
                                        setEntriesMenuOpen(open);

                                        if (open) {
                                            setEntriesDraft(
                                                String(filters.per_page),
                                            );
                                        }
                                    }}
                                >
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className={[
                                                'spkTableHeaderIconBtn--finishing',
                                                'spkTableHeaderIconTrigger--finishing',
                                                hasCustomEntries
                                                    ? 'is-active'
                                                    : '',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            aria-label="Tampilkan entri"
                                            title="Tampilkan entri"
                                        >
                                            <Icon
                                                name={listIcon}
                                                mode="Decorative"
                                            />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="spkTableHeaderMenu--finishing spkTableHeaderSortMenu--finishing"
                                        onCloseAutoFocus={(event) =>
                                            event.preventDefault()
                                        }
                                    >
                                        <div className="spkTableHeaderSortFields--finishing">
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Tampilkan</span>
                                                <select
                                                    value={entriesDraft}
                                                    aria-label="Tampilkan entri"
                                                    onChange={(event) =>
                                                        setEntriesDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {PER_PAGE_FILTER_OPTIONS.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>
                                            <div className="spkTableHeaderSortActions--finishing">
                                                <button
                                                    type="button"
                                                    className="spkTableHeaderSortClear--finishing"
                                                    disabled={
                                                        !hasEntriesDraftCustom &&
                                                        !hasCustomEntries
                                                    }
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        clearEntries();
                                                    }}
                                                >
                                                    Hapus
                                                </button>
                                                <Button
                                                    design="Default"
                                                    disabled={
                                                        !hasEntriesDraftChanges
                                                    }
                                                    className="spkTableHeaderSortApply--finishing"
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        applyEntries();
                                                    }}
                                                >
                                                    Terapkan
                                                </Button>
                                            </div>
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            <span
                                className="spkTableHeaderDivider--finishing"
                                aria-hidden="true"
                            />
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Kembali ke chain"
                                onClick={() =>
                                    router.visit(chainsIndex.url())
                                }
                            >
                                <Icon
                                    name={navBackIcon}
                                    mode="Decorative"
                                />
                                <span>Kembali ke Chain</span>
                            </button>
                        </div>
                    </div>

                    <ActiveFilterSummary
                        sort={null}
                        filters={[
                            filters.search !== '' && {
                                key: 'search',
                                label: 'Pencarian',
                                value: filters.search,
                                onRemove: () => {
                                    setSearchQuery('');
                                    visit({ search: '', page: 1 });
                                },
                            },
                        ]}
                        onClearAll={() => {
                            setSearchQuery('');
                            visit({ search: '', page: 1 });
                        }}
                    />

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th className="spkTableColCenter">#</th>
                                    <th>ID Unit</th>
                                    <th>Kategori</th>
                                    <th>Master</th>
                                    <th>Nama unit</th>
                                    <th>Sumber</th>
                                    <th>Tersedia</th>
                                    <th>Terpakai</th>
                                    <th>SPK</th>
                                    <th className="spkTableColCenter">
                                        Panjang
                                    </th>
                                    <th className="spkTableColCenter">
                                        Berat
                                    </th>
                                    <th>Warna</th>
                                    <th>Supplier</th>
                                    <th className="spkTableColCenter">
                                        Harga beli (USD)
                                    </th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={15}>
                                            Tidak ada data trxchain.
                                        </td>
                                    </tr>
                                ) : (
                                    transactions.data.map((row, index) => (
                                        <tr key={row.id}>
                                            <td className="spkTableColCenter tabular-nums">
                                                {(transactions.current_page - 1) *
                                                    transactions.per_page +
                                                    index +
                                                    1}
                                            </td>
                                            <td className="tabular-nums font-medium">
                                                {row.unitNo ?? '—'}
                                            </td>
                                            <td>{row.componentCategory ?? '—'}</td>
                                            <td>
                                                <Link
                                                    href={show.url(row.msComponentId)}
                                                    className="spkProduksiLink"
                                                >
                                                    {row.masterItemCode ??
                                                        row.masterItem ??
                                                        '—'}
                                                </Link>
                                            </td>
                                            <td>{row.name}</td>
                                            <td>
                                                {sourceTypeLabel(row.sourceType)}
                                            </td>
                                            <td>{row.entryDate ?? '—'}</td>
                                            <td>{row.outDate ?? '—'}</td>
                                            <td>{row.spkNo ?? '—'}</td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {row.componentSize} cm
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {row.weight ?? '—'}
                                            </td>
                                            <td>{row.color ?? '—'}</td>
                                            <td>{row.supplier ?? '—'}</td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {formatUsd(row.purchasePrice)}
                                            </td>
                                            <td>
                                                {row.status === 'in_stock'
                                                    ? 'Ready'
                                                    : 'Used'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total {transactions.total} unit
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={transactions.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: transactions.current_page - 1,
                                    })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {transactions.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={
                                    transactions.current_page >= totalPages
                                }
                                onClick={() =>
                                    visit({
                                        page: transactions.current_page + 1,
                                    })
                                }
                            >
                                Berikutnya
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

ChainTransactionsIndex.layout = {
    activeMenu: 'Transaksi Komponen',
    pageTitle: 'Transaksi Komponen',
};
