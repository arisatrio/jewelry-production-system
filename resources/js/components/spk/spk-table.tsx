import { useMemo, useState, type ReactNode } from 'react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import type { SpkRow, SpkStatus } from '@/components/spk/types';
import { SPK_TABLE_COLUMNS } from '@/components/spk/types';
import { SpkTypeBadge } from '@/components/spk/spk-type-badge';

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

export type SpkTableProps = {
    rows: SpkRow[];
    total?: number;
    pageSize?: number;
    defaultPageSize?: number;
    onPageSizeChange?: (size: number) => void;
    searchQuery?: string;
    defaultSearchQuery?: string;
    onSearchChange?: (query: string) => void;
    currentPage?: number;
    defaultPage?: number;
    onPageChange?: (page: number) => void;
    onCreate?: () => void;
    onOpenRow?: (row: SpkRow) => void;
    onProduksiNoClick?: (row: SpkRow) => void;
    footerNote?: ReactNode;
    emptyText?: string;
};

function statusClassName(status: SpkStatus): string {
    if (status === 'Approved') {
        return 'spkStatusBadge spkStatusBadge--approved';
    }

    if (status === 'Pengajuan Approval') {
        return 'spkStatusBadge spkStatusBadge--pending';
    }

    return 'spkStatusBadge spkStatusBadge--default';
}

function buildPageItems(currentPage: number, totalPages: number): Array<number | 'ellipsis'> {
    if (totalPages <= 7) {
        return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const pages = new Set<number>([1, totalPages, currentPage - 1, currentPage, currentPage + 1]);
    const sorted = [...pages].filter((page) => page >= 1 && page <= totalPages).sort((a, b) => a - b);
    const items: Array<number | 'ellipsis'> = [];

    for (const page of sorted) {
        const previous = items[items.length - 1];

        if (typeof previous === 'number' && page - previous > 1) {
            items.push('ellipsis');
        }

        items.push(page);
    }

    return items;
}

export default function SpkTable({
    rows,
    total,
    pageSize: controlledPageSize,
    defaultPageSize = 10,
    onPageSizeChange,
    searchQuery: controlledSearchQuery,
    defaultSearchQuery = '',
    onSearchChange,
    currentPage: controlledPage,
    defaultPage = 1,
    onPageChange,
    onCreate,
    onOpenRow,
    onProduksiNoClick,
    footerNote = '',
    emptyText = 'Tidak ada data SPK.',
}: SpkTableProps) {
    const isPageSizeControlled = controlledPageSize !== undefined;
    const [uncontrolledPageSize, setUncontrolledPageSize] = useState(defaultPageSize);
    const pageSize = isPageSizeControlled ? controlledPageSize : uncontrolledPageSize;

    const isSearchControlled = controlledSearchQuery !== undefined;
    const [uncontrolledSearchQuery, setUncontrolledSearchQuery] = useState(defaultSearchQuery);
    const searchQuery = isSearchControlled ? controlledSearchQuery : uncontrolledSearchQuery;

    const isPageControlled = controlledPage !== undefined;
    const [uncontrolledPage, setUncontrolledPage] = useState(defaultPage);
    const currentPage = isPageControlled ? controlledPage : uncontrolledPage;

    const filteredRows = useMemo(() => {
        if (total !== undefined) {
            return rows;
        }

        const query = searchQuery.trim().toLowerCase();

        if (!query) {
            return rows;
        }

        return rows.filter((row) =>
            [
                row.produksiNo,
                row.tipeProduksi,
                row.customer,
                row.item,
                row.description,
                row.orderDate,
                String(row.workEstimated),
                row.estimatedDelivery,
                row.status,
                row.prosesTerakhir,
            ]
                .join(' ')
                .toLowerCase()
                .includes(query),
        );
    }, [rows, searchQuery, total]);

    const totalRows = total ?? filteredRows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / pageSize));
    const safePage = Math.min(currentPage, totalPages);

    const visibleRows = useMemo(() => {
        if (total !== undefined) {
            return filteredRows;
        }

        const start = (safePage - 1) * pageSize;

        return filteredRows.slice(start, start + pageSize);
    }, [filteredRows, pageSize, safePage, total]);

    const pageItems = buildPageItems(safePage, totalPages);

    const updatePageSize = (size: number) => {
        if (!isPageSizeControlled) {
            setUncontrolledPageSize(size);
        }

        if (!isPageControlled) {
            setUncontrolledPage(1);
        }

        onPageSizeChange?.(size);
        onPageChange?.(1);
    };

    const updateSearchQuery = (query: string) => {
        if (!isSearchControlled) {
            setUncontrolledSearchQuery(query);
        }

        if (!isPageControlled) {
            setUncontrolledPage(1);
        }

        onSearchChange?.(query);
        onPageChange?.(1);
    };

    const updatePage = (page: number) => {
        if (!isPageControlled) {
            setUncontrolledPage(page);
        }

        onPageChange?.(page);
    };

    return (
        <div className="spkTableShell">
            <div className="spkTableActions">
                <button
                    type="button"
                    className="spkCreateBtn"
                    aria-label="Tambah SPK"
                    onClick={onCreate}
                >
                    <Icon name={addIcon} mode="Decorative" />
                    <span>Tambah</span>
                </button>
            </div>

            <div className="spkTableCard">
                <div className="spkTableToolbar">
                    <div className="spkTableToolbarLeft">
                        <span className="spkTablePageSizeLabel">Show</span>
                        <Select
                            accessibleName="Jumlah baris per halaman"
                            onChange={(event) => {
                                updatePageSize(
                                    Number(event.detail.selectedOption.value),
                                );
                            }}
                        >
                            {PAGE_SIZE_OPTIONS.map((size) => (
                                <Option
                                    key={size}
                                    value={String(size)}
                                    selected={size === pageSize}
                                >
                                    {size}
                                </Option>
                            ))}
                        </Select>
                        <span className="spkTablePageSizeLabel">entries</span>
                    </div>
                    <div className="spkTableToolbarRight">
                        <Input
                            accessibleName="Quick search"
                            placeholder="Quick search"
                            value={searchQuery}
                            icon={<Icon name={searchIcon} />}
                            onInput={(event) => {
                                updateSearchQuery(event.target.value ?? '');
                            }}
                        />
                    </div>
                </div>

                <div className="spkTableScroll">
                    <table className="spkTable">
                        <thead>
                            <tr>
                                {SPK_TABLE_COLUMNS.map((column) => (
                                    <th
                                        key={column.key}
                                        className={
                                            column.key === 'action'
                                                ? 'spkTableActionCol'
                                                : undefined
                                        }
                                        scope="col"
                                    >
                                        {column.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {visibleRows.length === 0 ? (
                                <tr>
                                    <td colSpan={SPK_TABLE_COLUMNS.length}>
                                        {emptyText}
                                    </td>
                                </tr>
                            ) : (
                                visibleRows.map((row) => (
                                    <tr key={row.id}>
                                        <td className="spkTableActionCol">
                                            <button
                                                type="button"
                                                className="spkActionBtn"
                                                aria-label={`Buka ${row.produksiNo}`}
                                                title="Buka detail"
                                                onClick={() => onOpenRow?.(row)}
                                            >
                                                <Icon
                                                    name={navigationRightIcon}
                                                />
                                            </button>
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                className="spkProduksiLink"
                                                onClick={() => {
                                                    if (onProduksiNoClick) {
                                                        onProduksiNoClick(row);
                                                        return;
                                                    }

                                                    onOpenRow?.(row);
                                                }}
                                            >
                                                {row.produksiNo}
                                            </button>
                                        </td>
                                        <td>
                                            <SpkTypeBadge
                                                type={row.tipeProduksi}
                                            />
                                        </td>
                                        <td>{row.customer}</td>
                                        <td>{row.item}</td>
                                        <td>{row.description}</td>
                                        <td>{row.orderDate}</td>
                                        <td>{row.workEstimated}</td>
                                        <td>{row.estimatedDelivery}</td>
                                        <td>
                                            <span
                                                className={statusClassName(
                                                    row.status,
                                                )}
                                            >
                                                {row.status}
                                            </span>
                                        </td>
                                        <td>{row.prosesTerakhir || '—'}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="spkTableFooter">
                    <div className="spkTableTotal">
                        Total: {totalRows.toLocaleString('en-US')}
                    </div>
                    <div className="spkPagination" aria-label="Pagination">
                        <button
                            type="button"
                            className="spkPageBtn"
                            disabled={safePage <= 1}
                            onClick={() => updatePage(safePage - 1)}
                        >
                            Previous
                        </button>
                        {pageItems.map((item, index) =>
                            item === 'ellipsis' ? (
                                <span
                                    key={`ellipsis-${index}`}
                                    className="spkPageBtn"
                                    aria-hidden="true"
                                >
                                    …
                                </span>
                            ) : (
                                <button
                                    key={item}
                                    type="button"
                                    className={`spkPageBtn${item === safePage ? ' is-active' : ''}`}
                                    aria-current={
                                        item === safePage ? 'page' : undefined
                                    }
                                    onClick={() => updatePage(item)}
                                >
                                    {item}
                                </button>
                            ),
                        )}
                        <button
                            type="button"
                            className="spkPageBtn"
                            disabled={safePage >= totalPages}
                            onClick={() => updatePage(safePage + 1)}
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>

            {footerNote ? (
                <div className="spkTableMeta">{footerNote}</div>
            ) : null}
        </div>
    );
}
