import { Head, router } from '@inertiajs/react';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { useEffect, useMemo, useState } from 'react';
import { index } from '@/routes/inventory/stone-transactions';

type StoneTransaction = {
    id: number;
    period: string;
    direction: string;
    category: 'Penambahan' | 'Pemakaian' | 'Lainnya';
    transactionType: string;
    stone: string;
    pcs: string;
    crt: string | null;
    signedPcs: string;
    productionNo: string | null;
    documentNo: string | null;
    notes: string | null;
    createdBy: string | null;
    createdDate: string | null;
};

type TransactionsPaginator = {
    data: StoneTransaction[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type PageProps = {
    transactions: TransactionsPaginator;
    total: string;
    filters: {
        search: string;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

function formatPcs(value: string): string {
    const numeric = Number(value);
    const formatted = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 2,
    }).format(Math.abs(numeric));

    return numeric < 0 ? `(${formatted})` : formatted;
}

function transactionPcs(transaction: StoneTransaction): string {
    const direction = transaction.direction.trim().toUpperCase();
    const prefix = direction === 'OUT' ? '-' : direction === 'IN' ? '+' : '';

    return prefix !== ''
        ? `${prefix} ${formatPcs(transaction.pcs)}`
        : formatPcs(transaction.pcs);
}

function categoryClass(category: StoneTransaction['category']): string {
    if (category === 'Pemakaian') {
        return 'inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700';
    }

    if (category === 'Penambahan') {
        return 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700';
    }

    return 'inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700';
}

function pcsClass(direction: string): string {
    const normalized = direction.trim().toUpperCase();

    if (normalized === 'OUT') {
        return 'font-semibold text-red-600';
    }

    if (normalized === 'IN') {
        return 'font-semibold text-emerald-600';
    }

    return 'font-semibold text-slate-700';
}

export default function StoneTransactionsIndex({
    transactions,
    total,
    filters,
}: PageProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
    }) => {
        router.get(
            index.url({
                query: {
                    search: params.search || undefined,
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
    }, [searchQuery, filters.search, filters.per_page]);

    const totalPages = useMemo(
        () => Math.max(1, transactions.last_page),
        [transactions.last_page],
    );

    return (
        <>
            <Head title="Transaksi Batu" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableActions">
                        <div className="spkTableTitleBlock">
                            <h1 className="spkTableTitle">Transaksi Batu</h1>
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
                        </div>
                        <div className="spkTableToolbarRight">
                            <Input
                                accessibleName="Cari transaksi batu"
                                placeholder="Cari batu, dokumen, SPK..."
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
                                    <th>Periode</th>
                                    <th>Batu</th>
                                    <th>Kategori</th>
                                    <th>Pcs</th>
                                    <th>CRT</th>
                                    <th>Produksi No</th>
                                    <th>Deskripsi</th>
                                    <th>Dibuat</th>
                                    <th>Tanggal dibuat</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9}>
                                            Tidak ada transaksi batu.
                                        </td>
                                    </tr>
                                ) : (
                                    transactions.data.map((transaction) => (
                                        <tr key={transaction.id}>
                                            <td>{transaction.period}</td>
                                            <td>{transaction.stone}</td>
                                            <td>
                                                <span
                                                    className={categoryClass(
                                                        transaction.category,
                                                    )}
                                                >
                                                    {transaction.category}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    className={pcsClass(
                                                        transaction.direction,
                                                    )}
                                                >
                                                    {transactionPcs(
                                                        transaction,
                                                    )}
                                                </span>
                                            </td>
                                            <td>{transaction.crt ?? '—'}</td>
                                            <td>
                                                {transaction.productionNo ??
                                                    transaction.documentNo ??
                                                    '—'}
                                            </td>
                                            <td>
                                                <div className="spkTableDescription">
                                                    <span>
                                                        {
                                                            transaction.transactionType
                                                        }
                                                    </span>
                                                    {transaction.notes ? (
                                                        <span className="spkTableDescriptionItem">
                                                            {transaction.notes}
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td>
                                                {transaction.createdBy ?? '—'}
                                            </td>
                                            <td>
                                                {transaction.createdDate ?? '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total: {formatPcs(total)} pcs
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={transactions.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: transactions.current_page - 1,
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

StoneTransactionsIndex.layout = {
    activeMenu: 'Transaksi Batu',
    pageTitle: 'Transaksi Batu',
};
