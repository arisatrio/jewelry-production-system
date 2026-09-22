import acceptIcon from '@ui5/webcomponents-icons/dist/accept.js';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import editIcon from '@ui5/webcomponents-icons/dist/edit.js';
import paperPlaneIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import { router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';
import {
    SpkApprovalTimelinePanel,
    type SpkApprovalTimelineEvent,
} from '@/components/spk/spk-approval-timeline-panel';

type PolesRangkaSpk = {
    spkId: number | null;
    spkNo: string | null;
    spkType: string | null;
    orderTypeLabel: string | null;
    skuCode: string | null;
    typeCode: string | null;
    productItemName: string | null;
    itemDescription: string | null;
    customerName: string | null;
    satuan: string;
};

type PolesRangkaDetailItem = {
    id: number;
    docNo: string | null;
    status: string | null;
    statusLabel: string;
    statusItem: string | null;
    craftsmanId: number | null;
    craftsmanName: string | null;
    sendCraftsmanDate: string | null;
    receivedCraftsmanDate: string | null;
    notes: string | null;
    startWeight: string | null;
    finishWeight: string | null;
    shrink: string | null;
    shrinkPercent: string | null;
    spk: PolesRangkaSpk | null;
};

type PolesRangkaWorkflowStatus = {
    key: string;
    label: string;
    stageIndex: number;
    stages: Array<{ key: string; label: string }>;
};

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

type PolesRangkaApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    canOpenEdit: boolean;
    canDelete: boolean;
    canManagerApprove: boolean;
    canComplete: boolean;
    status: string;
    statusLabel: string;
};

type PolesRangkaDetailProps = {
    polishFrameItem: PolesRangkaDetailItem;
    workflowStatus: PolesRangkaWorkflowStatus;
    approvalHistory: ApprovalHistoryEvent[];
    approvalFooter: ApprovalFooterColumn[];
    approval: PolesRangkaApprovalAbilities;
    backHref: string;
    editHref: string;
    submitUrl: string;
    managerApproveUrl: string;
    completeUrl: string;
};

function displayValue(value: string | null | undefined): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const [datePart, timePart] = value.split(' ');

    if (!datePart) {
        return value;
    }

    const [year, month, day] = datePart.split('-');

    if (!year || !month || !day) {
        return value;
    }

    const dateLabel = `${day}/${month}/${year}`;

    return timePart ? `${dateLabel} ${timePart.slice(0, 5)}` : dateLabel;
}

function DetailField({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="spkDetailField">
            <span className="spkDetailFieldLabel">{label}</span>
            <div className="spkDetailValue">{children}</div>
        </div>
    );
}

export function PolesRangkaDetail({
    polishFrameItem,
    workflowStatus,
    approvalHistory,
    approvalFooter,
    approval,
    backHref,
    editHref,
    submitUrl,
    managerApproveUrl,
    completeUrl,
}: PolesRangkaDetailProps) {
    const [mainSection, setMainSection] = useState<'informasi' | 'riwayat'>(
        'informasi',
    );
    const [submitting, setSubmitting] = useState(false);
    const activeStageIndex = workflowStatus.stageIndex;
    const statusStages = workflowStatus.stages;

    const approvalTimeline = useMemo<SpkApprovalTimelineEvent[]>(
        () =>
            approvalHistory.map((event) => ({
                source: 'Poles Rangka',
                status: event.status,
                statusLabel: event.statusLabel,
                approve: event.approve,
                notes: event.notes,
                createdBy: event.createdBy,
                createdAt: event.createdAt,
            })),
        [approvalHistory],
    );

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

    return (
        <div className="spkDetailShell">
            <div className="spkDetailStack">
                <div className="spkTopBarCard">
                    <div className="spkTopBarMain">
                        <div className="spkTopBarLeft">
                            <div className="spkDocTitleBlock">
                                <div className="spkDocTitleRow">
                                    <h1 className="spkDocTitle">
                                        {displayValue(polishFrameItem.docNo)}
                                    </h1>
                                </div>
                            </div>

                            <div
                                className="spkStatusPipeline"
                                aria-label="Status Dokumen Poles Rangka"
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
                                        title="Edit Dokumen Poles Rangka"
                                        disabled={submitting}
                                        onClick={() => router.visit(editHref)}
                                    >
                                        <Icon
                                            name={editIcon}
                                            mode="Decorative"
                                        />
                                    </button>
                                ) : null}
                                <button
                                    type="button"
                                    className="spkHeaderActionBtn spkHeaderActionBtn--danger"
                                    aria-label="Tutup"
                                    title="Tutup"
                                    disabled={submitting}
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
                            aria-label="Konten utama Poles Rangka"
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
                                    <div className="jewelCadDetailInfoLayout coranDetailInfoLayout">
                                        <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailNotesTable">
                                            <tbody>
                                                <tr>
                                                    <th scope="row">Status QC</th>
                                                    <td>
                                                        {displayValue(
                                                            polishFrameItem.statusItem,
                                                        )}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        Pengrajin
                                                    </th>
                                                    <td>
                                                        {displayValue(
                                                            polishFrameItem.craftsmanName,
                                                        )}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        Kirim ke Pengrajin
                                                    </th>
                                                    <td>
                                                        {formatDateTime(
                                                            polishFrameItem.sendCraftsmanDate,
                                                        )}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        Terima dari Pengrajin
                                                    </th>
                                                    <td>
                                                        {formatDateTime(
                                                            polishFrameItem.receivedCraftsmanDate,
                                                        )}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Catatan</th>
                                                    <td>
                                                        {displayValue(
                                                            polishFrameItem.notes,
                                                        )}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailNotesTable">
                                            <tbody>
                                                <tr>
                                                    <th scope="row">
                                                        Berat Awal (g)
                                                    </th>
                                                    <td>
                                                        {displayValue(
                                                            polishFrameItem.startWeight,
                                                        )}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        Berat Akhir (g)
                                                    </th>
                                                    <td>
                                                        {displayValue(
                                                            polishFrameItem.finishWeight,
                                                        )}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        Susut (g)
                                                    </th>
                                                    <td>
                                                        {displayValue(
                                                            polishFrameItem.shrink,
                                                        )}
                                                        {polishFrameItem.shrinkPercent
                                                            ? ` (${polishFrameItem.shrinkPercent})`
                                                            : ''}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <section className="spkShowSection spkDetailCard spkDetailCard--co6">
                                    <h3 className="spkShowSectionTitle">
                                        SPK
                                    </h3>
                                    {polishFrameItem.spk === null ? (
                                        <p>Belum ada SPK pada dokumen ini.</p>
                                    ) : (
                                        <div className="spkDetailGrid">
                                            <DetailField label="SPK">
                                                <strong>
                                                    {displayValue(
                                                        polishFrameItem.spk.spkNo,
                                                    )}
                                                </strong>
                                            </DetailField>
                                            <DetailField label="Tipe Produksi">
                                                <SpkOrderTypeColumn
                                                    spkType={
                                                        polishFrameItem.spk
                                                            .spkType
                                                    }
                                                    orderTypeLabel={
                                                        polishFrameItem.spk
                                                            .orderTypeLabel
                                                    }
                                                />
                                            </DetailField>
                                            <DetailField label="SKU">
                                                <SpkItemSkuColumn
                                                    typeCode={
                                                        polishFrameItem.spk
                                                            .typeCode
                                                    }
                                                    productItemName={
                                                        polishFrameItem.spk
                                                            .productItemName
                                                    }
                                                    skuCode={
                                                        polishFrameItem.spk
                                                            .skuCode
                                                    }
                                                    itemDescription={
                                                        polishFrameItem.spk
                                                            .itemDescription
                                                    }
                                                />
                                            </DetailField>
                                            <DetailField label="Customer">
                                                {displayValue(
                                                    polishFrameItem.spk
                                                        .customerName,
                                                )}
                                            </DetailField>
                                            <DetailField label="Qty">
                                                {polishFrameItem.spk.satuan}
                                            </DetailField>
                                        </div>
                                    )}
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
    );
}
