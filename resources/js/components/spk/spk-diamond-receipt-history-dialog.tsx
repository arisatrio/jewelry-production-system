import { useHttp } from '@inertiajs/react';
import acceptIcon from '@ui5/webcomponents-icons/dist/accept.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    destroy as destroySpkDiamondReceipt,
    index as spkDiamondReceiptHistory,
    receive as receiveSpkDiamondReceiptDocuments,
} from '@/routes/spk-diamond/print/receipt';

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
    canReceiveDocuments: boolean;
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

type SpkDiamondReceiptHistoryDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDocumentsReceived?: () => void;
};

const SEARCH_DEBOUNCE_MS = 300;
const DOC_PREVIEW_LIMIT = 2;

export function SpkDiamondReceiptHistoryDialog({
    open,
    onOpenChange,
    onDocumentsReceived,
}: SpkDiamondReceiptHistoryDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStatusListModal spkDiamondReceiptHistoryModal">
                <DialogHeader>
                    <DialogTitle>Faktur Tanda Terima</DialogTitle>
                    <DialogDescription>
                        Daftar faktur tanda terima SPK Pasang Batu. Klik baris
                        untuk mencetak ulang.
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <SpkDiamondReceiptHistoryBody
                        onDocumentsReceived={onDocumentsReceived}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function SpkDiamondReceiptHistoryBody({
    onDocumentsReceived,
}: {
    onDocumentsReceived?: () => void;
}) {
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<ReceiptHistoryResult | null>(null);
    const [reloadToken, setReloadToken] = useState(0);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [receivingId, setReceivingId] = useState<number | null>(null);
    const deleteRequest = useHttp({});
    const receiveRequest = useHttp({});
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

        fetch(spkDiamondReceiptHistory.url({ query }), {
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
            .delete(destroySpkDiamondReceipt.url(row.id), {
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

    const receiveDocuments = (row: ReceiptHistoryRow): void => {
        if (
            receivingId !== null ||
            deletingId !== null ||
            !row.canReceiveDocuments ||
            !window.confirm(
                `Terima ${row.jumlahSpk} dokumen pada faktur ${row.docNo}? Status SPK menjadi Diterima Workshop dan lokasi batu dossier/mikro diubah ke Workshop.`,
            )
        ) {
            return;
        }

        setReceivingId(row.id);

        void receiveRequest
            .post(receiveSpkDiamondReceiptDocuments.url(row.id), {
                onSuccess: (response) => {
                    const message =
                        typeof response?.message === 'string'
                            ? response.message
                            : 'Dokumen berhasil diterima.';
                    toast.success(message);
                    setReloadToken((current) => current + 1);
                    onDocumentsReceived?.();
                },
                onHttpException: () => {
                    toast.error(
                        'Gagal menerima dokumen. Silakan coba lagi.',
                    );
                },
                onNetworkError: () => {
                    toast.error(
                        'Koneksi terputus saat menerima dokumen. Silakan coba lagi.',
                    );
                },
                onFinish: () => setReceivingId(null),
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
                        placeholder="Cari No Form, No SPK Pasang Batu, nama..."
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
                    <table className="spkAlertModalTable spkReceiptHistoryTable spkDiamondReceiptHistoryTable">
                        <thead>
                            <tr>
                                <th className="spkDiamondReceiptHistoryColDoc">
                                    No Form
                                </th>
                                <th className="spkDiamondReceiptHistoryColDate">
                                    Tanggal
                                </th>
                                <th className="spkDiamondReceiptHistoryColRoute">
                                    Dari → Untuk
                                </th>
                                <th className="spkDiamondReceiptHistoryColSpk">
                                    SPK Pasang Batu
                                </th>
                                <th className="spkDiamondReceiptHistoryColMeta">
                                    Dibuat
                                </th>
                                <th className="spkTableColCenter spkDiamondReceiptHistoryColAction">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const hiddenDocCount =
                                    row.spkNos.length - DOC_PREVIEW_LIMIT;

                                return (
                                    <tr
                                        key={row.id}
                                        className="spkAlertModalRow"
                                        onClick={() => openPrint(row)}
                                    >
                                        <td className="spkDiamondReceiptHistoryColDoc">
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
                                        <td className="spkDiamondReceiptHistoryColDate tabular-nums">
                                            {row.tanggal}
                                        </td>
                                        <td className="spkDiamondReceiptHistoryColRoute">
                                            {row.dari ?? '—'} →{' '}
                                            {row.untuk ?? '—'}
                                        </td>
                                        <td className="spkDiamondReceiptHistoryColSpk spkReceiptHistoryColWrap">
                                            <div className="flex flex-col">
                                                <span className="spkAlertModalTableIdentifier">
                                                    {row.jumlahSpk} dokumen
                                                </span>
                                                <span className="spkAlertModalTableSubText">
                                                    {row.spkNos
                                                        .slice(
                                                            0,
                                                            DOC_PREVIEW_LIMIT,
                                                        )
                                                        .join(', ')}
                                                    {hiddenDocCount > 0
                                                        ? ` +${hiddenDocCount} lainnya`
                                                        : ''}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="spkDiamondReceiptHistoryColMeta">
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
                                        <td className="spkTableColCenter spkDiamondReceiptHistoryColAction">
                                            <div className="spkDiamondReceiptHistoryActionRow">
                                                {row.canReceiveDocuments ? (
                                                    <button
                                                        type="button"
                                                        className="spkChainFulfillmentReceiveBtn spkDiamondReceiptHistoryReceiveBtn"
                                                        disabled={
                                                            receivingId !==
                                                                null ||
                                                            deletingId !== null
                                                        }
                                                        title="Terima dokumen SPK pada faktur ini"
                                                        onClick={(event) => {
                                                            event.stopPropagation();
                                                            receiveDocuments(
                                                                row,
                                                            );
                                                        }}
                                                    >
                                                        <Icon
                                                            name={acceptIcon}
                                                            mode="Decorative"
                                                        />
                                                        <span className="spkChainFulfillmentActionBtnLabel">
                                                            {receivingId ===
                                                            row.id
                                                                ? 'Memproses…'
                                                                : 'Terima Dokumen'}
                                                        </span>
                                                    </button>
                                                ) : null}
                                                <div className="spkDiamondReceiptHistoryIconActions">
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
                                                            deletingId !==
                                                                null ||
                                                            receivingId !==
                                                                null
                                                        }
                                                        onClick={(event) => {
                                                            event.stopPropagation();
                                                            deleteReceipt(row);
                                                        }}
                                                    />
                                                </div>
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
