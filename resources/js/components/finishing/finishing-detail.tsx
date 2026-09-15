import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import editIcon from '@ui5/webcomponents-icons/dist/edit.js';
import { router } from '@inertiajs/react';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';
import {
    FinishingMaterialTables,
    type FinishingMaterials,
} from '@/components/finishing/finishing-material-tables';

type FinishingSpk = {
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

type FinishingDetailItem = {
    id: number;
    docNo: string | null;
    processName: string | null;
    status: string | null;
    statusLabel: string;
    craftsmanId: number | null;
    craftsmanName: string | null;
    sendCraftsmanDate: string | null;
    receivedCraftsmanDate: string | null;
    itemCategory: string | null;
    notes: string | null;
    startWeight: string | null;
    finishWeight: string | null;
    submitMaterial: string | null;
    resultMaterial: string | null;
    shrink: string | null;
    shrinkTolerance: string | null;
    shrinkPercent: string | null;
    koreksiQc: number | null;
    keteranganQc: string | null;
    materials: FinishingMaterials;
    spk: FinishingSpk | null;
};

type FinishingWorkflowStatus = {
    key: string;
    label: string;
    stageIndex: number;
    stages: Array<{ key: string; label: string }>;
};

type FinishingDetailProps = {
    finishingItem: FinishingDetailItem;
    workflowStatus: FinishingWorkflowStatus;
    backHref: string;
    editHref?: string;
    canEdit?: boolean;
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

export function FinishingDetail({
    finishingItem,
    workflowStatus,
    backHref,
    editHref,
    canEdit = false,
}: FinishingDetailProps) {
    const activeStageIndex = workflowStatus.stageIndex;
    const statusStages = workflowStatus.stages;

    return (
        <div className="spkDetailShell">
            <div className="spkDetailStack">
                <div className="spkTopBarCard">
                    <div className="spkTopBarMain">
                        <div className="spkTopBarLeft">
                            <div className="spkDocTitleBlock">
                                <div className="spkDocTitleRow">
                                    <h1 className="spkDocTitle">
                                        {displayValue(finishingItem.docNo)}
                                    </h1>
                                </div>
                            </div>

                            <div
                                className="spkStatusPipeline"
                                aria-label="Status Dokumen Finishing"
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
                                {canEdit && editHref ? (
                                    <button
                                        type="button"
                                        className="spkHeaderActionBtn"
                                        aria-label="Edit"
                                        title="Edit Dokumen Finishing"
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
                            aria-label="Konten utama Finishing"
                        >
                            <button
                                type="button"
                                role="tab"
                                aria-selected
                                className="spkSectionTab is-active"
                            >
                                <span className="spkSectionTabLabel">
                                    Informasi
                                </span>
                            </button>
                        </div>

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
                                                <th scope="row">Proses</th>
                                                <td>
                                                    {displayValue(
                                                        finishingItem.processName,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Pengrajin</th>
                                                <td>
                                                    {displayValue(
                                                        finishingItem.craftsmanName,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Tanggal serah pengrajin
                                                </th>
                                                <td>
                                                    {formatDateTime(
                                                        finishingItem.sendCraftsmanDate,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Tanggal terima pengrajin
                                                </th>
                                                <td>
                                                    {formatDateTime(
                                                        finishingItem.receivedCraftsmanDate,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Kategori</th>
                                                <td>
                                                    {displayValue(
                                                        finishingItem.itemCategory,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Catatan</th>
                                                <td>
                                                    {displayValue(
                                                        finishingItem.notes,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailNotesTable">
                                        <tbody>
                                            <tr>
                                                <th scope="row">
                                                    Berat awal (g)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        finishingItem.startWeight,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Berat akhir (g)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        finishingItem.finishWeight,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Bahan diserahkan (g)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        finishingItem.submitMaterial,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Sisa bahan (g)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        finishingItem.resultMaterial,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Susut (g)</th>
                                                <td>
                                                    {displayValue(
                                                        finishingItem.shrink,
                                                    )}
                                                    {finishingItem.shrinkPercent
                                                        ? ` (${finishingItem.shrinkPercent})`
                                                        : ''}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Toleransi susut (%)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        finishingItem.shrinkTolerance,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">
                                    Detail Bahan Emas
                                </h3>
                                <FinishingMaterialTables
                                    materials={finishingItem.materials}
                                    submitMaterial={finishingItem.submitMaterial}
                                    resultMaterial={finishingItem.resultMaterial}
                                />
                            </section>

                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">SPK</h3>
                                <div className="spkTableScroll">
                                    <table className="spkTable">
                                        <thead>
                                            <tr>
                                                <th>No SPK</th>
                                                <th>Tipe</th>
                                                <th>SKU / Item</th>
                                                <th>Customer</th>
                                                <th>Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {finishingItem.spk === null ? (
                                                <tr>
                                                    <td colSpan={5}>
                                                        Tidak ada SPK terkait.
                                                    </td>
                                                </tr>
                                            ) : (
                                                <tr>
                                                    <td>
                                                        {displayValue(
                                                            finishingItem.spk
                                                                .spkNo,
                                                        )}
                                                    </td>
                                                    <td>
                                                        <SpkOrderTypeColumn
                                                            spkType={
                                                                finishingItem
                                                                    .spk.spkType
                                                            }
                                                            orderTypeLabel={
                                                                finishingItem
                                                                    .spk
                                                                    .orderTypeLabel
                                                            }
                                                        />
                                                    </td>
                                                    <td>
                                                        <SpkItemSkuColumn
                                                            skuCode={
                                                                finishingItem
                                                                    .spk.skuCode
                                                            }
                                                            typeCode={
                                                                finishingItem
                                                                    .spk
                                                                    .typeCode
                                                            }
                                                            productItemName={
                                                                finishingItem
                                                                    .spk
                                                                    .productItemName
                                                            }
                                                            itemDescription={
                                                                finishingItem
                                                                    .spk
                                                                    .itemDescription
                                                            }
                                                        />
                                                    </td>
                                                    <td>
                                                        {displayValue(
                                                            finishingItem.spk
                                                                .customerName,
                                                        )}
                                                    </td>
                                                    <td>
                                                        {
                                                            finishingItem.spk
                                                                .satuan
                                                        }
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    );
}
