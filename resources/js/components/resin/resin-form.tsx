import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import paperPlaneIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import saveIcon from '@ui5/webcomponents-icons/dist/save.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { DatePicker } from '@ui5/webcomponents-react/DatePicker';
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
    ResinAddSpkDialog,
    type ResinAddedSpk,
} from '@/components/resin/resin-add-spk-dialog';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';

type OperatorOption = {
    value: string;
    label: string;
};

type StatusOption = {
    value: string;
    label: string;
};

type ApprovalFooterColumn = {
    title: string;
    name: string;
    date: string;
};

type ResinApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    status: string;
    statusLabel: string;
};

type ResinDetailForm = {
    spk_id: string;
    spk_no: string;
    spk_type: string;
    order_type_label: string;
    type_code: string;
    product_item_name: string;
    sku_code: string;
    item_description: string;
    satuan: string;
    berat_resin: string;
    status_resin: string;
    catatan: string;
};

type ResinFormValues = {
    doc_no: string;
    operator: string;
    trans_date: string;
    notes: string;
    details: ResinDetailForm[];
};

type ResinFormProps = {
    title: string;
    formDocumentNo: string;
    submitLabel: string;
    cancelHref: string;
    submitUrl: string;
    method: 'post' | 'put';
    isNew?: boolean;
    statusOptions: StatusOption[];
    operatorOptions: OperatorOption[];
    approvalFooter?: ApprovalFooterColumn[];
    approval?: ResinApprovalAbilities;
    submitToManagerUrl?: string;
    initialValues: ResinFormValues;
};

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function detailError(
    errors: Record<string, string>,
    index: number,
    field: keyof ResinDetailForm,
): string {
    return errors[`details.${index}.${field}`] ?? '';
}

function defaultStatusResin(): string {
    return '';
}

function normalizeStatusResinForSubmit(
    value: string | null | undefined,
): string | null {
    const trimmed = value?.trim() ?? '';

    if (trimmed === '' || trimmed === '—') {
        return null;
    }

    return trimmed;
}

function normalizeBeratResinForSubmit(value: string): string | null {
    const trimmed = value.trim().replace(',', '.');

    return trimmed !== '' ? trimmed : null;
}

export function ResinForm({
    title,
    formDocumentNo,
    submitLabel,
    cancelHref,
    submitUrl,
    method,
    isNew = false,
    statusOptions,
    operatorOptions,
    approvalFooter = [],
    approval,
    submitToManagerUrl,
    initialValues,
}: ResinFormProps) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<ResinFormValues>(initialValues);

    const [addSpkOpen, setAddSpkOpen] = useState(false);
    const [submittingToManager, setSubmittingToManager] = useState(false);
    const [operatorText, setOperatorText] = useState(
        () => initialValues.operator,
    );

    const displayDocNo = isNew ? 'auto-generated' : data.doc_no;

    const operatorSelectOptions = useMemo(() => {
        const options = [...operatorOptions];

        if (
            data.operator.trim() !== '' &&
            !options.some((option) => option.value === data.operator)
        ) {
            options.unshift({
                value: data.operator,
                label: data.operator,
            });
        }

        return options;
    }, [data.operator, operatorOptions]);

    const selectOperator = (nextValue: string, nextLabel?: string) => {
        const matched = operatorSelectOptions.find(
            (option) => option.value === nextValue,
        );
        const label = nextLabel ?? matched?.label ?? nextValue;

        setOperatorText(label);
        setData('operator', nextValue);
    };

    const clearOperator = () => {
        setOperatorText('');
        setData('operator', '');
    };

    const excludeSpkIds = useMemo(
        () =>
            data.details
                .map((detail) => Number(detail.spk_id))
                .filter((id) => Number.isFinite(id) && id > 0),
        [data.details],
    );

    const updateDetail = (
        index: number,
        field: keyof ResinDetailForm,
        value: string,
    ) => {
        setData(
            'details',
            data.details.map((detail, detailIndex) =>
                detailIndex === index
                    ? { ...detail, [field]: value }
                    : detail,
            ),
        );
    };

    const handleSpkAdded = (spk: ResinAddedSpk) => {
        const alreadyExists = data.details.some(
            (row) => row.spk_id === spk.spk_id,
        );

        if (alreadyExists) {
            return;
        }

        setData('details', [
            ...data.details,
            {
                ...spk,
                berat_resin: '',
                status_resin: defaultStatusResin(),
                catatan: '',
            },
        ]);
    };

    const openAddSpk = () => {
        setAddSpkOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        transform((formData) => ({
            doc_no: formData.doc_no,
            operator: formData.operator,
            trans_date: formData.trans_date,
            notes: formData.notes,
            details: formData.details.map((detail) => ({
                spk_id: detail.spk_id,
                berat_resin: normalizeBeratResinForSubmit(detail.berat_resin),
                status_resin: normalizeStatusResinForSubmit(
                    detail.status_resin,
                ),
                catatan: detail.catatan,
            })),
        }));

        const options = {
            preserveScroll: true,
        };

        if (method === 'put') {
            put(submitUrl, options);

            return;
        }

        post(submitUrl, options);
    };

    const submitToManager = () => {
        if (!submitToManagerUrl || approval?.canSubmit !== true) {
            return;
        }

        setSubmittingToManager(true);
        router.post(
            submitToManagerUrl,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmittingToManager(false),
            },
        );
    };

    const canSubmitToManager =
        !isNew && approval?.canSubmit === true && Boolean(submitToManagerUrl);

    const isReadOnly = !isNew && approval?.canEdit === false;

    return (
        <>
            <div className="spkDetailShell">
                <div className="spkDetailStack">
                    <form
                        id="resin-form"
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
                                        disabled={
                                            processing ||
                                            submittingToManager ||
                                            (!isNew && approval?.canEdit === false)
                                        }
                                    >
                                        {processing
                                            ? 'Menyimpan...'
                                            : submitLabel}
                                    </Button>
                                    {canSubmitToManager ? (
                                        <Button
                                            design="Emphasized"
                                            icon={paperPlaneIcon}
                                            disabled={
                                                processing || submittingToManager
                                            }
                                            onClick={submitToManager}
                                        >
                                            {submittingToManager
                                                ? 'Mengirim...'
                                                : 'Kirim ke Manager Produksi'}
                                        </Button>
                                    ) : null}
                                </div>
                            </div>

                            {isReadOnly ? (
                                <div
                                    className="spkFioriNotice"
                                    role="status"
                                >
                                    Dokumen berstatus{' '}
                                    <strong>{approval?.statusLabel}</strong>{' '}
                                    dan tidak dapat disimpan dari halaman ini.
                                    {approval?.status === 'RSN020' ? (
                                        <>
                                            {' '}
                                            Gunakan halaman detail untuk
                                            memperbarui progress resin.
                                        </>
                                    ) : null}
                                </div>
                            ) : null}

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
                                                Nomor Dokumen
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <Input
                                                value={displayDocNo}
                                                readonly={isNew}
                                                valueState={fieldState(
                                                    errors.doc_no,
                                                )}
                                                onInput={(event) => {
                                                    if (isNew) {
                                                        return;
                                                    }

                                                    setData(
                                                        'doc_no',
                                                        event.target.value ??
                                                            '',
                                                    );
                                                }}
                                            />
                                            {errors.doc_no ? (
                                                <Text className="spkFioriError">
                                                    {errors.doc_no}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Operator Resin
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <ComboBox
                                                accessibleName="Operator Resin"
                                                className="spkFioriComboBox"
                                                placeholder="Cari / pilih operator"
                                                filter="Contains"
                                                showClearIcon
                                                noTypeahead
                                                value={operatorText}
                                                valueState={fieldState(
                                                    errors.operator,
                                                )}
                                                onInput={(event) => {
                                                    const nextText =
                                                        event.target.value ??
                                                        '';
                                                    setOperatorText(nextText);

                                                    if (!nextText.trim()) {
                                                        clearOperator();

                                                        return;
                                                    }

                                                    const selected =
                                                        operatorSelectOptions.find(
                                                            (option) =>
                                                                option.value ===
                                                                data.operator,
                                                        );

                                                    if (
                                                        selected &&
                                                        selected.label !==
                                                            nextText
                                                    ) {
                                                        setData(
                                                            'operator',
                                                            '',
                                                        );
                                                    }
                                                }}
                                                onSelectionChange={(event) => {
                                                    const item =
                                                        event.detail.item;
                                                    if (!item?.value) {
                                                        return;
                                                    }

                                                    selectOperator(
                                                        String(item.value),
                                                        item.text ?? '',
                                                    );
                                                }}
                                                onChange={(event) => {
                                                    const typed =
                                                        event.target.value?.trim() ??
                                                        '';

                                                    if (!typed) {
                                                        clearOperator();

                                                        return;
                                                    }

                                                    const matched =
                                                        operatorSelectOptions.find(
                                                            (option) =>
                                                                option.label.toLowerCase() ===
                                                                typed.toLowerCase(),
                                                        );

                                                    if (matched) {
                                                        selectOperator(
                                                            matched.value,
                                                            matched.label,
                                                        );

                                                        return;
                                                    }

                                                    setOperatorText(typed);
                                                    setData('operator', '');
                                                }}
                                            >
                                                {operatorSelectOptions.map(
                                                    (option) => (
                                                        <ComboBoxItem
                                                            key={option.value}
                                                            text={option.label}
                                                            value={option.value}
                                                        />
                                                    ),
                                                )}
                                            </ComboBox>
                                            {errors.operator ? (
                                                <Text className="spkFioriError">
                                                    {errors.operator}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Tanggal
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <DatePicker
                                                accessibleName="Tanggal resin"
                                                value={data.trans_date}
                                                valueFormat="yyyy-MM-dd"
                                                displayFormat="dd/MM/yyyy"
                                                valueState={fieldState(
                                                    errors.trans_date,
                                                )}
                                                onChange={(event) =>
                                                    setData(
                                                        'trans_date',
                                                        event.target.value ??
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
                                            <Label showColon>Catatan</Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <TextArea
                                                rows={3}
                                                value={data.notes}
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
                            </Form>

                            <div className="spkFioriDetailBlock">
                                <div className="spkStoneCardHeader">
                                    <div className="spkFioriDetailBlockTitle">
                                        List SPK
                                    </div>
                                    <Button
                                        design="Emphasized"
                                        type="Button"
                                        disabled={isReadOnly}
                                        onClick={openAddSpk}
                                    >
                                        Tambah SPK
                                    </Button>
                                </div>

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
                                                <th>Berat Resin</th>
                                                <th>Status Resin</th>
                                                <th>Catatan</th>
                                                <th className="spkTableActionCol">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {data.details.length === 0 ? (
                                                <tr>
                                                    <td colSpan={8}>
                                                        Belum ada SPK. Klik
                                                        Tambah SPK untuk
                                                        memilih.
                                                    </td>
                                                </tr>
                                            ) : (
                                                data.details.map(
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
                                                                <div className="spkFioriFieldStack">
                                                                    <Input
                                                                        type="Number"
                                                                        accessibleName="Berat resin"
                                                                        value={
                                                                            detail.berat_resin
                                                                        }
                                                                        valueState={fieldState(
                                                                            detailError(
                                                                                errors,
                                                                                index,
                                                                                'berat_resin',
                                                                            ),
                                                                        )}
                                                                        onInput={(
                                                                            event,
                                                                        ) =>
                                                                            updateDetail(
                                                                                index,
                                                                                'berat_resin',
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
                                                                        'berat_resin',
                                                                    ) ? (
                                                                        <Text className="spkFioriError">
                                                                            {detailError(
                                                                                errors,
                                                                                index,
                                                                                'berat_resin',
                                                                            )}
                                                                        </Text>
                                                                    ) : null}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div className="spkFioriFieldStack">
                                                                    <Select
                                                                        accessibleName="Status resin"
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateDetail(
                                                                                index,
                                                                                'status_resin',
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
                                                                                detail.status_resin.trim() ===
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
                                                                                        detail.status_resin ===
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
                                                                        'status_resin',
                                                                    ) ? (
                                                                        <Text className="spkFioriError">
                                                                            {detailError(
                                                                                errors,
                                                                                index,
                                                                                'status_resin',
                                                                            )}
                                                                        </Text>
                                                                    ) : null}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div className="spkFioriFieldStack">
                                                                    <TextArea
                                                                        rows={2}
                                                                        value={
                                                                            detail.catatan
                                                                        }
                                                                        valueState={fieldState(
                                                                            detailError(
                                                                                errors,
                                                                                index,
                                                                                'catatan',
                                                                            ),
                                                                        )}
                                                                        onInput={(
                                                                            event,
                                                                        ) =>
                                                                            updateDetail(
                                                                                index,
                                                                                'catatan',
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
                                                                        'catatan',
                                                                    ) ? (
                                                                        <Text className="spkFioriError">
                                                                            {detailError(
                                                                                errors,
                                                                                index,
                                                                                'catatan',
                                                                            )}
                                                                        </Text>
                                                                    ) : null}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <Button
                                                                    design="Negative"
                                                                    type="Button"
                                                                    onClick={() =>
                                                                        setData(
                                                                            'details',
                                                                            data.details.filter(
                                                                                (
                                                                                    _row,
                                                                                    detailIndex,
                                                                                ) =>
                                                                                    detailIndex !==
                                                                                    index,
                                                                            ),
                                                                        )
                                                                    }
                                                                >
                                                                    Hapus
                                                                </Button>
                                                            </td>
                                                        </tr>
                                                    ),
                                                )
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="spkTableFooter">
                                    <div className="spkTableMeta">
                                        Total {data.details.length} SPK
                                    </div>
                                </div>
                                {errors.details ? (
                                    <Text className="spkFioriError">
                                        {errors.details}
                                    </Text>
                                ) : null}
                            </div>
                        </section>

                        {approvalFooter.length > 0 ? (
                            <div className="spkShowBottom">
                                <footer
                                    className="spkApprovalFooter spkApprovalFooter--splitEnds"
                                    aria-label="Persetujuan"
                                >
                                    {approvalFooter.map((column) => (
                                        <div
                                            key={column.title}
                                            className="spkApprovalFooterCol"
                                        >
                                            <div className="spkApprovalFooterTitle">
                                                {column.title}
                                            </div>
                                            <div className="spkApprovalFooterMeta">
                                                <div className="spkApprovalFooterMetaRow">
                                                    <span className="spkApprovalFooterMetaLabel">
                                                        Nama
                                                    </span>
                                                    <span className="spkApprovalFooterMetaValue">
                                                        {column.name || '—'}
                                                    </span>
                                                </div>
                                                <div className="spkApprovalFooterMetaRow">
                                                    <span className="spkApprovalFooterMetaLabel">
                                                        Tanggal
                                                    </span>
                                                    <span className="spkApprovalFooterMetaValue">
                                                        {column.date || '—'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </footer>
                            </div>
                        ) : null}
                    </form>
                </div>
            </div>

            <ResinAddSpkDialog
                open={addSpkOpen}
                onOpenChange={setAddSpkOpen}
                excludeSpkIds={excludeSpkIds}
                onAdded={handleSpkAdded}
            />
        </>
    );
}
