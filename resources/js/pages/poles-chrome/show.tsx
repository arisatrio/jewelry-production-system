import { Head } from '@inertiajs/react';
import { PolesChromeDetail } from '@/components/poles-chrome/poles-chrome-detail';
import {
    complete,
    edit,
    index,
    managerApprove,
    submit,
} from '@/routes/poles-chrome';

type PolesChromeWorkflowStatus = {
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

type PolesChromeApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    canOpenEdit: boolean;
    canDelete: boolean;
    canManagerApprove: boolean;
    canComplete: boolean;
    status: string;
    statusLabel: string;
};

type PolesChromeShowProps = {
    workflowStatus: PolesChromeWorkflowStatus;
    approvalHistory: ApprovalHistoryEvent[];
    approvalFooter: ApprovalFooterColumn[];
    approval: PolesChromeApprovalAbilities;
    polishFinishedGoodItem: {
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
        shrinkTolerance?: string | null;
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

export default function PolesChromeShow({
    polishFinishedGoodItem,
    workflowStatus,
    approvalHistory,
    approvalFooter,
    approval,
}: PolesChromeShowProps) {
    return (
        <>
            <Head
                title={`Detail Poles Chrome · ${polishFinishedGoodItem.docNo ?? polishFinishedGoodItem.id}`}
            />
            <PolesChromeDetail
                polishFinishedGoodItem={polishFinishedGoodItem}
                workflowStatus={workflowStatus}
                approvalHistory={approvalHistory}
                approvalFooter={approvalFooter}
                approval={approval}
                backHref={index.url()}
                editHref={edit.url(polishFinishedGoodItem.id)}
                submitUrl={submit.url(polishFinishedGoodItem.id)}
                managerApproveUrl={managerApprove.url(polishFinishedGoodItem.id)}
                completeUrl={complete.url(polishFinishedGoodItem.id)}
            />
        </>
    );
}

PolesChromeShow.layout = {
    activeMenu: 'Poles Chrome',
    pageTitle: 'Detail Poles Chrome',
};
