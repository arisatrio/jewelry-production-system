import { Head } from '@inertiajs/react';
import { PasangBatuForm } from '@/components/pasang-batu/pasang-batu-form';
import { index, store } from '@/routes/pasang-batu';

type OptionItem = {
    value: string;
    label: string;
};

type PasangBatuCreateProps = {
    formDocumentNo: string;
    processOptions: OptionItem[];
    craftsmanOptions: OptionItem[];
    form: {
        processName: string;
        sendCraftsmanDate: string;
        receivedCraftsmanDate: string;
        craftsmanId: number | null;
        notes: string;
        weightFrame: string;
        weightDiamond: string;
        weightFinishGoods: string;
        spk: null;
    };
};

export default function PasangBatuCreate({
    formDocumentNo,
    processOptions,
    craftsmanOptions,
    form,
}: PasangBatuCreateProps) {
    return (
        <>
            <Head title="Tambah Dokumen Pasang Batu" />
            <PasangBatuForm
                pickSpkDiamond
                title="Form Dokumen Pasang Batu"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={index.url()}
                submitUrl={store.url()}
                processOptions={processOptions}
                craftsmanOptions={craftsmanOptions}
                initialValues={{
                    process_name: form.processName ?? 'Pasang Batu',
                    craftsman_id:
                        form.craftsmanId !== null
                            ? String(form.craftsmanId)
                            : '',
                    send_craftsman_date: form.sendCraftsmanDate,
                    received_craftsman_date: form.receivedCraftsmanDate,
                    notes: form.notes ?? '',
                    weight_frame: form.weightFrame ?? '',
                    weight_diamond: form.weightDiamond ?? '',
                    weight_finish_goods: form.weightFinishGoods ?? '',
                    spk: null,
                    spk_diamond_id: '',
                    spk_diamond_no: '',
                    setting_awal_lines: [],
                    setting_stones: [],
                    return_stones: [],
                    diamonds: [],
                    mounted_stones: [],
                }}
            />
        </>
    );
}

PasangBatuCreate.layout = {
    activeMenu: 'Pasang Batu',
    pageTitle: 'Tambah Dokumen Pasang Batu',
};
