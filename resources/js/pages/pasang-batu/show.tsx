import { Head } from '@inertiajs/react';
import { PasangBatuDetail } from '@/components/pasang-batu/pasang-batu-detail';
import type { PasangBatuStones } from '@/components/pasang-batu/pasang-batu-stone-tables';
import {
    complete,
    edit,
    index,
    managerApprove,
    submit,
} from '@/routes/pasang-batu';

type PasangBatuWorkflowStatus = {
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

type PasangBatuApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    canOpenEdit: boolean;
    canDelete: boolean;
    canManagerApprove: boolean;
    canComplete: boolean;
    status: string;
    statusLabel: string;
};

type PasangBatuShowProps = {
    workflowStatus: PasangBatuWorkflowStatus;
    approvalHistory: ApprovalHistoryEvent[];
    approvalFooter: ApprovalFooterColumn[];
    approval: PasangBatuApprovalAbilities;
    diamondMountingItem: {
        id: number;
        docNo: string | null;
        status: string | null;
        statusLabel: string;
        craftsmanId: number | null;
        craftsmanName: string | null;
        sendCraftsmanDate: string | null;
        receivedCraftsmanDate: string | null;
        notes: string | null;
        weightFrame: string | null;
        weightDiamond: string | null;
        totalWeight: string | null;
        weightFinishGoods: string | null;
        shrink: string | null;
        shrinkPercent: string | null;
        shrinkTolerance?: string | null;
        stones: PasangBatuStones;
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

export default function PasangBatuShow({
    diamondMountingItem,
    workflowStatus,
    approvalHistory,
    approvalFooter,
    approval,
}: PasangBatuShowProps) {
    return (
        <>
            <Head
                title={`Detail Pasang Batu · ${diamondMountingItem.docNo ?? diamondMountingItem.id}`}
            />
            <PasangBatuDetail
                diamondMountingItem={diamondMountingItem}
                workflowStatus={workflowStatus}
                approvalHistory={approvalHistory}
                approvalFooter={approvalFooter}
                approval={approval}
                backHref={index.url()}
                editHref={edit.url(diamondMountingItem.id)}
                submitUrl={submit.url(diamondMountingItem.id)}
                managerApproveUrl={managerApprove.url(diamondMountingItem.id)}
                completeUrl={complete.url(diamondMountingItem.id)}
            />
        </>
    );
}

PasangBatuShow.layout = {
    activeMenu: 'Pasang Batu',
    pageTitle: 'Detail Pasang Batu',
};
