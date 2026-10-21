import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import saveIcon from '@ui5/webcomponents-icons/dist/save.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { DatePicker } from '@ui5/webcomponents-react/DatePicker';
import { Form } from '@ui5/webcomponents-react/Form';
import { FormGroup } from '@ui5/webcomponents-react/FormGroup';
import { FormItem } from '@ui5/webcomponents-react/FormItem';
import { Input } from '@ui5/webcomponents-react/Input';
import { Label } from '@ui5/webcomponents-react/Label';
import { Text } from '@ui5/webcomponents-react/Text';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { TextArea } from '@ui5/webcomponents-react/TextArea';
import { FinishingSelectSpkDialog } from '@/components/finishing/finishing-select-spk-dialog';
import type { FinishingSelectedSpk } from '@/components/finishing/finishing-select-spk-dialog';
import {
    PasangBatuStoneEditor,
    type DiamondLine,
    type PasangBatuDiamondOption,
    type PasangBatuStoneOption,
    type SettingStoneLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';
import { SpkStoneListCard } from '@/components/spk/spk-stone-list';
import type { SpkStoneItem } from '@/components/spk/types';
import {
    allocation as fetchSpkAllocation,
    spkStones as fetchSpkSkuStones,
} from '@/routes/spk-diamond/select';

type SpkDiamondSpkForm = {
    spk_id: string;
    spk_no: string;
    spk_type: string;
    order_type_label: string;
    type_code: string;
    product_item_name: string;
    sku_code: string;
    item_description: string;
    satuan: string;
};

type SpkDiamondFormProps = {
    title: string;
    submitLabel: string;
    cancelHref: string;
    submitUrl: string;
    method?: 'post' | 'put';
    stoneOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    initialSkuStones?: SpkStoneItem[];
    initialValues: {
        no_spk_diamond: string;
        tanggal: string;
        keterangan: string;
        weight_frame: string;
        weight_stone: string;
        spk_last_weight: string;
        spk: SpkDiamondSpkForm | null;
        setting_stones: SettingStoneLine[];
        diamonds: DiamondLine[];
    };
};

function emptySpk(): SpkDiamondSpkForm {
    return {
        spk_id: '',
        spk_no: '',
        spk_type: '',
        order_type_label: '',
        type_code: '',
        product_item_name: '',
        sku_code: '',
        item_description: '',
        satuan: '',
    };
}

function selectedSpkItemSummary(spk: SpkDiamondSpkForm | null): string {
    if (spk === null) {
        return '—';
    }

    const typeProduct = [spk.type_code, spk.product_item_name]
        .map((value) => value.trim())
        .filter(Boolean)
        .join(' | ');
    const sku = spk.sku_code.trim();
    const parts = [typeProduct, sku].filter(Boolean);

    return parts.length > 0 ? parts.join(' · ') : '—';
}

type SpkAllocationPayload = {
    setting_stones: Array<{
        stone_id: number;
        pcs: string | null;
        crt: string | null;
        notes: string | null;
    }>;
    diamonds: Array<{ diamond_id: number }>;
};

async function loadAllocationForSpk(
    spkId: string,
): Promise<SpkAllocationPayload | null> {
    if (spkId.trim() === '') {
        return null;
    }

    const response = await fetch(
        fetchSpkAllocation.url({ query: { spk_id: spkId } }),
        {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        },
    );

    if (!response.ok) {
        return null;
    }

    const payload = (await response.json()) as {
        data?: SpkAllocationPayload;
    };

    return payload.data ?? null;
}

async function loadSkuStonesForSpk(spkId: string): Promise<SpkStoneItem[]> {
    if (spkId.trim() === '') {
        return [];
    }

    const response = await fetch(
        fetchSpkSkuStones.url({ query: { spk_id: spkId } }),
        {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        },
    );

    if (!response.ok) {
        return [];
    }

    const payload = (await response.json()) as { data?: SpkStoneItem[] };

    return Array.isArray(payload.data) ? payload.data : [];
}

function lineKey(prefix: string): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${Math.random().toString(36).slice(2, 9)}`;
}

function emptySettingLine(): SettingStoneLine {
    return { key: lineKey('setting'), stone_id: '', pcs: '', crt: '', notes: '' };
}

function emptyDiamondLine(): DiamondLine {
    return { key: lineKey('diamond'), diamond_id: '' };
}

export function SpkDiamondForm({
    title,
    submitLabel,
    cancelHref,
    submitUrl,
    method = 'post',
    stoneOptions,
    diamondOptions,
    initialSkuStones = [],
    initialValues,
}: SpkDiamondFormProps) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm(initialValues);
    const [spkDialogOpen, setSpkDialogOpen] = useState(false);
    const [skuStones, setSkuStones] = useState<SpkStoneItem[]>(initialSkuStones);
    const hasSelectedSpk =
        data.spk !== null && data.spk.spk_id.trim() !== '';

    const handleSelectedSpk = (selected: FinishingSelectedSpk) => {
        const { lastWeight, ...spk } = selected;

        void Promise.all([
            loadSkuStonesForSpk(spk.spk_id),
            loadAllocationForSpk(spk.spk_id),
        ]).then(([stones, allocation]) => {
            setSkuStones(stones);

            const settingStones =
                allocation !== null && allocation.setting_stones.length > 0
                    ? allocation.setting_stones.map((line) => ({
                          key: lineKey('setting'),
                          stone_id: String(line.stone_id),
                          pcs: line.pcs ?? '',
                          crt: line.crt ?? '',
                          notes: line.notes ?? '',
                      }))
                    : data.setting_stones;

            const diamonds =
                allocation !== null && allocation.diamonds.length > 0
                    ? allocation.diamonds.map((line) => ({
                          key: lineKey('diamond'),
                          diamond_id: String(line.diamond_id),
                      }))
                    : data.diamonds;

            setData({
                ...data,
                spk,
                spk_last_weight: lastWeight?.trim() ?? '',
                setting_stones: settingStones,
                diamonds,
            });
        });
    };

    const weightTotal = useMemo(() => {
        const frame = Number(data.weight_frame.replace(',', '.'));
        const stone = Number(data.weight_stone.replace(',', '.'));

        if (!Number.isFinite(frame) && !Number.isFinite(stone)) {
            return '';
        }

        const total =
            (Number.isFinite(frame) ? frame : 0) +
            (Number.isFinite(stone) ? stone : 0);

        return total.toFixed(3);
    }, [data.weight_frame, data.weight_stone]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        transform((formData) => ({
            no_spk_diamond: formData.no_spk_diamond.trim(),
            tanggal: formData.tanggal,
            keterangan:
                formData.keterangan.trim() !== ''
                    ? formData.keterangan.trim()
                    : null,
            spk_id:
                formData.spk?.spk_id && formData.spk.spk_id.trim() !== ''
                    ? Number(formData.spk.spk_id)
                    : null,
            weight_frame: formData.weight_frame.trim().replace(',', '.'),
            weight_stone: formData.weight_stone.trim().replace(',', '.'),
            setting_stones: formData.setting_stones
                .filter((line) => line.stone_id.trim() !== '')
                .map((line) => ({
                    stone_id: Number(line.stone_id),
                    pcs:
                        line.pcs.trim() !== ''
                            ? String(
                                  Math.round(
                                      Number(
                                          line.pcs.trim().replace(',', '.'),
                                      ),
                                  ),
                              )
                            : null,
                    crt: line.crt.trim() !== '' ? line.crt.replace(',', '.') : null,
                    notes: line.notes.trim() !== '' ? line.notes.trim() : null,
                })),
            diamonds: formData.diamonds
                .filter((line) => line.diamond_id.trim() !== '')
                .map((line) => ({ diamond_id: Number(line.diamond_id) })),
        }));

        const options = { preserveScroll: true };

        if (method === 'put') {
            put(submitUrl, options);
        } else {
            post(submitUrl, options);
        }
    };

    return (
        <>
        <form onSubmit={submit} className="spkFioriForm">
            <section className="spkFioriFormCard">
                <div className="spkFioriFormCardHeader">
                    <h2 className="spkFioriFormCardTitle">{title}</h2>
                    <div className="spkFioriFormCardActions">
                        <Button
                            design="Default"
                            icon={declineIcon}
                            onClick={() => router.visit(cancelHref)}
                        >
                            Batal
                        </Button>
                        <Button
                            design="Attention"
                            icon={saveIcon}
                            type="Submit"
                            disabled={processing}
                        >
                            {submitLabel}
                        </Button>
                    </div>
                </div>

                <Form layout="S1 M2 L2 XL2" labelSpan="S12 M4 L4 XL4">
                    <FormGroup headerText="Informasi SPK Diamond" columnSpan={2}>
                        <FormItem labelContent={<Label showColon required>No SPK Diamond</Label>}>
                            <Input
                                value={data.no_spk_diamond}
                                onInput={(e) =>
                                    setData('no_spk_diamond', e.target.value ?? '')
                                }
                            />
                            {errors.no_spk_diamond ? (
                                <Text className="spkFioriError">{errors.no_spk_diamond}</Text>
                            ) : null}
                        </FormItem>
                        <FormItem labelContent={<Label showColon required>Tanggal</Label>}>
                            <DatePicker
                                value={data.tanggal}
                                onChange={(e) =>
                                    setData('tanggal', e.target.value ?? '')
                                }
                            />
                        </FormItem>
                        <FormItem
                            labelContent={
                                <Label showColon required>
                                    SPK Produksi
                                </Label>
                            }
                        >
                            <div className="spkFioriFieldStack">
                                <div className="spkCreateSelectorRow">
                                    <div className="spkCreateSelectorValue">
                                        {hasSelectedSpk ? (
                                            <>
                                                <strong>
                                                    {data.spk?.spk_no || '—'}
                                                </strong>
                                                <span>
                                                    {selectedSpkItemSummary(
                                                        data.spk,
                                                    )}
                                                </span>
                                            </>
                                        ) : (
                                            <span>Belum ada SPK dipilih</span>
                                        )}
                                    </div>
                                    <Button
                                        design="Emphasized"
                                        type="Button"
                                        disabled={processing}
                                        onClick={() => setSpkDialogOpen(true)}
                                    >
                                        {hasSelectedSpk ? 'Ganti' : 'Pilih'}
                                    </Button>
                                    {hasSelectedSpk ? (
                                        <Button
                                            design="Transparent"
                                            type="Button"
                                            disabled={processing}
                                            onClick={() => {
                                                setData({
                                                    ...data,
                                                    spk: emptySpk(),
                                                    spk_last_weight: '',
                                                });
                                                setSkuStones([]);
                                            }}
                                        >
                                            Hapus
                                        </Button>
                                    ) : null}
                                </div>
                                {errors.spk_id ? (
                                    <Text className="spkFioriError">
                                        {errors.spk_id}
                                    </Text>
                                ) : null}
                            </div>
                        </FormItem>
                        {hasSelectedSpk ? (
                            <>
                                <FormItem
                                    labelContent={
                                        <Label showColon>Tipe</Label>
                                    }
                                >
                                    <SpkOrderTypeColumn
                                        spkType={data.spk?.spk_type || null}
                                        orderTypeLabel={
                                            data.spk?.order_type_label || null
                                        }
                                    />
                                </FormItem>
                                <FormItem
                                    labelContent={
                                        <Label showColon>SKU / Item</Label>
                                    }
                                >
                                    <SpkItemSkuColumn
                                        skuCode={data.spk?.sku_code || null}
                                        typeCode={data.spk?.type_code || null}
                                        productItemName={
                                            data.spk?.product_item_name || null
                                        }
                                        itemDescription={
                                            data.spk?.item_description || null
                                        }
                                    />
                                </FormItem>
                                <FormItem
                                    labelContent={<Label showColon>Qty</Label>}
                                >
                                    <Input
                                        value={data.spk?.satuan || ''}
                                        readonly
                                    />
                                </FormItem>
                            </>
                        ) : null}
                        {hasSelectedSpk ? (
                            <FormItem
                                labelContent={
                                    <Label showColon>
                                        Berat Rangka Terakhir
                                    </Label>
                                }
                            >
                                <Input
                                    value={data.spk_last_weight}
                                    placeholder="—"
                                    readonly
                                    disabled
                                />
                            </FormItem>
                        ) : null}
                        <FormItem
                            labelContent={
                                <Label showColon required>
                                    Berat rangka (gr)
                                </Label>
                            }
                        >
                            <Input
                                value={data.weight_frame}
                                onInput={(e) =>
                                    setData('weight_frame', e.target.value ?? '')
                                }
                            />
                            {errors.weight_frame ? (
                                <Text className="spkFioriError">
                                    {errors.weight_frame}
                                </Text>
                            ) : null}
                        </FormItem>
                        <FormItem
                            labelContent={
                                <Label showColon required>
                                    Berat batu (ct)
                                </Label>
                            }
                        >
                            <Input
                                value={data.weight_stone}
                                onInput={(e) =>
                                    setData('weight_stone', e.target.value ?? '')
                                }
                            />
                            {errors.weight_stone ? (
                                <Text className="spkFioriError">
                                    {errors.weight_stone}
                                </Text>
                            ) : null}
                        </FormItem>
                        <FormItem labelContent={<Label showColon>Berat rangka + batu</Label>}>
                            <Input value={weightTotal} readonly />
                        </FormItem>
                        <FormItem labelContent={<Label showColon>Keterangan</Label>}>
                            <TextArea
                                value={data.keterangan}
                                rows={3}
                                onInput={(e) =>
                                    setData('keterangan', e.target.value ?? '')
                                }
                            />
                        </FormItem>
                    </FormGroup>
                </Form>

                {!hasSelectedSpk ? (
                    <div className="spkItemStoneSection">
                        <div className="spkStoneCardHeader">
                            <h2 className="spkFioriDetailBlockTitle">
                                Spesifikasi Batu SKU
                            </h2>
                        </div>
                        <div className="spkStoneEmpty">
                            <Text>
                                Pilih SPK produksi untuk menampilkan daftar
                                batu.
                            </Text>
                        </div>
                    </div>
                ) : (
                    <SpkStoneListCard
                        stones={skuStones}
                        title="Spesifikasi Batu SKU"
                    />
                )}

                {hasSelectedSpk ? (
                    <div className="px-1.5 pt-2">
                        <MessageStrip design="Information" hideCloseButton>
                            Batu setting dan dossier pada bagian{' '}
                            <strong>Setting awal</strong> diambil dari menu{' '}
                            <strong>Alokasi Batu</strong> untuk SPK produksi
                            yang dipilih. Saat SPK dipilih, daftar akan terisi
                            otomatis sesuai alokasi; jumlah dan pilihan batu
                            tidak boleh melebihi cadangan alokasi.
                        </MessageStrip>
                    </div>
                ) : null}

                <PasangBatuStoneEditor
                    mode="spk-diamond"
                    blockTitle="Setting awal"
                    stoneOptions={stoneOptions}
                    shapeOptions={[]}
                    diamondOptions={diamondOptions}
                    settingStones={data.setting_stones}
                    returnStones={[]}
                    diamonds={data.diamonds}
                    mountedStones={[]}
                    errors={errors}
                    onSettingChange={(lines) => setData('setting_stones', lines)}
                    onReturnChange={() => {}}
                    onDiamondsChange={(lines) => setData('diamonds', lines)}
                    onMountedChange={() => {}}
                />
            </section>
        </form>

            <FinishingSelectSpkDialog
                open={spkDialogOpen}
                onOpenChange={setSpkDialogOpen}
                excludeSpkIds={
                    data.spk?.spk_id
                        ? [Number(data.spk.spk_id)].filter((id) => id > 0)
                        : []
                }
                onSelected={handleSelectedSpk}
            />
        </>
    );
}

export { emptySettingLine, emptyDiamondLine, lineKey };
