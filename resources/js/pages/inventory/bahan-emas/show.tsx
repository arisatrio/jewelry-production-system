import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    edit,
    index as bahanEmasIndex,
    show,
} from '@/routes/inventory/gold-materials';

type BahanEmasDetail = {
    id: number;
    name: string | null;
    stock: string;
    createdBy: string | null;
    createdDate: string | null;
    modifiedBy: string | null;
    modifiedDate: string | null;
};

type GoldMaterialTransaction = {
    id: number;
    direction: string;
    category: 'Penambahan' | 'Pemakaian';
    transactionType: string;
    weight: string;
    productionNo: string | null;
    documentNo: string | null;
    notes: string | null;
    createdBy: string | null;
    createdDate: string | null;
};

type TransactionsPaginator = {
    data: GoldMaterialTransaction[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type BahanEmasShowProps = {
    item: BahanEmasDetail;
    activePeriod: { id: number; label: string } | null;
    transactions: TransactionsPaginator;
    filters: {
        per_page: number;
    };
};

function formatAudit(date: string | null, actor: string | null): string {
    if (!date && !actor) {
        return '—';
    }

    return [date, actor].filter(Boolean).join(' · ');
}

function isOutgoing(transaction: GoldMaterialTransaction): boolean {
    return transaction.direction.trim().toUpperCase() === 'OUT';
}

export default function BahanEmasShow({
    item,
    activePeriod,
    transactions,
    filters,
}: BahanEmasShowProps) {
    const detailRows = [
        { label: 'Nama', value: item.name ?? '—' },
        { label: 'Stok (g)', value: item.stock },
        { label: 'Periode Aktif', value: activePeriod?.label ?? '—' },
        {
            label: 'Dibuat',
            value: formatAudit(item.createdDate, item.createdBy),
        },
        {
            label: 'Diubah',
            value: formatAudit(item.modifiedDate, item.modifiedBy),
        },
    ];

    const visitPage = (page: number) => {
        router.get(
            show.url(item.id, {
                query: { page, per_page: filters.per_page },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title={`Bahan Emas · ${item.name ?? item.id}`} />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">
                            {item.name ?? 'Bahan Emas'}
                        </h1>
                        <p className="masterDataSubtitle">
                            Detail bahan emas dan riwayat transaksi pada periode
                            aktif.
                        </p>
                    </div>
                    <div className="masterDataActions">
                        <Button asChild variant="outline">
                            <Link href={bahanEmasIndex.url()}>Kembali</Link>
                        </Button>
                        <Button asChild>
                            <Link href={edit.url(item.id)}>Edit</Link>
                        </Button>
                    </div>
                </div>

                <div className="fioriVarianceDetailCard">
                    <span className="fioriVarianceDetailTitle">
                        Detail Bahan Emas
                    </span>
                    <dl className="fioriVarianceDetailGrid">
                        {detailRows.map((row) => (
                            <div
                                key={row.label}
                                className="fioriVarianceDetailRow"
                            >
                                <dt>{row.label}</dt>
                                <dd
                                    className={
                                        row.label === 'Stok (g)' &&
                                        Number(item.stock) < 0
                                            ? 'text-red-600'
                                            : undefined
                                    }
                                >
                                    {row.value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <div className="spkTableCard">
                    <div className="spkTableActions">
                        <div className="spkTableTitleBlock">
                            <h2 className="spkTableTitle">Riwayat Transaksi</h2>
                        </div>
                    </div>

                    <div className="spkTableScroll">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Kategori</th>
                                    <th className="text-right">Berat (g)</th>
                                    <th>Produksi No</th>
                                    <th>Deskripsi</th>
                                    <th>Dibuat</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6}>
                                            Belum ada transaksi bahan emas ini
                                            pada periode aktif.
                                        </td>
                                    </tr>
                                ) : (
                                    transactions.data.map(
                                        (transaction, index) => (
                                            <tr key={transaction.id}>
                                                <td>
                                                    {(transactions.current_page -
                                                        1) *
                                                        transactions.per_page +
                                                        index +
                                                        1}
                                                </td>
                                                <td>
                                                    <span
                                                        className={
                                                            isOutgoing(
                                                                transaction,
                                                            )
                                                                ? 'inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700'
                                                                : 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700'
                                                        }
                                                    >
                                                        {transaction.category}
                                                    </span>
                                                </td>
                                                <td
                                                    className={`text-right font-semibold tabular-nums ${isOutgoing(transaction) ? 'text-red-600' : 'text-emerald-600'}`}
                                                >
                                                    {isOutgoing(transaction)
                                                        ? '-'
                                                        : '+'}{' '}
                                                    {transaction.weight}
                                                </td>
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
                                                                {
                                                                    transaction.notes
                                                                }
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </td>
                                                <td>
                                                    {formatAudit(
                                                        transaction.createdDate,
                                                        transaction.createdBy,
                                                    )}
                                                </td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="masterDataFooter">
                        <span>
                            Total {transactions.total} transaksi · Halaman{' '}
                            {transactions.current_page} /{' '}
                            {Math.max(1, transactions.last_page)}
                        </span>
                        <div className="masterDataPager">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={transactions.current_page <= 1}
                                onClick={() =>
                                    visitPage(transactions.current_page - 1)
                                }
                            >
                                Sebelumnya
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    transactions.current_page >=
                                    transactions.last_page
                                }
                                onClick={() =>
                                    visitPage(transactions.current_page + 1)
                                }
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

BahanEmasShow.layout = {
    activeMenu: 'Bahan Emas',
    pageTitle: 'Detail Bahan Emas',
};
