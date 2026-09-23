import type { FormEvent } from 'react';
import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import saveIcon from '@ui5/webcomponents-icons/dist/save.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { DateTimePicker } from '@ui5/webcomponents-react/DateTimePicker';
import { Form } from '@ui5/webcomponents-react/Form';
import { FormGroup } from '@ui5/webcomponents-react/FormGroup';
import { FormItem } from '@ui5/webcomponents-react/FormItem';
import { Input } from '@ui5/webcomponents-react/Input';
import { Label } from '@ui5/webcomponents-react/Label';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { Text } from '@ui5/webcomponents-react/Text';
import { TextArea } from '@ui5/webcomponents-react/TextArea';
import {
    PasangBatuSelectSpkDialog,
    type PasangBatuSelectedSpk,
} from '@/components/pasang-batu/pasang-batu-select-spk-dialog';
import {
    PasangBatuStoneEditor,
    type DiamondLine,
    type MountedStoneLine,
    type PasangBatuStoneOption,
    type SettingStoneLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';

type OptionItem = {
    value: string;
    label: string;
};

type PasangBatuSpkForm = {
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

type PasangBatuFormValues = {
    craftsman_id: string;
    send_craftsman_date: string;
    received_craftsman_date: string;
    notes: string;
    weight_frame: string;
    weight_diamond: string;
    weight_finish_goods: string;
    spk: PasangBatuSpkForm | null;
    setting_stones: SettingStoneLine[];
    return_stones: SettingStoneLine[];
    diamonds: DiamondLine[];
    mounted_stones: MountedStoneLine[];
};

type PasangBatuFormProps = {
    title: string;
    formDocumentNo: string;
    submitLabel: string;
    cancelHref: string;
    submitUrl: string;
    method?: 'post' | 'put';
    craftsmanOptions: OptionItem[];
    stoneOptions: PasangBatuStoneOption[];
    shapeOptions: PasangBatuStoneOption[];
    initialValues: PasangBatuFormValues;
};

function normalizeNullableNumber(value: string): string | null {
    const trimmed = value.trim().replace(',', '.');

    return trimmed !== '' ? trimmed : null;
}

function normalizeOptionalId(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function parseWeight(value: string): number | null {
    const trimmed = value.trim().replace(',', '.');

    if (trimmed === '') {
        return null;
    }

    const parsed = Number.parseFloat(trimmed);

    return Number.isFinite(parsed) ? parsed : null;
}

function normalizeWeightForSubmit(value: string): string | null {
    const trimmed = value.trim().replace(',', '.');

    return trimmed !== '' ? trimmed : null;
}

function computeShrink(
    weightFrame: string,
    weightDiamond: string,
    weightFinishGoods: string,
): string | null {
    const frame = parseWeight(weightFrame);
    const diamond = parseWeight(weightDiamond) ?? 0;
    const finish = parseWeight(weightFinishGoods);

    if (frame === null || finish === null) {
        return null;
    }

    return (frame + diamond - finish).toFixed(2);
}

function emptySpk(): PasangBatuSpkForm {
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

function selectedSpkItemSummary(spk: PasangBatuSpkForm | null): string {
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

export function PasangBatuForm({
    title,
    formDocumentNo,
    submitLabel,
    cancelHref,
    submitUrl,
    method = 'post',
    craftsmanOptions,
    stoneOptions,
    shapeOptions,
    initialValues,
}: PasangBatuFormProps) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<PasangBatuFormValues>(initialValues);
    const [spkDialogOpen, setSpkDialogOpen] = useState(false);
    const hasSelectedSpk =
        data.spk !== null && data.spk.spk_id.trim() !== '';
    const shrinkPreview = computeShrink(
        data.weight_frame,
        data.weight_diamond,
        data.weight_finish_goods,
    );

    const handleSelectedSpk = (selected: PasangBatuSelectedSpk) => {
        const { lastWeight, ...spk } = selected;

        setData({
            ...data,
            spk,
            weight_frame: lastWeight?.trim() ?? '',
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        transform((formData) => ({
            spk_id:
                formData.spk?.spk_id && formData.spk.spk_id.trim() !== ''
                    ? Number(formData.spk.spk_id)
                    : null,
            craftsman_id:
                formData.craftsman_id.trim() !== ''
                    ? formData.craftsman_id
                    : null,
            send_craftsman_date:
                formData.send_craftsman_date.trim() !== ''
                    ? formData.send_craftsman_date
                    : null,
            received_craftsman_date:
                formData.received_craftsman_date.trim() !== ''
                    ? formData.received_craftsman_date
                    : null,
            notes:
                formData.notes.trim() !== '' ? formData.notes.trim() : null,
            weight_frame: normalizeWeightForSubmit(formData.weight_frame),
            weight_diamond: normalizeWeightForSubmit(formData.weight_diamond),
            weight_finish_goods: normalizeWeightForSubmit(
                formData.weight_finish_goods,
            ),
            setting_stones: formData.setting_stones
                .filter((line) => line.stone_id.trim() !== '')
                .map((line) => ({
                    stone_id: Number(line.stone_id),
                    pcs: normalizeNullableNumber(line.pcs),
                    crt: normalizeNullableNumber(line.crt),
                    notes:
                        line.notes.trim() !== '' ? line.notes.trim() : null,
                })),
            return_stones: formData.return_stones
                .filter((line) => line.stone_id.trim() !== '')
                .map((line) => ({
                    stone_id: Number(line.stone_id),
                    pcs: normalizeNullableNumber(line.pcs),
                    crt: normalizeNullableNumber(line.crt),
                    notes:
                        line.notes.trim() !== '' ? line.notes.trim() : null,
                })),
            diamonds: formData.diamonds
                .filter(
                    (line) =>
                        line.kode.trim() !== '' ||
                        line.diamond_type.trim() !== '' ||
                        line.shape_id.trim() !== '' ||
                        line.certificate.trim() !== '' ||
                        line.crt.trim() !== '',
                )
                .map((line) => ({
                    kode: line.kode.trim() !== '' ? line.kode.trim() : null,
                    diamond_type:
                        line.diamond_type.trim() !== ''
                            ? line.diamond_type.trim()
                            : null,
                    shape_id: normalizeOptionalId(line.shape_id),
                    certificate:
                        line.certificate.trim() !== ''
                            ? line.certificate.trim()
                            : null,
                    crt: normalizeNullableNumber(line.crt),
                })),
            mounted_stones: formData.mounted_stones
                .filter(
                    (line) =>
                        line.diamond_code.trim() !== '' ||
                        line.shape_id.trim() !== '' ||
                        line.pcs.trim() !== '' ||
                        line.crt.trim() !== '' ||
                        line.size.trim() !== '',
                )
                .map((line) => ({
                    diamond_code:
                        line.diamond_code.trim() !== ''
                            ? line.diamond_code.trim()
                            : null,
                    shape_id: normalizeOptionalId(line.shape_id),
                    pcs: normalizeNullableNumber(line.pcs),
                    crt: normalizeNullableNumber(line.crt),
                    size:
                        line.size.trim() !== '' ? line.size.trim() : null,
                })),
        }));

        const options = {
            preserveScroll: true,
        };

        if (method === 'put') {
            put(submitUrl, options);
        } else {
            post(submitUrl, options);
        }
    };

    return (
        <>
            <div className="spkDetailShell">
                <div className="spkDetailStack">
                    <form
                        id="pasang-batu-form"
                        onSubmit={submit}
                        className="spkFioriForm"
                    >
                        <section className="spkFioriFormCard">
                            <div className="spkFioriFormCardHeader">
                                <div className="spkFioriFormCardHeaderMain">
                                    <h2 className="spkFioriFormCardTitle">
                                        {title}
                                    </h2>
                                    <p className="spkFioriFormCardSubtitle">
                                        No. Form Dokumen: {formDocumentNo}
                                    </p>
                                </div>
                                <div className="spkFioriFormCardActions">
                                    <Button
                                        design="Default"
                                        className="spkFioriFormCardBtnGrey"
                                        icon={declineIcon}
                                        disabled={processing}
                                        onClick={() =>
                                            router.visit(cancelHref)
                                        }
                                    >
                                        Batal
                                    </Button>
                                    <Button
                                        design="Attention"
                                        className="spkFioriFormCardBtnYellow"
                                        icon={saveIcon}
                                        type="Submit"
                                        disabled={processing}
                                    >
                                        {processing
                                            ? 'Menyimpan...'
                                            : submitLabel}
                                    </Button>
                                </div>
                            </div>

                            <Form
                                accessibleMode="Edit"
                                layout="S1 M2 L2 XL2"
                                labelSpan="S12 M4 L4 XL4"
                                itemSpacing="Normal"
                            >
                                <FormGroup
                                    headerText="Serah ke Pengrajin"
                                    columnSpan={2}
                                >
                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                No. SPK
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <div className="spkCreateSelectorRow">
                                                <div className="spkCreateSelectorValue">
                                                    {hasSelectedSpk ? (
                                                        <>
                                                            <strong>
                                                                {data.spk
                                                                    ?.spk_no ||
                                                                    '—'}
                                                            </strong>
                                                            <span>
                                                                {selectedSpkItemSummary(
                                                                    data.spk,
                                                                )}
                                                            </span>
                                                        </>
                                                    ) : (
                                                        <span>
                                                            Belum ada SPK
                                                            dipilih
                                                        </span>
                                                    )}
                                                </div>
                                                <Button
                                                    design="Emphasized"
                                                    type="Button"
                                                    disabled={processing}
                                                    onClick={() =>
                                                        setSpkDialogOpen(true)
                                                    }
                                                >
                                                    {hasSelectedSpk
                                                        ? 'Ganti'
                                                        : 'Pilih'}
                                                </Button>
                                                {hasSelectedSpk ? (
                                                    <Button
                                                        design="Transparent"
                                                        type="Button"
                                                        disabled={processing}
                                                        onClick={() =>
                                                            setData({
                                                                ...data,
                                                                spk: emptySpk(),
                                                                weight_frame:
                                                                    '',
                                                            })
                                                        }
                                                    >
                                                        Hapus
                                                    </Button>
                                                ) : null}
                                            </div>
                                            {'spk_id' in errors &&
                                            errors.spk_id ? (
                                                <Text className="spkFioriError">
                                                    {String(errors.spk_id)}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    {hasSelectedSpk ? (
                                        <>
                                            <FormItem
                                                labelContent={
                                                    <Label showColon>
                                                        Tipe
                                                    </Label>
                                                }
                                            >
                                                <SpkOrderTypeColumn
                                                    spkType={
                                                        data.spk?.spk_type ||
                                                        null
                                                    }
                                                    orderTypeLabel={
                                                        data.spk
                                                            ?.order_type_label ||
                                                        null
                                                    }
                                                />
                                            </FormItem>

                                            <FormItem
                                                labelContent={
                                                    <Label showColon>
                                                        SKU / Item
                                                    </Label>
                                                }
                                            >
                                                <SpkItemSkuColumn
                                                    skuCode={
                                                        data.spk?.sku_code ||
                                                        null
                                                    }
                                                    typeCode={
                                                        data.spk?.type_code ||
                                                        null
                                                    }
                                                    productItemName={
                                                        data.spk
                                                            ?.product_item_name ||
                                                        null
                                                    }
                                                    itemDescription={
                                                        data.spk
                                                            ?.item_description ||
                                                        null
                                                    }
                                                />
                                            </FormItem>

                                            <FormItem
                                                labelContent={
                                                    <Label showColon>
                                                        Qty
                                                    </Label>
                                                }
                                            >
                                                <Input
                                                    value={
                                                        data.spk?.satuan || ''
                                                    }
                                                    readonly
                                                />
                                            </FormItem>
                                        </>
                                    ) : null}

                                    <FormItem
                                        labelContent={
                                            <Label showColon>
                                                Berat Rangka (g)
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <Input
                                                type="Number"
                                                accessibleName="Berat Rangka pasang batu"
                                                value={data.weight_frame}
                                                valueState={fieldState(
                                                    errors.weight_frame,
                                                )}
                                                onInput={(event) =>
                                                    setData(
                                                        'weight_frame',
                                                        event.target.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            {errors.weight_frame ? (
                                                <Text className="spkFioriError">
                                                    {errors.weight_frame}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon>
                                                Berat Batu (g)
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <Input
                                                type="Number"
                                                accessibleName="Berat Batu pasang batu"
                                                value={data.weight_diamond}
                                                valueState={fieldState(
                                                    errors.weight_diamond,
                                                )}
                                                onInput={(event) =>
                                                    setData(
                                                        'weight_diamond',
                                                        event.target.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            {errors.weight_diamond ? (
                                                <Text className="spkFioriError">
                                                    {errors.weight_diamond}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon>Pengrajin</Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <Select
                                                accessibleName="Pengrajin pasang batu"
                                                valueState={fieldState(
                                                    errors.craftsman_id,
                                                )}
                                                onChange={(event) =>
                                                    setData(
                                                        'craftsman_id',
                                                        event.detail
                                                            .selectedOption
                                                            .value ?? '',
                                                    )
                                                }
                                            >
                                                <Option
                                                    value=""
                                                    selected={
                                                        data.craftsman_id ===
                                                        ''
                                                    }
                                                >
                                                    —
                                                </Option>
                                                {craftsmanOptions.map(
                                                    (option) => (
                                                        <Option
                                                            key={option.value}
                                                            value={option.value}
                                                            selected={
                                                                data.craftsman_id ===
                                                                option.value
                                                            }
                                                        >
                                                            {option.label}
                                                        </Option>
                                                    ),
                                                )}
                                            </Select>
                                            {errors.craftsman_id ? (
                                                <Text className="spkFioriError">
                                                    {errors.craftsman_id}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon>
                                                Tanggal serah pengrajin
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <DateTimePicker
                                                value={
                                                    data.send_craftsman_date
                                                }
                                                valueFormat="yyyy-MM-dd HH:mm"
                                                displayFormat="dd/MM/yyyy HH:mm"
                                                valueState={fieldState(
                                                    errors.send_craftsman_date,
                                                )}
                                                onChange={(event) =>
                                                    setData(
                                                        'send_craftsman_date',
                                                        event.detail.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            {errors.send_craftsman_date ? (
                                                <Text className="spkFioriError">
                                                    {
                                                        errors.send_craftsman_date
                                                    }
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon>Catatan</Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <TextArea
                                                accessibleName="Catatan pasang batu"
                                                value={data.notes}
                                                rows={3}
                                                valueState={fieldState(
                                                    errors.notes,
                                                )}
                                                onInput={(event) =>
                                                    setData(
                                                        'notes',
                                                        event.target.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            {errors.notes ? (
                                                <Text className="spkFioriError">
                                                    {errors.notes}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>
                                </FormGroup>

                                <FormGroup
                                    headerText="Terima dari Pengrajin"
                                    columnSpan={2}
                                >
                                    <FormItem
                                        labelContent={
                                            <Label showColon>
                                                Tanggal terima pengrajin
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <DateTimePicker
                                                value={
                                                    data.received_craftsman_date
                                                }
                                                valueFormat="yyyy-MM-dd HH:mm"
                                                displayFormat="dd/MM/yyyy HH:mm"
                                                valueState={fieldState(
                                                    errors.received_craftsman_date,
                                                )}
                                                onChange={(event) =>
                                                    setData(
                                                        'received_craftsman_date',
                                                        event.detail.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            {errors.received_craftsman_date ? (
                                                <Text className="spkFioriError">
                                                    {
                                                        errors.received_craftsman_date
                                                    }
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon>
                                                Berat Barang Jadi (g)
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <Input
                                                type="Number"
                                                accessibleName="Berat Barang Jadi pasang batu"
                                                value={data.weight_finish_goods}
                                                valueState={fieldState(
                                                    errors.weight_finish_goods,
                                                )}
                                                onInput={(event) =>
                                                    setData(
                                                        'weight_finish_goods',
                                                        event.target.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            {errors.weight_finish_goods ? (
                                                <Text className="spkFioriError">
                                                    {
                                                        errors.weight_finish_goods
                                                    }
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon>Susut (g)</Label>
                                        }
                                    >
                                        <Input
                                            value={shrinkPreview ?? '—'}
                                            readonly
                                            accessibleName="Susut pasang batu"
                                        />
                                    </FormItem>
                                </FormGroup>
                            </Form>

                            <PasangBatuStoneEditor
                                stoneOptions={stoneOptions}
                                shapeOptions={shapeOptions}
                                settingStones={data.setting_stones}
                                returnStones={data.return_stones}
                                diamonds={data.diamonds}
                                mountedStones={data.mounted_stones}
                                errors={errors}
                                onSettingChange={(settingStones) =>
                                    setData('setting_stones', settingStones)
                                }
                                onReturnChange={(returnStones) =>
                                    setData('return_stones', returnStones)
                                }
                                onDiamondsChange={(diamonds) =>
                                    setData('diamonds', diamonds)
                                }
                                onMountedChange={(mountedStones) =>
                                    setData('mounted_stones', mountedStones)
                                }
                            />
                        </section>
                    </form>
                </div>
            </div>

            <PasangBatuSelectSpkDialog
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
