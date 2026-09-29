import { useHttp } from '@inertiajs/react';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    destroy as destroySpkReceipt,
    index as spkReceiptHistory,
} from '@/routes/spk/print/receipt';

type ReceiptHistoryRow = {
    id: number;
    docNo: string;
    tanggal: string;
    dari: string | null;
    untuk: string | null;
    jumlahSpk: number;
    spkNos: string[];
    diserahkanOleh: string | null;
    diketahuiOleh: string | null;
    diterimaOleh: string | null;
    createdBy: string | null;
    createdAt: string | null;
    printUrl: string;
};

type ReceiptHistoryMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
};

type ReceiptHistoryResult = {
    requestKey: string;
    rows: ReceiptHistoryRow[];
    meta: ReceiptHistoryMeta | null;
    failed: boolean;
};

type SpkReceiptHistoryDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const SEARCH_DEBOUNCE_MS = 300;
const SPK_PREVIEW_LIMIT = 3;

export function SpkReceiptHistoryDialog({
    open,
    onOpenChange,
}: SpkReceiptHistoryDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStatusListModal">
                <DialogHeader>
                    <DialogTitle>Riwayat Tanda Terima</DialogTitle>
                    <DialogDescription>
                        Daftar form serah terima SPK yang sudah dibuat. Klik
                        baris untuk mencetak ulang.
                    </DialogDescription>
                </DialogHeader>
                {open ? <SpkReceiptHistoryBody /> : null}
            </DialogContent>
        </Dialog>
    );
}

function SpkReceiptHistoryBody() {
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<ReceiptHistoryResult | null>(null);
    const [reloadToken, setReloadToken] = useState(0);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const deleteRequest = useHttp({});
    const requestKey = `${page}|${search}|${reloadToken}`;
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
        const query: Record<string, string | number> = {};

        if (page > 1) {
            query.page = page;
        }

        if (search !== '') {
            query.search = search;
        }

        fetch(spkReceiptHistory.url({ query }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = (await response.json()) as {
                    data?: ReceiptHistoryRow[];
                    meta?: ReceiptHistoryMeta;
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

                setResult({ requestKey, rows: [], meta: null, failed: true });
            });

        return () => controller.abort();
    }, [page, search, requestKey]);

    const openPrint = (row: ReceiptHistoryRow): void => {
        const receiptWindow = window.open(row.printUrl, '_blank');

        if (!receiptWindow) {
            window.alert(
                'Gagal membuka tanda terima. Izinkan pop-up untuk situs ini, lalu coba lagi.',
            );
        }
    };

    const deleteReceipt = (row: ReceiptHistoryRow): void => {
        if (
            deletingId !== null ||
            !window.confirm(
                `Hapus tanda terima ${row.docNo}? Nomor form ini tidak akan dipakai ulang.`,
            )
        ) {
            return;
        }

        setDeletingId(row.id);

        void deleteRequest
            .delete(destroySpkReceipt.url(row.id), {
                onSuccess: () => {
                    if (rows.length === 1 && page > 1) {
                        setPage(page - 1);
                    } else {
                        setReloadToken((current) => current + 1);
                    }
                },
                onHttpException: () => {
                    window.alert(
                        'Gagal menghapus tanda terima. Silakan coba lagi.',
                    );
                },
                onNetworkError: () => {
                    window.alert(
                        'Koneksi terputus saat menghapus tanda terima. Silakan coba lagi.',
                    );
                },
                onFinish: () => setDeletingId(null),
            })
            .catch(() => undefined);
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
                        placeholder="Cari No Form, No SPK, nama..."
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        aria-label="Cari tanda terima"
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
                        Belum ada tanda terima.
                    </p>
                ) : (
                    <table className="spkAlertModalTable spkReceiptHistoryTable">
                        <thead>
                            <tr>
                                <th>No Form</th>
                                <th>Tanggal</th>
                                <th>Dari → Untuk</th>
                                <th>SPK</th>
                                <th>Penanda Tangan</th>
                                <th>Dibuat Oleh</th>
                                <th className="spkTableColCenter">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const hiddenSpkCount =
                                    row.spkNos.length - SPK_PREVIEW_LIMIT;

                                return (
                                    <tr
                                        key={row.id}
                                        className="spkAlertModalRow"
                                        onClick={() => openPrint(row)}
                                    >
                                        <td className="whitespace-nowrap">
                                            <button
                                                type="button"
                                                className="spkAlertModalLink"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    openPrint(row);
                                                }}
                                            >
                                                {row.docNo}
                                            </button>
                                        </td>
                                        <td className="whitespace-nowrap">
                                            {row.tanggal}
                                        </td>
                                        <td className="whitespace-nowrap">
                                            {row.dari ?? '—'} →{' '}
                                            {row.untuk ?? '—'}
                                        </td>
                                        <td className="spkReceiptHistoryColWrap">
                                            <div className="flex flex-col">
                                                <span className="spkAlertModalTableIdentifier">
                                                    {row.jumlahSpk} SPK
                                                </span>
                                                <span className="spkAlertModalTableSubText">
                                                    {row.spkNos
                                                        .slice(
                                                            0,
                                                            SPK_PREVIEW_LIMIT,
                                                        )
                                                        .join(', ')}
                                                    {hiddenSpkCount > 0
                                                        ? ` +${hiddenSpkCount} lainnya`
                                                        : ''}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="spkReceiptHistoryColWrap">
                                            <div className="flex flex-col text-xs">
                                                <span>
                                                    {row.diserahkanOleh ?? '—'}
                                                </span>
                                                <span>
                                                    {row.diketahuiOleh ?? '—'}
                                                </span>
                                                <span>
                                                    {row.diterimaOleh ?? '—'}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div className="flex flex-col">
                                                <span>
                                                    {row.createdBy ?? '—'}
                                                </span>
                                                {row.createdAt ? (
                                                    <span className="spkAlertModalTableSubText">
                                                        {row.createdAt}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="spkTableColCenter">
                                            <div className="flex items-center justify-center">
                                                <Button
                                                    design="Transparent"
                                                    icon={printIcon}
                                                    accessibleName={`Cetak ulang ${row.docNo}`}
                                                    tooltip="Cetak ulang"
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        openPrint(row);
                                                    }}
                                                />
                                                <Button
                                                    design="Transparent"
                                                    icon={deleteIcon}
                                                    className="spkReceiptHistoryDeleteBtn"
                                                    accessibleName={`Hapus ${row.docNo}`}
                                                    tooltip="Hapus"
                                                    disabled={
                                                        deletingId !== null
                                                    }
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        deleteReceipt(row);
                                                    }}
                                                />
                                            </div>
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
                        Total {meta.total.toLocaleString('id-ID')} tanda terima
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
