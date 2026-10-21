import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import editIcon from '@ui5/webcomponents-icons/dist/edit.js';
import paperPlaneIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import {
    PasangBatuStoneTables,
    type PasangBatuStones,
} from '@/components/pasang-batu/pasang-batu-stone-tables';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';
import { show as pasangBatuShow } from '@/routes/pasang-batu';

type SpkDiamondSpk = {
    spkId: number;
    spkNo: string;
    spkType: string | null;
    orderTypeLabel: string | null;
    skuCode: string | null;
    typeCode: string | null;
    productItemName: string | null;
    itemDescription: string | null;
    customerName: string | null;
    satuan: string;
    lastWeight: string | null;
};

type SpkDiamondDetailItem = {
    id: number;
    noSpkDiamond: string;
    tanggal: string;
    keterangan: string | null;
    weightFrame: string;
    weightStone: string;
    weightTotal: string;
    status: string;
    statusLabel: string;
    submittedAt: string | null;
    submittedBy: string | null;
    mountingId: number | null;
    mountingDocNo: string | null;
    stones: PasangBatuStones;
    spk: SpkDiamondSpk | null;
};

type SpkDiamondWorkflowStatus = {
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

type SpkDiamondDetailProps = {
    spkDiamondItem: SpkDiamondDetailItem;
    workflowStatus: SpkDiamondWorkflowStatus;
    approvalFooter: ApprovalFooterColumn[];
    canEdit: boolean;
    canSubmit: boolean;
    backHref: string;
    editHref: string;
    submitUrl: string;
};

function displayValue(value: string | null | undefined): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

function formatDateDisplay(value: string | null): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-');

    if (!year || !month || !day) {
        return value;
    }

    return `${day}/${month}/${year}`;
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

export function SpkDiamondDetail({
    spkDiamondItem,
    workflowStatus,
    approvalFooter,
    canEdit,
    canSubmit,
    backHref,
    editHref,
    submitUrl,
}: SpkDiamondDetailProps) {
    const [submitting, setSubmitting] = useState(false);
    const activeStageIndex = workflowStatus.stageIndex;

    const submitToProduction = () => {
        if (!canSubmit || !submitUrl) {
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

    return (
        <div className="spkDetailShell">
            <div className="spkDetailStack">
                <div className="spkTopBarCard">
                    <div className="spkTopBarMain">
                        <div className="spkTopBarLeft">
                            <div className="spkDocTitleBlock">
                                <div className="spkDocTitleRow">
                                    <h1 className="spkDocTitle">
                                        {displayValue(
                                            spkDiamondItem.noSpkDiamond,
                                        )}
                                    </h1>
                                </div>
                            </div>

                            <div
                                className="spkStatusPipeline"
                                aria-label="Status SPK Diamond"
                            >
                                {workflowStatus.stages.map((stage, index) => (
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
                                {canSubmit ? (
                                    <div className="spkApprovalActions">
                                        <Button
                                            design="Emphasized"
                                            icon={paperPlaneIcon}
                                            disabled={submitting}
                                            onClick={submitToProduction}
                                        >
                                            {submitting
                                                ? 'Mengirim...'
                                                : 'Kirim ke produksi'}
                                        </Button>
                                    </div>
                                ) : null}
                                {canEdit ? (
                                    <button
                                        type="button"
                                        className="spkHeaderActionBtn"
                                        aria-label="Edit"
                                        title="Edit SPK Diamond"
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
                            className="spkInformasiProduksiBody"
                            role="tabpanel"
                            aria-label="Informasi"
                        >
                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">
                                    Informasi Dokumen
                                </h3>
                                <div className="jewelCadDetailInfoLayout coranDetailInfoLayout">
                                    <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailNotesTable">
                                        <tbody>
                                            <tr>
                                                <th scope="row">Status</th>
                                                <td>
                                                    {displayValue(
                                                        spkDiamondItem.statusLabel,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Tanggal</th>
                                                <td>
                                                    {formatDateDisplay(
                                                        spkDiamondItem.tanggal,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Keterangan</th>
                                                <td>
                                                    {displayValue(
                                                        spkDiamondItem.keterangan,
                                                    )}
                                                </td>
                                            </tr>
                                            {spkDiamondItem.mountingDocNo ? (
                                                <tr>
                                                    <th scope="row">
                                                        Pasang Batu
                                                    </th>
                                                    <td>
                                                        {spkDiamondItem.mountingId ? (
                                                            <button
                                                                type="button"
                                                                className="spkProduksiLink"
                                                                onClick={() =>
                                                                    router.visit(
                                                                        pasangBatuShow.url(
                                                                            spkDiamondItem.mountingId!,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                {
                                                                    spkDiamondItem.mountingDocNo
                                                                }
                                                            </button>
                                                        ) : (
                                                            spkDiamondItem.mountingDocNo
                                                        )}
                                                    </td>
                                                </tr>
                                            ) : null}
                                        </tbody>
                                    </table>
                                    <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailNotesTable">
                                        <tbody>
                                            <tr>
                                                <th scope="row">
                                                    Berat Rangka Terakhir (g)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        spkDiamondItem.spk
                                                            ?.lastWeight,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Berat Rangka (g)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        spkDiamondItem.weightFrame,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Berat Batu (ct)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        spkDiamondItem.weightStone,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Berat Rangka + Batu
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        spkDiamondItem.weightTotal,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section className="spkShowSection spkDetailCard spkDetailCard--co6">
                                <h3 className="spkShowSectionTitle">SPK</h3>
                                {spkDiamondItem.spk === null ? (
                                    <p>Belum ada SPK pada dokumen ini.</p>
                                ) : (
                                    <div className="spkDetailGrid">
                                        <DetailField label="SPK">
                                            <strong>
                                                {displayValue(
                                                    spkDiamondItem.spk.spkNo,
                                                )}
                                            </strong>
                                        </DetailField>
                                        <DetailField label="Tipe Produksi">
                                            <SpkOrderTypeColumn
                                                spkType={
                                                    spkDiamondItem.spk.spkType
                                                }
                                                orderTypeLabel={
                                                    spkDiamondItem.spk
                                                        .orderTypeLabel
                                                }
                                            />
                                        </DetailField>
                                        <DetailField label="SKU">
                                            <SpkItemSkuColumn
                                                typeCode={
                                                    spkDiamondItem.spk.typeCode
                                                }
                                                productItemName={
                                                    spkDiamondItem.spk
                                                        .productItemName
                                                }
                                                skuCode={
                                                    spkDiamondItem.spk.skuCode
                                                }
                                                itemDescription={
                                                    spkDiamondItem.spk
                                                        .itemDescription
                                                }
                                            />
                                        </DetailField>
                                        <DetailField label="Customer">
                                            {displayValue(
                                                spkDiamondItem.spk
                                                    .customerName,
                                            )}
                                        </DetailField>
                                        <DetailField label="Qty">
                                            {spkDiamondItem.spk.satuan}
                                        </DetailField>
                                    </div>
                                )}
                            </section>

                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">
                                    Detail Batu
                                </h3>
                                <PasangBatuStoneTables
                                    stones={spkDiamondItem.stones}
                                />
                            </section>

                            {approvalFooter.length > 0 ? (
                                <div className="spkShowBottom">
                                    <footer
                                        className="spkApprovalFooter spkApprovalFooter--splitEnds"
                                        aria-label="Riwayat dokumen"
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
                    </section>
                </div>
            </div>
        </div>
    );
}
