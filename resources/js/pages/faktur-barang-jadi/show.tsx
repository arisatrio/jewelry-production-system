import { Head, router, useForm } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import navBackIcon from '@ui5/webcomponents-icons/dist/nav-back.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useMemo, useState } from 'react';
import { FakturBarangJadiStatusBadge } from '@/components/spk/faktur-barang-jadi-status-badge';
import type { FakturBarangJadiWorkflowStatus } from '@/components/spk/faktur-barang-jadi-status-badge';
import {
    FakturBarangJadiDetailTable,
    type FakturBarangJadiPrintGroup,
} from '@/components/spk/faktur-barang-jadi-detail-table';
import {
    FakturBarangJadiSelectSpkDialog,
    type FakturBarangJadiSelectedSpk,
} from '@/components/spk/faktur-barang-jadi-select-spk-dialog';
import { index as fakturBarangJadiIndex } from '@/routes/faktur-barang-jadi';
import {
    attach as attachFakturBarangJadiSpk,
    detach as detachFakturBarangJadiSpk,
} from '@/routes/faktur-barang-jadi/spk';
export type FakturBarangJadiReceiptItem = {
    spkRowId: number;
    spkNo: string;
    type: string;
    status: string;
    item: string;
    plu: string | null;
    location: string;
};

export type FakturBarangJadiReceiptDetail = {
    id: number;
    docNo: string;
    tanggal: string;
    dari: string | null;
    untuk: string | null;
    jumlahSpk: number;
    spkNos: string[];
    items: FakturBarangJadiReceiptItem[];
    diserahkanOleh: string | null;
    diketahuiOleh: string | null;
    diterimaOleh: string | null;
    workflowStatus: FakturBarangJadiWorkflowStatus;
    workflowStatusLabel: string;
    canSend: boolean;
    canReceive: boolean;
    canEditSpk: boolean;
    createdBy: string | null;
    createdAt: string | null;
    printUrl: string;
    showUrl: string;
};

type FakturBarangJadiShowProps = {
    receipt: FakturBarangJadiReceiptDetail;
    printGroups: FakturBarangJadiPrintGroup[];
    fakturBarangJadiFotoBrjAvailable: boolean;
};

export default function FakturBarangJadiShow({
    receipt,
    printGroups,
}: FakturBarangJadiShowProps) {
    const [spkDialogOpen, setSpkDialogOpen] = useState(false);
    const attachForm = useForm<{ ids: number[] }>({ ids: [] });
    const detachForm = useForm({});

    const excludeSpkIds = useMemo(
        () => receipt.items.map((item) => item.spkRowId),
        [receipt.items],
    );

    const openPrint = (): void => {
        window.open(receipt.printUrl, '_blank', 'noopener,noreferrer');
    };

    const attachSpk = (row: FakturBarangJadiSelectedSpk): void => {
        if (!receipt.canEditSpk || attachForm.processing) {
            return;
        }

        attachForm.setData('ids', [row.rowId]);
        attachForm.post(attachFakturBarangJadiSpk.url(receipt.id), {
            preserveScroll: true,
        });
    };

    const detachSpk = (spkRowId: number, spkNo: string): void => {
        if (
            !receipt.canEditSpk ||
            detachForm.processing ||
            !window.confirm(`Keluarkan SPK ${spkNo} dari faktur ini?`)
        ) {
            return;
        }

        detachForm.delete(
            detachFakturBarangJadiSpk.url([receipt.id, spkRowId]),
            {
                preserveScroll: true,
            },
        );
    };

    const routeLabel = `${receipt.dari ?? '—'} → ${receipt.untuk ?? '—'}`;

    return (
        <>
            <Head title={`Faktur ${receipt.docNo}`} />

            <div className="spkTableShell">
                <div className="spkTableCard spkFakturBarangJadiShow">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="spkTableHeaderTitle--finishing">
                                        {receipt.docNo}
                                    </h2>
                                    <FakturBarangJadiStatusBadge
                                        status={receipt.workflowStatus}
                                    />
                                </div>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    {receipt.tanggal} · {routeLabel}
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <div
                                className="spkTableHeaderActions--finishing"
                                role="group"
                                aria-label="Aksi faktur"
                            >
                                <button
                                    type="button"
                                    className="spkCreateBtn spkCreateBtn--outline"
                                    aria-label="Kembali ke daftar faktur"
                                    onClick={() =>
                                        router.visit(
                                            fakturBarangJadiIndex.url(),
                                        )
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
                                    aria-label="Cetak faktur"
                                    onClick={openPrint}
                                >
                                    <Icon name={printIcon} mode="Decorative" />
                                    <span>Cetak</span>
                                </button>
                                {receipt.canEditSpk ? (
                                    <button
                                        type="button"
                                        className="spkCreateBtn"
                                        disabled={attachForm.processing}
                                        aria-label="Tambah SPK ke faktur"
                                        onClick={() => setSpkDialogOpen(true)}
                                    >
                                        <Icon name={addIcon} mode="Decorative" />
                                        <span>
                                            {attachForm.processing
                                                ? 'Menambah…'
                                                : 'Tambah SPK'}
                                        </span>
                                    </button>
                                ) : null}
                            </div>
                        </div>
                    </div>

                    <div className="spkFakturBarangJadiShowMeta border-t border-[var(--sapList_BorderColor)] px-4 py-4">
                        <span className="spkDetailFieldLabel">
                            Informasi faktur
                        </span>
                        <dl className="spkDetailGrid mt-2">
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Tanggal
                                </dt>
                                <dd className="spkDetailValue">
                                    {receipt.tanggal}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">Dari</dt>
                                <dd className="spkDetailValue">
                                    {receipt.dari ?? '—'}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">Untuk</dt>
                                <dd className="spkDetailValue">
                                    {receipt.untuk ?? '—'}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Jumlah SPK
                                </dt>
                                <dd className="spkDetailValue tabular-nums">
                                    {receipt.jumlahSpk}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Diserahkan oleh
                                </dt>
                                <dd className="spkDetailValue">
                                    {receipt.diserahkanOleh ?? '—'}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Diketahui oleh
                                </dt>
                                <dd className="spkDetailValue">
                                    {receipt.diketahuiOleh ?? '—'}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Diterima oleh
                                </dt>
                                <dd className="spkDetailValue">
                                    {receipt.diterimaOleh ?? '—'}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">Dibuat</dt>
                                <dd className="spkDetailValue">
                                    {receipt.createdBy ?? '—'}
                                    {receipt.createdAt
                                        ? ` · ${receipt.createdAt}`
                                        : ''}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div className="spkFakturBarangJadiShowMeta px-4 pb-2">
                        <MessageStrip design="Information" hideCloseButton>
                            {receipt.canEditSpk
                                ? 'SPK dapat ditambah atau dikeluarkan pada faktur ini kapan pun (termasuk setelah dikirim atau diterima). SPK baru harus memenuhi syarat faktur dan pindah ke lokasi tujuan; yang dikeluarkan kembali ke lokasi asal faktur.'
                                : 'Daftar SPK tidak dapat diubah pada faktur ini.'}
                        </MessageStrip>
                    </div>

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <FakturBarangJadiDetailTable
                            groups={printGroups}
                            canEditSpk={receipt.canEditSpk}
                            jumlahSpk={receipt.jumlahSpk}
                            detachProcessing={detachForm.processing}
                            onDetach={detachSpk}
                        />
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total {receipt.jumlahSpk.toLocaleString('id-ID')}{' '}
                            SPK
                        </div>
                    </div>
                </div>
            </div>

            {receipt.canEditSpk ? (
                <FakturBarangJadiSelectSpkDialog
                    open={spkDialogOpen}
                    onOpenChange={setSpkDialogOpen}
                    excludeSpkIds={excludeSpkIds}
                    receiptId={receipt.id}
                    onSelected={attachSpk}
                />
            ) : null}
        </>
    );
}

FakturBarangJadiShow.layout = {
    activeMenu: 'Faktur Barang Jadi',
    pageTitle: 'Detail Faktur Barang Jadi',
};