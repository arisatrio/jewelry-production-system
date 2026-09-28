import { router } from '@inertiajs/react';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useState } from 'react';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import {
    SpkPaymentStatusBadge,
    SpkTableDescriptionCell,
    SpkTableLastProcessCell,
    SpkTableStatusCell,
    displayListDate,
    isListRowIncomplete,
    targetDaysLeftHint,
    tipeProduksiBadgeClass,
} from '@/components/spk/spk-list-cells';
import type { SpkIndexRow } from '@/components/spk/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { show as spkShow } from '@/routes/spk';

type StatusListMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
};

type SpkStatusListRow = SpkIndexRow & {
    documentId?: number | null;
    documentNo?: string | null;
};

type StatusListResult = {
    requestKey: string;
    rows: SpkStatusListRow[];
    meta: StatusListMeta | null;
    failed: boolean;
};

type SpkStatusListDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** URL endpoint daftar SPK (tanpa query `page` / `search`). */
    listUrl: string | null;
    requestId: number;
    title: string;
    hint: string;
    /** Query `status` untuk navigasi prev/next di halaman detail SPK. */
    detailStatus?: string;
    documentUrl?: (documentId: number) => string;
};

const SEARCH_DEBOUNCE_MS = 300;

export function SpkStatusListDialog({
    open,
    onOpenChange,
    listUrl,
    requestId,
    title,
    hint,
    detailStatus,
    documentUrl,
}: SpkStatusListDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStatusListModal">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{hint}</DialogDescription>
                </DialogHeader>
                {listUrl !== null ? (
                    <SpkStatusListBody
                        key={`${listUrl}-${requestId}`}
                        listUrl={listUrl}
                        detailStatus={detailStatus}
                        documentUrl={documentUrl}
                        onNavigate={() => onOpenChange(false)}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function SpkStatusListBody({
    listUrl,
    detailStatus,
    documentUrl,
    onNavigate,
}: {
    listUrl: string;
    detailStatus?: string;
    documentUrl?: (documentId: number) => string;
    onNavigate: () => void;
}) {
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<StatusListResult | null>(null);
    const requestKey = `${listUrl}|${page}|${search}`;
    const loading = result?.requestKey !== requestKey;
    const rows = result?.rows ?? [];
    const meta = result?.meta ?? null;

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            if (searchInput.trim() !== search) {
                setSearch(searchInput.trim());
                setPage(1);
            }
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timeoutId);
    }, [searchInput, search]);

    useEffect(() => {
        const controller = new AbortController();
        const url = new URL(listUrl, window.location.origin);

        if (page > 1) {
            url.searchParams.set('page', String(page));
        }

        if (search !== '') {
            url.searchParams.set('search', search);
        }

        fetch(url.toString(), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = (await response.json()) as {
                    data?: SpkStatusListRow[];
                    meta?: StatusListMeta;
                };

                setResult({
                    requestKey,
                    rows: Array.isArray(payload.data) ? payload.data : [],
                    meta: payload.meta ?? null,
                    failed: false,
                });
            })
            .catch(() => {
                if (controller.signal.aborted) {
                    return;
                }

                setResult({
                    requestKey,
                    rows: [],
                    meta: null,
                    failed: true,
                });
            });

        return () => controller.abort();
    }, [listUrl, page, search, requestKey]);

    const openDetail = (row: SpkStatusListRow) => {
        onNavigate();
        router.visit(
            spkShow.url(row.produksiNo, {
                query: { status: detailStatus },
            }),
        );
    };

    const openDocument = (documentId: number) => {
        if (!documentUrl) {
            return;
        }

        onNavigate();
        router.visit(documentUrl(documentId));
    };

    return (
        <>
            <div className="spkStatusListToolbar">
                <div
                    className="spkTableHeaderSearch--finishing spkStatusListSearch"
                    role="search"
                >
                    <input
                        type="search"
                        className="spkTableHeaderSearchInput--finishing"
                        placeholder="Cari No SPK, pesanan, item..."
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        aria-label="Cari SPK"
                    />
                    <Icon
                        name={searchIcon}
                        className="spkTableHeaderSearchIcon--finishing"
                    />
                </div>
            </div>

            <div
                className={`spkAlertModalBody spkStatusListBody${loading && result !== null ? 'is-loading' : ''}`}
            >
                {loading && result === null ? (
                    <p className="spkAlertModalEmpty">Memuat data...</p>
                ) : result?.failed ? (
                    <p className="spkAlertModalEmpty">
                        Gagal memuat data. Silakan coba lagi.
                    </p>
                ) : rows.length === 0 ? (
                    <p className="spkAlertModalEmpty">
                        Tidak ada SPK untuk status ini.
                    </p>
                ) : (
                    <table className="spkAlertModalTable">
                        <thead>
                            <tr>
                                <th className="spkStatusListColDoc">ID</th>
                                <th>Item</th>
                                <th>Dibuat Oleh</th>
                                <th>Tanggal</th>
                                <th className="spkTableColCenter">
                                    Target Selesai
                                </th>
                                <th className="spkTableColCenter">
                                    Proses Terakhir
                                </th>
                                <th className="spkTableColCenter">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const targetHint =
                                    row.targetDaysLeft !== null &&
                                    isListRowIncomplete(row.status)
                                        ? targetDaysLeftHint(row.targetDaysLeft)
                                        : null;
                                const documentId =
                                    typeof row.documentId === 'number'
                                        ? row.documentId
                                        : null;

                                return (
                                    <tr
                                        key={row.rowId}
                                        className="spkAlertModalRow"
                                        onClick={() => openDetail(row)}
                                    >
                                        <td className="spkStatusListColDoc">
                                            <div className="flex flex-col items-start gap-1">
                                                <button
                                                    type="button"
                                                    className="spkAlertModalLink"
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        openDetail(row);
                                                    }}
                                                >
                                                    {row.produksiNo}
                                                </button>
                                                <span
                                                    className={`spkTableBadge ${tipeProduksiBadgeClass(row.tipeProduksi)}`}
                                                >
                                                    {row.tipeProduksi}
                                                </span>
                                                {row.orderReference ? (
                                                    <span className="spkTableDocMeta">
                                                        {row.orderReference}
                                                    </span>
                                                ) : null}
                                                <SpkPaymentStatusBadge
                                                    status={row.paymentStatus}
                                                />
                                                {row.documentNo ? (
                                                    documentId !== null &&
                                                    documentUrl ? (
                                                        <button
                                                            type="button"
                                                            className="spkStatusListDocLink"
                                                            onClick={(
                                                                event,
                                                            ) => {
                                                                event.stopPropagation();
                                                                openDocument(
                                                                    documentId,
                                                                );
                                                            }}
                                                        >
                                                            Dok.{' '}
                                                            {row.documentNo}
                                                        </button>
                                                    ) : (
                                                        <span className="spkTableDocMeta">
                                                            Dok.{' '}
                                                            {row.documentNo}
                                                        </span>
                                                    )
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="spkStatusListColItem">
                                            <div className="flex items-start gap-3">
                                                <SpkItemThumbnail
                                                    imageUrl={row.spkImageUrl}
                                                    spkNo={row.produksiNo}
                                                />
                                                <SpkTableDescriptionCell
                                                    row={row}
                                                />
                                            </div>
                                        </td>
                                        <td>{row.createdBy ?? '—'}</td>
                                        <td>
                                            {displayListDate(row.createdDate)}
                                        </td>
                                        <td className="spkTableColCenter">
                                            <div className="flex flex-col">
                                                <span>
                                                    {displayListDate(
                                                        row.estimatedDelivery,
                                                    )}
                                                </span>
                                                {targetHint ? (
                                                    <span
                                                        className={`text-xs ${targetHint.className}`}
                                                    >
                                                        {targetHint.label}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="spkTableColCenter">
                                            <SpkTableLastProcessCell
                                                row={row}
                                            />
                                        </td>
                                        <td className="spkTableColCenter">
                                            <SpkTableStatusCell row={row} />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>

            {meta !== null && meta.total > 0 ? (
                <div className="spkTableFooter spkStatusListFooter">
                    <div className="spkTableTotal">
                        Total {meta.total.toLocaleString('id-ID')} SPK
                    </div>
                    <div className="spkPagination">
                        <button
                            type="button"
                            className="spkPageBtn"
                            disabled={loading || meta.currentPage <= 1}
                            onClick={() => setPage(meta.currentPage - 1)}
                        >
                            Sebelumnya
                        </button>
                        <button type="button" className="spkPageBtn is-active">
                            {meta.currentPage}
                        </button>
                        <button
                            type="button"
                            className="spkPageBtn"
                            disabled={
                                loading || meta.currentPage >= meta.lastPage
                            }
                            onClick={() => setPage(meta.currentPage + 1)}
                        >
                            Berikutnya
                        </button>
                    </div>
                </div>
            ) : null}
        </>
    );
}
