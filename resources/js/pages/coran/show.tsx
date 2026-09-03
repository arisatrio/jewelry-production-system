import { Head } from '@inertiajs/react';
import { CoranDetail } from '@/components/coran/coran-detail';
import { index } from '@/routes/coran';
import type { CoranBreakdownSection } from '@/components/coran/coran-material-breakdown';

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

type ApprovalHistoryEvent = {
    status: string;
    statusLabel: string;
    approve: string;
    notes: string | null;
    createdBy: string | null;
    createdAt: string | null;
};

type CoranShowProps = {
    workflowStatus: CoranWorkflowStatus;
    approvalHistory: ApprovalHistoryEvent[];
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
            status: string | null;
            statusLabel: string;
        }>;
    };
};

export default function CoranShow({
    workflowStatus,
    approvalHistory,
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
                backHref={index.url()}
            />
        </>
    );
}

CoranShow.layout = {
    activeMenu: 'Coran',
    pageTitle: 'Detail Dokumen Coran',
};
