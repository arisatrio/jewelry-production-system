import { Head } from '@inertiajs/react';
import { FinishingDetail } from '@/components/finishing/finishing-detail';
import type { FinishingMaterials } from '@/components/finishing/finishing-material-tables';
import {
    complete,
    edit,
    index,
    managerApprove,
    submit,
} from '@/routes/finishing';

type FinishingWorkflowStatus = {
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

type FinishingApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    canOpenEdit: boolean;
    canDelete: boolean;
    canManagerApprove: boolean;
    canComplete: boolean;
    status: string;
    statusLabel: string;
};

type FinishingShowProps = {
    workflowStatus: FinishingWorkflowStatus;
    approvalHistory: ApprovalHistoryEvent[];
    approvalFooter: ApprovalFooterColumn[];
    approval: FinishingApprovalAbilities;
    finishingItem: {
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
        spk: {
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
        } | null;
    };
};

export default function FinishingShow({
    finishingItem,
    workflowStatus,
    approvalHistory,
    approvalFooter,
    approval,
}: FinishingShowProps) {
    return (
        <>
            <Head
                title={`Detail Finishing · ${finishingItem.docNo ?? finishingItem.id}`}
            />
            <FinishingDetail
                finishingItem={finishingItem}
                workflowStatus={workflowStatus}
                approvalHistory={approvalHistory}
                approvalFooter={approvalFooter}
                approval={approval}
                backHref={index.url()}
                editHref={edit.url(finishingItem.id)}
                submitUrl={submit.url(finishingItem.id)}
                managerApproveUrl={managerApprove.url(finishingItem.id)}
                completeUrl={complete.url(finishingItem.id)}
            />
        </>
    );
}

FinishingShow.layout = {
    activeMenu: 'Finishing',
    pageTitle: 'Detail Finishing',
};
