import { Head, Link, router } from '@inertiajs/react';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
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
import { index as diamondDossiersIndex, show as diamondDossierShow } from '@/routes/inventory/diamond-dossiers';
import { index as diamondDossierTransactionsIndex } from '@/routes/inventory/diamond-dossier-transactions';
import { show as pasangBatuShow } from '@/routes/pasang-batu';
import { show as spkShow } from '@/routes/spk';

type DossierEventType = 'entry' | 'out';

type DossierTransactionRow = {
    id: string;
    eventType: DossierEventType;
    eventLabel: string;
    eventDate: string | null;
    diamondId: number;
    code: string | null;
    shape: string | null;
    color: string | null;
    crt: string | null;
    certificate: string | null;
    supplier: string | null;
    mountingDocumentNo: string | null;
    mountingDocumentId: number | null;
    spkNo: string | null;
    actor: string | null;
    actorAt: string | null;
};

type TransactionsPaginator = {
    data: DossierTransactionRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type PageProps = {
    transactions: TransactionsPaginator;
    filters: {
        search: string;
        date_from: string | null;
        date_to: string | null;
        event_type: DossierEventType | null;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;
const DEFAULT_PER_PAGE = 50;

const PER_PAGE_FILTER_OPTIONS = PAGE_SIZE_OPTIONS.map((size) => ({
    value: String(size),
    label: String(size),
}));

const EVENT_TYPE_OPTIONS: {
    value: '' | DossierEventType;
    label: string;
}[] = [
    { value: '', label: 'Semua jenis' },
    { value: 'entry', label: 'Penambahan' },
    { value: 'out', label: 'Pemakaian' },
];

function eventTypeLabel(value: DossierEventType | null): string | null {
    if (value === null) {
        return null;
    }

    return EVENT_TYPE_OPTIONS.find((option) => option.value === value)?.label
        ?? value;
}

function formatCrt(value: string | null): string {
    if (value === null) {
        return '—';
    }

    const numeric = Number.parseFloat(value);

    if (!Number.isFinite(numeric)) {
        return value;
    }

    return numeric.toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

const MONTH_LABELS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
] as const;

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

    const monthLabel = MONTH_LABELS[Number(month) - 1];

    if (!monthLabel) {
        return trimmed;
    }

    const dateLabel = `${day.padStart(2, '0')}-${monthLabel}-${year}`;

    if (!timePart) {
        return dateLabel;
    }

    return `${dateLabel} ${timePart.slice(0, 5)}`;
}

export default function DiamondDossierTransactionsIndex({
    transactions,
    filters,
}: PageProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [syncedSearch, setSyncedSearch] = useState(filters.search);
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [entriesDraft, setEntriesDraft] = useState(String(filters.per_page));
    const [filterMenuOpen, setFilterMenuOpen] = useState(false);
    const [eventTypeDraft, setEventTypeDraft] = useState(
        filters.event_type ?? '',
    );
    const [dateFromDraft, setDateFromDraft] = useState(
        filters.date_from ?? '',
    );
    const [dateToDraft, setDateToDraft] = useState(filters.date_to ?? '');

    if (syncedSearch !== filters.search) {
        setSyncedSearch(filters.search);
        setSearchQuery(filters.search);
    }

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    useEffect(() => {
        setEventTypeDraft(filters.event_type ?? '');
        setDateFromDraft(filters.date_from ?? '');
        setDateToDraft(filters.date_to ?? '');
    }, [filters.event_type, filters.date_from, filters.date_to]);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
        date_from?: string | null;
        date_to?: string | null;
        event_type?: DossierEventType | null;
    }) => {
        router.get(
            diamondDossierTransactionsIndex.url({
                query: {
                    search: (params.search ?? filters.search) || undefined,
                    date_from:
                        (params.date_from ?? filters.date_from) || undefined,
                    date_to: (params.date_to ?? filters.date_to) || undefined,
                    event_type:
                        (params.event_type ?? filters.event_type) || undefined,
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

    const hasActiveEventFilter = filters.event_type !== null;
    const hasActiveDateFrom = filters.date_from !== null && filters.date_from !== '';
    const hasActiveDateTo = filters.date_to !== null && filters.date_to !== '';
    const hasActiveTableFilters =
        hasActiveEventFilter || hasActiveDateFrom || hasActiveDateTo;

    const hasFilterDraftChanges =
        eventTypeDraft !== (filters.event_type ?? '') ||
        dateFromDraft !== (filters.date_from ?? '') ||
        dateToDraft !== (filters.date_to ?? '');

    const hasFilterDraftCustom =
        eventTypeDraft !== '' ||
        dateFromDraft !== '' ||
        dateToDraft !== '';

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

    const applyTableFilters = () => {
        setFilterMenuOpen(false);
        visit({
            page: 1,
            event_type: (eventTypeDraft as DossierEventType) || null,
            date_from: dateFromDraft || null,
            date_to: dateToDraft || null,
        });
    };

    const clearTableFilters = () => {
        setEventTypeDraft('');
        setDateFromDraft('');
        setDateToDraft('');
        setFilterMenuOpen(false);
        visit({
            page: 1,
            event_type: null,
            date_from: null,
            date_to: null,
        });
    };

    const clearAllFilters = () => {
        setSearchQuery('');
        setEventTypeDraft('');
        setDateFromDraft('');
        setDateToDraft('');
        visit({
            search: '',
            page: 1,
            event_type: null,
            date_from: null,
            date_to: null,
        });
    };

    return (
        <>
            <Head title="Transaksi Batu Dossier" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Transaksi Batu Dossier
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Log masuk dan keluar batu dossier
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
                                    aria-label="Cari log transaksi batu dossier"
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

                                <DropdownMenu
                                    open={filterMenuOpen}
                                    onOpenChange={(open) => {
                                        setFilterMenuOpen(open);

                                        if (open) {
                                            setEventTypeDraft(
                                                filters.event_type ?? '',
                                            );
                                            setDateFromDraft(
                                                filters.date_from ?? '',
                                            );
                                            setDateToDraft(
                                                filters.date_to ?? '',
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
                                                hasActiveTableFilters
                                                    ? 'is-active'
                                                    : '',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            aria-label="Filter"
                                            title="Filter"
                                        >
                                            <Icon
                                                name={filterIcon}
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
                                                <span>Jenis</span>
                                                <select
                                                    value={eventTypeDraft}
                                                    aria-label="Jenis transaksi"
                                                    onChange={(event) =>
                                                        setEventTypeDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {EVENT_TYPE_OPTIONS.map(
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
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Tanggal dari</span>
                                                <input
                                                    type="date"
                                                    value={dateFromDraft}
                                                    aria-label="Tanggal dari"
                                                    onChange={(event) =>
                                                        setDateFromDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                />
                                            </label>
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Tanggal sampai</span>
                                                <input
                                                    type="date"
                                                    value={dateToDraft}
                                                    aria-label="Tanggal sampai"
                                                    onChange={(event) =>
                                                        setDateToDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                />
                                            </label>
                                            <div className="spkTableHeaderSortActions--finishing">
                                                <button
                                                    type="button"
                                                    className="spkTableHeaderSortClear--finishing"
                                                    disabled={
                                                        !hasFilterDraftCustom &&
                                                        !hasActiveTableFilters
                                                    }
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        clearTableFilters();
                                                    }}
                                                >
                                                    Hapus
                                                </button>
                                                <Button
                                                    design="Default"
                                                    disabled={
                                                        !hasFilterDraftChanges
                                                    }
                                                    className="spkTableHeaderSortApply--finishing"
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        applyTableFilters();
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
                                aria-label="Kembali ke stok batu dossier"
                                onClick={() =>
                                    router.visit(diamondDossiersIndex.url())
                                }
                            >
                                <Icon
                                    name={navBackIcon}
                                    mode="Decorative"
                                />
                                <span>Kembali ke Stok</span>
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
                            hasActiveEventFilter && {
                                key: 'event_type',
                                label: 'Jenis',
                                value:
                                    eventTypeLabel(filters.event_type) ?? '—',
                                onRemove: () => {
                                    visit({ event_type: null, page: 1 });
                                },
                            },
                            hasActiveDateFrom && {
                                key: 'date_from',
                                label: 'Dari',
                                value: filters.date_from ?? '',
                                onRemove: () => {
                                    visit({ date_from: null, page: 1 });
                                },
                            },
                            hasActiveDateTo && {
                                key: 'date_to',
                                label: 'Sampai',
                                value: filters.date_to ?? '',
                                onRemove: () => {
                                    visit({ date_to: null, page: 1 });
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
                                    <th>Tanggal</th>
                                    <th className="spkTableColCenter">Jenis</th>
                                    <th>Kode</th>
                                    <th>Bentuk</th>
                                    <th className="spkTableColCenter">CRT</th>
                                    <th className="spkTableColCenter">Warna</th>
                                    <th className="spkTableColCenter">No. Sertifikat</th>
                                    <th className="spkTableColCenter">Supplier</th>
                                    <th className="spkTableColCenter">No. Pasang Batu</th>
                                    <th className="spkTableColCenter">No. SPK</th>
                                    <th>Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={12}>
                                            Tidak ada data transaksi dossier.
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
                                            <td>
                                                {formatDateDisplay(row.eventDate)}
                                            </td>
                                            <td className="spkTableColCenter">
                                                <span
                                                    className={
                                                        row.eventType ===
                                                        'entry'
                                                            ? 'spkStoneStockBadge is-available'
                                                            : 'spkStoneStockBadge is-unavailable'
                                                    }
                                                >
                                                    {row.eventLabel}
                                                </span>
                                            </td>
                                            <td className="font-medium">
                                                {row.code !== null &&
                                                row.code !== '' ? (
                                                    <Link
                                                        href={diamondDossierShow.url(
                                                            row.diamondId,
                                                        )}
                                                        className="spkProduksiLink"
                                                    >
                                                        {row.code}
                                                    </Link>
                                                ) : (
                                                    (row.code ?? '—')
                                                )}
                                            </td>
                                            <td>{row.shape ?? '—'}</td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {formatCrt(row.crt)}
                                            </td>
                                            <td className="spkTableColCenter">{row.color ?? '—'}</td>
                                            <td className="spkTableColCenter">{row.certificate ?? '—'}</td>
                                            <td className="spkTableColCenter">{row.supplier ?? '—'}</td>
                                            <td className="spkTableColCenter">
                                                {row.mountingDocumentId !==
                                                    null &&
                                                row.mountingDocumentNo !==
                                                    null ? (
                                                    <Link
                                                        href={pasangBatuShow.url(
                                                            row.mountingDocumentId,
                                                        )}
                                                        className="spkProduksiLink"
                                                    >
                                                        {
                                                            row.mountingDocumentNo
                                                        }
                                                    </Link>
                                                ) : (
                                                    row.mountingDocumentNo ??
                                                    '—'
                                                )}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {row.spkNo !== null &&
                                                row.spkNo !== '' ? (
                                                    <Link
                                                        href={spkShow.url(
                                                            row.spkNo,
                                                        )}
                                                        className="spkProduksiLink"
                                                    >
                                                        {row.spkNo}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {row.actor !== null ||
                                                row.actorAt !== null ? (
                                                    <div className="spkTableDescription items-center text-center">
                                                        <span>
                                                            {row.actor ?? '—'}
                                                        </span>
                                                        {row.actorAt ? (
                                                            <span className="spkTableDescriptionItem">
                                                                {row.actorAt}
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total {transactions.total} baris
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

DiamondDossierTransactionsIndex.layout = {
    activeMenu: 'Transaksi Batu Dossier',
    pageTitle: 'Transaksi Batu Dossier',
};
