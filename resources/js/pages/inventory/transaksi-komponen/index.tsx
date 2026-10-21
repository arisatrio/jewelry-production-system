import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create,
    destroy,
    edit,
    index as transaksiKomponenIndex,
    show,
} from '@/routes/inventory/component-transactions';

type KomponenStatus = 'available' | 'reserved' | 'used';

type TransaksiKomponenRow = {
    id: number;
    jenisKomponen: string | null;
    name: string | null;
    itemCode: string | null;
    supplier: string | null;
    length: string | null;
    color: string | null;
    weight: string | null;
    productionNo: string | null;
    status: KomponenStatus;
    entryDate: string | null;
    outDate: string | null;
};

type ItemsPaginator = {
    data: TransaksiKomponenRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type TransaksiKomponenIndexProps = {
    items: ItemsPaginator;
    filters: {
        search: string;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

const STATUS_LABELS: Record<KomponenStatus, string> = {
    available: 'Tersedia',
    reserved: 'Reservasi',
    used: 'Terpakai',
};

function statusClass(status: KomponenStatus): string {
    if (status === 'used') {
        return 'inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700';
    }

    if (status === 'reserved') {
        return 'inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800';
    }

    return 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700';
}

export default function TransaksiKomponenIndex({
    items,
    filters,
}: TransaksiKomponenIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [syncedSearch, setSyncedSearch] = useState(filters.search);

    if (syncedSearch !== filters.search) {
        setSyncedSearch(filters.search);
        setSearchQuery(filters.search);
    }

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            router.get(
                transaksiKomponenIndex.url({
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

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
    }) => {
        router.get(
            transaksiKomponenIndex.url({
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

    const handleDelete = (item: TransaksiKomponenRow) => {
        const label = item.name ?? item.jenisKomponen ?? String(item.id);

        if (!window.confirm(`Hapus transaksi komponen "${label}"?`)) {
            return;
        }

        router.delete(destroy.url(item.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Transaksi Komponen" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Transaksi Komponen</h1>
                        <p className="masterDataSubtitle">
                            Kelola data komponen inventory pada tabel trkomponen.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create.url()}>Tambah Komponen</Link>
                    </Button>
                </div>

                <div className="spkTableCard">
                    <div className="spkTableToolbar">
                        <div className="spkTableToolbarLeft">
                            <Input
                                type="search"
                                placeholder="Cari jenis, nama, kode, no PRD..."
                                value={searchQuery}
                                onChange={(event) =>
                                    setSearchQuery(event.target.value)
                                }
                                className="masterDataSearch"
                            />
                        </div>
                        <div className="spkTableToolbarRight">
                            <label className="spkTablePageSizeLabel">
                                Tampilkan
                                <select
                                    className="masterDataSelect"
                                    value={filters.per_page}
                                    onChange={(event) =>
                                        visit({
                                            page: 1,
                                            per_page: Number(
                                                event.target.value,
                                            ),
                                            search: searchQuery,
                                        })
                                    }
                                >
                                    {PAGE_SIZE_OPTIONS.map((size) => (
                                        <option key={size} value={size}>
                                            {size}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                    </div>

                    <div className="spkTableScroll">
                        <table className="spkTable masterDataTable">
                            <thead>
                                <tr>
                                    <th>Tgl Masuk</th>
                                    <th>#</th>
                                    <th>Jenis</th>
                                    <th>Nama</th>
                                    <th>Kode</th>
                                    <th>Supplier</th>
                                    <th className="text-right">Panjang</th>
                                    <th className="text-right">Berat</th>
                                    <th>Status</th>
                                    <th>No PRD</th>
                                    <th>Tgl Keluar</th>
                                    <th className="spkTableActionCol">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={12}>
                                            Tidak ada data transaksi komponen.
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((item, index) => (
                                        <tr key={item.id}>
                                            <td className="tabular-nums whitespace-nowrap">
                                                {item.entryDate ?? '—'}
                                            </td>
                                            <td>
                                                {(items.current_page - 1) *
                                                    items.per_page +
                                                    index +
                                                    1}
                                            </td>
                                            <td>{item.jenisKomponen ?? '—'}</td>
                                            <td>
                                                <Link
                                                    href={show.url(item.id)}
                                                    className="font-medium text-blue-700 hover:underline"
                                                >
                                                    {item.name ?? '—'}
                                                </Link>
                                            </td>
                                            <td>{item.itemCode ?? '—'}</td>
                                            <td>{item.supplier ?? '—'}</td>
                                            <td className="text-right tabular-nums">
                                                {item.length ?? '—'}
                                            </td>
                                            <td className="text-right tabular-nums">
                                                {item.weight ?? '—'}
                                            </td>
                                            <td>
                                                <span
                                                    className={statusClass(
                                                        item.status,
                                                    )}
                                                >
                                                    {STATUS_LABELS[item.status]}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap">
                                                {item.productionNo ?? '—'}
                                            </td>
                                            <td className="tabular-nums whitespace-nowrap">
                                                {item.outDate ?? '—'}
                                            </td>
                                            <td>
                                                <div className="masterDataActions">
                                                    <Link
                                                        href={edit.url(item.id)}
                                                        className="masterDataLinkBtn"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        className="masterDataDangerBtn"
                                                        onClick={() =>
                                                            handleDelete(item)
                                                        }
                                                    >
                                                        Hapus
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="masterDataFooter">
                        <span>
                            Total {items.total} transaksi komponen · Halaman{' '}
                            {items.current_page} / {items.last_page}
                        </span>
                        <div className="masterDataPager">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={items.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: items.current_page - 1,
                                        search: searchQuery,
                                    })
                                }
                            >
                                Sebelumnya
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={items.current_page >= items.last_page}
                                onClick={() =>
                                    visit({
                                        page: items.current_page + 1,
                                        search: searchQuery,
                                    })
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

TransaksiKomponenIndex.layout = {
    activeMenu: 'Transaksi Komponen',
    pageTitle: 'Transaksi Komponen',
};
