import { Head } from '@inertiajs/react';
import {
    SpkDiamondForm,
    emptyDiamondLine,
    emptySettingLine,
    lineKey,
} from '@/components/spk-diamond/spk-diamond-form';
import type {
    PasangBatuDiamondOption,
    PasangBatuStoneOption,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import { index, store } from '@/routes/spk-diamond';

type SpkDiamondCreateProps = {
    stoneOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    form: {
        noSpkDiamond: string;
        tanggal: string;
        keterangan: string;
        weightFrame: string;
        weightStone: string;
    };
};

export default function SpkDiamondCreate({
    stoneOptions,
    diamondOptions,
    form,
}: SpkDiamondCreateProps) {
    return (
        <>
            <Head title="Tambah SPK Diamond" />
            <SpkDiamondForm
                title="Form SPK Diamond"
                submitLabel="Simpan Draft"
                cancelHref={index.url()}
                submitUrl={store.url()}
                stoneOptions={stoneOptions}
                diamondOptions={diamondOptions}
                initialValues={{
                    no_spk_diamond: form.noSpkDiamond,
                    tanggal: form.tanggal,
                    keterangan: form.keterangan,
                    weight_frame: form.weightFrame,
                    weight_stone: form.weightStone,
                    spk_last_weight: '',
                    spk: null,
                    setting_stones: [emptySettingLine()],
                    diamonds: [],
                }}
            />
        </>
    );
}

SpkDiamondCreate.layout = {
    activeMenu: 'SPK Diamond',
    pageTitle: 'Tambah SPK Diamond',
};
