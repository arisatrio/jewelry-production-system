import { Head } from '@inertiajs/react';
import { FinishingForm } from '@/components/finishing/finishing-form';
import { index, store } from '@/routes/finishing';

type OptionItem = {
    value: string;
    label: string;
};

type MaterialOption = {
    value: string;
    label: string;
    stock: string;
};

type FinishingCreateProps = {
    formDocumentNo: string;
    processOptions: OptionItem[];
    itemCategoryOptions: OptionItem[];
    workCategoryOptions: OptionItem[];
    workTypeOptionsByCategory: Record<string, string[]>;
    shrinkAllowanceMatrix: Record<string, Record<string, string>>;
    craftsmanOptions: OptionItem[];
    materialOptions: MaterialOption[];
    qcNoteOptions: string[];
    form: {
        sendCraftsmanDate: string;
        receivedCraftsmanDate: string;
        processName: string;
        craftsmanId: number | null;
        itemCategory: string | null;
        workCategory: string | null;
        workType: string | null;
        notes: string;
        startWeight: string;
        finishWeight: string;
        koreksiQc: string;
        keteranganQc: string;
        spk: null;
        materials: Array<{
            section: string;
            materialgoldId: number | string;
            weight: string;
            notes?: string | null;
        }>;
    };
};

export default function FinishingCreate({
    formDocumentNo,
    processOptions,
    itemCategoryOptions,
    workCategoryOptions,
    workTypeOptionsByCategory,
    shrinkAllowanceMatrix,
    craftsmanOptions,
    materialOptions,
    qcNoteOptions,
    form,
}: FinishingCreateProps) {
    return (
        <>
            <Head title="Tambah Dokumen Finishing" />
            <FinishingForm
                title="Form Dokumen Finishing"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={index.url()}
                submitUrl={store.url()}
                processOptions={processOptions}
                itemCategoryOptions={itemCategoryOptions}
                workCategoryOptions={workCategoryOptions}
                workTypeOptionsByCategory={workTypeOptionsByCategory}
                shrinkAllowanceMatrix={shrinkAllowanceMatrix}
                craftsmanOptions={craftsmanOptions}
                materialOptions={materialOptions}
                qcNoteOptions={qcNoteOptions}
                initialValues={{
                    process_name: form.processName,
                    craftsman_id:
                        form.craftsmanId !== null
                            ? String(form.craftsmanId)
                            : '',
                    send_craftsman_date: form.sendCraftsmanDate,
                    received_craftsman_date: form.receivedCraftsmanDate,
                    item_category: form.itemCategory ?? '',
                    work_category: form.workCategory ?? '',
                    work_type: form.workType ?? '',
                    notes: form.notes ?? '',
                    start_weight: form.startWeight ?? '',
                    finish_weight: form.finishWeight ?? '',
                    koreksi_qc: form.koreksiQc ?? '0',
                    keterangan_qc: form.keteranganQc ?? '',
                    spk: null,
                    materials: (form.materials ?? []).map(
                        (material, index) => ({
                            key: `material-${index}-${material.section}-${material.materialgoldId}`,
                            section:
                                material.section === 'sisa' ? 'sisa' : 'bahan',
                            materialgold_id: String(material.materialgoldId),
                            weight: material.weight ?? '',
                            notes: material.notes ?? '',
                        }),
                    ),
                }}
            />
        </>
    );
}

FinishingCreate.layout = {
    activeMenu: 'Finishing',
    pageTitle: 'Tambah Dokumen Finishing',
};
