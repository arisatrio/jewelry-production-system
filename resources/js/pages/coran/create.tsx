import { Head } from '@inertiajs/react';
import { CoranForm } from '@/components/coran/coran-form';
import { index, store } from '@/routes/coran';

type StatusOption = {
    value: string;
    label: string;
};

type CraftsmanOption = {
    value: string;
    label: string;
};

type CoranCreateProps = {
    formDocumentNo: string;
    statusOptions: StatusOption[];
    craftsmanOptions: CraftsmanOption[];
    form: {
        transDate: string;
        craftsmanId: number | null;
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
            weight: string;
            status: string;
        }>;
    };
};

export default function CoranCreate({
    formDocumentNo,
    statusOptions,
    craftsmanOptions,
    form,
}: CoranCreateProps) {
    return (
        <>
            <Head title="Tambah Dokumen Coran" />
            <CoranForm
                title="Form Dokumen Coran"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={index.url()}
                submitUrl={store.url()}
                statusOptions={statusOptions}
                craftsmanOptions={craftsmanOptions}
                initialValues={{
                    trans_date: form.transDate,
                    craftsman_id:
                        form.craftsmanId !== null
                            ? String(form.craftsmanId)
                            : '',
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
                        weight: detail.weight ?? '',
                        status: detail.status ?? '',
                    })),
                }}
            />
        </>
    );
}
