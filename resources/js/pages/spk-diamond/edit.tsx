import { Head } from '@inertiajs/react';
import { SpkDiamondForm, lineKey } from '@/components/spk-diamond/spk-diamond-form';
import type {
    PasangBatuDiamondOption,
    PasangBatuStoneOption,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import type { SpkStoneItem } from '@/components/spk/types';
import { show, update } from '@/routes/spk-diamond';

type SpkDiamondEditProps = {
    stoneOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    form: {
        id: number;
        noSpkDiamond: string;
        tanggal: string;
        keterangan: string;
        weightFrame: string;
        weightStone: string;
        spk: {
            spkId: number;
            spkNo: string;
            spkType: string | null;
            orderTypeLabel: string | null;
            skuCode: string | null;
            typeCode: string | null;
            productItemName: string | null;
            itemDescription: string | null;
            satuan: string;
            lastWeight: string | null;
        } | null;
        settingStones: Array<{
            stoneId: number;
            pcs: string;
            crt: string;
            notes: string;
        }>;
        diamonds: Array<{ diamondId: number }>;
    };
    skuStones: SpkStoneItem[];
};

export default function SpkDiamondEdit({
    stoneOptions,
    diamondOptions,
    form,
    skuStones,
}: SpkDiamondEditProps) {
    return (
        <>
            <Head title={`Edit SPK Diamond · ${form.noSpkDiamond}`} />
            <SpkDiamondForm
                title="Edit SPK Diamond"
                submitLabel="Simpan"
                cancelHref={show.url(form.id)}
                submitUrl={update.url(form.id)}
                method="put"
                stoneOptions={stoneOptions}
                diamondOptions={diamondOptions}
                initialSkuStones={skuStones}
                initialValues={{
                    no_spk_diamond: form.noSpkDiamond,
                    tanggal: form.tanggal,
                    keterangan: form.keterangan ?? '',
                    weight_frame: form.weightFrame,
                    weight_stone: form.weightStone,
                    spk_last_weight: form.spk?.lastWeight ?? '',
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
                    setting_stones: form.settingStones.map((line, index) => ({
                        key: lineKey(`setting-${index}`),
                        stone_id: String(line.stoneId),
                        pcs: line.pcs,
                        crt: line.crt,
                        notes: line.notes,
                    })),
                    diamonds: form.diamonds.map((line, index) => ({
                        key: lineKey(`diamond-${index}`),
                        diamond_id: String(line.diamondId),
                    })),
                }}
            />
        </>
    );
}

SpkDiamondEdit.layout = {
    activeMenu: 'SPK Diamond',
    pageTitle: 'Edit SPK Diamond',
};
