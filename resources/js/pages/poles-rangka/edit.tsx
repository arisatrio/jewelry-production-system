import { Head } from '@inertiajs/react';
import { PolesRangkaForm } from '@/components/poles-rangka/poles-rangka-form';
import { show, update } from '@/routes/poles-rangka';

type OptionItem = {
    value: string;
    label: string;
};

type PolesRangkaEditProps = {
    formDocumentNo: string;
    craftsmanOptions: OptionItem[];
    statusItemOptions: OptionItem[];
    form: {
        id: number;
        docNo: string | null;
        sendCraftsmanDate: string;
        receivedCraftsmanDate: string;
        craftsmanId: number | null;
        notes: string;
        statusItem: string | null;
        startWeight: string;
        finishWeight: string;
        spk: {
            spkId: number;
            spkNo: string | null;
            spkType: string | null;
            orderTypeLabel: string | null;
            skuCode: string | null;
            typeCode: string | null;
            productItemName: string | null;
            itemDescription: string | null;
            satuan: string;
        } | null;
    };
};

export default function PolesRangkaEdit({
    formDocumentNo,
    craftsmanOptions,
    statusItemOptions,
    form,
}: PolesRangkaEditProps) {
    return (
        <>
            <Head
                title={`Edit Dokumen Poles Rangka · ${form.docNo ?? form.id}`}
            />
            <PolesRangkaForm
                title="Form Edit Dokumen Poles Rangka"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={show.url(form.id)}
                submitUrl={update.url(form.id)}
                method="put"
                craftsmanOptions={craftsmanOptions}
                statusItemOptions={statusItemOptions}
                initialValues={{
                    craftsman_id:
                        form.craftsmanId !== null
                            ? String(form.craftsmanId)
                            : '',
                    send_craftsman_date: form.sendCraftsmanDate,
                    received_craftsman_date: form.receivedCraftsmanDate,
                    notes: form.notes ?? '',
                    status_item: form.statusItem ?? '',
                    start_weight: form.startWeight ?? '',
                    finish_weight: form.finishWeight ?? '',
                    spk: form.spk
                        ? {
                              spk_id: String(form.spk.spkId),
                              spk_no: form.spk.spkNo ?? '',
                              spk_type: form.spk.spkType ?? '',
                              order_type_label: form.spk.orderTypeLabel ?? '',
                              type_code: form.spk.typeCode ?? '',
                              product_item_name:
                                  form.spk.productItemName ?? '',
                              sku_code: form.spk.skuCode ?? '',
                              item_description:
                                  form.spk.itemDescription ?? '',
                              satuan: form.spk.satuan ?? '',
                          }
                        : null,
                }}
            />
        </>
    );
}

PolesRangkaEdit.layout = {
    activeMenu: 'Poles Rangka',
    pageTitle: 'Edit Dokumen Poles Rangka',
};
