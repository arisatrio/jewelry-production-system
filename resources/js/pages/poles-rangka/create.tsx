import { Head } from '@inertiajs/react';
import { PolesRangkaForm } from '@/components/poles-rangka/poles-rangka-form';
import { index, store } from '@/routes/poles-rangka';

type OptionItem = {
    value: string;
    label: string;
};

type PolesRangkaCreateProps = {
    formDocumentNo: string;
    craftsmanOptions: OptionItem[];
    statusItemOptions: OptionItem[];
    form: {
        sendCraftsmanDate: string;
        receivedCraftsmanDate: string;
        craftsmanId: number | null;
        notes: string;
        statusItem: string | null;
        startWeight: string;
        finishWeight: string;
        spk: null;
    };
};

export default function PolesRangkaCreate({
    formDocumentNo,
    craftsmanOptions,
    statusItemOptions,
    form,
}: PolesRangkaCreateProps) {
    return (
        <>
            <Head title="Tambah Dokumen Poles Rangka" />
            <PolesRangkaForm
                title="Form Dokumen Poles Rangka"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={index.url()}
                submitUrl={store.url()}
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
                    spk: null,
                }}
            />
        </>
    );
}

PolesRangkaCreate.layout = {
    activeMenu: 'Poles Rangka',
    pageTitle: 'Tambah Dokumen Poles Rangka',
};
