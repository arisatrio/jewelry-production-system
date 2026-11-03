import { Head, Link, router } from '@inertiajs/react';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useEffect, useMemo, useState } from 'react';
import { ActiveFilterSummary } from '@/components/active-filter-summary';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as stonePurchasesIndex } from '@/routes/inventory/stone-purchases';
import { show as spkShow } from '@/routes/spk';

type StonePurchaseRow = {
    lineId: number;
    spkId: number;
    spkNo: string;
    customerName: string | null;
    itemName: string | null;
    positionName: string | null;
    shapeName: string | null;
    size: string | null;
    pcs: number;
    caratPerPcs: string;
    totalCarat: string;
    fulfillmentLabel: string;
    orderedAt: string | null;
};

type StonesPaginator = {
    data: StonePurchaseRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type PageProps = {
    stones: StonesPaginator;
    listAvailable: boolean;
    filters: {
        search: string;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;
const DEFAULT_PER_PAGE = 25;

const PER_PAGE_FILTER_OPTIONS = PAGE_SIZE_OPTIONS.map((size) => ({
    value: String(size),
    label: String(size),
}));

function formatCrt(value: string): string {
    const numeric = Number.parseFloat(value);

    if (!Number.isFinite(numeric)) {
        return value;
    }

    return numeric.toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

function formatDateDisplay(value: string | null): string {
    const trimmed = value?.trim() ?? '';

    if (trimmed === '') {
        return '—';
    }

    const [datePart, timePart] = trimmed.split(/\s+/u);
    const [year, month, day] = (datePart ?? '').split('-');

    if (!year || !month || !day) {
        return trimmed;
    }

    const dateLabel = `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year}`;

    if (!timePart) {
        return dateLabel;
    }

    return `${dateLabel} ${timePart.slice(0, 5)}`;
}

export default function StonePurchaseIndex({
    stones,
    listAvailable,
    filters,
}: PageProps) {
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

    const visit = (params: { page?: number; per_page?: number; search?: string }) => {
        router.get(
            stonePurchasesIndex.url({
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
        () => Math.max(1, stones.last_page),
        [stones.last_page],
    );

    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasEntriesDraftChanges = Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom = entriesDraft !== String(DEFAULT_PER_PAGE);

    const applyEntries = () => {
        setEntriesMenuOpen(false);
        visit({ per_page: Number(entriesDraft), page: 1 });
    };

    const clearEntries = () => {
        setEntriesDraft(String(DEFAULT_PER_PAGE));
        setEntriesMenuOpen(false);
        visit({ per_page: DEFAULT_PER_PAGE, page: 1 });
    };

    const clearAllFilters = () => {
        setSearchQuery('');
        visit({ search: '', page: 1 });
    };

    return (
        <>
            <Head title="Pembelian Batu" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Pembelian Batu
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Batu SPK dengan status sedang diorder
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
                                    aria-label="Cari pembelian batu"
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
                                                <span>Baris per halaman</span>
                                                <select
                                                    value={entriesDraft}
                                                    aria-label="Baris per halaman"
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
                        </div>
                    </div>

                    {!listAvailable ? (
                        <MessageStrip design="Warning" hideCloseButton>
                            Kolom status pemenuhan batu SPK belum tersedia di
                            database.
                        </MessageStrip>
                    ) : null}

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
                        onClearAll={clearAllFilters}
                    />

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th className="spkTableColCenter">#</th>
                                    <th>No. SPK</th>
                                    <th>Pelanggan</th>
                                    <th>Item</th>
                                    <th>Posisi</th>
                                    <th>Bentuk</th>
                                    <th className="spkTableColCenter">Ukuran</th>
                                    <th className="spkTableColCenter">Pcs</th>
                                    <th className="spkTableColCenter">CRT/pcs</th>
                                    <th className="spkTableColCenter">Total CRT</th>
                                    <th className="spkTableColCenter">Status</th>
                                    <th className="spkTableColCenter">Diorder</th>
                                </tr>
                            </thead>
                            <tbody>
                                {stones.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={12}>
                                            {listAvailable
                                                ? 'Tidak ada batu SPK yang sedang diorder.'
                                                : 'Data tidak tersedia.'}
                                        </td>
                                    </tr>
                                ) : (
                                    stones.data.map((row, index) => (
                                        <tr key={row.lineId}>
                                            <td className="spkTableColCenter tabular-nums">
                                                {(stones.current_page - 1) *
                                                    stones.per_page +
                                                    index +
                                                    1}
                                            </td>
                                            <td className="font-medium">
                                                <Link
                                                    href={spkShow.url(row.spkId)}
                                                    className="spkProduksiLink"
                                                >
                                                    {row.spkNo}
                                                </Link>
                                            </td>
                                            <td>{row.customerName ?? '—'}</td>
                                            <td>{row.itemName ?? '—'}</td>
                                            <td>{row.positionName ?? '—'}</td>
                                            <td>{row.shapeName ?? '—'}</td>
                                            <td className="spkTableColCenter">
                                                {row.size ?? '—'}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {row.pcs}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {formatCrt(row.caratPerPcs)}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {formatCrt(row.totalCarat)}
                                            </td>
                                            <td className="spkTableColCenter">
                                                <span className="spkStoneStockBadge spkChainFulfillmentBadge--progress">
                                                    {row.fulfillmentLabel}
                                                </span>
                                            </td>
                                            <td className="spkTableColCenter">
                                                {formatDateDisplay(row.orderedAt)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            {stones.total} baris · halaman {stones.current_page}{' '}
                            / {totalPages}
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={stones.current_page <= 1}
                                onClick={() =>
                                    visit({ page: stones.current_page - 1 })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {stones.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={stones.current_page >= totalPages}
                                onClick={() =>
                                    visit({ page: stones.current_page + 1 })
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

StonePurchaseIndex.layout = {
    activeMenu: 'Pembelian Batu',
    pageTitle: 'Pembelian Batu',
};
