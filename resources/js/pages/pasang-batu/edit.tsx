import { Head } from '@inertiajs/react';
import { PasangBatuForm } from '@/components/pasang-batu/pasang-batu-form';
import type { PasangBatuStoneOption } from '@/components/pasang-batu/pasang-batu-stone-editor';
import { show, update } from '@/routes/pasang-batu';

type OptionItem = {
    value: string;
    label: string;
};

type PasangBatuEditProps = {
    formDocumentNo: string;
    craftsmanOptions: OptionItem[];
    stoneOptions: PasangBatuStoneOption[];
    shapeOptions: PasangBatuStoneOption[];
    form: {
        id: number;
        docNo: string | null;
        sendCraftsmanDate: string;
        receivedCraftsmanDate: string;
        craftsmanId: number | null;
        notes: string;
        weightFrame: string;
        weightDiamond: string;
        weightFinishGoods: string;
        settingStones?: Array<{
            stoneId: number;
            pcs: string;
            crt: string;
            notes: string;
        }>;
        returnStones?: Array<{
            stoneId: number;
            pcs: string;
            crt: string;
            notes: string;
        }>;
        diamonds?: Array<{
            kode: string;
            diamondType: string;
            shapeId: number | null;
            certificate: string;
            crt: string;
        }>;
        mountedStones?: Array<{
            diamondCode: string;
            shapeId: number | null;
            pcs: string;
            crt: string;
            size: string;
        }>;
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

function lineKey(prefix: string, index: number): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${index}-${Math.random().toString(36).slice(2, 9)}`;
}

export default function PasangBatuEdit({
    formDocumentNo,
    craftsmanOptions,
    stoneOptions,
    shapeOptions,
    form,
}: PasangBatuEditProps) {
    return (
        <>
            <Head
                title={`Edit Dokumen Pasang Batu · ${form.docNo ?? form.id}`}
            />
            <PasangBatuForm
                title="Form Edit Dokumen Pasang Batu"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={show.url(form.id)}
                submitUrl={update.url(form.id)}
                method="put"
                craftsmanOptions={craftsmanOptions}
                stoneOptions={stoneOptions}
                shapeOptions={shapeOptions}
                initialValues={{
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
                    setting_stones: (form.settingStones ?? []).map(
                        (line, index) => ({
                            key: lineKey('setting', index),
                            stone_id: String(line.stoneId),
                            pcs: line.pcs ?? '',
                            crt: line.crt ?? '',
                            notes: line.notes ?? '',
                        }),
                    ),
                    return_stones: (form.returnStones ?? []).map(
                        (line, index) => ({
                            key: lineKey('return', index),
                            stone_id: String(line.stoneId),
                            pcs: line.pcs ?? '',
                            crt: line.crt ?? '',
                            notes: line.notes ?? '',
                        }),
                    ),
                    diamonds: (form.diamonds ?? []).map((line, index) => ({
                        key: lineKey('diamond', index),
                        kode: line.kode ?? '',
                        diamond_type: line.diamondType ?? '',
                        shape_id:
                            line.shapeId !== null && line.shapeId !== undefined
                                ? String(line.shapeId)
                                : '',
                        certificate: line.certificate ?? '',
                        crt: line.crt ?? '',
                    })),
                    mounted_stones: (form.mountedStones ?? []).map(
                        (line, index) => ({
                            key: lineKey('mounted', index),
                            diamond_code: line.diamondCode ?? '',
                            shape_id:
                                line.shapeId !== null &&
                                line.shapeId !== undefined
                                    ? String(line.shapeId)
                                    : '',
                            pcs: line.pcs ?? '',
                            crt: line.crt ?? '',
                            size: line.size ?? '',
                        }),
                    ),
                }}
            />
        </>
    );
}

PasangBatuEdit.layout = {
    activeMenu: 'Pasang Batu',
    pageTitle: 'Edit Dokumen Pasang Batu',
};
