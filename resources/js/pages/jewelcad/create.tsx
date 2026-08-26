import { Head } from '@inertiajs/react';
import { JewelCadForm } from '@/components/jewelcad/jewelcad-form';
import { index, store } from '@/routes/jewelcad';

type OperatorOption = {
    value: string;
    label: string;
};

type ApprovalFooterColumn = {
    title: string;
    name: string;
    date: string;
};

type JewelCadCreateProps = {
    formDocumentNo: string;
    operatorOptions: OperatorOption[];
    approvalFooter: ApprovalFooterColumn[];
    form: {
        operator: string;
        transDate: string;
        notes: string;
        details: Array<{
            spkId: number | null;
            spkNo: string;
            material: string;
            goldWeight: string;
            qty: number;
            estimationBrj: string;
            notes: string;
        }>;
    };
};

export default function JewelCadCreate({
    formDocumentNo,
    operatorOptions,
    approvalFooter,
    form,
}: JewelCadCreateProps) {
    return (
        <>
            <Head title="Tambah Request JewelCAD" />
            <JewelCadForm
                title="Form Request JewelCAD"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan Draft"
                cancelHref={index.url()}
                submitUrl={store.url()}
                method="post"
                isNew
                operatorOptions={operatorOptions}
                approvalFooter={approvalFooter}
                initialValues={{
                    doc_no: '',
                    operator: form.operator,
                    trans_date: form.transDate,
                    notes: form.notes,
                    details: form.details.map((detail) => ({
                        spk_id: detail.spkId ? String(detail.spkId) : '',
                        spk_no: detail.spkNo,
                        material: detail.material,
                        gold_weight: detail.goldWeight,
                        jwcad_3d: '',
                        file: null,
                        image_url: null,
                        file_name: null,
                        qty: detail.qty ? String(detail.qty) : '',
                        estimation_brj: detail.estimationBrj,
                        notes: detail.notes,
                    })),
                }}
            />
        </>
    );
}

JewelCadCreate.layout = {
    activeMenu: 'JewelCAD',
    pageTitle: 'Request JewelCAD',
};
