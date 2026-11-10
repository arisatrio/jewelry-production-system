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
    PasangBatuSelectSpkDiamondDialog,
    type PasangBatuSelectedSpkDiamond,
} from '@/components/pasang-batu/pasang-batu-select-spk-diamond-dialog';
import {
    PasangBatuSelectSpkDialog,
    type PasangBatuSelectedSpk,
} from '@/components/pasang-batu/pasang-batu-select-spk-dialog';
import { importMethod as importPasangBatuSpkDiamond } from '@/routes/pasang-batu/select/spk-diamonds';
import {
    PasangBatuStoneEditor,
    type DiamondLine,
    type MountedStoneLine,
    type PasangBatuDiamondOption,
    type PasangBatuStoneOption,
    type SettingStoneLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import { buildPasangBatuStoneSubmitPayload } from '@/components/pasang-batu/pasang-batu-stone-submit';
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
    process_name: string;
    craftsman_id: string;
    send_craftsman_date: string;
    received_craftsman_date: string;
    notes: string;
    weight_frame: string;
    weight_diamond: string;
    weight_finish_goods: string;
    spk_diamond_id: string;
    spk_diamond_doc_no: string;
    spk: PasangBatuSpkForm;
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
    processOptions: OptionItem[];
    craftsmanOptions: OptionItem[];
    stoneOptions: PasangBatuStoneOption[];
    shapeOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    initialValues: PasangBatuFormValues;
    allowMountingId?: number | null;
    /** Create: SPK produksi hanya dari impor SPK Diamond (tanpa dialog Pilih SPK). */
    allowManualSpkSelection?: boolean;
};

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

function newLineKey(prefix: string): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

export function emptyPasangBatuSpk(): PasangBatuSpkForm {
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

function selectedSpkItemSummary(spk: PasangBatuSpkForm): string {
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
    processOptions,
    craftsmanOptions,
    stoneOptions,
    shapeOptions,
    diamondOptions,
    initialValues,
    allowMountingId = null,
    allowManualSpkSelection = true,
}: PasangBatuFormProps) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<PasangBatuFormValues>(initialValues);
    const [spkDialogOpen, setSpkDialogOpen] = useState(false);
    const [spkDiamondDialogOpen, setSpkDiamondDialogOpen] = useState(false);
    const [importingSpkDiamond, setImportingSpkDiamond] = useState(false);
    const [spkDiamondImportError, setSpkDiamondImportError] = useState<
        string | null
    >(null);
    const hasSelectedSpk = data.spk.spk_id.trim() !== '';
    const hasSelectedSpkDiamond = data.spk_diamond_id.trim() !== '';
    const showSpkChangeActions =
        allowManualSpkSelection &&
        (hasSelectedSpk || hasSelectedSpkDiamond);
    const showSpkDetails =
        hasSelectedSpk ||
        (hasSelectedSpkDiamond && data.spk.spk_no.trim() !== '');
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

    const handleSelectedSpkDiamond = (
        selected: PasangBatuSelectedSpkDiamond,
    ) => {
        setSpkDiamondImportError(null);
        setImportingSpkDiamond(true);

        void fetch(
            importPasangBatuSpkDiamond.url(selected.rowId, {
                query:
                    allowMountingId !== null && allowMountingId > 0
                        ? { allowMountingId }
                        : undefined,
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        )
            .then(async (response) => {
                if (!response.ok) {
                    const payload = (await response.json().catch(() => null)) as
                        | { message?: string }
                        | null;

                    throw new Error(
                        payload?.message ??
                            'Gagal mengimpor detail batu dari SPK Diamond.',
                    );
                }

                const payload = (await response.json()) as {
                    data: {
                        spkDiamondId: number;
                        spkDiamondDocNo: string;
                        weightDiamond: string | null;
                        spk: {
                            rowId: number;
                            spkNo: string | null;
                            lastWeight: string | null;
                            spkType: string | null;
                            orderTypeLabel: string | null;
                            skuCode: string | null;
                            typeCode: string | null;
                            productItemName: string | null;
                            itemDescription: string | null;
                            satuan: string;
                        };
                        settingStones: Array<{
                            stoneId: number;
                            pcs: string;
                            crt: string;
                            notes: string;
                        }>;
                        diamonds: Array<{ diamondId: number }>;
                        mountedStones: Array<{
                            diamondCode: string;
                            shapeId: number | null;
                            pcs: string;
                            crt: string;
                            size: string;
                        }>;
                    };
                };

                const imported = payload.data;
                const nextSpk = {
                    spk_id: String(imported.spk.rowId),
                    spk_no: imported.spk.spkNo ?? '',
                    spk_type: imported.spk.spkType ?? '',
                    order_type_label: imported.spk.orderTypeLabel ?? '',
                    type_code: imported.spk.typeCode ?? '',
                    product_item_name: imported.spk.productItemName ?? '',
                    sku_code: imported.spk.skuCode ?? '',
                    item_description: imported.spk.itemDescription ?? '',
                    satuan: imported.spk.satuan ?? '',
                };

                setData({
                    ...data,
                    spk_diamond_id: String(imported.spkDiamondId),
                    spk_diamond_doc_no: imported.spkDiamondDocNo,
                    spk: nextSpk,
                    weight_frame: imported.spk.lastWeight?.trim() ?? '',
                    weight_diamond: imported.weightDiamond?.trim() ?? '',
                    setting_stones: imported.settingStones.map((line) => ({
                        key: newLineKey('setting'),
                        stone_id: String(line.stoneId),
                        pcs: line.pcs ?? '',
                        crt: line.crt ?? '',
                        notes: line.notes ?? '',
                    })),
                    return_stones: [],
                    ...(() => {
                        const dossierCount = Math.max(
                            imported.diamonds.length,
                            imported.mountedStones.length,
                        );
                        const diamonds: DiamondLine[] = [];
                        const mounted_stones: MountedStoneLine[] = [];

                        for (let index = 0; index < dossierCount; index += 1) {
                            const batchKey = newLineKey('batch');
                            const diamond = imported.diamonds[index];
                            const mounted = imported.mountedStones[index];

                            if (diamond) {
                                diamonds.push({
                                    key: newLineKey('diamond'),
                                    batch_key: batchKey,
                                    diamond_id: String(diamond.diamondId),
                                });
                            }

                            if (mounted) {
                                mounted_stones.push({
                                    key: newLineKey('mounted'),
                                    batch_key: batchKey,
                                    diamond_code: mounted.diamondCode ?? '',
                                    shape_id:
                                        mounted.shapeId !== null &&
                                        mounted.shapeId > 0
                                            ? String(mounted.shapeId)
                                            : '',
                                    pcs: mounted.pcs ?? '',
                                    crt: mounted.crt ?? '',
                                    size: mounted.size ?? '',
                                    disposition: 'pending',
                                });
                            }
                        }

                        return { diamonds, mounted_stones };
                    })(),
                });
                setData('spk', nextSpk);
            })
            .catch((error: unknown) => {
                setSpkDiamondImportError(
                    error instanceof Error
                        ? error.message
                        : 'Gagal mengimpor SPK Diamond.',
                );
            })
            .finally(() => setImportingSpkDiamond(false));
    };

    const clearSpkDiamondImport = () => {
        setSpkDiamondImportError(null);
        setData({
            ...data,
            spk_diamond_id: '',
            spk_diamond_doc_no: '',
            spk: emptyPasangBatuSpk(),
            weight_frame: '',
            weight_diamond: '',
            setting_stones: [],
            return_stones: [],
            diamonds: [],
            mounted_stones: [],
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        transform((formData) => ({
            process_name: formData.process_name.trim() || null,
            spk_id:
                formData.spk?.spk_id && formData.spk.spk_id.trim() !== ''
                    ? Number(formData.spk.spk_id)
                    : null,
            spk_diamond_id:
                formData.spk_diamond_id.trim() !== ''
                    ? Number(formData.spk_diamond_id)
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
            ...buildPasangBatuStoneSubmitPayload({
                setting_stones: formData.setting_stones,
                return_stones: formData.return_stones,
                diamonds: formData.diamonds,
                mounted_stones: formData.mounted_stones,
            }),
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
                                            <Label
                                                showColon
                                                required={
                                                    !allowManualSpkSelection
                                                }
                                            >
                                                No. SPK Diamond
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <div className="spkCreateSelectorRow">
                                                <div className="spkCreateSelectorValue">
                                                    {hasSelectedSpkDiamond ? (
                                                        <strong>
                                                            {data.spk_diamond_doc_no ||
                                                                '—'}
                                                        </strong>
                                                    ) : (
                                                        <span>
                                                            Belum ada SPK
                                                            Diamond dipilih
                                                        </span>
                                                    )}
                                                </div>
                                                <Button
                                                    design="Emphasized"
                                                    type="Button"
                                                    disabled={
                                                        processing ||
                                                        importingSpkDiamond
                                                    }
                                                    onClick={() =>
                                                        setSpkDiamondDialogOpen(
                                                            true,
                                                        )
                                                    }
                                                >
                                                    {importingSpkDiamond
                                                        ? 'Memuat...'
                                                        : hasSelectedSpkDiamond
                                                          ? 'Ganti'
                                                          : 'Pilih'}
                                                </Button>
                                                {hasSelectedSpkDiamond ? (
                                                    <Button
                                                        design="Transparent"
                                                        type="Button"
                                                        disabled={
                                                            processing ||
                                                            importingSpkDiamond
                                                        }
                                                        onClick={
                                                            clearSpkDiamondImport
                                                        }
                                                    >
                                                        Hapus
                                                    </Button>
                                                ) : null}
                                            </div>
                                            {spkDiamondImportError ? (
                                                <Text className="spkFioriError">
                                                    {spkDiamondImportError}
                                                </Text>
                                            ) : null}
                                            {'spk_diamond_id' in errors &&
                                            errors.spk_diamond_id ? (
                                                <Text className="spkFioriError">
                                                    {String(
                                                        errors.spk_diamond_id,
                                                    )}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label
                                                showColon
                                                required={allowManualSpkSelection}
                                            >
                                                No. SPK
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            {allowManualSpkSelection ? (
                                                <div className="spkCreateSelectorRow">
                                                    <div className="spkCreateSelectorValue">
                                                        {showSpkDetails ? (
                                                            <>
                                                                <strong>
                                                                    {data.spk
                                                                        .spk_no ||
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
                                                            setSpkDialogOpen(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        {showSpkChangeActions
                                                            ? 'Ganti'
                                                            : 'Pilih'}
                                                    </Button>
                                                    {showSpkChangeActions ? (
                                                        <Button
                                                            design="Transparent"
                                                            type="Button"
                                                            disabled={
                                                                processing
                                                            }
                                                            onClick={() => {
                                                                if (
                                                                    hasSelectedSpkDiamond
                                                                ) {
                                                                    clearSpkDiamondImport();

                                                                    return;
                                                                }

                                                                setData({
                                                                    ...data,
                                                                    spk: emptyPasangBatuSpk(),
                                                                    weight_frame:
                                                                        '',
                                                                });
                                                            }}
                                                        >
                                                            Hapus
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            ) : (
                                                <div className="spkCreateSelectorValue">
                                                    {showSpkDetails ? (
                                                        <>
                                                            <strong>
                                                                {data.spk
                                                                    .spk_no ||
                                                                    '—'}
                                                            </strong>
                                                            <span>
                                                                {selectedSpkItemSummary(
                                                                    data.spk,
                                                                )}
                                                            </span>
                                                        </>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            Terisi otomatis
                                                            setelah memilih
                                                            SPK Diamond
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                            {'spk_id' in errors &&
                                            errors.spk_id ? (
                                                <Text className="spkFioriError">
                                                    {String(errors.spk_id)}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    {showSpkDetails ? (
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
                                                        data.spk.spk_type ||
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
                                                        data.spk.sku_code ||
                                                        null
                                                    }
                                                    typeCode={
                                                        data.spk.type_code ||
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
                                                        data.spk.satuan || ''
                                                    }
                                                    readonly
                                                />
                                            </FormItem>
                                        </>
                                    ) : null}

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Tipe Proses
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <Select
                                                name="process_name"
                                                valueState={fieldState(
                                                    errors.process_name,
                                                )}
                                                disabled={processing}
                                                onChange={(e) =>
                                                    setData(
                                                        'process_name',
                                                        e.detail.selectedOption
                                                            .dataset.value || '',
                                                    )
                                                }
                                            >
                                                {(processOptions ?? []).map((option) => (
                                                    <Option
                                                        key={option.value}
                                                        value={option.value}
                                                        data-value={option.value}
                                                        selected={
                                                            data.process_name ===
                                                            option.value
                                                        }
                                                    >
                                                        {option.label}
                                                    </Option>
                                                ))}
                                            </Select>
                                            {errors.process_name ? (
                                                <Text className="spkFioriError">
                                                    {errors.process_name}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

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
                                diamondOptions={diamondOptions}
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

            {allowManualSpkSelection ? (
                <PasangBatuSelectSpkDialog
                    open={spkDialogOpen}
                    onOpenChange={setSpkDialogOpen}
                    excludeSpkIds={
                        data.spk.spk_id
                            ? [Number(data.spk.spk_id)].filter((id) => id > 0)
                            : []
                    }
                    onSelected={handleSelectedSpk}
                />
            ) : null}

            <PasangBatuSelectSpkDiamondDialog
                open={spkDiamondDialogOpen}
                onOpenChange={setSpkDiamondDialogOpen}
                allowMountingId={allowMountingId}
                onSelected={handleSelectedSpkDiamond}
            />
        </>
    );
}
