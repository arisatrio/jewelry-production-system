import { Head } from '@inertiajs/react';
import { JewelCadDetail } from '@/components/jewelcad/jewelcad-detail';
import { complete, destroy, edit, index, managerApprove, submit } from '@/routes/jewelcad';

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

type JewelCadApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    canOpenEdit: boolean;
    canDelete: boolean;
    canManagerApprove: boolean;
    canComplete: boolean;
    status: string;
    statusLabel: string;
};

type JewelCadShowProps = {
    formDocumentNo: string;
    approvalFooter: ApprovalFooterColumn[];
    approvalHistory: ApprovalHistoryEvent[];
    approval: JewelCadApprovalAbilities;
    requestItem: {
        id: number;
        docNo: string | null;
        operator: string | null;
        transDate: string | null;
        notes: string | null;
        status: string | null;
        details: Array<{
            spkId: number;
            spkNo: string | null;
            material: string | null;
            goldWeight: string;
            skuCode: string | null;
            typeCode: string | null;
            productItemName: string | null;
            satuan: string;
            qty: number | null;
            estimationBrj: string;
            notes: string | null;
        }>;
    };
};

export default function JewelCadShow({
    approvalFooter,
    approvalHistory,
    approval,
    requestItem,
}: JewelCadShowProps) {
    return (
        <>
            <Head
                title={`Detail Request JewelCAD · ${requestItem.docNo ?? requestItem.id}`}
            />
            <JewelCadDetail
                requestItem={requestItem}
                approvalFooter={approvalFooter}
                approvalHistory={approvalHistory}
                approval={approval}
                backHref={index.url()}
                editHref={edit.url(requestItem.id)}
                submitUrl={submit.url(requestItem.id)}
                managerApproveUrl={managerApprove.url(requestItem.id)}
                completeUrl={complete.url(requestItem.id)}
                deleteUrl={destroy.url(requestItem.id)}
            />
        </>
    );
}

JewelCadShow.layout = {
    activeMenu: 'JewelCAD',
    pageTitle: 'Detail Request JewelCAD',
};
