import { Head } from '@inertiajs/react';
import { ResinDetail } from '@/components/resin/resin-detail';
import {
    complete,
    destroy,
    edit,
    index,
    managerApprove,
    submit,
} from '@/routes/resin';

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

type StatusOption = {
    value: string;
    label: string;
};

type ResinShowProps = {
    approvalFooter: ApprovalFooterColumn[];
    approvalHistory: ApprovalHistoryEvent[];
    approval: ResinApprovalAbilities;
    workflowStatus: ResinWorkflowStatus;
    statusOptions: StatusOption[];
    saveProgressUrl: string;
    resinItem: {
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
};

export default function ResinShow({
    approvalFooter,
    approvalHistory,
    approval,
    workflowStatus,
    statusOptions,
    saveProgressUrl,
    resinItem,
}: ResinShowProps) {
    return (
        <>
            <Head
                title={`Detail Request Resin · ${resinItem.docNo ?? resinItem.id}`}
            />
            <ResinDetail
                resinItem={resinItem}
                approvalFooter={approvalFooter}
                approvalHistory={approvalHistory}
                approval={approval}
                workflowStatus={workflowStatus}
                statusOptions={statusOptions}
                saveProgressUrl={saveProgressUrl}
                backHref={index.url()}
                editHref={edit.url(resinItem.id)}
                submitUrl={submit.url(resinItem.id)}
                managerApproveUrl={managerApprove.url(resinItem.id)}
                completeUrl={complete.url(resinItem.id)}
                deleteUrl={destroy.url(resinItem.id)}
            />
        </>
    );
}

ResinShow.layout = {
    activeMenu: 'Resin',
    pageTitle: 'Detail Request Resin',
};
