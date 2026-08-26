import { Head } from '@inertiajs/react';
import { ResinForm } from '@/components/resin/resin-form';
import { index, update } from '@/routes/resin';

type ShapeOption = {
    id: number;
    name: string;
};

type ResinEditProps = {
    shapeOptions: ShapeOption[];
    resinItem: {
        id: number;
        docNo: string | null;
        transDate: string | null;
        status: string | null;
        spkId: number | null;
        spkNo: string | null;
        itemName: string | null;
        customerName: string | null;
        fileUpload: string | null;
        fileUrl: string | null;
        stones: Array<{
            shapeId: number | null;
            shapeName: string | null;
            pcs: number | null;
            carat: number | null;
            size: string;
        }>;
    };
};

export default function ResinEdit({
    shapeOptions,
    resinItem,
}: ResinEditProps) {
    return (
        <>
            <Head
                title={`Edit Dokumen Resin · ${resinItem.docNo ?? resinItem.id}`}
            />
            <ResinForm
                title="Form Edit Dokumen Resin"
                submitLabel="Simpan"
                cancelHref={index.url()}
                submitUrl={update.url(resinItem.id)}
                method="put"
                shapeOptions={shapeOptions}
                initialValues={{
                    doc_no: resinItem.docNo ?? '',
                    trans_date: resinItem.transDate ?? '',
                    spk_id: resinItem.spkId
                        ? String(resinItem.spkId)
                        : '',
                    spk_no: resinItem.spkNo ?? '',
                    item_name: resinItem.itemName ?? '',
                    customer_name: resinItem.customerName ?? '',
                    file: null,
                    file_upload: resinItem.fileUpload,
                    file_url: resinItem.fileUrl,
                    stones: resinItem.stones.map((stone) => ({
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

ResinEdit.layout = {
    activeMenu: 'Resin',
    pageTitle: 'Dokumen Resin',
};
