import { Head } from '@inertiajs/react';
import { useState } from 'react';
import type { SpkApprovalAbilities } from '@/components/spk/spk-approval-actions';
import type { SpkApprovalTimelineEvent } from '@/components/spk/spk-approval-timeline-panel';
import { SpkDetailLayout } from '@/components/spk/spk-detail-header';
import type { SpkApprovalFooterColumn } from '@/components/spk/spk-informasi-produksi-panel';
import { SpkProcessPanel } from '@/components/spk/spk-process-panel';
import type {
    SpkCraftsmanReportCard,
    SpkDefaultProcessSelection,
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

type SpkShowProps = {
    production: SpkDetail;
    item: SpkItemDetail;
    stones: SpkStoneItem[];
    processes: SpkProcessTab[];
    defaultProcessSelection: SpkDefaultProcessSelection;
    shrinkReport: SpkShrinkReport;
    craftsmanReport: SpkCraftsmanReportCard[];
    goldReport: SpkGoldReport;
    stoneReport: SpkStoneReport;
    productionControlReport: SpkProductionControlReport;
    navigation: SpkNavigation;
    detailUrl: string;
    approval: SpkApprovalAbilities;
    approvalTimeline: SpkApprovalTimelineEvent[];
    approvalFooter: SpkApprovalFooterColumn[];
};

export default function SpkShow({
    production,
    item,
    stones,
    processes,
    defaultProcessSelection,
    shrinkReport,
    craftsmanReport,
    goldReport,
    stoneReport,
    productionControlReport,
    navigation,
    detailUrl,
    approval,
    approvalTimeline,
    approvalFooter,
}: SpkShowProps) {
    const productionProcesses = processes.filter(
        (process) =>
            (process.placement ?? 'proses-produksi') === 'proses-produksi',
    );
    const [activeTab, setActiveTab] = useState(
        defaultProcessSelection.processKey ||
            productionProcesses[0]?.key ||
            'JewelCAD',
    );

    const activeProcess =
        productionProcesses.find((process) => process.key === activeTab) ??
        productionProcesses[0] ??
        null;

    return (
        <>
            <Head title={`SPK ${production.produksiNo}`} />
            <div className="spkDetailShell">
                <SpkDetailLayout
                    production={production}
                    item={item}
                    navigation={navigation}
                    detailUrl={detailUrl}
                    stones={stones}
                    processes={processes}
                    shrinkReport={shrinkReport}
                    craftsmanReport={craftsmanReport}
                    goldReport={goldReport}
                    stoneReport={stoneReport}
                    productionControlReport={productionControlReport}
                    activeTab={activeTab}
                    onTabChange={setActiveTab}
                    initialMainSection={defaultProcessSelection.mainSection}
                    approval={approval}
                    approvalTimeline={approvalTimeline}
                    approvalFooter={approvalFooter}
                >
                    {activeProcess ? (
                        <SpkProcessPanel process={activeProcess} />
                    ) : null}
                </SpkDetailLayout>
            </div>
        </>
    );
}

SpkShow.layout = {
    activeMenu: 'SPK',
    pageTitle: 'Detail SPK',
};
