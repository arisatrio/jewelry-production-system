import { Head } from '@inertiajs/react';
import { FinishingDetail } from '@/components/finishing/finishing-detail';
import type { FinishingMaterials } from '@/components/finishing/finishing-material-tables';
import { edit, index } from '@/routes/finishing';

type FinishingWorkflowStatus = {
    key: string;
    label: string;
    stageIndex: number;
    stages: Array<{ key: string; label: string }>;
};

type FinishingShowProps = {
    workflowStatus: FinishingWorkflowStatus;
    canEdit: boolean;
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
    canEdit,
}: FinishingShowProps) {
    return (
        <>
            <Head
                title={`Detail Finishing · ${finishingItem.docNo ?? finishingItem.id}`}
            />
            <FinishingDetail
                finishingItem={finishingItem}
                workflowStatus={workflowStatus}
                backHref={index.url()}
                editHref={edit.url(finishingItem.id)}
                canEdit={canEdit}
            />
        </>
    );
}

FinishingShow.layout = {
    activeMenu: 'Finishing',
    pageTitle: 'Detail Finishing',
};
