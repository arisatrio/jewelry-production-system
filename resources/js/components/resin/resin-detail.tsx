import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import acceptIcon from '@ui5/webcomponents-icons/dist/accept.js';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import editIcon from '@ui5/webcomponents-icons/dist/edit.js';
import paperPlaneIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import saveIcon from '@ui5/webcomponents-icons/dist/save.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { Text } from '@ui5/webcomponents-react/Text';
import { TextArea } from '@ui5/webcomponents-react/TextArea';
import {
    resolveQcStatusFromCoranStatus,
    SpkQcStatusBadge,
} from '@/components/spk/spk-qc-status-badge';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';
import {
    SpkApprovalTimelinePanel,
    type SpkApprovalTimelineEvent,
} from '@/components/spk/spk-approval-timeline-panel';
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

type ResinApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    canOpenEdit: boolean;
    canDelete: boolean;
    canManagerApprove: boolean;
    canComplete: boolean;
    status: string;
    statusLabel: string;
};

type ResinWorkflowStatus = {
    key: string;
    label: string;
    stageIndex: number;
    stages: Array<{ key: string; label: string }>;
};

type ResinDetailItem = {
    id: number;
    docNo: string | null;
    operator: string | null;
    notes: string | null;
    transDate: string | null;
    status: string | null;
    statusLabel: string;
    details: Array<{
        spkId: number;
        spkNo: string | null;
        spkType: string | null;
        orderTypeLabel: string | null;
        skuCode: string | null;
        typeCode: string | null;
        productItemName: string | null;
        itemDescription: string | null;
        satuan: string;
        beratResin: string;
        statusResin: string | null;
        statusResinLabel: string;
        catatan: string | null;
    }>;
};

type StatusOption = {
    value: string;
    label: string;
};

type ResinDetailForm = {
    spk_id: string;
    catatan: string;
    berat_resin: string;
    status_resin: string;
};

type ResinDetailProps = {
    resinItem: ResinDetailItem;
    approvalFooter: ApprovalFooterColumn[];
    approvalHistory: ApprovalHistoryEvent[];
    approval: ResinApprovalAbilities;
    workflowStatus: ResinWorkflowStatus;
    statusOptions: StatusOption[];
    saveProgressUrl: string;
    backHref: string;
    editHref: string;
    submitUrl: string;
    managerApproveUrl: string;
    completeUrl: string;
    deleteUrl: string;
};

function displayValue(value: string | null | undefined): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
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

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function detailError(
    errors: Record<string, string>,
    index: number,
    field: keyof ResinDetailForm,
): string | undefined {
    return errors[`details.${index}.${field}`];
}

export function ResinDetail({
    resinItem,
    approvalFooter,
    approvalHistory,
    approval,
    workflowStatus,
    statusOptions,
    saveProgressUrl,
    backHref,
    editHref,
    submitUrl,
    managerApproveUrl,
    completeUrl,
    deleteUrl,
}: ResinDetailProps) {
    const [submitting, setSubmitting] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [mainSection, setMainSection] = useState<'informasi' | 'riwayat'>(
        'informasi',
    );
    const canEditProgress = approval.canComplete;

    const approvalTimeline = useMemo<SpkApprovalTimelineEvent[]>(
        () =>
            approvalHistory.map((event) => ({
                source: 'Resin',
                status: event.status,
                statusLabel: event.statusLabel,
                approve: event.approve,
                notes: event.notes,
                createdBy: event.createdBy,
                createdAt: event.createdAt,
            })),
        [approvalHistory],
    );

    const {
        data: progressData,
        setData: setProgressData,
        put: saveProgress,
        processing: savingProgress,
        errors: progressErrors,
    } = useForm<{ details: ResinDetailForm[] }>({
        details: resinItem.details.map((detail) => ({
            spk_id: String(detail.spkId),
            catatan: detail.catatan ?? '',
            berat_resin: detail.beratResin ?? '',
            status_resin: detail.statusResin ?? '',
        })),
    });

    const updateProgressDetail = (
        index: number,
        field: keyof ResinDetailForm,
        value: string,
    ) => {
        setProgressData(
            'details',
            progressData.details.map((row, rowIndex) =>
                rowIndex === index ? { ...row, [field]: value } : row,
            ),
        );
    };

    const handleSaveProgress = () => {
        if (!canEditProgress) {
            return;
        }

        saveProgress(saveProgressUrl, {
            preserveScroll: true,
        });
    };

    const activeStageIndex = workflowStatus.stageIndex;
    const statusStages = workflowStatus.stages;

    const submitToManager = () => {
        if (!approval.canSubmit) {
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
        if (!approval.canManagerApprove) {
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
        if (!approval.canComplete) {
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
        if (!approval.canDelete) {
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
                                            {displayValue(resinItem.docNo)}
                                        </h1>
                                    </div>
                                </div>

                                <div
                                    className="spkStatusPipeline"
                                    aria-label="Status Request Resin"
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
                                    {(approval.canSubmit ||
                                        approval.canManagerApprove) && (
                                        <div className="spkApprovalActions">
                                            {approval.canSubmit ? (
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
                                            {approval.canManagerApprove ? (
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
                                    {approval.canComplete ? (
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
                                            title="Edit Request Resin"
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
                                    {approval.canDelete ? (
                                        <button
                                            type="button"
                                            className="spkHeaderActionBtn spkHeaderActionBtn--danger"
                                            aria-label="Hapus"
                                            title="Hapus Request Resin"
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
                            <div
                                className="spkSectionTabs"
                                role="tablist"
                                aria-label="Konten utama Resin"
                            >
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={mainSection === 'informasi'}
                                    className={[
                                        'spkSectionTab',
                                        mainSection === 'informasi'
                                            ? 'is-active'
                                            : '',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                    onClick={() => setMainSection('informasi')}
                                >
                                    <span className="spkSectionTabLabel">
                                        Informasi
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={mainSection === 'riwayat'}
                                    className={[
                                        'spkSectionTab',
                                        mainSection === 'riwayat'
                                            ? 'is-active'
                                            : '',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                    onClick={() => setMainSection('riwayat')}
                                >
                                    <span className="spkSectionTabLabel">
                                        Riwayat
                                    </span>
                                </button>
                            </div>

                            {mainSection === 'informasi' ? (
                            <div
                                role="tabpanel"
                                aria-label="Informasi"
                                className="spkInformasiProduksiBody"
                            >
                                <section className="spkShowSection">
                                    <h3 className="spkShowSectionTitle">
                                        Informasi Dokumen
                                    </h3>
                                    <div className="jewelCadDetailInfoLayout">
                                        <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailInfoTable">
                                            <tbody>
                                                <tr>
                                                    <th scope="row">
                                                        Operator Resin
                                                    </th>
                                                    <td>
                                                        {displayValue(
                                                            resinItem.operator,
                                                        )}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        Tanggal
                                                    </th>
                                                    <td>
                                                        {formatTransDate(
                                                            resinItem.transDate,
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
                                                            resinItem.notes,
                                                        )}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <section className="spkShowSection">
                                    <div className="spkStoneCardHeader">
                                        <h3 className="spkShowSectionTitle">
                                            List SPK
                                        </h3>
                                        {canEditProgress ? (
                                            <Button
                                                design="Emphasized"
                                                type="Button"
                                                icon={saveIcon}
                                                disabled={
                                                    savingProgress || submitting
                                                }
                                                onClick={handleSaveProgress}
                                            >
                                                {savingProgress
                                                    ? 'Menyimpan...'
                                                    : 'Simpan'}
                                            </Button>
                                        ) : null}
                                    </div>
                                    <div className="spkTableScroll">
                                        <table className="spkTable masterDataTable">
                                            <thead>
                                                <tr>
                                                    <th className="spkTableColSpkNo">
                                                        SPK
                                                    </th>
                                                    <th className="spkTableColCenter spkTableColTipeProduksi">
                                                        Tipe Produksi
                                                    </th>
                                                    <th>SKU</th>
                                                    <th>Qty</th>
                                                    <th>Berat Resin</th>
                                                    <th>Status Resin</th>
                                                    <th>Catatan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {resinItem.details.length ===
                                                0 ? (
                                                    <tr>
                                                        <td colSpan={7}>
                                                            Belum ada SPK pada
                                                            dokumen ini.
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    resinItem.details.map(
                                                        (detail, index) => {
                                                            const progressDetail =
                                                                progressData
                                                                    .details[
                                                                    index
                                                                ];
                                                            const qcStatus =
                                                                canEditProgress
                                                                    ? resolveQcStatusFromCoranStatus(
                                                                          progressDetail?.status_resin,
                                                                      )
                                                                    : resolveQcStatusFromCoranStatus(
                                                                          detail.statusResin,
                                                                      );

                                                            return (
                                                                <tr
                                                                    key={`detail-${detail.spkId}-${index}`}
                                                                >
                                                                    <td className="spkTableColSpkNo">
                                                                        <strong>
                                                                            {displayValue(
                                                                                detail.spkNo,
                                                                            )}
                                                                        </strong>
                                                                    </td>
                                                                    <td className="spkTableColCenter spkTableColTipeProduksi">
                                                                        <SpkOrderTypeColumn
                                                                            spkType={
                                                                                detail.spkType
                                                                            }
                                                                            orderTypeLabel={
                                                                                detail.orderTypeLabel
                                                                            }
                                                                        />
                                                                    </td>
                                                                    <td>
                                                                        <SpkItemSkuColumn
                                                                            typeCode={
                                                                                detail.typeCode
                                                                            }
                                                                            productItemName={
                                                                                detail.productItemName
                                                                            }
                                                                            skuCode={
                                                                                detail.skuCode
                                                                            }
                                                                            itemDescription={
                                                                                detail.itemDescription
                                                                            }
                                                                        />
                                                                    </td>
                                                                    <td>
                                                                        {displayValue(
                                                                            detail.satuan,
                                                                        )}
                                                                    </td>
                                                                    <td>
                                                                        {canEditProgress &&
                                                                        progressDetail ? (
                                                                            <div className="spkFioriFieldStack">
                                                                                <Input
                                                                                    type="Number"
                                                                                    accessibleName="Berat resin"
                                                                                    value={
                                                                                        progressDetail.berat_resin
                                                                                    }
                                                                                    valueState={fieldState(
                                                                                        detailError(
                                                                                            progressErrors,
                                                                                            index,
                                                                                            'berat_resin',
                                                                                        ),
                                                                                    )}
                                                                                    onInput={(
                                                                                        event,
                                                                                    ) =>
                                                                                        updateProgressDetail(
                                                                                            index,
                                                                                            'berat_resin',
                                                                                            event
                                                                                                .target
                                                                                                .value ??
                                                                                                '',
                                                                                        )
                                                                                    }
                                                                                />
                                                                                {detailError(
                                                                                    progressErrors,
                                                                                    index,
                                                                                    'berat_resin',
                                                                                ) ? (
                                                                                    <Text className="spkFioriError">
                                                                                        {detailError(
                                                                                            progressErrors,
                                                                                            index,
                                                                                            'berat_resin',
                                                                                        )}
                                                                                    </Text>
                                                                                ) : null}
                                                                            </div>
                                                                        ) : (
                                                                            displayValue(
                                                                                detail.beratResin,
                                                                            )
                                                                        )}
                                                                    </td>
                                                                    <td>
                                                                        {canEditProgress &&
                                                                        progressDetail ? (
                                                                            <div className="spkFioriFieldStack">
                                                                                <Select
                                                                                    accessibleName="Status resin"
                                                                                    onChange={(
                                                                                        event,
                                                                                    ) =>
                                                                                        updateProgressDetail(
                                                                                            index,
                                                                                            'status_resin',
                                                                                            event
                                                                                                .detail
                                                                                                .selectedOption
                                                                                                .value ??
                                                                                                '',
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Option
                                                                                        value=""
                                                                                        selected={
                                                                                            progressDetail.status_resin.trim() ===
                                                                                            ''
                                                                                        }
                                                                                    >
                                                                                        —
                                                                                    </Option>
                                                                                    {statusOptions.map(
                                                                                        (
                                                                                            option,
                                                                                        ) => (
                                                                                            <Option
                                                                                                key={
                                                                                                    option.value
                                                                                                }
                                                                                                value={
                                                                                                    option.value
                                                                                                }
                                                                                                selected={
                                                                                                    progressDetail.status_resin ===
                                                                                                    option.value
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    option.label
                                                                                                }
                                                                                            </Option>
                                                                                        ),
                                                                                    )}
                                                                                </Select>
                                                                                {detailError(
                                                                                    progressErrors,
                                                                                    index,
                                                                                    'status_resin',
                                                                                ) ? (
                                                                                    <Text className="spkFioriError">
                                                                                        {detailError(
                                                                                            progressErrors,
                                                                                            index,
                                                                                            'status_resin',
                                                                                        )}
                                                                                    </Text>
                                                                                ) : null}
                                                                            </div>
                                                                        ) : qcStatus ? (
                                                                            <SpkQcStatusBadge
                                                                                status={
                                                                                    qcStatus
                                                                                }
                                                                            />
                                                                        ) : (
                                                                            '—'
                                                                        )}
                                                                    </td>
                                                                    <td>
                                                                        {canEditProgress &&
                                                                        progressDetail ? (
                                                                            <div className="spkFioriFieldStack">
                                                                                <TextArea
                                                                                    rows={2}
                                                                                    value={
                                                                                        progressDetail.catatan
                                                                                    }
                                                                                    valueState={fieldState(
                                                                                        detailError(
                                                                                            progressErrors,
                                                                                            index,
                                                                                            'catatan',
                                                                                        ),
                                                                                    )}
                                                                                    onInput={(
                                                                                        event,
                                                                                    ) =>
                                                                                        updateProgressDetail(
                                                                                            index,
                                                                                            'catatan',
                                                                                            event
                                                                                                .target
                                                                                                .value ??
                                                                                                '',
                                                                                        )
                                                                                    }
                                                                                />
                                                                                {detailError(
                                                                                    progressErrors,
                                                                                    index,
                                                                                    'catatan',
                                                                                ) ? (
                                                                                    <Text className="spkFioriError">
                                                                                        {detailError(
                                                                                            progressErrors,
                                                                                            index,
                                                                                            'catatan',
                                                                                        )}
                                                                                    </Text>
                                                                                ) : null}
                                                                            </div>
                                                                        ) : (
                                                                            displayValue(
                                                                                detail.catatan,
                                                                            )
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            );
                                                        },
                                                    )
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

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
                                                                {displayValue(
                                                                    column.name,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <div className="spkApprovalFooterMetaRow">
                                                            <span className="spkApprovalFooterMetaLabel">
                                                                Tanggal
                                                            </span>
                                                            <span className="spkApprovalFooterMetaValue">
                                                                {displayValue(
                                                                    column.date,
                                                                )}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </footer>
                                    </div>
                                ) : null}
                            </div>
                            ) : null}

                            {mainSection === 'riwayat' ? (
                                <SpkApprovalTimelinePanel
                                    events={approvalTimeline}
                                />
                            ) : null}
                        </section>
                    </div>
                </div>
            </div>

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent className="spkDeleteDialog">
                    <DialogHeader>
                        <DialogTitle>Hapus Request Resin</DialogTitle>
                        <DialogDescription>
                            Dokumen {resinItem.docNo ?? resinItem.id} akan
                            dihapus dan tidak lagi muncul di daftar resin.
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
