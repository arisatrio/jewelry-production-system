import { Head, router } from '@inertiajs/react';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { useEffect, useMemo, useState } from 'react';
import { index } from '@/routes/inventory/diamond-dossiers';

type DiamondStatus = 'available' | 'used';

type DossierDiamond = {
    id: number;
    code: string | null;
    diamondType: string | null;
    shape: string | null;
    color: string | null;
    crt: string | null;
    certificate: string | null;
    supplier: string | null;
    entryDate: string | null;
    outDate: string | null;
    status: DiamondStatus;
    mountingDocumentNo: string | null;
    createdBy: string | null;
    createdDate: string | null;
};

type DiamondsPaginator = {
    data: DossierDiamond[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type PageProps = {
    diamonds: DiamondsPaginator;
    summary: {
        count: number;
        crt: string;
    };
    filters: {
        search: string;
        status: DiamondStatus | null;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

const STATUS_OPTIONS: { value: '' | DiamondStatus; label: string }[] = [
    { value: '', label: 'Semua status' },
    { value: 'available', label: 'Tersedia' },
    { value: 'used', label: 'Terpakai' },
];

function formatCrt(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }).format(Number(value));
}

function statusClass(status: DiamondStatus): string {
    if (status === 'used') {
        return 'inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700';
    }

    return 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700';
}

export default function DiamondDossierIndex({
    diamonds,
    summary,
    filters,
}: PageProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
        status?: DiamondStatus | null;
    }) => {
        const status =
            params.status !== undefined ? params.status : filters.status;

        router.get(
            index.url({
                query: {
                    search: params.search || undefined,
                    status: status || undefined,
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

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            router.get(
                index.url({
                    query: {
                        search: searchQuery || undefined,
                        status: filters.status || undefined,
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
    }, [searchQuery, filters.search, filters.status, filters.per_page]);

    const totalPages = useMemo(
        () => Math.max(1, diamonds.last_page),
        [diamonds.last_page],
    );

    return (
        <>
            <Head title="Batu Dossier" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableActions">
                        <div className="spkTableTitleBlock">
                            <h1 className="spkTableTitle">Batu Dossier</h1>
                        </div>
                    </div>

                    <div className="spkTableToolbar">
                        <div className="spkTableToolbarLeft">
                            <span className="spkTablePageSizeLabel">Show</span>
                            <Select
                                accessibleName="Jumlah baris per halaman"
                                onChange={(event) =>
                                    visit({
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
                            <Select
                                accessibleName="Filter status batu"
                                onChange={(event) =>
                                    visit({
                                        search: searchQuery,
                                        status:
                                            (event.detail.selectedOption
                                                .value as DiamondStatus) ||
                                            null,
                                    })
                                }
                            >
                                {STATUS_OPTIONS.map((option) => (
                                    <Option
                                        key={option.value}
                                        value={option.value}
                                        selected={
                                            (filters.status ?? '') ===
                                            option.value
                                        }
                                    >
                                        {option.label}
                                    </Option>
                                ))}
                            </Select>
                        </div>
                        <div className="spkTableToolbarRight">
                            <Input
                                accessibleName="Cari batu dossier"
                                placeholder="Cari kode, sertifikat, dokumen..."
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
                                    <th>Kode</th>
                                    <th>Tipe</th>
                                    <th>Bentuk</th>
                                    <th>Warna</th>
                                    <th className="text-right">CRT</th>
                                    <th>Sertifikat</th>
                                    <th>Supplier</th>
                                    <th>Tanggal masuk</th>
                                    <th>Tanggal keluar</th>
                                    <th>Status</th>
                                    <th>Dokumen Pasang Batu</th>
                                </tr>
                            </thead>
                            <tbody>
                                {diamonds.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={11}>
                                            Tidak ada batu dossier.
                                        </td>
                                    </tr>
                                ) : (
                                    diamonds.data.map((diamond) => (
                                        <tr key={diamond.id}>
                                            <td className="font-medium">
                                                {diamond.code ?? '—'}
                                            </td>
                                            <td>
                                                {diamond.diamondType ?? '—'}
                                            </td>
                                            <td>{diamond.shape ?? '—'}</td>
                                            <td>{diamond.color ?? '—'}</td>
                                            <td className="text-right tabular-nums">
                                                {formatCrt(diamond.crt)}
                                            </td>
                                            <td>
                                                {diamond.certificate ?? '—'}
                                            </td>
                                            <td>{diamond.supplier ?? '—'}</td>
                                            <td>{diamond.entryDate ?? '—'}</td>
                                            <td>{diamond.outDate ?? '—'}</td>
                                            <td>
                                                <span
                                                    className={statusClass(
                                                        diamond.status,
                                                    )}
                                                >
                                                    {diamond.status === 'used'
                                                        ? 'Terpakai'
                                                        : 'Tersedia'}
                                                </span>
                                            </td>
                                            <td>
                                                {diamond.mountingDocumentNo ??
                                                    '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total: {summary.count.toLocaleString('id-ID')} batu
                            · {formatCrt(summary.crt)} crt
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={diamonds.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: diamonds.current_page - 1,
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
                                {diamonds.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={diamonds.current_page >= totalPages}
                                onClick={() =>
                                    visit({
                                        page: diamonds.current_page + 1,
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

DiamondDossierIndex.layout = {
    activeMenu: 'Batu Dossier',
    pageTitle: 'Batu Dossier',
};
