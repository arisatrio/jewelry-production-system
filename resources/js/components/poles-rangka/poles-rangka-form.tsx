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
    PolesRangkaSelectSpkDialog,
    type PolesRangkaSelectedSpk,
} from '@/components/poles-rangka/poles-rangka-select-spk-dialog';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';

type OptionItem = {
    value: string;
    label: string;
};

type PolesRangkaSpkForm = {
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

type PolesRangkaFormValues = {
    craftsman_id: string;
    send_craftsman_date: string;
    received_craftsman_date: string;
    notes: string;
    status_item: string;
    start_weight: string;
    finish_weight: string;
    spk: PolesRangkaSpkForm | null;
};

type PolesRangkaFormProps = {
    title: string;
    formDocumentNo: string;
    submitLabel: string;
    cancelHref: string;
    submitUrl: string;
    method?: 'post' | 'put';
    craftsmanOptions: OptionItem[];
    statusItemOptions: OptionItem[];
    initialValues: PolesRangkaFormValues;
};

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function normalizeWeightForSubmit(value: string): string | null {
    const trimmed = value.trim().replace(',', '.');

    return trimmed !== '' ? trimmed : null;
}

function computeShrink(startWeight: string, finishWeight: string): string | null {
    const start = Number.parseFloat(startWeight.trim().replace(',', '.'));
    const finish = Number.parseFloat(finishWeight.trim().replace(',', '.'));

    if (Number.isNaN(start) || Number.isNaN(finish)) {
        return null;
    }

    return (start - finish).toFixed(2);
}

function emptySpk(): PolesRangkaSpkForm {
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

function selectedSpkItemSummary(spk: PolesRangkaSpkForm | null): string {
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

export function PolesRangkaForm({
    title,
    formDocumentNo,
    submitLabel,
    cancelHref,
    submitUrl,
    method = 'post',
    craftsmanOptions,
    statusItemOptions,
    initialValues,
}: PolesRangkaFormProps) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<PolesRangkaFormValues>(initialValues);
    const [spkDialogOpen, setSpkDialogOpen] = useState(false);
    const hasSelectedSpk =
        data.spk !== null && data.spk.spk_id.trim() !== '';
    const shrinkPreview = computeShrink(data.start_weight, data.finish_weight);

    const handleSelectedSpk = (selected: PolesRangkaSelectedSpk) => {
        const { lastWeight, ...spk } = selected;

        setData({
            ...data,
            spk,
            start_weight: lastWeight?.trim() ?? '',
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
            status_item:
                formData.status_item.trim() !== ''
                    ? formData.status_item
                    : null,
            start_weight: normalizeWeightForSubmit(formData.start_weight),
            finish_weight: normalizeWeightForSubmit(formData.finish_weight),
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
                        id="poles-rangka-form"
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
                                                                start_weight:
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
                                                Berat awal (g)
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <Input
                                                type="Number"
                                                accessibleName="Berat awal"
                                                value={data.start_weight}
                                                valueState={fieldState(
                                                    errors.start_weight,
                                                )}
                                                onInput={(event) =>
                                                    setData(
                                                        'start_weight',
                                                        event.target.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            {errors.start_weight ? (
                                                <Text className="spkFioriError">
                                                    {errors.start_weight}
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
                                                accessibleName="Pengrajin poles rangka"
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
                                            <Label showColon>Status QC</Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <Select
                                                accessibleName="Status QC poles rangka"
                                                valueState={fieldState(
                                                    errors.status_item,
                                                )}
                                                onChange={(event) =>
                                                    setData(
                                                        'status_item',
                                                        event.detail
                                                            .selectedOption
                                                            .value ?? '',
                                                    )
                                                }
                                            >
                                                <Option
                                                    value=""
                                                    selected={
                                                        data.status_item ===
                                                        ''
                                                    }
                                                >
                                                    —
                                                </Option>
                                                {statusItemOptions.map(
                                                    (option) => (
                                                        <Option
                                                            key={option.value}
                                                            value={option.value}
                                                            selected={
                                                                data.status_item ===
                                                                option.value
                                                            }
                                                        >
                                                            {option.label}
                                                        </Option>
                                                    ),
                                                )}
                                            </Select>
                                            {errors.status_item ? (
                                                <Text className="spkFioriError">
                                                    {errors.status_item}
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
                                                accessibleName="Catatan poles rangka"
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
                                                Berat akhir (g)
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <Input
                                                type="Number"
                                                accessibleName="Berat akhir"
                                                value={data.finish_weight}
                                                valueState={fieldState(
                                                    errors.finish_weight,
                                                )}
                                                onInput={(event) =>
                                                    setData(
                                                        'finish_weight',
                                                        event.target.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            {errors.finish_weight ? (
                                                <Text className="spkFioriError">
                                                    {errors.finish_weight}
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
                                            accessibleName="Susut"
                                        />
                                    </FormItem>
                                </FormGroup>
                            </Form>

                        </section>
                    </form>
                </div>
            </div>

            <PolesRangkaSelectSpkDialog
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
