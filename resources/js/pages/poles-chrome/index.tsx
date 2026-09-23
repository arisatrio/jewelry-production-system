import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { create, index as polesChromeIndex, show } from '@/routes/poles-chrome';
import { show as spkShow } from '@/routes/spk';
import { NotesCell } from '@/components/notes-cell';
import { PolesChromeSpkStatusCards } from '@/components/poles-chrome/poles-chrome-spk-status-cards';

type PolesChromeRow = {
    id: number;
    docNo: string | null;
    transDate: string | null;
    status: string | null;
    statusLabel: string | null;
    spkNo: string | null;
    startWeight: string | null;
    finishWeight: string | null;
    shrink: string | null;
    notes: string | null;
};

type DocumentsPaginator = {
    data: PolesChromeRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type PolesChromeIndexProps = {
    documents: DocumentsPaginator;
    spkStatusCounts: {
        pending: number;
        inProgress: number;
        completed: number;
    };
    filters: {
        search: string;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

function formatStatusLabel(
    statusLabel: string | null,
    status: string | null,
): string {
    const label = statusLabel?.trim() ?? '';

    if (label !== '') {
        return label;
    }

    const code = status?.trim() ?? '';

    return code !== '' ? code : '—';
}

function statusBadgeClass(status: string | null, statusLabel: string | null): string {
    const label = formatStatusLabel(statusLabel, status).toLowerCase();

    if (label.includes('done') || label.includes('completed')) {
        return 'spkTableBadge--done';
    }

    if (label.includes('open') || label.includes('draft')) {
        return 'spkTableBadge--default';
    }

    if (label.includes('serahkan') || label.includes('pengrajin') || label.includes('loket')) {
        return 'spkTableBadge--approved';
    }

    return 'spkTableBadge--default';
}

export default function PolesChromeIndex({
    documents,
    spkStatusCounts,
    filters,
}: PolesChromeIndexProps) {
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
                polesChromeIndex.url({
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
        () => Math.max(1, documents.last_page),
        [documents.last_page],
    );

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
    }) => {
        router.get(
            polesChromeIndex.url({
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

    return (
        <>
            <Head title="Poles Chrome" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableActions">
                        <div className="spkTableTitleBlock">
                            <h1 className="spkTableTitle">Dokumen Poles Chrome</h1>
                        </div>
                        <button
                            type="button"
                            className="spkCreateBtn"
                            aria-label="Tambah dokumen Poles Chrome"
                            onClick={() => router.visit(create.url())}
                        >
                            <Icon name={addIcon} mode="Decorative" />
                            <span>Tambah</span>
                        </button>
                    </div>

                    <PolesChromeSpkStatusCards counts={spkStatusCounts} />

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
                                accessibleName="Cari dokumen Poles Chrome"
                                placeholder="Cari nomor dokumen, SPK..."
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
                                    <th>Berat Awal (g)</th>
                                    <th>Berat Akhir (g)</th>
                                    <th>Susut (g)</th>
                                    <th className="spkTableColNotes">Catatan</th>
                                    <th className="spkTableColStatus">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {documents.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8}>
                                            Tidak ada data dokumen Poles Chrome.
                                        </td>
                                    </tr>
                                ) : (
                                    documents.data.map((item) => (
                                        <tr key={item.id}>
                                            <td>
                                                <button
                                                    type="button"
                                                    className="spkProduksiLink"
                                                    onClick={() =>
                                                        router.visit(
                                                            show.url(item.id),
                                                        )
                                                    }
                                                >
                                                    {item.docNo ?? '—'}
                                                </button>
                                            </td>
                                            <td>{item.transDate ?? '—'}</td>
                                            <td>
                                                {item.spkNo ? (
                                                    <button
                                                        type="button"
                                                        className="spkProduksiLink"
                                                        onClick={() =>
                                                            router.visit(
                                                                spkShow.url(
                                                                    item.spkNo!,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        {item.spkNo}
                                                    </button>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>{item.startWeight ?? '—'}</td>
                                            <td>{item.finishWeight ?? '—'}</td>
                                            <td>{item.shrink ?? '—'}</td>
                                            <td className="spkTableColNotes">
                                                <NotesCell
                                                    notes={item.notes}
                                                    docNo={item.docNo}
                                                    spkNo={item.spkNo}
                                                />
                                            </td>
                                            <td className="spkTableColStatus">
                                                {item.status ||
                                                item.statusLabel ? (
                                                    <div className="spkTableStatus">
                                                        <span
                                                            className={`spkTableBadge ${statusBadgeClass(item.status, item.statusLabel)}`}
                                                        >
                                                            {formatStatusLabel(
                                                                item.statusLabel,
                                                                item.status,
                                                            )}
                                                        </span>
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
                            Total {documents.total} dokumen Poles Chrome
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={documents.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: documents.current_page - 1,
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
                                {documents.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={
                                    documents.current_page >= totalPages
                                }
                                onClick={() =>
                                    visit({
                                        page: documents.current_page + 1,
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

PolesChromeIndex.layout = {
    activeMenu: 'Poles Chrome',
    pageTitle: 'Poles Chrome',
};
