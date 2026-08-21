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
    index as jewelCadIndex,
} from '@/routes/jewelcad';

type JewelCadRow = {
    id: number;
    docNo: string | null;
    transDate: string | null;
    status: string | null;
    notes: string | null;
    detailCount: number;
    materials: string[];
    spkNos: string[];
};

type RequestsPaginator = {
    data: JewelCadRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type JewelCadIndexProps = {
    requests: RequestsPaginator;
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

    if (lower.includes('draft')) {
        return 'spkTableBadge--draft';
    }

    if (lower.includes('open')) {
        return 'spkTableBadge--approved';
    }

    return 'spkTableBadge--default';
}

export default function JewelCadIndex({
    requests,
    filters,
}: JewelCadIndexProps) {
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
                jewelCadIndex.url({
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
        () => Math.max(1, requests.last_page),
        [requests.last_page],
    );

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
    }) => {
        router.get(
            jewelCadIndex.url({
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

    const handleDelete = (item: JewelCadRow) => {
        if (!window.confirm(`Hapus request JewelCAD "${item.docNo ?? item.id}"?`)) {
            return;
        }

        router.delete(destroy.url(item.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="JewelCAD" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableActions">
                        <button
                            type="button"
                            className="spkCreateBtn"
                            aria-label="Tambah request JewelCAD"
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
                            <span className="spkTablePageSizeLabel">entries</span>
                        </div>
                        <div className="spkTableToolbarRight">
                            <Input
                                accessibleName="Cari request JewelCAD"
                                placeholder="Cari nomor request, SPK, material..."
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
                                    <th>No Request</th>
                                    <th>Tanggal</th>
                                    <th>SPK</th>
                                    <th>Material</th>
                                    <th>Baris</th>
                                    <th>Status</th>
                                    <th>Catatan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {requests.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8}>
                                            Tidak ada data request JewelCAD.
                                        </td>
                                    </tr>
                                ) : (
                                    requests.data.map((item) => (
                                        <tr key={item.id}>
                                            <td>
                                                <span className="spkProduksiLink">
                                                    {item.docNo ?? '—'}
                                                </span>
                                            </td>
                                            <td>{item.transDate ?? '—'}</td>
                                            <td>
                                                {item.spkNos.length > 0 ? (
                                                    <div className="spkTableDescription">
                                                        {item.spkNos.map((spkNo) => (
                                                            <span
                                                                key={spkNo}
                                                                className="spkTableDescriptionItem"
                                                            >
                                                                {spkNo}
                                                            </span>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>
                                                {item.materials.length > 0 ? (
                                                    <div className="spkTableDescription">
                                                        {item.materials.map(
                                                            (material) => (
                                                                <span
                                                                    key={material}
                                                                    className="spkTableDescriptionItem"
                                                                >
                                                                    {material}
                                                                </span>
                                                            ),
                                                        )}
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>{item.detailCount}</td>
                                            <td>
                                                {item.status ? (
                                                    <div className="spkTableStatus">
                                                        <span
                                                            className={`spkTableBadge ${statusBadgeClass(item.status)}`}
                                                        >
                                                            {item.status}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>{item.notes ?? '—'}</td>
                                            <td>
                                                <div className="masterDataActions">
                                                    <button
                                                        type="button"
                                                        className="masterDataLinkBtn"
                                                        onClick={() =>
                                                            router.visit(
                                                                edit.url(item.id),
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
                            Total {requests.total} request JewelCAD
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={requests.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: requests.current_page - 1,
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
                                {requests.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={requests.current_page >= totalPages}
                                onClick={() =>
                                    visit({
                                        page: requests.current_page + 1,
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

JewelCadIndex.layout = {
    activeMenu: 'JewelCAD',
    pageTitle: 'JewelCAD',
};
