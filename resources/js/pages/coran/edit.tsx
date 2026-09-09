import { Head } from '@inertiajs/react';
import { CoranForm } from '@/components/coran/coran-form';
import { show, update } from '@/routes/coran';

type StatusOption = {
    value: string;
    label: string;
};

type CraftsmanOption = {
    value: string;
    label: string;
};

type MaterialOption = {
    value: string;
    label: string;
    stock: string;
};

type CoranEditProps = {
    formDocumentNo: string;
    statusOptions: StatusOption[];
    craftsmanOptions: CraftsmanOption[];
    materialOptions: MaterialOption[];
    approval: {
        canEdit: boolean;
        canOpenEdit: boolean;
        status: string;
        statusLabel: string;
    };
    form: {
        id: number;
        docNo: string | null;
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
            kadar?: string | null;
            status: string;
        }>;
        materials: Array<{
            section: string;
            materialgoldId: number | string;
            weight: string;
            notes?: string | null;
        }>;
    };
};

export default function CoranEdit({
    formDocumentNo,
    statusOptions,
    craftsmanOptions,
    materialOptions,
    form,
}: CoranEditProps) {
    return (
        <>
            <Head title={`Edit Dokumen Coran · ${form.docNo ?? form.id}`} />
            <CoranForm
                title="Form Edit Dokumen Coran"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={show.url(form.id)}
                submitUrl={update.url(form.id)}
                method="put"
                statusOptions={statusOptions}
                craftsmanOptions={craftsmanOptions}
                materialOptions={materialOptions}
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
                        kadar: detail.kadar ?? '',
                        status: detail.status ?? '',
                    })),
                    materials: (form.materials ?? []).map(
                        (material, index) => ({
                            key: `material-${index}-${material.section}-${material.materialgoldId}`,
                            section: material.section,
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

CoranEdit.layout = {
    activeMenu: 'Coran',
    pageTitle: 'Edit Dokumen Coran',
};
