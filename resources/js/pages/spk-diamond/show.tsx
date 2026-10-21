import { Head } from '@inertiajs/react';
import { SpkDiamondDetail } from '@/components/spk-diamond/spk-diamond-detail';
import type { PasangBatuStones } from '@/components/pasang-batu/pasang-batu-stone-tables';
import { edit, index, submit } from '@/routes/spk-diamond';

type SpkDiamondShowProps = {
    workflowStatus: {
        key: string;
        label: string;
        stageIndex: number;
        stages: Array<{ key: string; label: string }>;
    };
    spkDiamondItem: {
        id: number;
        noSpkDiamond: string;
        tanggal: string;
        keterangan: string | null;
        weightFrame: string;
        weightStone: string;
        weightTotal: string;
        status: string;
        statusLabel: string;
        submittedAt: string | null;
        submittedBy: string | null;
        mountingId: number | null;
        mountingDocNo: string | null;
        stones: PasangBatuStones;
        spk: {
            spkId: number;
            spkNo: string;
            spkType: string | null;
            orderTypeLabel: string | null;
            skuCode: string | null;
            typeCode: string | null;
            productItemName: string | null;
            itemDescription: string | null;
            customerName: string | null;
            satuan: string;
            lastWeight: string | null;
        } | null;
    };
    approvalFooter: Array<{
        title: string;
        name: string;
        date: string;
    }>;
    canEdit: boolean;
    canSubmit: boolean;
};

export default function SpkDiamondShow({
    workflowStatus,
    spkDiamondItem,
    approvalFooter,
    canEdit,
    canSubmit,
}: SpkDiamondShowProps) {
    return (
        <>
            <Head
                title={`Detail SPK Diamond · ${spkDiamondItem.noSpkDiamond}`}
            />
            <SpkDiamondDetail
                spkDiamondItem={spkDiamondItem}
                workflowStatus={workflowStatus}
                approvalFooter={approvalFooter}
                canEdit={canEdit}
                canSubmit={canSubmit}
                backHref={index.url()}
                editHref={edit.url(spkDiamondItem.id)}
                submitUrl={submit.url(spkDiamondItem.id)}
            />
        </>
    );
}

SpkDiamondShow.layout = {
    activeMenu: 'SPK Diamond',
    pageTitle: 'Detail SPK Diamond',
};
