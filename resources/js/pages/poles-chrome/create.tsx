import { Head } from '@inertiajs/react';
import { PolesChromeForm } from '@/components/poles-chrome/poles-chrome-form';
import { index, store } from '@/routes/poles-chrome';

type OptionItem = {
    value: string;
    label: string;
};

type PolesChromeCreateProps = {
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

export default function PolesChromeCreate({
    formDocumentNo,
    craftsmanOptions,
    statusItemOptions,
    form,
}: PolesChromeCreateProps) {
    return (
        <>
            <Head title="Tambah Dokumen Poles Chrome" />
            <PolesChromeForm
                title="Form Dokumen Poles Chrome"
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

PolesChromeCreate.layout = {
    activeMenu: 'Poles Chrome',
    pageTitle: 'Tambah Dokumen Poles Chrome',
};
