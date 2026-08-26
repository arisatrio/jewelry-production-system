import { Head } from '@inertiajs/react';
import { ResinForm } from '@/components/resin/resin-form';
import { index, store } from '@/routes/resin';

type ShapeOption = {
    id: number;
    name: string;
};

type ResinCreateProps = {
    shapeOptions: ShapeOption[];
    form: {
        transDate: string;
        spkId: number | null;
        spkNo: string;
        itemName: string;
        customerName: string;
        fileUpload: string | null;
        fileUrl: string | null;
        stones: Array<{
            shapeId: number | null;
            pcs: number | null;
            carat: number | null;
            size: string;
        }>;
    };
};

export default function ResinCreate({
    shapeOptions,
    form,
}: ResinCreateProps) {
    return (
        <>
            <Head title="Tambah Dokumen Resin" />
            <ResinForm
                title="Form Dokumen Resin"
                submitLabel="Simpan"
                cancelHref={index.url()}
                submitUrl={store.url()}
                method="post"
                isNew
                shapeOptions={shapeOptions}
                initialValues={{
                    doc_no: '',
                    trans_date: form.transDate,
                    spk_id: form.spkId ? String(form.spkId) : '',
                    spk_no: form.spkNo,
                    item_name: form.itemName,
                    customer_name: form.customerName,
                    file: null,
                    file_upload: form.fileUpload,
                    file_url: form.fileUrl,
                    stones: form.stones.map((stone) => ({
                        shape_id: stone.shapeId
                            ? String(stone.shapeId)
                            : '',
                        pcs:
                            stone.pcs !== null && stone.pcs !== undefined
                                ? String(stone.pcs)
                                : '',
                        carat:
                            stone.carat !== null && stone.carat !== undefined
                                ? String(stone.carat)
                                : '',
                        size: stone.size ?? '',
                    })),
                }}
            />
        </>
    );
}

ResinCreate.layout = {
    activeMenu: 'Resin',
    pageTitle: 'Dokumen Resin',
};
