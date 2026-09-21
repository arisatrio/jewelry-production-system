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
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { Text } from '@ui5/webcomponents-react/Text';
import {
    CoranAddSpkDialog,
    type CoranAddedSpk,
} from '@/components/coran/coran-add-spk-dialog';
import {
    CoranMaterialEditor,
    type CoranMaterialFormLine,
    type CoranMaterialOption,
} from '@/components/coran/coran-material-editor';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';

type CraftsmanOption = {
    value: string;
    label: string;
};

type StatusOption = {
    value: string;
    label: string;
};

type CoranDetailForm = {
    spk_id: string;
    spk_no: string;
    spk_type: string;
    order_type_label: string;
    type_code: string;
    product_item_name: string;
    sku_code: string;
    item_description: string;
    satuan: string;
    weight?: string;
    weight_rosegold: string;
    weight_whitegold: string;
    weight_yellowgold: string;
    kadar: string;
    status: string;
};

type CoranFormValues = {
    trans_date: string;
    craftsman_id: string;
    details: CoranDetailForm[];
    materials: CoranMaterialFormLine[];
};

type CoranFormProps = {
    title: string;
    formDocumentNo: string;
    submitLabel: string;
    cancelHref: string;
    submitUrl: string;
    method?: 'post' | 'put';
    statusOptions: StatusOption[];
    craftsmanOptions: CraftsmanOption[];
    materialOptions: CoranMaterialOption[];
    initialValues: CoranFormValues;
};

const SPK_WEIGHT_COLORS = [
    { field: 'weight_rosegold', label: 'Rose Gold' },
    { field: 'weight_whitegold', label: 'White Gold' },
    { field: 'weight_yellowgold', label: 'Yellow Gold' },
] as const;

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function detailError(
    errors: Record<string, string>,
    index: number,
    field: keyof CoranDetailForm,
): string {
    return errors[`details.${index}.${field}`] ?? '';
}

function normalizeWeightForSubmit(value: string): string | null {
    const trimmed = value.trim().replace(',', '.');

    return trimmed !== '' ? trimmed : null;
}

function toWeightNumber(value: string): number {
    const normalized = value.trim().replace(',', '.');

    if (normalized === '') {
        return 0;
    }

    const parsed = Number(normalized);

    return Number.isFinite(parsed) ? parsed : 0;
}

function sumDetailColorWeights(detail: CoranDetailForm): number {
    return (
        toWeightNumber(detail.weight_rosegold) +
        toWeightNumber(detail.weight_whitegold) +
        toWeightNumber(detail.weight_yellowgold)
    );
}

function detailTotalWeight(detail: CoranDetailForm): number {
    const colorTotal = sumDetailColorWeights(detail);

    if (
        detail.weight_rosegold.trim() !== '' ||
        detail.weight_whitegold.trim() !== '' ||
        detail.weight_yellowgold.trim() !== ''
    ) {
        return colorTotal;
    }

    return toWeightNumber(detail.weight ?? '');
}

function formatWeightTotal(total: number): string {
    return total.toFixed(3);
}

function normalizeStatusForSubmit(value: string): string | null {
    const trimmed = value.trim();

    if (trimmed === '' || trimmed === '—') {
        return null;
    }

    return trimmed;
}

export function CoranForm({
    title,
    formDocumentNo,
    submitLabel,
    cancelHref,
    submitUrl,
    method = 'post',
    statusOptions,
    craftsmanOptions,
    materialOptions,
    initialValues,
}: CoranFormProps) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<CoranFormValues>(initialValues);
    const [addSpkOpen, setAddSpkOpen] = useState(false);

    const excludeSpkIds = useMemo(
        () =>
            data.details
                .map((detail) => Number(detail.spk_id))
                .filter((id) => id > 0),
        [data.details],
    );

    const updateDetail = <K extends keyof CoranDetailForm>(
        index: number,
        field: K,
        value: CoranDetailForm[K],
    ) => {
        setData(
            'details',
            data.details.map((detail, detailIndex) =>
                detailIndex === index
                    ? {
                          ...detail,
                          [field]: value,
                      }
                    : detail,
            ),
        );
    };

    const removeDetail = (index: number) => {
        setData(
            'details',
            data.details.filter((_, detailIndex) => detailIndex !== index),
        );
    };

    const openAddSpk = () => setAddSpkOpen(true);

    const handleAddedSpk = (added: CoranAddedSpk) => {
        setData('details', [
            ...data.details,
            {
                ...added,
                weight_rosegold: '',
                weight_whitegold: '',
                weight_yellowgold: '',
                kadar: '',
                status: '',
            },
        ]);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        transform((formData) => ({
            trans_date: formData.trans_date,
            craftsman_id:
                formData.craftsman_id.trim() !== ''
                    ? formData.craftsman_id
                    : null,
            details: formData.details.map((detail) => {
                const weightRosegold = normalizeWeightForSubmit(
                    detail.weight_rosegold,
                );
                const weightWhitegold = normalizeWeightForSubmit(
                    detail.weight_whitegold,
                );
                const weightYellowgold = normalizeWeightForSubmit(
                    detail.weight_yellowgold,
                );
                const total = sumDetailColorWeights(detail);

                return {
                    spk_id: Number(detail.spk_id),
                    weight_rosegold: weightRosegold,
                    weight_whitegold: weightWhitegold,
                    weight_yellowgold: weightYellowgold,
                    weight:
                        weightRosegold !== null ||
                        weightWhitegold !== null ||
                        weightYellowgold !== null
                            ? formatWeightTotal(total)
                            : normalizeWeightForSubmit(detail.weight ?? ''),
                    kadar: normalizeWeightForSubmit(detail.kadar),
                    status: normalizeStatusForSubmit(detail.status),
                };
            }),
            materials: formData.materials
                .filter(
                    (line) =>
                        line.materialgold_id.trim() !== '' ||
                        line.weight.trim() !== '',
                )
                .map((line) => ({
                    section: line.section,
                    materialgold_id:
                        line.materialgold_id.trim() !== ''
                            ? Number(line.materialgold_id)
                            : null,
                    weight: normalizeWeightForSubmit(line.weight),
                    notes:
                        line.notes.trim() !== '' ? line.notes.trim() : null,
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
                        id="coran-form"
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
                                    headerText="Informasi Dokumen"
                                    columnSpan={2}
                                >
                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Tanggal
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <DatePicker
                                                value={data.trans_date}
                                                valueFormat="yyyy-MM-dd"
                                                displayFormat="dd/MM/yyyy"
                                                valueState={fieldState(
                                                    errors.trans_date,
                                                )}
                                                onChange={(event) =>
                                                    setData(
                                                        'trans_date',
                                                        event.detail.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            {errors.trans_date ? (
                                                <Text className="spkFioriError">
                                                    {errors.trans_date}
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
                                                accessibleName="Pengrajin"
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
                                                        data.craftsman_id.trim() ===
                                                        ''
                                                    }
                                                >
                                                    —
                                                </Option>
                                                {craftsmanOptions.map(
                                                    (option) => (
                                                        <Option
                                                            key={option.value}
                                                            value={
                                                                option.value
                                                            }
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
                                </FormGroup>
                            </Form>

                            <div className="spkFioriDetailBlock">
                                <div className="spkFioriDetailBlockTitle">
                                    List SPK
                                </div>

                                {errors.details ? (
                                    <Text className="spkFioriError">
                                        {errors.details}
                                    </Text>
                                ) : null}

                                <div className="spkTableScroll">
                                    <table className="spkTable masterDataTable">
                                        <thead>
                                            <tr>
                                                <th className="spkTableColSpkNo">
                                                    SPK
                                                </th>
                                                <th className="spkTableColCenter spkTableColTipeProduksi">
                                                    Tipe Produksi
                                                </th>
                                                <th>SKU</th>
                                                <th>Qty</th>
                                                <th>
                                                    Berat Keluar
                                                    <br />
                                                    hasil coran (g)
                                                </th>
                                                <th>Total Berat (g)</th>
                                                <th>Kadar</th>
                                                <th>Status Coran</th>
                                                <th className="spkTableActionCol">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {data.details.map(
                                                (detail, index) => (
                                                    <tr
                                                        key={`detail-${detail.spk_id}-${index}`}
                                                        className="masterDataRowForm"
                                                    >
                                                            <td className="spkTableColSpkNo">
                                                                <strong>
                                                                    {detail.spk_no ||
                                                                        '—'}
                                                                </strong>
                                                                {errors[
                                                                    `details.${index}.spk_id`
                                                                ] ? (
                                                                    <Text className="spkFioriError">
                                                                        {
                                                                            errors[
                                                                                `details.${index}.spk_id`
                                                                            ]
                                                                        }
                                                                    </Text>
                                                                ) : null}
                                                            </td>
                                                            <td className="spkTableColCenter spkTableColTipeProduksi">
                                                                <SpkOrderTypeColumn
                                                                    spkType={
                                                                        detail.spk_type
                                                                    }
                                                                    orderTypeLabel={
                                                                        detail.order_type_label
                                                                    }
                                                                />
                                                            </td>
                                                            <td>
                                                                <SpkItemSkuColumn
                                                                    typeCode={
                                                                        detail.type_code ||
                                                                        null
                                                                    }
                                                                    productItemName={
                                                                        detail.product_item_name ||
                                                                        null
                                                                    }
                                                                    skuCode={
                                                                        detail.sku_code ||
                                                                        null
                                                                    }
                                                                    itemDescription={
                                                                        detail.item_description ||
                                                                        null
                                                                    }
                                                                />
                                                            </td>
                                                            <td>
                                                                {detail.satuan.trim() !==
                                                                ''
                                                                    ? detail.satuan
                                                                    : '—'}
                                                            </td>
                                                            <td>
                                                                <div className="spkFioriFieldStack gap-2">
                                                                    {SPK_WEIGHT_COLORS.map(
                                                                        (
                                                                            color,
                                                                        ) => (
                                                                            <div
                                                                                key={
                                                                                    color.field
                                                                                }
                                                                                className="spkFioriFieldStack"
                                                                            >
                                                                                <Label>
                                                                                    {
                                                                                        color.label
                                                                                    }
                                                                                </Label>
                                                                                <Input
                                                                                    type="Number"
                                                                                    accessibleName={`Berat ${color.label}`}
                                                                                    value={
                                                                                        detail[
                                                                                            color
                                                                                                .field
                                                                                        ]
                                                                                    }
                                                                                    valueState={fieldState(
                                                                                        detailError(
                                                                                            errors,
                                                                                            index,
                                                                                            color.field,
                                                                                        ),
                                                                                    )}
                                                                                    onInput={(
                                                                                        event,
                                                                                    ) =>
                                                                                        updateDetail(
                                                                                            index,
                                                                                            color.field,
                                                                                            event
                                                                                                .target
                                                                                                .value ??
                                                                                                '',
                                                                                        )
                                                                                    }
                                                                                />
                                                                                {detailError(
                                                                                    errors,
                                                                                    index,
                                                                                    color.field,
                                                                                ) ? (
                                                                                    <Text className="spkFioriError">
                                                                                        {detailError(
                                                                                            errors,
                                                                                            index,
                                                                                            color.field,
                                                                                        )}
                                                                                    </Text>
                                                                                ) : null}
                                                                            </div>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <strong>
                                                                    {formatWeightTotal(
                                                                        detailTotalWeight(
                                                                            detail,
                                                                        ),
                                                                    )}
                                                                </strong>
                                                            </td>
                                                            <td>
                                                                <div className="spkFioriFieldStack">
                                                                    <Input
                                                                        type="Number"
                                                                        accessibleName="Kadar"
                                                                        value={
                                                                            detail.kadar
                                                                        }
                                                                        valueState={fieldState(
                                                                            detailError(
                                                                                errors,
                                                                                index,
                                                                                'kadar',
                                                                            ),
                                                                        )}
                                                                        onInput={(
                                                                            event,
                                                                        ) =>
                                                                            updateDetail(
                                                                                index,
                                                                                'kadar',
                                                                                event
                                                                                    .target
                                                                                    .value ??
                                                                                    '',
                                                                            )
                                                                        }
                                                                    />
                                                                    {detailError(
                                                                        errors,
                                                                        index,
                                                                        'kadar',
                                                                    ) ? (
                                                                        <Text className="spkFioriError">
                                                                            {detailError(
                                                                                errors,
                                                                                index,
                                                                                'kadar',
                                                                            )}
                                                                        </Text>
                                                                    ) : null}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div className="spkFioriFieldStack">
                                                                    <Select
                                                                        accessibleName="Status coran"
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateDetail(
                                                                                index,
                                                                                'status',
                                                                                event
                                                                                    .detail
                                                                                    .selectedOption
                                                                                    .value ??
                                                                                    '',
                                                                            )
                                                                        }
                                                                    >
                                                                        <Option
                                                                            value=""
                                                                            selected={
                                                                                detail.status.trim() ===
                                                                                ''
                                                                            }
                                                                        >
                                                                            —
                                                                        </Option>
                                                                        {statusOptions.map(
                                                                            (
                                                                                option,
                                                                            ) => (
                                                                                <Option
                                                                                    key={
                                                                                        option.value
                                                                                    }
                                                                                    value={
                                                                                        option.value
                                                                                    }
                                                                                    selected={
                                                                                        detail.status ===
                                                                                        option.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        option.label
                                                                                    }
                                                                                </Option>
                                                                            ),
                                                                        )}
                                                                    </Select>
                                                                    {detailError(
                                                                        errors,
                                                                        index,
                                                                        'status',
                                                                    ) ? (
                                                                        <Text className="spkFioriError">
                                                                            {detailError(
                                                                                errors,
                                                                                index,
                                                                                'status',
                                                                            )}
                                                                        </Text>
                                                                    ) : null}
                                                                </div>
                                                            </td>
                                                            <td className="spkTableActionCol">
                                                                <Button
                                                                    design="Transparent"
                                                                    type="Button"
                                                                    onClick={() =>
                                                                        removeDetail(
                                                                            index,
                                                                        )
                                                                    }
                                                                >
                                                                    Hapus
                                                                </Button>
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            <tr>
                                                <td colSpan={7}>
                                                    {data.details.length === 0
                                                        ? 'Belum ada SPK.'
                                                        : null}
                                                </td>
                                                <td className="spkTableActionCol">
                                                    <Button
                                                        design="Emphasized"
                                                        type="Button"
                                                        onClick={openAddSpk}
                                                    >
                                                        Tambah SPK
                                                    </Button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <CoranMaterialEditor
                                materials={data.materials}
                                materialOptions={materialOptions}
                                errors={errors}
                                disabled={processing}
                                onChange={(materials) =>
                                    setData('materials', materials)
                                }
                            />
                        </section>
                    </form>
                </div>
            </div>

            <CoranAddSpkDialog
                open={addSpkOpen}
                onOpenChange={setAddSpkOpen}
                excludeSpkIds={excludeSpkIds}
                onAdded={handleAddedSpk}
            />
        </>
    );
}
