import { router, useHttp } from '@inertiajs/react';
import acceptIcon from '@ui5/webcomponents-icons/dist/accept.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { FakturBarangJadiStatusBadge } from '@/components/spk/faktur-barang-jadi-status-badge';
import type { FakturBarangJadiWorkflowStatus } from '@/components/spk/faktur-barang-jadi-status-badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    destroy as destroyFakturBarangJadiReceipt,
    index as fakturBarangJadiReceiptHistory,
    receive as receiveFakturBarangJadiReceipt,
    send as sendFakturBarangJadiReceipt,
} from '@/routes/faktur-barang-jadi/print/receipt';

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
    workflowStatus: FakturBarangJadiWorkflowStatus;
    workflowStatusLabel: string;
    canSend: boolean;
    canReceive: boolean;
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

type FakturBarangJadiReceiptHistoryDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employeeOptions: string[];
    onWorkflowChanged?: () => void;
};

const SEARCH_DEBOUNCE_MS = 300;
const SPK_PREVIEW_LIMIT = 3;

export function FakturBarangJadiReceiptHistoryDialog({
    open,
    onOpenChange,
    employeeOptions,
    onWorkflowChanged,
}: FakturBarangJadiReceiptHistoryDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStatusListModal">
                <DialogHeader>
                    <DialogTitle>Riwayat Faktur Barang Jadi</DialogTitle>
                    <DialogDescription>
                        Kelola alur faktur: dibuat → dikirim → diterima. Klik
                        baris atau tombol cetak untuk preview.
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <FakturBarangJadiReceiptHistoryBody
                        employeeOptions={employeeOptions}
                        onWorkflowChanged={onWorkflowChanged}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function FakturBarangJadiReceiptHistoryBody({
    employeeOptions,
    onWorkflowChanged,
}: {
    employeeOptions: string[];
    onWorkflowChanged?: () => void;
}) {
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<ReceiptHistoryResult | null>(null);
    const [reloadToken, setReloadToken] = useState(0);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [sendingId, setSendingId] = useState<number | null>(null);
    const [receivingId, setReceivingId] = useState<number | null>(null);
    const [receiveName, setReceiveName] = useState('');
    const deleteRequest = useHttp({});
    const sendRequest = useHttp({});
    const receiveRequest = useHttp<{ diterima_oleh: string }>({
        diterima_oleh: '',
    });
    const requestKey = `${page}|${search}|${reloadToken}`;
    const loading = result?.requestKey !== requestKey;
    const rows = result?.rows ?? [];
    const meta = result?.meta ?? null;
    const receiveNameValid =
        receiveName.trim() !== '' && employeeOptions.includes(receiveName.trim());

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

        fetch(fakturBarangJadiReceiptHistory.url({ query }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json() as Promise<{
                    data: ReceiptHistoryRow[];
                    meta: ReceiptHistoryMeta;
                }>;
            })
            .then((payload) => {
                setResult({
                    requestKey,
                    rows: payload.data,
                    meta: payload.meta,
                    failed: false,
                });
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') {
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
    }, [page, search, requestKey, reloadToken]);

    const notifyWorkflowChanged = (): void => {
        setReloadToken((current) => current + 1);
        onWorkflowChanged?.();
        router.reload({ only: ['productions', 'statusCounts'] });
    };

    const openPrint = (row: ReceiptHistoryRow): void => {
        window.open(row.printUrl, '_blank', 'noopener,noreferrer');
    };

    const deleteReceipt = (row: ReceiptHistoryRow): void => {
        if (
            deletingId !== null ||
            sendingId !== null ||
            receivingId !== null ||
            !window.confirm(`Hapus faktur ${row.docNo}?`)
        ) {
            return;
        }

        setDeletingId(row.id);

        void deleteRequest
            .delete(destroyFakturBarangJadiReceipt.url(row.id), {
                onSuccess: () => {
                    if (rows.length === 1 && page > 1) {
                        setPage(page - 1);
                    } else {
                        notifyWorkflowChanged();
                    }
                },
                onHttpException: () => {
                    window.alert(
                        'Gagal menghapus faktur. Silakan coba lagi.',
                    );
                },
                onNetworkError: () => {
                    window.alert(
                        'Koneksi terputus saat menghapus faktur. Silakan coba lagi.',
                    );
                },
                onFinish: () => setDeletingId(null),
            })
            .catch(() => undefined);
    };

    const sendReceipt = (row: ReceiptHistoryRow): void => {
        if (
            sendingId !== null ||
            deletingId !== null ||
            receivingId !== null ||
            !row.canSend ||
            !window.confirm(
                `Tandai faktur ${row.docNo} sebagai dikirim ke ${row.untuk ?? 'tujuan'}?`,
            )
        ) {
            return;
        }

        setSendingId(row.id);

        void sendRequest
            .post(sendFakturBarangJadiReceipt.url(row.id), {
                onSuccess: (response) => {
                    const message =
                        typeof response?.message === 'string'
                            ? response.message
                            : 'Faktur berhasil dikirim.';
                    toast.success(message);
                    notifyWorkflowChanged();
                },
                onHttpException: () => {
                    toast.error('Gagal menandai faktur dikirim.');
                },
                onNetworkError: () => {
                    toast.error('Koneksi terputus saat mengirim faktur.');
                },
                onFinish: () => setSendingId(null),
            })
            .catch(() => undefined);
    };

    const startReceive = (row: ReceiptHistoryRow): void => {
        if (!row.canReceive) {
            return;
        }

        setReceivingId(row.id);
        setReceiveName('');
    };

    const cancelReceive = (): void => {
        setReceivingId(null);
        setReceiveName('');
    };

    const confirmReceive = (row: ReceiptHistoryRow): void => {
        if (!receiveNameValid || receivingId !== row.id) {
            return;
        }

        receiveRequest.setData('diterima_oleh', receiveName.trim());

        void receiveRequest
            .post(receiveFakturBarangJadiReceipt.url(row.id), {
                onSuccess: (response) => {
                    const message =
                        typeof response?.message === 'string'
                            ? response.message
                            : 'Faktur berhasil diterima.';
                    toast.success(message);
                    cancelReceive();
                    notifyWorkflowChanged();
                },
                onHttpException: () => {
                    toast.error('Gagal menerima faktur.');
                },
                onNetworkError: () => {
                    toast.error('Koneksi terputus saat menerima faktur.');
                },
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
                        placeholder="Cari no. faktur, SPK, nama..."
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        aria-label="Cari riwayat faktur"
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
                    <p className="spkAlertModalEmpty">Belum ada faktur.</p>
                ) : (
                    <table className="spkAlertModalTable spkReceiptHistoryTable">
                        <thead>
                            <tr>
                                <th>No. Faktur</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Dari → Untuk</th>
                                <th>SPK</th>
                                <th>Penanda Tangan</th>
                                <th className="spkTableColCenter">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const hiddenSpkCount =
                                    row.spkNos.length - SPK_PREVIEW_LIMIT;
                                const isReceiving = receivingId === row.id;

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
                                        <td className="spkTableColCenter whitespace-nowrap">
                                            <FakturBarangJadiStatusBadge
                                                status={row.workflowStatus}
                                            />
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
                                        <td
                                            className="spkTableColCenter"
                                            onClick={(event) =>
                                                event.stopPropagation()
                                            }
                                        >
                                            {isReceiving ? (
                                                <div className="flex max-w-[14rem] flex-col gap-2 text-left">
                                                    <ComboBox
                                                        placeholder="Diterima oleh"
                                                        value={receiveName}
                                                        onInput={(event) =>
                                                            setReceiveName(
                                                                event.target.value,
                                                            )
                                                        }
                                                        onSelectionChange={(
                                                            event,
                                                        ) => {
                                                            const detail =
                                                                event.detail;
                                                            const item =
                                                                detail.item;
                                                            if (item) {
                                                                setReceiveName(
                                                                    item.text ??
                                                                        '',
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        {employeeOptions.map(
                                                            (name) => (
                                                                <ComboBoxItem
                                                                    key={name}
                                                                    text={name}
                                                                />
                                                            ),
                                                        )}
                                                    </ComboBox>
                                                    <div className="flex gap-2">
                                                        <button
                                                            type="button"
                                                            className="spkPageBtn"
                                                            disabled={
                                                                !receiveNameValid
                                                            }
                                                            onClick={() =>
                                                                confirmReceive(
                                                                    row,
                                                                )
                                                            }
                                                        >
                                                            Simpan
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="spkPageBtn"
                                                            onClick={
                                                                cancelReceive
                                                            }
                                                        >
                                                            Batal
                                                        </button>
                                                    </div>
                                                </div>
                                            ) : (
                                                <div className="flex flex-col items-center gap-1">
                                                    {row.canSend ? (
                                                        <button
                                                            type="button"
                                                            className="spkChainFulfillmentReceiveBtn"
                                                            disabled={
                                                                sendingId !==
                                                                    null ||
                                                                deletingId !==
                                                                    null
                                                            }
                                                            onClick={() =>
                                                                sendReceipt(row)
                                                            }
                                                        >
                                                            {sendingId === row.id
                                                                ? 'Mengirim…'
                                                                : 'Kirim'}
                                                        </button>
                                                    ) : null}
                                                    {row.canReceive ? (
                                                        <button
                                                            type="button"
                                                            className="spkChainFulfillmentReceiveBtn"
                                                            disabled={
                                                                receivingId !==
                                                                    null ||
                                                                deletingId !==
                                                                    null
                                                            }
                                                            onClick={() =>
                                                                startReceive(
                                                                    row,
                                                                )
                                                            }
                                                        >
                                                            <Icon
                                                                name={acceptIcon}
                                                                mode="Decorative"
                                                            />
                                                            <span className="spkChainFulfillmentActionBtnLabel">
                                                                Terima
                                                            </span>
                                                        </button>
                                                    ) : null}
                                                    <div className="flex items-center justify-center">
                                                        <Button
                                                            design="Transparent"
                                                            icon={printIcon}
                                                            accessibleName="Cetak"
                                                            onClick={() =>
                                                                openPrint(row)
                                                            }
                                                        />
                                                        <Button
                                                            design="Transparent"
                                                            icon={deleteIcon}
                                                            accessibleName="Hapus"
                                                            disabled={
                                                                deletingId ===
                                                                row.id
                                                            }
                                                            onClick={() =>
                                                                deleteReceipt(
                                                                    row,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>

            {meta && meta.lastPage > 1 ? (
                <div className="spkStatusListPagination">
                    <button
                        type="button"
                        className="spkPageBtn"
                        disabled={page <= 1}
                        onClick={() => setPage(page - 1)}
                    >
                        Sebelumnya
                    </button>
                    <span>
                        Halaman {meta.currentPage} / {meta.lastPage}
                    </span>
                    <button
                        type="button"
                        className="spkPageBtn"
                        disabled={page >= meta.lastPage}
                        onClick={() => setPage(page + 1)}
                    >
                        Berikutnya
                    </button>
                </div>
            ) : null}
        </>
    );
}
