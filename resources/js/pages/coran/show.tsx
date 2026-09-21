import { Head } from '@inertiajs/react';
import { CoranDetail } from '@/components/coran/coran-detail';
import type { CoranBreakdownSection } from '@/components/coran/coran-material-breakdown';
import {
    complete,
    destroy,
    edit,
    index,
    managerApprove,
    submit,
} from '@/routes/coran';

type CoranMaterialLine = {
    name: string;
    weight: string;
};

type CoranWorkflowStatus = {
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

type CoranApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    canOpenEdit: boolean;
    canDelete: boolean;
    canManagerApprove: boolean;
    canComplete: boolean;
    status: string;
    statusLabel: string;
};

type CoranShowProps = {
    workflowStatus: CoranWorkflowStatus;
    approvalHistory: ApprovalHistoryEvent[];
    approvalFooter: ApprovalFooterColumn[];
    approval: CoranApprovalAbilities;
    coranItem: {
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
        details: Array<{
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
            weightRosegold?: string | null;
            weightWhitegold?: string | null;
            weightYellowgold?: string | null;
            kadar: string | null;
            kadarRosegold?: string | null;
            kadarWhitegold?: string | null;
            kadarYellowgold?: string | null;
            status: string | null;
            statusLabel: string;
            statusRosegold?: string | null;
            statusRosegoldLabel?: string;
            statusWhitegold?: string | null;
            statusWhitegoldLabel?: string;
            statusYellowgold?: string | null;
            statusYellowgoldLabel?: string;
        }>;
    };
};

export default function CoranShow({
    workflowStatus,
    approvalHistory,
    approvalFooter,
    approval,
    coranItem,
}: CoranShowProps) {
    return (
        <>
            <Head
                title={`Detail Dokumen Coran · ${coranItem.docNo ?? coranItem.id}`}
            />
            <CoranDetail
                coranItem={coranItem}
                workflowStatus={workflowStatus}
                approvalHistory={approvalHistory}
                approvalFooter={approvalFooter}
                approval={approval}
                editHref={edit.url(coranItem.id)}
                backHref={index.url()}
                deleteUrl={destroy.url(coranItem.id)}
                submitUrl={submit.url(coranItem.id)}
                managerApproveUrl={managerApprove.url(coranItem.id)}
                completeUrl={complete.url(coranItem.id)}
            />
        </>
    );
}

CoranShow.layout = {
    activeMenu: 'Coran',
    pageTitle: 'Detail Dokumen Coran',
};
