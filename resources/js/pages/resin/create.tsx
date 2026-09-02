import { Head } from '@inertiajs/react';
import { ResinForm } from '@/components/resin/resin-form';
import { index, store } from '@/routes/resin';

type StatusOption = {
    value: string;
    label: string;
};

type OperatorOption = {
    value: string;
    label: string;
};

type ResinCreateProps = {
    formDocumentNo: string;
    statusOptions: StatusOption[];
    operatorOptions: OperatorOption[];
    approvalFooter: Array<{
        title: string;
        name: string;
        date: string;
    }>;
    form: {
        operator: string;
        transDate: string;
        notes: string;
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
            catatan: string | null;
        }>;
    };
};

export default function ResinCreate({
    formDocumentNo,
    statusOptions,
    operatorOptions,
    approvalFooter,
    form,
}: ResinCreateProps) {
    return (
        <>
            <Head title="Tambah Request Resin" />
            <ResinForm
                title="Form Request Resin"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={index.url()}
                submitUrl={store.url()}
                method="post"
                isNew
                statusOptions={statusOptions}
                operatorOptions={operatorOptions}
                approvalFooter={approvalFooter}
                initialValues={{
                    doc_no: '',
                    operator: form.operator,
                    trans_date: form.transDate,
                    notes: form.notes,
                    details: form.details.map((detail) => ({
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

ResinCreate.layout = {
    activeMenu: 'Resin',
    pageTitle: 'Request Resin',
};
