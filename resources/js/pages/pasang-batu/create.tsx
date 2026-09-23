import { Head } from '@inertiajs/react';
import { PasangBatuForm } from '@/components/pasang-batu/pasang-batu-form';
import type { PasangBatuStoneOption } from '@/components/pasang-batu/pasang-batu-stone-editor';
import { index, store } from '@/routes/pasang-batu';

type OptionItem = {
    value: string;
    label: string;
};

type PasangBatuCreateProps = {
    formDocumentNo: string;
    craftsmanOptions: OptionItem[];
    stoneOptions: PasangBatuStoneOption[];
    shapeOptions: PasangBatuStoneOption[];
    form: {
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
        spk: null;
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
    craftsmanOptions,
    stoneOptions,
    shapeOptions,
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
                    spk: null,
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

PasangBatuCreate.layout = {
    activeMenu: 'Pasang Batu',
    pageTitle: 'Tambah Dokumen Pasang Batu',
};
