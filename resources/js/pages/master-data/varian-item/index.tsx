import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input as Ui5Input } from '@ui5/webcomponents-react/Input';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import {
    VarianItemFormDialog,
    type ItemOption,
    type VarianItemRow,
} from '@/components/master-data/varian-item-form-dialog';
import {
    destroy,
    index as varianItemIndex,
} from '@/routes/master-data/varian-item';
import { index as stonesIndex } from '@/routes/master-data/varian-item/stones';

type VariancesPaginator = {
    data: VarianItemRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type VarianItemIndexProps = {
    variances: VariancesPaginator;
    itemOptions: ItemOption[];
    goldColorOptions: string[];
    filters: {
        search: string;
        item_id: number | null;
        per_page: number;
        create: boolean;
        edit: number | null;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

function formatStoneBullet(stone: {
    shapeName: string | null;
    pcs: number | null;
    caratPerPcs: string | null;
    totalCarat: string | null;
    size: string | null;
}): string {
    const parts = [
        stone.shapeName?.trim() || 'Tanpa nama',
        stone.size
            ? /\bmm\b/i.test(stone.size)
                ? stone.size
                : `${stone.size} mm`
            : null,
        stone.pcs !== null ? `${stone.pcs} pcs` : null,
        stone.caratPerPcs ? `${stone.caratPerPcs} crt/pcs` : null,
        stone.totalCarat ? `${stone.totalCarat} crt` : null,
    ].filter((value): value is string => value !== null && value !== '');

    return parts.join(', ');
}

export default function VarianItemIndex({
    variances,
    itemOptions,
    goldColorOptions,
    filters,
}: VarianItemIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const [formMode, setFormMode] = useState<'create' | 'edit'>('create');
    const [editingVariance, setEditingVariance] =
        useState<VarianItemRow | null>(null);

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    useEffect(() => {
        if (filters.create) {
            setFormMode('create');
            setEditingVariance(null);
            setFormOpen(true);
        }
    }, [filters.create]);

    useEffect(() => {
        if (!filters.edit) {
            return;
        }

        const row = variances.data.find((item) => item.id === filters.edit);

        if (!row) {
            return;
        }

        setFormMode('edit');
        setEditingVariance(row);
        setFormOpen(true);
    }, [filters.edit, variances.data]);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            router.get(
                varianItemIndex.url({
                    query: {
                        search: searchQuery || undefined,
                        item_id: filters.item_id || undefined,
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
    }, [searchQuery, filters.search, filters.item_id, filters.per_page]);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
        item_id?: number | null;
    }) => {
        router.get(
            varianItemIndex.url({
                query: {
                    search: params.search || undefined,
                    item_id: params.item_id || undefined,
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

    const openCreate = () => {
        setFormMode('create');
        setEditingVariance(null);
        setFormOpen(true);
    };

    const openEdit = (row: VarianItemRow) => {
        setFormMode('edit');
        setEditingVariance(row);
        setFormOpen(true);
    };

    const openAddStone = (row: VarianItemRow) => {
        router.visit(stonesIndex.url(row.id));
    };

    const handleDelete = (row: VarianItemRow) => {
        if (
            !window.confirm(
                `Hapus Master Item Product "${row.name ?? row.id}"?`,
            )
        ) {
            return;
        }

        router.delete(destroy.url(row.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Master Item Product" />

            <div className="spkTableShell">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">Master Item Product</h1>
                    </div>
                </header>

                <div className="spkTableCard">
                    <div className="spkTableActions">
                        <button
                            type="button"
                            className="spkCreateBtn"
                            aria-label="Tambah Master Item Product"
                            onClick={openCreate}
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
                                onChange={(event) => {
                                    visit({
                                        page: 1,
                                        per_page: Number(
                                            event.detail.selectedOption.value,
                                        ),
                                        search: searchQuery,
                                        item_id: filters.item_id,
                                    });
                                }}
                            >
                                {PAGE_SIZE_OPTIONS.map((size) => (
                                    <Option
                                        key={size}
                                        value={String(size)}
                                        selected={size === filters.per_page}
                                    >
                                        {size}
                                    </Option>
                                ))}
                            </Select>
                            <span className="spkTablePageSizeLabel">
                                entries
                            </span>

                            <Select
                                accessibleName="Filter tipe item"
                                onChange={(event) => {
                                    const value =
                                        event.detail.selectedOption?.value ??
                                        '';

                                    visit({
                                        page: 1,
                                        search: searchQuery,
                                        item_id: value ? Number(value) : null,
                                    });
                                }}
                            >
                                <Option
                                    value=""
                                    selected={filters.item_id === null}
                                >
                                    Semua tipe item
                                </Option>
                                {itemOptions.map((item) => (
                                    <Option
                                        key={item.id}
                                        value={String(item.id)}
                                        selected={filters.item_id === item.id}
                                    >
                                        {item.name}
                                    </Option>
                                ))}
                            </Select>
                        </div>
                        <div className="spkTableToolbarRight">
                            <Ui5Input
                                accessibleName="Cari Master Item Product"
                                placeholder="Cari nama / deskripsi..."
                                value={searchQuery}
                                icon={<Icon name={searchIcon} />}
                                onInput={(event) => {
                                    setSearchQuery(event.target.value ?? '');
                                }}
                            />
                        </div>
                    </div>

                    <div className="spkTableScroll">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Gambar</th>
                                    <th>Tipe Item</th>
                                    <th>Nama Varian</th>
                                    <th>Ukuran</th>
                                    <th>Berat Emas (g)</th>
                                    <th>Warna Emas</th>
                                    <th>File JewelCAD 3D</th>
                                    <th>List Batu</th>
                                    <th className="spkTableActionCol">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {variances.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={10}>
                                            Tidak ada data Master Item Product.
                                        </td>
                                    </tr>
                                ) : (
                                    variances.data.map((row) => (
                                        <tr key={row.id}>
                                            <td>{row.id}</td>
                                            <td>
                                                {row.imageUrl ? (
                                                    <img
                                                        src={row.imageUrl}
                                                        alt={
                                                            row.name
                                                                ? `Gambar ${row.name}`
                                                                : `Gambar varian ${row.id}`
                                                        }
                                                        className="masterDataThumb"
                                                    />
                                                ) : (
                                                    <span className="masterDataMuted">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td>{row.itemName ?? '—'}</td>
                                            <td>{row.name ?? '—'}</td>
                                            <td>
                                                <ul className="masterDataStoneBullets">
                                                    <li>
                                                        Diameter (mm):{' '}
                                                        {row.diameter?.trim() ||
                                                            '—'}
                                                    </li>
                                                    <li>
                                                        Dimensi PxL (mm):{' '}
                                                        {row.dimensi?.trim() ||
                                                            '—'}
                                                    </li>
                                                    <li>
                                                        Ring Size:{' '}
                                                        {row.ringSize?.trim() ||
                                                            '—'}
                                                    </li>
                                                </ul>
                                            </td>
                                            <td>{row.goldWeight ?? '—'}</td>
                                            <td>{row.goldColor ?? '—'}</td>
                                            <td>{row.jwcad3d ?? '—'}</td>
                                            <td>
                                                {(row.stones?.length ?? 0) ===
                                                0 ? (
                                                    <span className="masterDataMuted">
                                                        —
                                                    </span>
                                                ) : (
                                                    <ul className="masterDataStoneBullets">
                                                        {row.stones?.map(
                                                            (stone) => (
                                                                <li
                                                                    key={
                                                                        stone.id
                                                                    }
                                                                >
                                                                    {formatStoneBullet(
                                                                        stone,
                                                                    )}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                            </td>
                                            <td>
                                                <div className="masterDataActions">
                                                    <button
                                                        type="button"
                                                        className="masterDataLinkBtn"
                                                        onClick={() =>
                                                            openEdit(row)
                                                        }
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="masterDataLinkBtn"
                                                        onClick={() =>
                                                            openAddStone(row)
                                                        }
                                                    >
                                                        Tambah Batu
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="masterDataDangerBtn"
                                                        onClick={() =>
                                                            handleDelete(row)
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
                            Total: {variances.total.toLocaleString('en-US')}
                        </div>
                        <div className="spkPagination" aria-label="Pagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={variances.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: variances.current_page - 1,
                                        search: searchQuery,
                                        item_id: filters.item_id,
                                    })
                                }
                            >
                                Previous
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                                aria-current="page"
                            >
                                {variances.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={
                                    variances.current_page >=
                                    variances.last_page
                                }
                                onClick={() =>
                                    visit({
                                        page: variances.current_page + 1,
                                        search: searchQuery,
                                        item_id: filters.item_id,
                                    })
                                }
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <VarianItemFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                mode={formMode}
                itemOptions={itemOptions}
                goldColorOptions={goldColorOptions}
                variance={editingVariance}
            />
        </>
    );
}

VarianItemIndex.layout = {
    activeMenu: 'Master Item Product',
    pageTitle: 'Master Item Product',
};
