import { Head, router } from '@inertiajs/react';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { useEffect, useMemo, useState } from 'react';
import { index } from '@/routes/inventory/micro-stones';

type StockStatus = 'available' | 'empty';

type MicroStone = {
    id: number;
    name: string;
    parcel: string | null;
    size: string | null;
    shape: string | null;
    pcsIn: string;
    pcsOut: string;
    balancePcs: string;
    balanceCrt: string;
};

type StonesPaginator = {
    data: MicroStone[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type PageProps = {
    stones: StonesPaginator;
    totals: {
        pcs: string;
        crt: string;
    };
    activePeriod: { id: number; label: string } | null;
    filters: {
        search: string;
        stock_status: StockStatus | null;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

const STOCK_STATUS_OPTIONS: { value: '' | StockStatus; label: string }[] = [
    { value: '', label: 'Semua stok' },
    { value: 'available', label: 'Ada stok' },
    { value: 'empty', label: 'Stok kosong / minus' },
];

function formatPcs(value: string): string {
    const numeric = Number(value);
    const formatted = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 2,
    }).format(Math.abs(numeric));

    return numeric < 0 ? `(${formatted})` : formatted;
}

function formatCrt(value: string): string {
    const numeric = Number(value);
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    }).format(Math.abs(numeric));

    return numeric < 0 ? `(${formatted})` : formatted;
}

function balanceClass(value: string): string {
    const numeric = Number(value);

    if (numeric < 0) {
        return 'font-semibold text-red-600';
    }

    if (numeric > 0) {
        return 'font-semibold text-emerald-600';
    }

    return 'font-semibold text-slate-500';
}

export default function MicroStoneIndex({
    stones,
    totals,
    activePeriod,
    filters,
}: PageProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
        stock_status?: StockStatus | null;
    }) => {
        const stockStatus =
            params.stock_status !== undefined
                ? params.stock_status
                : filters.stock_status;

        router.get(
            index.url({
                query: {
                    search: params.search || undefined,
                    stock_status: stockStatus || undefined,
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

            router.get(
                index.url({
                    query: {
                        search: searchQuery || undefined,
                        stock_status: filters.stock_status || undefined,
                        per_page: filters.per_page,
                        page: 1,
                    },
                }),
                {},
                {
                    preserveState: true,
                    replace: true,
                },
            );
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [searchQuery, filters.search, filters.stock_status, filters.per_page]);

    const totalPages = useMemo(
        () => Math.max(1, stones.last_page),
        [stones.last_page],
    );

    return (
        <>
            <Head title="Batu Mikro" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableActions">
                        <div className="spkTableTitleBlock">
                            <h1 className="spkTableTitle">Batu Mikro</h1>
                            {activePeriod ? (
                                <span className="spkTablePageSizeLabel">
                                    Periode {activePeriod.label}
                                </span>
                            ) : null}
                        </div>
                    </div>

                    <div className="spkTableToolbar">
                        <div className="spkTableToolbarLeft">
                            <span className="spkTablePageSizeLabel">Show</span>
                            <Select
                                accessibleName="Jumlah baris per halaman"
                                onChange={(event) =>
                                    visit({
                                        per_page: Number(
                                            event.detail.selectedOption.value,
                                        ),
                                        search: searchQuery,
                                    })
                                }
                            >
                                {PAGE_SIZE_OPTIONS.map((size) => (
                                    <Option
                                        key={size}
                                        value={String(size)}
                                        selected={filters.per_page === size}
                                    >
                                        {size}
                                    </Option>
                                ))}
                            </Select>
                            <span className="spkTablePageSizeLabel">
                                entries
                            </span>
                            <Select
                                accessibleName="Filter status stok"
                                onChange={(event) =>
                                    visit({
                                        search: searchQuery,
                                        stock_status:
                                            (event.detail.selectedOption
                                                .value as StockStatus) || null,
                                    })
                                }
                            >
                                {STOCK_STATUS_OPTIONS.map((option) => (
                                    <Option
                                        key={option.value}
                                        value={option.value}
                                        selected={
                                            (filters.stock_status ?? '') ===
                                            option.value
                                        }
                                    >
                                        {option.label}
                                    </Option>
                                ))}
                            </Select>
                        </div>
                        <div className="spkTableToolbarRight">
                            <Input
                                accessibleName="Cari batu mikro"
                                placeholder="Cari nama batu, parcel, bentuk..."
                                value={searchQuery}
                                icon={<Icon name={searchIcon} />}
                                onInput={(event) =>
                                    setSearchQuery(event.target.value ?? '')
                                }
                            />
                        </div>
                    </div>

                    <div className="spkTableScroll">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th>Batu</th>
                                    <th>Parcel</th>
                                    <th>Ukuran</th>
                                    <th>Bentuk</th>
                                    <th className="text-right">Masuk (pcs)</th>
                                    <th className="text-right">Keluar (pcs)</th>
                                    <th className="text-right">Saldo (pcs)</th>
                                    <th className="text-right">Saldo (crt)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {stones.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8}>
                                            {activePeriod
                                                ? 'Tidak ada batu mikro.'
                                                : 'Periode batu aktif tidak tersedia.'}
                                        </td>
                                    </tr>
                                ) : (
                                    stones.data.map((stone) => (
                                        <tr key={stone.id}>
                                            <td className="font-medium">
                                                {stone.name}
                                            </td>
                                            <td>{stone.parcel ?? '—'}</td>
                                            <td>
                                                {stone.size
                                                    ? `${stone.size} mm`
                                                    : '—'}
                                            </td>
                                            <td>{stone.shape ?? '—'}</td>
                                            <td className="text-right tabular-nums">
                                                {formatPcs(stone.pcsIn)}
                                            </td>
                                            <td className="text-right tabular-nums">
                                                {formatPcs(stone.pcsOut)}
                                            </td>
                                            <td className="text-right tabular-nums">
                                                <span
                                                    className={balanceClass(
                                                        stone.balancePcs,
                                                    )}
                                                >
                                                    {formatPcs(
                                                        stone.balancePcs,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="text-right tabular-nums">
                                                {formatCrt(stone.balanceCrt)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total saldo: {formatPcs(totals.pcs)} pcs ·{' '}
                            {formatCrt(totals.crt)} crt
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={stones.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: stones.current_page - 1,
                                        search: searchQuery,
                                    })
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
                                    visit({
                                        page: stones.current_page + 1,
                                        search: searchQuery,
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

MicroStoneIndex.layout = {
    activeMenu: 'Batu Mikro',
    pageTitle: 'Batu Mikro',
};
