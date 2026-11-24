import { useHttp } from '@inertiajs/react';
import acceptIcon from '@ui5/webcomponents-icons/dist/accept.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useState } from 'react';
import { toast } from 'sonner';
import { FakturBarangJadiStatusBadge } from '@/components/spk/faktur-barang-jadi-status-badge';
import type { FakturBarangJadiWorkflowStatus } from '@/components/spk/faktur-barang-jadi-status-badge';
import {
    destroy as destroyFakturBarangJadiReceipt,
    receive as receiveFakturBarangJadiReceipt,
    send as sendFakturBarangJadiReceipt,
} from '@/routes/faktur-barang-jadi/print/receipt';

export type FakturBarangJadiReceiptRow = {
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

export type FakturBarangJadiReceiptMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
};

const SPK_PREVIEW_LIMIT = 3;

type FakturBarangJadiReceiptListProps = {
    employeeOptions: string[];
    rows: FakturBarangJadiReceiptRow[];
    meta: FakturBarangJadiReceiptMeta | null;
    page: number;
    onPageChange: (page: number) => void;
    loading?: boolean;
    failed?: boolean;
    onWorkflowChanged?: () => void;
    layout?: 'page' | 'modal';
};

export function FakturBarangJadiReceiptList({
    employeeOptions,
    rows,
    meta,
    page,
    onPageChange,
    loading = false,
    failed = false,
    onWorkflowChanged,
    layout = 'modal',
}: FakturBarangJadiReceiptListProps) {
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [sendingId, setSendingId] = useState<number | null>(null);
    const [receivingId, setReceivingId] = useState<number | null>(null);
    const [receiveName, setReceiveName] = useState('');
    const deleteRequest = useHttp({});
    const sendRequest = useHttp({});
    const receiveRequest = useHttp<{ diterima_oleh: string }>({
        diterima_oleh: '',
    });
    const receiveNameValid =
        receiveName.trim() !== '' && employeeOptions.includes(receiveName.trim());
    const isPage = layout === 'page';
    const bodyClassName = [
        isPage ? undefined : 'spkAlertModalBody spkStatusListBody',
        !isPage && loading ? 'is-loading' : '',
    ]
        .filter(Boolean)
        .join(' ');
    const tableClassName = isPage
        ? 'spkTable spkReceiptHistoryTable'
        : 'spkAlertModalTable spkReceiptHistoryTable';
    const rowClassName = isPage ? undefined : 'spkAlertModalRow';
    const docLinkClassName = isPage ? 'spkProduksiLink' : 'spkAlertModalLink';

    const notifyWorkflowChanged = (): void => {
        onWorkflowChanged?.();
    };

    const openPrint = (row: FakturBarangJadiReceiptRow): void => {
        window.open(row.printUrl, '_blank', 'noopener,noreferrer');
    };

    const deleteReceipt = (row: FakturBarangJadiReceiptRow): void => {
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
                        onPageChange(page - 1);
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

    const sendReceipt = (row: FakturBarangJadiReceiptRow): void => {
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

    const startReceive = (row: FakturBarangJadiReceiptRow): void => {
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

    const confirmReceive = (row: FakturBarangJadiReceiptRow): void => {
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

    const tableBody =
        failed && !isPage ? null : (
            <tbody>
                {failed && isPage ? (
                    <tr>
                        <td colSpan={7}>
                            Gagal memuat data. Silakan coba lagi.
                        </td>
                    </tr>
                ) : rows.length === 0 ? (
                    <tr>
                        <td colSpan={7}>
                            {isPage
                                ? 'Tidak ada faktur barang jadi.'
                                : 'Belum ada faktur.'}
                        </td>
                    </tr>
                ) : (
                    rows.map((row) => {
                                const hiddenSpkCount =
                                    row.spkNos.length - SPK_PREVIEW_LIMIT;
                                const isReceiving = receivingId === row.id;

                                return (
                                    <tr
                                        key={row.id}
                                        className={rowClassName}
                                        onClick={
                                            isPage
                                                ? undefined
                                                : () => openPrint(row)
                                        }
                                    >
                                        <td
                                            className="spkTableColCenter spkFakturBarangJadiReceiptHistoryColAction"
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
                                                            const item =
                                                                event.detail
                                                                    .item;
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
                                        <td className="whitespace-nowrap font-medium">
                                            <button
                                                type="button"
                                                className={docLinkClassName}
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
                                                <span
                                                    className={
                                                        isPage
                                                            ? 'font-medium'
                                                            : 'spkAlertModalTableIdentifier'
                                                    }
                                                >
                                                    {row.jumlahSpk} SPK
                                                </span>
                                                <span
                                                    className={
                                                        isPage
                                                            ? 'spkTableDescriptionItem'
                                                            : 'spkAlertModalTableSubText'
                                                    }
                                                >
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
                                    </tr>
                                );
                            })
                )}
            </tbody>
        );

    const tableElement = (
        <table className={tableClassName}>
            <thead>
                <tr>
                    <th className="spkTableColCenter spkFakturBarangJadiReceiptHistoryColAction">
                        Aksi
                    </th>
                    <th>No. Faktur</th>
                    <th className="spkTableColCenter">Status</th>
                    <th className="spkTableColCenter">Tanggal</th>
                    <th className="spkTableColCenter">Dari → Untuk</th>
                    <th>SPK</th>
                    <th>Penanda Tangan</th>
                </tr>
            </thead>
            {tableBody}
        </table>
    );

    if (isPage) {
        return tableElement;
    }

    return (
        <>
            <div className={bodyClassName}>
                {loading && rows.length === 0 ? (
                    <p className="spkAlertModalEmpty">Memuat data...</p>
                ) : failed ? (
                    <p className="spkAlertModalEmpty">
                        Gagal memuat data. Silakan coba lagi.
                    </p>
                ) : rows.length === 0 ? (
                    <p className="spkAlertModalEmpty">Belum ada faktur.</p>
                ) : (
                    tableElement
                )}
            </div>

            {meta && meta.lastPage > 1 ? (
                <div className="spkStatusListPagination">
                    <button
                        type="button"
                        className="spkPageBtn"
                        disabled={page <= 1}
                        onClick={() => onPageChange(page - 1)}
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
                        onClick={() => onPageChange(page + 1)}
                    >
                        Berikutnya
                    </button>
                </div>
            ) : null}
        </>
    );
}