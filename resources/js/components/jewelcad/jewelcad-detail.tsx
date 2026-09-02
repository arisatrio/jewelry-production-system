import { Fragment, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import acceptIcon from '@ui5/webcomponents-icons/dist/accept.js';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import editIcon from '@ui5/webcomponents-icons/dist/edit.js';
import paperPlaneIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ApprovalFooterColumn = {
    title: string;
    name: string;
    date: string;
};

type ApprovalHistoryEvent = {
    status: string;
    statusLabel: string;
    approve: string;
    notes: string | null;
    createdBy: string | null;
    createdAt: string | null;
};

type JewelCadDetailItem = {
    id: number;
    docNo: string | null;
    operator: string | null;
    transDate: string | null;
    notes: string | null;
    status: string | null;
    details: Array<{
        spkId: number;
        spkNo: string | null;
        material: string | null;
        goldWeight: string;
        skuCode: string | null;
        typeCode: string | null;
        productItemName: string | null;
        satuan: string;
        qty: number | null;
        estimationBrj: string;
        notes: string | null;
    }>;
};

type JewelCadApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    canOpenEdit: boolean;
    canDelete: boolean;
    canManagerApprove: boolean;
    canComplete: boolean;
    status: string;
    statusLabel: string;
};

type JewelCadWorkflowStatus = {
    key: string;
    label: string;
    stageIndex: number;
    stages: Array<{ key: string; label: string }>;
};

type JewelCadDetailProps = {
    requestItem: JewelCadDetailItem;
    approvalFooter: ApprovalFooterColumn[];
    approvalHistory: ApprovalHistoryEvent[];
    approval: JewelCadApprovalAbilities;
    workflowStatus?: JewelCadWorkflowStatus;
    backHref: string;
    editHref: string;
    submitUrl?: string;
    managerApproveUrl?: string;
    completeUrl?: string;
    deleteUrl?: string;
};

function displayValue(value: string | null | undefined): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

function SkuColumnValue({
    typeCode,
    productItemName,
    skuCode,
}: {
    typeCode: string | null;
    productItemName: string | null;
    skuCode: string | null;
}) {
    const typeProductLine = [typeCode, productItemName]
        .map((value) => value?.trim() ?? '')
        .filter(Boolean)
        .join(' | ');
    const sku = skuCode?.trim() ?? '';

    if (typeProductLine === '' && sku === '') {
        return <>—</>;
    }

    return (
        <div className="spkItemTypeProductStack">
            {typeProductLine !== '' ? (
                <span className="spkItemTypeProductLine">{typeProductLine}</span>
            ) : null}
            {sku !== '' ? (
                <span className="spkItemSkuCode">{sku}</span>
            ) : null}
        </div>
    );
}

function formatTransDate(isoDate: string | null): string {
    if (!isoDate) {
        return '—';
    }

    const [year, month, day] = isoDate.split('-');

    if (!year || !month || !day) {
        return isoDate;
    }

    return `${day}/${month}/${year}`;
}

function materialGroupKey(material: string): string {
    const trimmed = material.trim();

    return trimmed !== '' ? trimmed : 'Tanpa Bahan Emas';
}

export function JewelCadDetail({
    requestItem,
    approvalFooter,
    approvalHistory,
    approval,
    workflowStatus,
    backHref,
    editHref,
    submitUrl,
    managerApproveUrl,
    completeUrl,
    deleteUrl,
}: JewelCadDetailProps) {
    const [submitting, setSubmitting] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const detailsByMaterial = useMemo(() => {
        const groups = new Map<
            string,
            Array<{
                detail: JewelCadDetailItem['details'][number];
                index: number;
            }>
        >();

        requestItem.details.forEach((detail, index) => {
            const key = materialGroupKey(detail.material ?? '');
            const rows = groups.get(key) ?? [];
            rows.push({ detail, index });
            groups.set(key, rows);
        });

        return Array.from(groups.entries()).map(([material, rows]) => ({
            material,
            rows,
        }));
    }, [requestItem.details]);

    const activeStageIndex = workflowStatus?.stageIndex ?? 0;
    const statusStages = workflowStatus?.stages ?? [
        { key: 'draft', label: 'Draft' },
        { key: 'submitted', label: 'Pengajuan Approval' },
        { key: 'manager', label: 'Serahkan ke JWCAD' },
        { key: 'done', label: 'Done' },
    ];

    const submitToManager = () => {
        if (!submitUrl || !approval.canSubmit) {
            return;
        }

        setSubmitting(true);
        router.post(
            submitUrl,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const approveByManager = () => {
        if (!managerApproveUrl || !approval.canManagerApprove) {
            return;
        }

        setSubmitting(true);
        router.post(
            managerApproveUrl,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const markComplete = () => {
        if (!completeUrl || !approval.canComplete) {
            return;
        }

        setSubmitting(true);
        router.post(
            completeUrl,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const handleDelete = () => {
        if (!deleteUrl || !approval.canDelete) {
            return;
        }

        setDeleting(true);
        router.delete(deleteUrl, {
            onFinish: () => {
                setDeleting(false);
                setDeleteOpen(false);
            },
        });
    };

    return (
        <>
        <div className="spkDetailShell">
            <div className="spkDetailStack">
                <div className="spkTopBarCard">
                    <div className="spkTopBarMain">
                        <div className="spkTopBarLeft">
                            <div className="spkDocTitleBlock">
                                <div className="spkDocTitleRow">
                                    <h1 className="spkDocTitle">
                                        {displayValue(requestItem.docNo)}
                                    </h1>
                                </div>
                            </div>

                            <div
                                className="spkStatusPipeline"
                                aria-label="Status Request JewelCAD"
                            >
                                {statusStages.map((stage, index) => (
                                    <div
                                        key={stage.key}
                                        className={[
                                            'spkStatusStage',
                                            index === activeStageIndex
                                                ? 'is-active'
                                                : '',
                                            index < activeStageIndex
                                                ? 'is-done'
                                                : '',
                                        ]
                                            .filter(Boolean)
                                            .join(' ')}
                                    >
                                        {stage.label}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="spkControlBarRight">
                            <div className="spkHeaderActions">
                                {((approval.canSubmit && submitUrl) ||
                                    (approval.canManagerApprove &&
                                        managerApproveUrl)) && (
                                    <div className="spkApprovalActions">
                                        {approval.canSubmit && submitUrl ? (
                                            <Button
                                                design="Emphasized"
                                                icon={paperPlaneIcon}
                                                disabled={submitting}
                                                onClick={submitToManager}
                                            >
                                                {submitting
                                                    ? 'Mengirim...'
                                                    : 'Kirim ke Manager Produksi'}
                                            </Button>
                                        ) : null}
                                        {approval.canManagerApprove &&
                                        managerApproveUrl ? (
                                            <Button
                                                design="Positive"
                                                className="spkApprovalApproveBtn"
                                                icon={paperPlaneIcon}
                                                disabled={submitting}
                                                onClick={approveByManager}
                                            >
                                                {submitting
                                                    ? 'Memproses...'
                                                    : 'Approve'}
                                            </Button>
                                        ) : null}
                                    </div>
                                )}
                                {approval.canComplete && completeUrl ? (
                                    <button
                                        type="button"
                                        className="spkHeaderActionBtn spkHeaderActionBtn--positive spkHeaderTextActionBtn"
                                        disabled={submitting}
                                        onClick={markComplete}
                                    >
                                        <Icon
                                            name={acceptIcon}
                                            mode="Decorative"
                                        />
                                        {submitting
                                            ? 'Memproses...'
                                            : 'Selesai'}
                                    </button>
                                ) : null}
                                {approval.canOpenEdit ? (
                                    <button
                                        type="button"
                                        className="spkHeaderActionBtn"
                                        aria-label="Edit"
                                        title="Edit Request JewelCAD"
                                        disabled={submitting || deleting}
                                        onClick={() =>
                                            router.visit(editHref)
                                        }
                                    >
                                        <Icon
                                            name={editIcon}
                                            mode="Decorative"
                                        />
                                    </button>
                                ) : null}
                                {approval.canDelete && deleteUrl ? (
                                    <button
                                        type="button"
                                        className="spkHeaderActionBtn spkHeaderActionBtn--danger"
                                        aria-label="Hapus"
                                        title="Hapus Request JewelCAD"
                                        disabled={submitting || deleting}
                                        onClick={() => setDeleteOpen(true)}
                                    >
                                        <Icon
                                            name={deleteIcon}
                                            mode="Decorative"
                                        />
                                    </button>
                                ) : null}
                                <button
                                    type="button"
                                    className="spkHeaderActionBtn spkHeaderActionBtn--danger"
                                    aria-label="Tutup"
                                    title="Tutup"
                                    disabled={submitting || deleting}
                                    onClick={() => router.visit(backHref)}
                                >
                                    <Icon
                                        name={declineIcon}
                                        mode="Decorative"
                                    />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="spkDetailBody">
                    <section className="spkMainPanel">
                        <div className="spkInformasiProduksiBody">
                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">
                                    Informasi Request
                                </h3>
                                <div className="jewelCadDetailInfoLayout">
                                    <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailInfoTable">
                                        <tbody>
                                            <tr>
                                                <th scope="row">
                                                    Operator JewelCAD
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        requestItem.operator,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Tanggal Request
                                                </th>
                                                <td>
                                                    {formatTransDate(
                                                        requestItem.transDate,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailNotesTable">
                                        <tbody>
                                            <tr>
                                                <th scope="row">Catatan</th>
                                                <td className="spkItemNotesCell jewelCadDetailNotesCell">
                                                    {displayValue(
                                                        requestItem.notes,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">List SPK</h3>
                                <div className="spkTableScroll">
                            <table className="spkTable masterDataTable">
                                <thead>
                                    <tr>
                                        <th>SPK</th>
                                        <th>SKU</th>
                                        <th>Satuan</th>
                                        <th>Catatan</th>
                                        <th>Berat <br /> (SPK) (g)</th>
                                        <th>Estimasi Berat Barang Jadi <br /> (JewelCAD) (g)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requestItem.details.length === 0 ? (
                                        <tr>
                                            <td colSpan={6}>
                                                Belum ada SPK pada request ini.
                                            </td>
                                        </tr>
                                    ) : (
                                        detailsByMaterial.map((group) => (
                                            <Fragment
                                                key={`group-${group.material}`}
                                            >
                                                <tr className="jewelCadMaterialGroupRow">
                                                    <td colSpan={6}>
                                                        <em>
                                                            {group.material.toUpperCase()}
                                                        </em>
                                                    </td>
                                                </tr>
                                                {group.rows.map(
                                                    ({ detail, index }) => (
                                                        <tr
                                                            key={`detail-${detail.spkId}-${index}`}
                                                        >
                                                            <td>
                                                                <strong>
                                                                    {displayValue(
                                                                        detail.spkNo,
                                                                    )}
                                                                </strong>
                                                            </td>
                                                            <td>
                                                                <SkuColumnValue
                                                                    typeCode={
                                                                        detail.typeCode
                                                                    }
                                                                    productItemName={
                                                                        detail.productItemName
                                                                    }
                                                                    skuCode={
                                                                        detail.skuCode
                                                                    }
                                                                />
                                                            </td>
                                                            <td>
                                                                {displayValue(
                                                                    detail.satuan,
                                                                )}
                                                            </td>
                                                            <td>
                                                                {displayValue(
                                                                    detail.notes,
                                                                )}
                                                            </td>
                                                            <td>
                                                                {displayValue(
                                                                    detail.goldWeight,
                                                                )}
                                                            </td>
                                                            <td>
                                                                {displayValue(
                                                                    detail.estimationBrj,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </Fragment>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                            </section>

                            {approvalHistory.length > 0 ? (
                                <section className="spkShowSection">
                                    <h3 className="spkShowSectionTitle">
                                        Riwayat Approval
                                    </h3>
                                    <div className="spkTableScroll">
                                <table className="spkTable masterDataTable">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Keputusan</th>
                                            <th>Oleh</th>
                                            <th>Tanggal</th>
                                            <th>Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {approvalHistory.map((event, index) => (
                                            <tr
                                                key={`history-${event.status}-${index}`}
                                            >
                                                <td>
                                                    {displayValue(
                                                        event.statusLabel,
                                                    )}
                                                </td>
                                                <td>
                                                    {displayValue(
                                                        event.approve,
                                                    )}
                                                </td>
                                                <td>
                                                    {displayValue(
                                                        event.createdBy,
                                                    )}
                                                </td>
                                                <td>
                                                    {displayValue(
                                                        event.createdAt,
                                                    )}
                                                </td>
                                                <td>
                                                    {displayValue(event.notes)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                    </div>
                                </section>
                            ) : null}

                            {approvalFooter.length > 0 ? (
                                <div className="spkShowBottom">
                                    <footer
                                        className="spkApprovalFooter spkApprovalFooter--splitEnds"
                                        aria-label="Persetujuan"
                                    >
                            {approvalFooter.map((column) => (
                                <div
                                    key={column.title}
                                    className="spkApprovalFooterCol"
                                >
                                    <div className="spkApprovalFooterTitle">
                                        {column.title}
                                    </div>
                                    <div className="spkApprovalFooterMeta">
                                        <div className="spkApprovalFooterMetaRow">
                                            <span className="spkApprovalFooterMetaLabel">
                                                Nama
                                            </span>
                                            <span className="spkApprovalFooterMetaValue">
                                                {displayValue(column.name)}
                                            </span>
                                        </div>
                                        <div className="spkApprovalFooterMetaRow">
                                            <span className="spkApprovalFooterMetaLabel">
                                                Tanggal
                                            </span>
                                            <span className="spkApprovalFooterMetaValue">
                                                {displayValue(column.date)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                                    </footer>
                                </div>
                            ) : null}
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
            <DialogContent className="spkDeleteDialog">
                <DialogHeader>
                    <DialogTitle>Hapus Request JewelCAD</DialogTitle>
                    <DialogDescription>
                        Request {requestItem.docNo ?? requestItem.id} akan
                        dihapus dan tidak lagi muncul di daftar JewelCAD.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        design="Transparent"
                        disabled={deleting}
                        onClick={() => setDeleteOpen(false)}
                    >
                        Batal
                    </Button>
                    <Button
                        design="Negative"
                        disabled={deleting}
                        onClick={handleDelete}
                    >
                        {deleting ? 'Menghapus...' : 'Hapus'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        </>
    );
}
