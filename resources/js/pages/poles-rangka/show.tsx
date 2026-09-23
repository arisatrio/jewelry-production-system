import { Head } from '@inertiajs/react';
import { PolesRangkaDetail } from '@/components/poles-rangka/poles-rangka-detail';
import {
    complete,
    edit,
    index,
    managerApprove,
    submit,
} from '@/routes/poles-rangka';

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

type PolesRangkaShowProps = {
    workflowStatus: PolesRangkaWorkflowStatus;
    approvalHistory: ApprovalHistoryEvent[];
    approvalFooter: ApprovalFooterColumn[];
    approval: PolesRangkaApprovalAbilities;
    polishFrameItem: {
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

export default function PolesRangkaShow({
    polishFrameItem,
    workflowStatus,
    approvalHistory,
    approvalFooter,
    approval,
}: PolesRangkaShowProps) {
    return (
        <>
            <Head
                title={`Detail Poles Rangka · ${polishFrameItem.docNo ?? polishFrameItem.id}`}
            />
            <PolesRangkaDetail
                polishFrameItem={polishFrameItem}
                workflowStatus={workflowStatus}
                approvalHistory={approvalHistory}
                approvalFooter={approvalFooter}
                approval={approval}
                backHref={index.url()}
                editHref={edit.url(polishFrameItem.id)}
                submitUrl={submit.url(polishFrameItem.id)}
                managerApproveUrl={managerApprove.url(polishFrameItem.id)}
                completeUrl={complete.url(polishFrameItem.id)}
            />
        </>
    );
}

PolesRangkaShow.layout = {
    activeMenu: 'Poles Rangka',
    pageTitle: 'Detail Poles Rangka',
};
