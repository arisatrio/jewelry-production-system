import { Head } from '@inertiajs/react';
import {
    PasangBatuForm,
    emptyPasangBatuSpk,
} from '@/components/pasang-batu/pasang-batu-form';
import type {
    PasangBatuDiamondOption,
    PasangBatuStoneOption,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import { index, store } from '@/routes/pasang-batu';

type OptionItem = {
    value: string;
    label: string;
};

type PasangBatuCreateProps = {
    formDocumentNo: string;
    processOptions: OptionItem[];
    craftsmanOptions: OptionItem[];
    stoneOptions: PasangBatuStoneOption[];
    shapeOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    form: {
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
        spkDiamond?: null;
    };
};

function lineKey(prefix: string, index: number): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${index}-${Math.random().toString(36).slice(2, 9)}`;
}

export default function PasangBatuCreate({
    formDocumentNo,
    processOptions,
    craftsmanOptions,
    stoneOptions,
    shapeOptions,
    diamondOptions,
    form,
}: PasangBatuCreateProps) {
    return (
        <>
            <Head title="Tambah Dokumen Pasang Batu" />
            <PasangBatuForm
                title="Form Dokumen Pasang Batu"
                formDocumentNo={formDocumentNo}
                submitLabel="Simpan"
                cancelHref={index.url()}
                submitUrl={store.url()}
                processOptions={processOptions}
                craftsmanOptions={craftsmanOptions}
                stoneOptions={stoneOptions}
                shapeOptions={shapeOptions}
                diamondOptions={diamondOptions}
                allowManualSpkSelection={false}
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
                    spk_diamond_id: '',
                    spk_diamond_doc_no: '',
                    spk: emptyPasangBatuSpk(),
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

PasangBatuCreate.layout = {
    activeMenu: 'Pasang Batu',
    pageTitle: 'Tambah Dokumen Pasang Batu',
};
