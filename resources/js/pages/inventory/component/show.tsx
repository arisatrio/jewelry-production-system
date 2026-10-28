import { Head, router } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import editIcon from '@ui5/webcomponents-icons/dist/edit.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import navBackIcon from '@ui5/webcomponents-icons/dist/nav-back.js';
import outboxIcon from '@ui5/webcomponents-icons/dist/outbox.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useMemo, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ComponentAddStockDialog } from '@/pages/inventory/component/component-add-stock-dialog';
import { ComponentIssueStockDialog } from '@/pages/inventory/component/component-issue-stock-dialog';
import { sourceTypeLabel } from '@/pages/inventory/component/component-source-type';
import { formatUsd } from '@/pages/inventory/component/format-usd';
import { InventoryMasterPhotoPanel } from '@/pages/inventory/inventory-master-photo-field';
import {
    destroy,
    edit,
    index as chainsIndex,
    show,
} from '@/routes/inventory/components';

type ChainDetail = {
    id: number;
    componentCategory: string;
    item: string;
    itemCode: string;
    itemName: string | null;
    description: string | null;
    photo: string | null;
    stockIn: number;
    stockOut: number;
    balance: number;
    balanceLength: string;
};

type ChainUnit = {
    id: number;
    unitNo: string | null;
    name: string;
    sourceType: string;
    componentSize: string;
    weight: string | null;
    color: string | null;
    supplier: string | null;
    purchasePrice: string | null;
    entryDate: string | null;
    outDate: string | null;
    spkNo: string | null;
    notes: string | null;
    status: 'in_stock' | 'issued';
    createdBy: string | null;
    createdDate: string | null;
};

type UnitsPaginator = {
    data: ChainUnit[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type ChainShowProps = {
    item: ChainDetail;
    units: UnitsPaginator;
    colorOptions: string[];
    filters: {
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;
const DEFAULT_PER_PAGE = 10;

const PER_PAGE_FILTER_OPTIONS = PAGE_SIZE_OPTIONS.map((size) => ({
    value: String(size),
    label: String(size),
}));

export default function ChainShow({
    item,
    units,
    colorOptions,
    filters,
}: ChainShowProps) {
    const [issuingUnit, setIssuingUnit] = useState<ChainUnit | null>(null);
    const [addStockOpen, setAddStockOpen] = useState(false);
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [entriesDraft, setEntriesDraft] = useState(String(filters.per_page));

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    const title = item.itemName ?? item.itemCode;

    const totalPages = useMemo(
        () => Math.max(1, units.last_page),
        [units.last_page],
    );

    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasEntriesDraftChanges =
        Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom =
        entriesDraft !== String(DEFAULT_PER_PAGE);

    const visitPage = (page: number, perPage?: number) => {
        router.get(
            show.url(item.id, {
                query: {
                    page,
                    per_page: perPage ?? filters.per_page,
                },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const applyEntries = () => {
        setEntriesMenuOpen(false);
        visitPage(1, Number(entriesDraft));
    };

    const clearEntries = () => {
        setEntriesDraft(String(DEFAULT_PER_PAGE));
        setEntriesMenuOpen(false);
        visitPage(1, DEFAULT_PER_PAGE);
    };

    const handleDelete = () => {
        const label = item.itemName ?? item.itemCode;

        if (!window.confirm(`Hapus chain "${label}"?`)) {
            return;
        }

        router.delete(destroy.url(item.id));
    };

    return (
        <>
            <Head title={`Chain · ${title}`} />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    {title}
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Riwayat unit stok trxchain untuk master
                                    ini
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
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
                            </div>
                            <span
                                className="spkTableHeaderDivider--finishing"
                                aria-hidden="true"
                            />
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Kembali ke daftar chain"
                                onClick={() =>
                                    router.visit(chainsIndex.url())
                                }
                            >
                                <Icon
                                    name={navBackIcon}
                                    mode="Decorative"
                                />
                                <span>Kembali</span>
                            </button>
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Edit master chain"
                                onClick={() =>
                                    router.visit(edit.url(item.id))
                                }
                            >
                                <Icon name={editIcon} mode="Decorative" />
                                <span>Edit Master</span>
                            </button>
                            <button
                                type="button"
                                className="spkCreateBtn"
                                aria-label="Tambah stok"
                                onClick={() => setAddStockOpen(true)}
                            >
                                <Icon name={addIcon} mode="Decorative" />
                                <span>Tambah Stok</span>
                            </button>
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Hapus master chain"
                                onClick={handleDelete}
                            >
                                <Icon name={deleteIcon} mode="Decorative" />
                                <span>Hapus</span>
                            </button>
                        </div>
                    </div>

                    <div className="spkChainDetailSummary spkChainDetailSummary--withPhoto">
                        <InventoryMasterPhotoPanel
                            photoUrl={item.photo}
                            title={title}
                        />
                        <div className="spkChainDetailSummaryMain">
                        <span className="spkDetailFieldLabel">
                            Ringkasan stok
                        </span>
                        <dl className="spkDetailGrid">
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Kategori
                                </dt>
                                <dd className="spkDetailValue">
                                    {item.componentCategory}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">Kode</dt>
                                <dd className="spkDetailValue">
                                    {item.itemCode}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Tersedia
                                </dt>
                                <dd
                                    className="spkDetailValue tabular-nums spkChainStockIn"
                                >
                                    {item.stockIn}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Terpakai
                                </dt>
                                <dd
                                    className="spkDetailValue tabular-nums spkChainStockOut"
                                >
                                    {item.stockOut}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">Stok</dt>
                                <dd className="spkDetailValue tabular-nums">
                                    <span className="spkChainStockBalanceLabel">
                                        {item.balance}
                                    </span>
                                    {' '}
                                    unit · {item.balanceLength} cm
                                </dd>
                            </div>
                        </dl>
                        </div>
                    </div>

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th className="spkTableColCenter">#</th>
                                    <th>ID Unit</th>
                                    <th>Nama</th>
                                    <th>Sumber</th>
                                    <th>Tersedia</th>
                                    <th>Terpakai</th>
                                    <th>SPK</th>
                                    <th className="spkTableColCenter">
                                        Panjang
                                    </th>
                                    <th className="spkTableColCenter">
                                        Berat
                                    </th>
                                    <th>Warna</th>
                                    <th>Supplier</th>
                                    <th className="spkTableColCenter">
                                        Harga beli (USD)
                                    </th>
                                    <th>Status</th>
                                    <th
                                        className="spkTableActionCol spkTableActionCol--iconGroup spkTableColCenter"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {units.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={14}>
                                            Belum ada unit stok untuk chain
                                            ini.
                                        </td>
                                    </tr>
                                ) : (
                                    units.data.map((unit, index) => (
                                        <tr key={unit.id}>
                                            <td className="spkTableColCenter tabular-nums">
                                                {(units.current_page - 1) *
                                                    units.per_page +
                                                    index +
                                                    1}
                                            </td>
                                            <td className="tabular-nums font-medium">
                                                {unit.unitNo ?? '—'}
                                            </td>
                                            <td>{unit.name}</td>
                                            <td>
                                                {sourceTypeLabel(
                                                    unit.sourceType,
                                                )}
                                            </td>
                                            <td>{unit.entryDate ?? '—'}</td>
                                            <td>{unit.outDate ?? '—'}</td>
                                            <td>{unit.spkNo ?? '—'}</td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {unit.componentSize} cm
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {unit.weight ?? '—'}
                                            </td>
                                            <td>{unit.color ?? '—'}</td>
                                            <td>{unit.supplier ?? '—'}</td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {formatUsd(unit.purchasePrice)}
                                            </td>
                                            <td>
                                                {unit.status === 'in_stock'
                                                    ? 'Ready'
                                                    : 'Used'}
                                            </td>
                                            <td
                                                className="spkTableActionCol spkTableActionCol--iconGroup spkTableColCenter"
                                            >
                                                {unit.status === 'in_stock' ? (
                                                    <div
                                                        className="spkTableRowActions spkTableRowActions--row"
                                                    >
                                                        <Button
                                                            design="Transparent"
                                                            type="Button"
                                                            icon={outboxIcon}
                                                            accessibleName="Kurangi stok"
                                                            title="Kurangi stok"
                                                            onClick={() =>
                                                                setIssuingUnit(
                                                                    unit,
                                                                )
                                                            }
                                                        />
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
                            Total {units.total} unit
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={units.current_page <= 1}
                                onClick={() =>
                                    visitPage(units.current_page - 1)
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {units.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={
                                    units.current_page >= totalPages
                                }
                                onClick={() =>
                                    visitPage(units.current_page + 1)
                                }
                            >
                                Berikutnya
                            </button>
                        </div>
                    </div>
                </div>

                <ComponentIssueStockDialog
                    unit={issuingUnit}
                    open={issuingUnit !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setIssuingUnit(null);
                        }
                    }}
                />

                <ComponentAddStockDialog
                    item={{
                        id: item.id,
                        item: item.item,
                        itemCode: item.itemCode,
                        itemName: item.itemName,
                    }}
                    colorOptions={colorOptions}
                    open={addStockOpen}
                    returnTo="show"
                    onOpenChange={setAddStockOpen}
                />
            </div>
        </>
    );
}

ChainShow.layout = {
    activeMenu: 'Stok Komponen',
    pageTitle: 'Detail Chain',
};
