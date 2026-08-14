import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    create,
    destroy,
    edit,
    index as tipeItemIndex,
} from '@/routes/master-data/tipe-item';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type TipeItemRow = {
    id: number;
    name: string | null;
    createdBy: string | null;
    createdDate: string | null;
    modifiedBy: string | null;
    modifiedDate: string | null;
};

type ItemsPaginator = {
    data: TipeItemRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type TipeItemIndexProps = {
    items: ItemsPaginator;
    filters: {
        search: string;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

export default function TipeItemIndex({ items, filters }: TipeItemIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            router.get(
                tipeItemIndex.url({
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
            tipeItemIndex.url({
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

    const handleDelete = (item: TipeItemRow) => {
        if (!window.confirm(`Hapus tipe item "${item.name ?? item.id}"?`)) {
            return;
        }

        router.delete(destroy.url(item.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Tipe Item" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Tipe Item</h1>
                        <p className="masterDataSubtitle">
                            Kelola master data tipe item dari tabel msitem.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create.url()}>Tambah Tipe Item</Link>
                    </Button>
                </div>

                <div className="spkTableCard">
                    <div className="spkTableToolbar">
                        <div className="spkTableToolbarLeft">
                            <Input
                                type="search"
                                placeholder="Cari nama tipe item..."
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
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Dibuat</th>
                                    <th>Diubah</th>
                                    <th className="spkTableActionCol">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5}>
                                            Tidak ada data tipe item.
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((item) => (
                                        <tr key={item.id}>
                                            <td>{item.id}</td>
                                            <td>{item.name ?? '—'}</td>
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
                            Total {items.total} item · Halaman{' '}
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
                                disabled={
                                    items.current_page >= items.last_page
                                }
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

TipeItemIndex.layout = {
    activeMenu: 'Tipe Item',
    pageTitle: 'Tipe Item',
};
