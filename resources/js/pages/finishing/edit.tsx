import { Head } from '@inertiajs/react';
import { FinishingForm } from '@/components/finishing/finishing-form';
import { show, update } from '@/routes/finishing';

type OptionItem = {
    value: string;
    label: string;
};

type MaterialOption = {
    value: string;
    label: string;
    stock: string;
};

type FinishingEditProps = {
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
        id: number;
        docNo: string | null;
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
        materials: Array<{
            section: string;
            materialgoldId: number | string;
            weight: string;
            notes?: string | null;
        }>;
    };
};

export default function FinishingEdit({
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
}: FinishingEditProps) {
    return (
        <>
            <Head title={`Edit Dokumen Finishing · ${form.docNo ?? form.id}`} />
            <FinishingForm
                title="Form Edit Dokumen Finishing"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={show.url(form.id)}
                submitUrl={update.url(form.id)}
                method="put"
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
                    koreksi_qc: form.koreksiQc ?? '',
                    keterangan_qc: form.keteranganQc ?? '',
                    spk: form.spk
                        ? {
                              spk_id: String(form.spk.spkId),
                              spk_no: form.spk.spkNo ?? '',
                              spk_type: form.spk.spkType ?? '',
                              order_type_label: form.spk.orderTypeLabel ?? '',
                              type_code: form.spk.typeCode ?? '',
                              product_item_name: form.spk.productItemName ?? '',
                              sku_code: form.spk.skuCode ?? '',
                              item_description: form.spk.itemDescription ?? '',
                              satuan: form.spk.satuan ?? '',
                          }
                        : null,
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

FinishingEdit.layout = {
    activeMenu: 'Finishing',
    pageTitle: 'Edit Dokumen Finishing',
};
