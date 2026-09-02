import { Head } from '@inertiajs/react';
import { ResinForm } from '@/components/resin/resin-form';
import { index, submit, update } from '@/routes/resin';

type StatusOption = {
    value: string;
    label: string;
};

type OperatorOption = {
    value: string;
    label: string;
};

type ResinEditProps = {
    formDocumentNo: string;
    statusOptions: StatusOption[];
    operatorOptions: OperatorOption[];
    approvalFooter: Array<{
        title: string;
        name: string;
        date: string;
    }>;
    approval: {
        canSubmit: boolean;
        canEdit: boolean;
        status: string;
        statusLabel: string;
    };
    resinItem: {
        id: number;
        docNo: string | null;
        transDate: string | null;
        status: string | null;
        operator: string | null;
        notes: string | null;
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
            statusResin: string;
            statusResinLabel: string;
            catatan: string | null;
        }>;
    };
};

export default function ResinEdit({
    formDocumentNo,
    statusOptions,
    operatorOptions,
    approvalFooter,
    approval,
    resinItem,
}: ResinEditProps) {
    return (
        <>
            <Head
                title={`Edit Request Resin · ${resinItem.docNo ?? resinItem.id}`}
            />
            <ResinForm
                title="Form Edit Request Resin"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={index.url()}
                submitUrl={update.url(resinItem.id)}
                method="put"
                statusOptions={statusOptions}
                operatorOptions={operatorOptions}
                approvalFooter={approvalFooter}
                approval={approval}
                submitToManagerUrl={submit.url(resinItem.id)}
                initialValues={{
                    doc_no: resinItem.docNo ?? '',
                    operator: resinItem.operator ?? '',
                    trans_date: resinItem.transDate ?? '',
                    notes: resinItem.notes ?? '',
                    details: resinItem.details.map((detail) => ({
                        spk_id: String(detail.spkId),
                        spk_no: detail.spkNo ?? '',
                        spk_type: detail.spkType ?? '',
                        order_type_label: detail.orderTypeLabel ?? '',
                        type_code: detail.typeCode ?? '',
                        product_item_name: detail.productItemName ?? '',
                        sku_code: detail.skuCode ?? '',
                        item_description: detail.itemDescription ?? '',
                        satuan: detail.satuan ?? '',
                        berat_resin: detail.beratResin,
                        status_resin: detail.statusResin ?? '',
                        catatan: detail.catatan ?? '',
                    })),
                }}
            />
        </>
    );
}

ResinEdit.layout = {
    activeMenu: 'Resin',
    pageTitle: 'Request Resin',
};
