import { Head } from '@inertiajs/react';
import { PolesChromeForm } from '@/components/poles-chrome/poles-chrome-form';
import { show, update } from '@/routes/poles-chrome';

type OptionItem = {
    value: string;
    label: string;
};

type PolesChromeEditProps = {
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

export default function PolesChromeEdit({
    formDocumentNo,
    craftsmanOptions,
    statusItemOptions,
    form,
}: PolesChromeEditProps) {
    return (
        <>
            <Head
                title={`Edit Dokumen Poles Chrome · ${form.docNo ?? form.id}`}
            />
            <PolesChromeForm
                title="Form Edit Dokumen Poles Chrome"
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

PolesChromeEdit.layout = {
    activeMenu: 'Poles Chrome',
    pageTitle: 'Edit Dokumen Poles Chrome',
};
