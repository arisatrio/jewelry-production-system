import { Head } from '@inertiajs/react';
import {
    PasangBatuForm,
    emptyPasangBatuSpk,
} from '@/components/pasang-batu/pasang-batu-form';
import type {
    PasangBatuDiamondOption,
    PasangBatuStoneOption,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import { show, update } from '@/routes/pasang-batu';

type OptionItem = {
    value: string;
    label: string;
};

type PasangBatuEditProps = {
    formDocumentNo: string;
    processOptions: OptionItem[];
    craftsmanOptions: OptionItem[];
    stoneOptions: PasangBatuStoneOption[];
    shapeOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    form: {
        id: number;
        docNo: string | null;
        processName: string;
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
            diamondId: number;
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
        spkDiamond?: {
            id: number;
            docNo: string;
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
    processOptions,
    craftsmanOptions,
    stoneOptions,
    shapeOptions,
    diamondOptions,
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
                processOptions={processOptions}
                craftsmanOptions={craftsmanOptions}
                stoneOptions={stoneOptions}
                shapeOptions={shapeOptions}
                diamondOptions={diamondOptions}
                allowMountingId={form.id}
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
                    spk_diamond_id:
                        form.spkDiamond !== null &&
                        form.spkDiamond !== undefined
                            ? String(form.spkDiamond.id)
                            : '',
                    spk_diamond_doc_no: form.spkDiamond?.docNo ?? '',
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
                        : emptyPasangBatuSpk(),
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
                    ...(() => {
                        const diamondRows = form.diamonds ?? [];
                        const mountedRows = form.mountedStones ?? [];
                        const count = Math.max(
                            diamondRows.length,
                            mountedRows.length,
                        );

                        const diamonds: Array<{
                            key: string;
                            batch_key: string;
                            diamond_id: string;
                        }> = [];
                        const mounted_stones: Array<{
                            key: string;
                            batch_key: string;
                            diamond_code: string;
                            shape_id: string;
                            pcs: string;
                            crt: string;
                            size: string;
                            disposition: 'terpasang';
                        }> = [];

                        for (let index = 0; index < count; index += 1) {
                            const batchKey = lineKey('batch', index);
                            const diamond = diamondRows[index];
                            const mounted = mountedRows[index];

                            if (diamond) {
                                diamonds.push({
                                    key: lineKey('diamond', index),
                                    batch_key: batchKey,
                                    diamond_id: String(diamond.diamondId),
                                });
                            }

                            if (mounted) {
                                mounted_stones.push({
                                    key: lineKey('mounted', index),
                                    batch_key: batchKey,
                                    diamond_code: mounted.diamondCode ?? '',
                                    shape_id:
                                        mounted.shapeId !== null &&
                                        mounted.shapeId !== undefined
                                            ? String(mounted.shapeId)
                                            : '',
                                    pcs: mounted.pcs ?? '',
                                    crt: mounted.crt ?? '',
                                    size: mounted.size ?? '',
                                    disposition: 'terpasang',
                                });
                            }
                        }

                        return { diamonds, mounted_stones };
                    })(),
                }}
            />
        </>
    );
}

PasangBatuEdit.layout = {
    activeMenu: 'Pasang Batu',
    pageTitle: 'Edit Dokumen Pasang Batu',
};
