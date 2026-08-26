import { Head } from '@inertiajs/react';
import { JewelCadForm } from '@/components/jewelcad/jewelcad-form';
import { index, update } from '@/routes/jewelcad';

type OperatorOption = {
    value: string;
    label: string;
};

type ApprovalFooterColumn = {
    title: string;
    name: string;
    date: string;
};

type JewelCadEditProps = {
    formDocumentNo: string;
    operatorOptions: OperatorOption[];
    approvalFooter: ApprovalFooterColumn[];
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
            qty: number | null;
            estimationBrj: string;
            notes: string | null;
        }>;
    };
};

export default function JewelCadEdit({
    formDocumentNo,
    operatorOptions,
    approvalFooter,
    requestItem,
}: JewelCadEditProps) {
    return (
        <>
            <Head
                title={`Edit Request JewelCAD · ${requestItem.docNo ?? requestItem.id}`}
            />
            <JewelCadForm
                title="Form Edit Request JewelCAD"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan Draft"
                cancelHref={index.url()}
                submitUrl={update.url(requestItem.id)}
                method="put"
                operatorOptions={operatorOptions}
                approvalFooter={approvalFooter}
                initialValues={{
                    doc_no: requestItem.docNo ?? '',
                    operator: requestItem.operator ?? '',
                    trans_date: requestItem.transDate ?? '',
                    notes: requestItem.notes ?? '',
                    details: requestItem.details.map((detail) => ({
                        spk_id: String(detail.spkId),
                        spk_no: detail.spkNo ?? '',
                        material: detail.material ?? '',
                        gold_weight: detail.goldWeight,
                        jwcad_3d: '',
                        file: null,
                        image_url: null,
                        file_name: null,
                        qty: detail.qty !== null ? String(detail.qty) : '',
                        estimation_brj: detail.estimationBrj,
                        notes: detail.notes ?? '',
                    })),
                }}
            />
        </>
    );
}

JewelCadEdit.layout = {
    activeMenu: 'JewelCAD',
    pageTitle: 'Request JewelCAD',
};
