import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';
import {
    SpkApprovalTimelinePanel,
    type SpkApprovalTimelineEvent,
} from '@/components/spk/spk-approval-timeline-panel';
import {
    CoranMaterialBreakdownTables,
    type CoranBreakdownSection,
} from '@/components/coran/coran-material-breakdown';
import {
    resolveQcStatusFromCoranStatus,
    SpkQcStatusBadge,
} from '@/components/spk/spk-qc-status-badge';

type CoranMaterialLine = {
    name: string;
    weight: string;
};

type CoranDetailRow = {
    lineId: number;
    spkId: number;
    spkNo: string | null;
    spkType: string | null;
    orderTypeLabel: string | null;
    skuCode: string | null;
    typeCode: string | null;
    productItemName: string | null;
    itemDescription: string | null;
    customerName: string | null;
    satuan: string;
    weight: string | null;
    status: string | null;
    statusLabel: string;
};

type CoranDetailItem = {
    id: number;
    docNo: string | null;
    transDate: string | null;
    status: string | null;
    statusLabel: string;
    craftsmanId: number | null;
    craftsmanName: string | null;
    submitMaterials: CoranMaterialLine[];
    resultMaterials: CoranMaterialLine[];
    totalSubmitMaterial: string | null;
    totalResultMaterial: string | null;
    totalSpkWeight: string | null;
    spkCount: number;
    okSpkPercent: string | null;
    shrink: string | null;
    coranBreakdown: CoranBreakdownSection[];
    details: CoranDetailRow[];
};

type CoranWorkflowStatus = {
    key: string;
    label: string;
    stageIndex: number;
    stages: Array<{ key: string; label: string }>;
};

type ApprovalHistoryEvent = {
    status: string;
    statusLabel: string;
    approve: string;
    notes: string | null;
    createdBy: string | null;
    createdAt: string | null;
};

type CoranDetailProps = {
    coranItem: CoranDetailItem;
    workflowStatus: CoranWorkflowStatus;
    approvalHistory: ApprovalHistoryEvent[];
    backHref: string;
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

export function CoranDetail({
    coranItem,
    workflowStatus,
    approvalHistory,
    backHref,
}: CoranDetailProps) {
    const [mainSection, setMainSection] = useState<'informasi' | 'riwayat'>(
        'informasi',
    );
    const activeStageIndex = workflowStatus.stageIndex;
    const statusStages = workflowStatus.stages;

    const approvalTimeline = useMemo<SpkApprovalTimelineEvent[]>(
        () =>
            approvalHistory.map((event) => ({
                source: 'Coran',
                status: event.status,
                statusLabel: event.statusLabel,
                approve: event.approve,
                notes: event.notes,
                createdBy: event.createdBy,
                createdAt: event.createdAt,
            })),
        [approvalHistory],
    );

    return (
        <div className="spkDetailShell">
            <div className="spkDetailStack">
                <div className="spkTopBarCard">
                    <div className="spkTopBarMain">
                        <div className="spkTopBarLeft">
                            <div className="spkDocTitleBlock">
                                <div className="spkDocTitleRow">
                                    <h1 className="spkDocTitle">
                                        {displayValue(coranItem.docNo)}
                                    </h1>
                                </div>
                            </div>

                            <div
                                className="spkStatusPipeline"
                                aria-label="Status Dokumen Coran"
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
                                <button
                                    type="button"
                                    className="spkHeaderActionBtn spkHeaderActionBtn--danger"
                                    aria-label="Tutup"
                                    title="Tutup"
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
                            aria-label="Konten utama Coran"
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
                                    <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailInfoTable">
                                        <tbody>
                                            <tr>
                                                <th scope="row">Tanggal</th>
                                                <td>
                                                    {formatTransDate(
                                                        coranItem.transDate,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Pengrajin</th>
                                                <td>
                                                    {displayValue(
                                                        coranItem.craftsmanName,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Total SPK
                                                </th>
                                                <td>{coranItem.spkCount}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Hasil coran OK (%)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        coranItem.okSpkPercent,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailNotesTable">
                                        <tbody>
                                            <tr>
                                                <th scope="row">
                                                    Total bahan emas
                                                    diserahkan (g)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        coranItem.totalSubmitMaterial,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Total sisa hasil coran (g)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        coranItem.totalResultMaterial,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Total hasil coran (g)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        coranItem.totalSpkWeight,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Susut (g)</th>
                                                <td>
                                                    {displayValue(
                                                        coranItem.shrink,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">
                                    Bahan Emas
                                </h3>
                                <CoranMaterialBreakdownTables
                                    breakdown={coranItem.coranBreakdown}
                                    submitMaterials={coranItem.submitMaterials}
                                    resultMaterials={coranItem.resultMaterials}
                                    totalSubmit={
                                        coranItem.totalSubmitMaterial
                                    }
                                    totalResult={
                                        coranItem.totalResultMaterial
                                    }
                                />
                            </section>

                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">
                                    List SPK
                                </h3>
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
                                                <th>
                                                    Berat Hasil
                                                    <br />
                                                    Coran (g)
                                                </th>
                                                <th className="spkTableColCenter">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {coranItem.details.length === 0 ? (
                                                <tr>
                                                    <td colSpan={6}>
                                                        Belum ada SPK pada
                                                        dokumen ini.
                                                    </td>
                                                </tr>
                                            ) : (
                                                coranItem.details.map(
                                                    (detail) => {
                                                        const qcStatus =
                                                            resolveQcStatusFromCoranStatus(
                                                                detail.status,
                                                            );

                                                        return (
                                                            <tr
                                                                key={`detail-${detail.lineId}`}
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
                                                                    {displayValue(
                                                                        detail.weight,
                                                                    )}
                                                                </td>
                                                                <td className="spkTableColCenter">
                                                                    {qcStatus ? (
                                                                        <SpkQcStatusBadge
                                                                            status={
                                                                                qcStatus
                                                                            }
                                                                        />
                                                                    ) : (
                                                                        displayValue(
                                                                            detail.statusLabel,
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
