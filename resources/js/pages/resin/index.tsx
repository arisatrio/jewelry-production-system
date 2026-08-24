import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import {
    create,
    destroy,
    edit,
    index as resinIndex,
} from '@/routes/resin';

type ResinRow = {
    id: number;
    docNo: string | null;
    transDate: string | null;
    status: string | null;
    spkNo: string | null;
    itemName: string | null;
    customerName: string | null;
    stoneCount: number;
    fileUpload: string | null;
};

type ResinsPaginator = {
    data: ResinRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type ResinIndexProps = {
    resins: ResinsPaginator;
    filters: {
        search: string;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

function statusBadgeClass(status: string): string {
    const lower = status.toLowerCase();

    if (lower.includes('done')) {
        return 'spkTableBadge--done';
    }

    if (lower.includes('010') || lower.includes('open')) {
        return 'spkTableBadge--approved';
    }

    return 'spkTableBadge--default';
}

function statusLabel(status: string | null): string {
    if (!status) {
        return '—';
    }

    if (status === 'RESDONE') {
        return 'Done';
    }

    if (status === 'RES010') {
        return 'Open';
    }

    return status;
}

export default function ResinIndex({ resins, filters }: ResinIndexProps) {
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
                resinIndex.url({
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
        () => Math.max(1, resins.last_page),
        [resins.last_page],
    );

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
    }) => {
        router.get(
            resinIndex.url({
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

    const handleDelete = (item: ResinRow) => {
        if (
            !window.confirm(
                `Hapus dokumen resin "${item.docNo ?? item.id}"?`,
            )
        ) {
            return;
        }

        router.delete(destroy.url(item.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Resin" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableActions">
                        <button
                            type="button"
                            className="spkCreateBtn"
                            aria-label="Tambah dokumen resin"
                            onClick={() => router.visit(create.url())}
                        >
                            <Icon name={addIcon} mode="Decorative" />
                            <span>Tambah</span>
                        </button>
                    </div>

                    <div className="spkTableToolbar">
                        <div className="spkTableToolbarLeft">
                            <span className="spkTablePageSizeLabel">Show</span>
                            <Select
                                accessibleName="Jumlah baris per halaman"
                                onChange={(event) =>
                                    visit({
                                        page: 1,
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
                                accessibleName="Cari dokumen resin"
                                placeholder="Cari nomor dokumen, SPK, item..."
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
                                    <th>No Dokumen</th>
                                    <th>Tanggal</th>
                                    <th>SPK</th>
                                    <th>Item</th>
                                    <th>Customer</th>
                                    <th>Batu</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {resins.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8}>
                                            Tidak ada data dokumen resin.
                                        </td>
                                    </tr>
                                ) : (
                                    resins.data.map((item) => (
                                        <tr key={item.id}>
                                            <td>
                                                <span className="spkProduksiLink">
                                                    {item.docNo ?? '—'}
                                                </span>
                                            </td>
                                            <td>{item.transDate ?? '—'}</td>
                                            <td>{item.spkNo ?? '—'}</td>
                                            <td>{item.itemName ?? '—'}</td>
                                            <td>
                                                {item.customerName ?? '—'}
                                            </td>
                                            <td>{item.stoneCount}</td>
                                            <td>
                                                {item.status ? (
                                                    <div className="spkTableStatus">
                                                        <span
                                                            className={`spkTableBadge ${statusBadgeClass(item.status)}`}
                                                        >
                                                            {statusLabel(
                                                                item.status,
                                                            )}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>
                                                <div className="masterDataActions">
                                                    <button
                                                        type="button"
                                                        className="masterDataLinkBtn"
                                                        onClick={() =>
                                                            router.visit(
                                                                edit.url(
                                                                    item.id,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        Edit
                                                    </button>
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

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total {resins.total} dokumen resin
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={resins.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: resins.current_page - 1,
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
                                {resins.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={
                                    resins.current_page >= totalPages
                                }
                                onClick={() =>
                                    visit({
                                        page: resins.current_page + 1,
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

ResinIndex.layout = {
    activeMenu: 'Resin',
    pageTitle: 'Resin',
};
