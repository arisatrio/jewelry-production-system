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

type MaterialOption = {
    value: string;
    label: string;
    stock: string;
};

type CoranCreateProps = {
    formDocumentNo: string;
    statusOptions: StatusOption[];
    craftsmanOptions: CraftsmanOption[];
    materialOptions: MaterialOption[];
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
            weightRosegold?: string | null;
            weightWhitegold?: string | null;
            weightYellowgold?: string | null;
            kadarRosegold?: string | null;
            kadarWhitegold?: string | null;
            kadarYellowgold?: string | null;
            statusRosegold?: string | null;
            statusWhitegold?: string | null;
            statusYellowgold?: string | null;
        }>;
        materials: Array<{
            section: string;
            materialgoldId: number | string;
            weight: string;
            notes?: string | null;
        }>;
    };
};

export default function CoranCreate({
    formDocumentNo,
    statusOptions,
    craftsmanOptions,
    materialOptions,
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
                        weight_rosegold: detail.weightRosegold ?? '',
                        weight_whitegold: detail.weightWhitegold ?? '',
                        weight_yellowgold: detail.weightYellowgold ?? '',
                        kadar_rosegold: detail.kadarRosegold ?? '',
                        kadar_whitegold: detail.kadarWhitegold ?? '',
                        kadar_yellowgold: detail.kadarYellowgold ?? '',
                        status_rosegold: detail.statusRosegold ?? '',
                        status_whitegold: detail.statusWhitegold ?? '',
                        status_yellowgold: detail.statusYellowgold ?? '',
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
