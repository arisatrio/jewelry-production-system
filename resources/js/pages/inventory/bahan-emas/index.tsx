import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create,
    destroy,
    edit,
    index as bahanEmasIndex,
    show,
} from '@/routes/inventory/gold-materials';

type BahanEmasRow = {
    id: number;
    name: string | null;
    createdBy: string | null;
    createdDate: string | null;
    modifiedBy: string | null;
    modifiedDate: string | null;
    stock: string;
};

type ItemsPaginator = {
    data: BahanEmasRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type BahanEmasIndexProps = {
    items: ItemsPaginator;
    filters: {
        search: string;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

export default function BahanEmasIndex({
    items,
    filters,
}: BahanEmasIndexProps) {
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
                bahanEmasIndex.url({
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
            bahanEmasIndex.url({
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

    const handleDelete = (item: BahanEmasRow) => {
        if (!window.confirm(`Hapus bahan emas "${item.name ?? item.id}"?`)) {
            return;
        }

        router.delete(destroy.url(item.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Bahan Emas" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Bahan Emas</h1>
                        <p className="masterDataSubtitle">
                            Kelola master data bahan emas dari tabel
                            msmaterialgold.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create.url()}>Tambah Bahan Emas</Link>
                    </Button>
                </div>

                <div className="spkTableCard">
                    <div className="spkTableToolbar">
                        <div className="spkTableToolbarLeft">
                            <Input
                                type="search"
                                placeholder="Cari nama bahan emas..."
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
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th className="text-right">Stok (g)</th>
                                    <th>Dibuat</th>
                                    <th>Diubah</th>
                                    <th className="spkTableActionCol">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6}>
                                            Tidak ada data bahan emas.
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((item, index) => (
                                        <tr key={item.id}>
                                            <td>
                                                {(items.current_page - 1) *
                                                    items.per_page +
                                                    index +
                                                    1}
                                            </td>
                                            <td>
                                                <Link
                                                    href={show.url(item.id)}
                                                    className="font-medium text-blue-700 hover:underline"
                                                >
                                                    {item.name ?? '—'}
                                                </Link>
                                            </td>
                                            <td
                                                className={`text-right tabular-nums${Number(item.stock) < 0 ? 'text-red-600' : ''}`}
                                            >
                                                {item.stock}
                                            </td>
                                            <td>
                                                {item.createdDate ?? '—'}
                                                {item.createdBy
                                                    ? ` · ${item.createdBy}`
                                                    : ''}
                                            </td>
                                            <td>
                                                {item.modifiedDate ?? '—'}
                                                {item.modifiedBy
                                                    ? ` · ${item.modifiedBy}`
                                                    : ''}
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
                            Total {items.total} bahan emas · Halaman{' '}
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

BahanEmasIndex.layout = {
    activeMenu: 'Bahan Emas',
    pageTitle: 'Bahan Emas',
};
