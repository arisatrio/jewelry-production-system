import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import barCodeIcon from '@ui5/webcomponents-icons/dist/bar-code.js';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import editIcon from '@ui5/webcomponents-icons/dist/edit.js';
import navigationLeftIcon from '@ui5/webcomponents-icons/dist/navigation-left-arrow.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import pictureIcon from '@ui5/webcomponents-icons/dist/picture.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import ProductionController from '@/actions/App/Http/Controllers/ProductionController';
import { destroy as spkDestroy, form as spkForm } from '@/routes/spk';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SpkApprovalActions, type SpkApprovalAbilities } from '@/components/spk/spk-approval-actions';
import {
    SpkApprovalTimelinePanel,
    type SpkApprovalTimelineEvent,
} from '@/components/spk/spk-approval-timeline-panel';
import { SpkBarcodeDialog } from '@/components/spk/spk-barcode-dialog';
import { SpkCraftsmanReportSection } from '@/components/spk/spk-craftsman-report-section';
import { SpkGoldReportTable } from '@/components/spk/spk-gold-report-table';
import { SpkInformasiProduksiPanel } from '@/components/spk/spk-informasi-produksi-panel';
import type { SpkApprovalFooterColumn } from '@/components/spk/spk-informasi-produksi-panel';
import { SpkProcessPanel } from '@/components/spk/spk-process-panel';
import { SpkProductionControlReportSection } from '@/components/spk/spk-production-control-report';
import { SpkShrinkReportTable } from '@/components/spk/spk-shrink-report-table';
import { SpkStoneReportTable } from '@/components/spk/spk-stone-report-table';
import { SpkTypeBadge } from '@/components/spk/spk-type-badge';
import type {
    SpkCraftsmanReportCard,
    SpkDetail,
    SpkGoldReport,
    SpkItemDetail,
    SpkNavigation,
    SpkProcessTab,
    SpkProductionControlReport,
    SpkShrinkReport,
    SpkStoneItem,
    SpkStoneReport,
} from '@/components/spk/types';

export type { SpkNavigation };

type MainSectionTab = string;

const REPORT_SECTION_IDS = new Set([
    'laporan',
    'laporan-susut',
    'laporan-emas',
    'laporan-batu',
    'laporan-kontrol',
    'laporan-pengrajin',
]);

function normalizeMainSection(section: string): MainSectionTab {
    if (REPORT_SECTION_IDS.has(section)) {
        return 'laporan';
    }

    return section;
}

type SpkDetailLayoutProps = {
    production: SpkDetail;
    item: SpkItemDetail;
    navigation: SpkNavigation;
    detailUrl: string;
    stones?: SpkStoneItem[];
    processes: SpkProcessTab[];
    shrinkReport: SpkShrinkReport;
    craftsmanReport: SpkCraftsmanReportCard[];
    goldReport: SpkGoldReport;
    stoneReport: SpkStoneReport;
    productionControlReport: SpkProductionControlReport;
    activeTab: string;
    onTabChange: (tab: string) => void;
    initialMainSection?: string;
    approval?: SpkApprovalAbilities;
    approvalTimeline?: SpkApprovalTimelineEvent[];
    approvalFooter?: SpkApprovalFooterColumn[];
    children?: ReactNode;
};

function isProductionProcess(process: SpkProcessTab): boolean {
    return (process.placement ?? 'proses-produksi') === 'proses-produksi';
}

export function SpkDetailLayout({
    production,
    item,
    navigation,
    detailUrl,
    stones = [],
    processes,
    shrinkReport,
    craftsmanReport,
    goldReport,
    stoneReport,
    productionControlReport,
    activeTab,
    onTabChange,
    initialMainSection = 'informasi-produksi',
    approval,
    approvalTimeline = [],
    approvalFooter,
    children,
}: SpkDetailLayoutProps) {
    const [mainSection, setMainSection] = useState<MainSectionTab>(() =>
        normalizeMainSection(initialMainSection),
    );
    const [barcodeOpen, setBarcodeOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const workflowStatus = production.workflowStatus;
    const activeStageIndex = workflowStatus?.stageIndex ?? 0;
    const statusStages = workflowStatus?.stages ?? [
        { key: 'draft', label: 'Draft' },
        { key: 'confirmed', label: 'Approved' },
        { key: 'inProgress', label: 'In Progress' },
        { key: 'done', label: 'Done' },
    ];
    const orderPriorityLevel = production.orderPriorityLevel ?? null;
    const orderPriorityLabel = production.orderPriorityLabel?.trim() ?? '';
    const isOverdue = workflowStatus?.isOverdue === true;
    const productionProcesses = processes.filter(isProductionProcess);
    const mainProcesses = processes.filter(
        (process) => !isProductionProcess(process),
    );
    const mainSectionTabs: Array<{ id: MainSectionTab; label: string }> = [
        { id: 'informasi-produksi', label: 'Informasi Produksi' },
        { id: 'proses-produksi', label: 'Proses Produksi' },
        ...mainProcesses.map((process) => ({
            id: process.key,
            label: process.label,
        })),
        { id: 'laporan', label: 'Laporan' },
        { id: 'timeline', label: 'Timeline' },
    ];
    const activeMainProcess =
        mainProcesses.find((process) => process.key === mainSection) ?? null;
    const isInformasiProduksi = mainSection === 'informasi-produksi';
    const lastProcessLabel = production.prosesTerakhir.trim();
    const showLastProcessOnProsesTab =
        workflowStatus?.key === 'inProgress' && lastProcessLabel !== '';

    const goTo = (url: string | null): void => {
        if (!url) {
            return;
        }

        router.visit(url);
    };

    const openPrintPreview = (): void => {
        const previewUrl = ProductionController.print.url(
            Number(production.id),
        );
        const previewWindow = window.open(previewUrl, '_blank');

        if (!previewWindow) {
            window.alert(
                'Gagal membuka preview print. Izinkan pop-up untuk situs ini, lalu coba lagi.',
            );
        }
    };

    const openItemImage = (): void => {
        const imageUrl = item.imageUrl?.trim() ?? '';

        if (imageUrl === '') {
            window.alert('Gambar item belum tersedia untuk SPK ini.');

            return;
        }

        const imageWindow = window.open(imageUrl, '_blank', 'noopener,noreferrer');

        if (!imageWindow) {
            window.alert(
                'Gagal membuka gambar. Izinkan pop-up untuk situs ini, lalu coba lagi.',
            );
        }
    };

    const openEditForm = (): void => {
        router.visit(spkForm.url(Number(production.id)));
    };

    const closeDetail = (): void => {
        router.visit(navigation.backUrl);
    };

    const handleDelete = (): void => {
        setDeleting(true);
        router.delete(spkDestroy.url(Number(production.id)), {
            onFinish: () => {
                setDeleting(false);
                setDeleteOpen(false);
            },
        });
    };

    return (
        <div className="spkDetailStack">
            <div className="spkTopBarCard">
                <div className="spkTopBarMain">
                    <div className="spkTopBarLeft">
                        <div className="spkDocTitleBlock">
                            <div className="spkDocTitleRow">
                                <h1 className="spkDocTitle">
                                    {production.produksiNo}
                                </h1>
                                <SpkTypeBadge
                                    type={production.tipeProduksi}
                                />
                                {orderPriorityLabel !== '' && orderPriorityLevel ? (
                                    <span
                                        className={`spkPriorityLabel spkPriorityLabel--${orderPriorityLevel}`}
                                    >
                                        {orderPriorityLabel}
                                    </span>
                                ) : null}
                                {isOverdue ? (
                                    <span className="spkOverdueLabel">
                                        OVERDUE
                                    </span>
                                ) : null}
                            </div>
                        </div>

                        <div
                            className="spkStatusPipeline"
                            aria-label="Status SPK"
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
                        <div className="spkRecordPagerGroup">
                            <span className="spkRecordPager">
                                {navigation.position} / {navigation.total}
                            </span>
                            <div className="spkRecordNav">
                                <Button
                                    design="Transparent"
                                    icon={navigationLeftIcon}
                                    tooltip="Previous"
                                    disabled={!navigation.previousUrl}
                                    onClick={() => goTo(navigation.previousUrl)}
                                />
                                <Button
                                    design="Transparent"
                                    icon={navigationRightIcon}
                                    tooltip="Next"
                                    disabled={!navigation.nextUrl}
                                    onClick={() => goTo(navigation.nextUrl)}
                                />
                            </div>
                        </div>

                        <div className="spkHeaderActions">
                            {approval ? (
                                <SpkApprovalActions
                                    productionId={Number(production.id)}
                                    approval={approval}
                                />
                            ) : null}
                            <button
                                type="button"
                                className="spkHeaderActionBtn"
                                aria-label="Edit"
                                title="Edit SPK"
                                disabled={approval?.canEdit === false}
                                onClick={openEditForm}
                            >
                                <Icon name={editIcon} mode="Decorative" />
                            </button>
                            <button
                                type="button"
                                className="spkHeaderActionBtn"
                                aria-label="Print"
                                title="Preview Print"
                                onClick={openPrintPreview}
                            >
                                <Icon name={printIcon} mode="Decorative" />
                            </button>
                            <button
                                type="button"
                                className="spkHeaderActionBtn"
                                aria-label="QR Code"
                                title="QR Code"
                                onClick={() => setBarcodeOpen(true)}
                            >
                                <Icon name={barCodeIcon} mode="Decorative" />
                            </button>
                            <button
                                type="button"
                                className="spkHeaderActionBtn"
                                aria-label="Gambar"
                                title={
                                    item.imageUrl
                                        ? 'Buka gambar di tab baru'
                                        : 'Gambar belum tersedia'
                                }
                                disabled={!item.imageUrl}
                                onClick={openItemImage}
                            >
                                <Icon name={pictureIcon} mode="Decorative" />
                            </button>
                            {approval?.canDelete ? (
                                <button
                                    type="button"
                                    className="spkHeaderActionBtn spkHeaderActionBtn--danger"
                                    aria-label="Hapus"
                                    title="Hapus SPK"
                                    onClick={() => setDeleteOpen(true)}
                                >
                                    <Icon name={deleteIcon} mode="Decorative" />
                                </button>
                            ) : null}
                            <button
                                type="button"
                                className="spkHeaderActionBtn spkHeaderActionBtn--danger"
                                aria-label="Tutup"
                                title="Tutup"
                                onClick={closeDetail}
                            >
                                <Icon name={declineIcon} mode="Decorative" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <SpkBarcodeDialog
                open={barcodeOpen}
                onOpenChange={setBarcodeOpen}
                value={detailUrl}
                label={production.produksiNo}
            />

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent className="spkDeleteDialog">
                    <DialogHeader>
                        <DialogTitle>Hapus SPK</DialogTitle>
                        <DialogDescription>
                            SPK {production.produksiNo} akan dihapus dan tidak
                            lagi muncul di daftar SPK.
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
                            Hapus SPK
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div className="spkDetailBody">
                <section className="spkMainPanel">
                    <div
                        className="spkSectionTabs"
                        role="tablist"
                        aria-label="Konten utama SPK"
                    >
                        {mainSectionTabs.map((tab) => (
                            <button
                                key={tab.id}
                                type="button"
                                role="tab"
                                aria-selected={mainSection === tab.id}
                                className={[
                                    'spkSectionTab',
                                    mainSection === tab.id ? 'is-active' : '',
                                    tab.id === 'proses-produksi' &&
                                    showLastProcessOnProsesTab
                                        ? 'spkSectionTab--withLastProcess'
                                        : '',
                                ]
                                    .filter(Boolean)
                                    .join(' ')}
                                onClick={() => setMainSection(tab.id)}
                            >
                                <span className="spkSectionTabLabel">
                                    {tab.label}
                                </span>
                                {tab.id === 'proses-produksi' &&
                                showLastProcessOnProsesTab ? (
                                    <span className="spkSectionTabLastProcess">
                                        {lastProcessLabel}
                                    </span>
                                ) : null}
                            </button>
                        ))}
                    </div>

                    {isInformasiProduksi ? (
                        <SpkInformasiProduksiPanel
                            production={production}
                            item={item}
                            stones={stones}
                            approvalFooter={approvalFooter}
                        />
                    ) : null}

                    {mainSection === 'proses-produksi' ? (
                        <div
                            role="tabpanel"
                            aria-label="Proses Produksi"
                            className="spkProcessSection"
                        >
                            <div
                                className="spkProcessTabs"
                                role="tablist"
                                aria-label="Proses SPK"
                            >
                                {productionProcesses.map((process) => (
                                    <button
                                        key={process.key}
                                        type="button"
                                        role="tab"
                                        aria-selected={
                                            activeTab === process.key
                                        }
                                        className={[
                                            'spkProcessTab',
                                            activeTab === process.key
                                                ? 'is-active'
                                                : '',
                                        ]
                                            .filter(Boolean)
                                            .join(' ')}
                                        onClick={() =>
                                            onTabChange(process.key)
                                        }
                                    >
                                        {process.label}
                                        {process.recordCount > 0 ? (
                                            <span className="spkProcessTabCount">
                                                {process.recordCount}
                                            </span>
                                        ) : null}
                                    </button>
                                ))}
                            </div>

                            <div
                                className="spkProcessTabPanel"
                                role="tabpanel"
                                aria-label={`Panel ${activeTab}`}
                            >
                                {children}
                            </div>
                        </div>
                    ) : null}

                    {activeMainProcess ? (
                        <div
                            role="tabpanel"
                            aria-label={activeMainProcess.label}
                            className="spkProcessSection"
                        >
                            <div
                                className="spkProcessTabPanel"
                                role="tabpanel"
                                aria-label={activeMainProcess.label}
                            >
                                <SpkProcessPanel
                                    process={activeMainProcess}
                                />
                            </div>
                        </div>
                    ) : null}

                    {mainSection === 'laporan' ? (
                        <div
                            role="tabpanel"
                            aria-label="Laporan"
                            className="spkMainTabPanel spkLaporanPanel"
                        >
                            <SpkShrinkReportTable report={shrinkReport} />
                            <SpkGoldReportTable report={goldReport} />
                            <SpkStoneReportTable report={stoneReport} />
                            <SpkProductionControlReportSection
                                report={productionControlReport}
                            />
                            <SpkCraftsmanReportSection
                                cards={craftsmanReport}
                            />
                        </div>
                    ) : null}

                    {mainSection === 'timeline' ? (
                        <SpkApprovalTimelinePanel events={approvalTimeline} />
                    ) : null}
                </section>
            </div>
        </div>
    );
}

/** @deprecated Use SpkDetailLayout */
export const SpkDetailHeader = SpkDetailLayout;
