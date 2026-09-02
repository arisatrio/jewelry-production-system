import { Fragment, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
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
import { Text } from '@ui5/webcomponents-react/Text';
import { TextArea } from '@ui5/webcomponents-react/TextArea';
import {
    JewelCadAddSpkDialog,
    type JewelCadAddedSpk,
} from '@/components/jewelcad/jewelcad-add-spk-dialog';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';

type OperatorOption = {
    value: string;
    label: string;
};

type ApprovalFooterColumn = {
    title: string;
    name: string;
    date: string;
};

type JewelCadApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    status: string;
    statusLabel: string;
};

type JewelCadDetailForm = {
    spk_id: string;
    spk_no: string;
    spk_type: string;
    order_type_label: string;
    material: string;
    gold_weight: string;
    jwcad_3d: string;
    file: File | null;
    image_url: string | null;
    file_name: string | null;
    qty: string;
    estimation_brj: string;
    notes: string;
    stones?: Array<{
        shape_id: string;
        position_id: string;
        position_nama: string;
        pcs: string;
        carat_per_pcs: string;
        size: string;
    }>;
};

type JewelCadFormValues = {
    doc_no: string;
    operator: string;
    trans_date: string;
    notes: string;
    details: JewelCadDetailForm[];
};

type JewelCadFormProps = {
    title: string;
    formDocumentNo: string;
    submitLabel: string;
    cancelHref: string;
    submitUrl: string;
    method: 'post' | 'put';
    isNew?: boolean;
    operatorOptions: OperatorOption[];
    approvalFooter?: ApprovalFooterColumn[];
    approval?: JewelCadApprovalAbilities;
    submitToManagerUrl?: string;
    initialValues: JewelCadFormValues;
};

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function materialGroupKey(material: string): string {
    const trimmed = material.trim();

    return trimmed !== '' ? trimmed : 'Tanpa Bahan Emas';
}

function serializeJewelCadFormPayload(data: JewelCadFormValues) {
    return {
        operator: data.operator,
        trans_date: data.trans_date,
        notes: data.notes,
        details: data.details.map((detail) => ({
            spk_id: detail.spk_id,
            material: detail.material,
            gold_weight: detail.gold_weight,
            jwcad_3d: detail.jwcad_3d,
            qty: detail.qty,
            estimation_brj: detail.estimation_brj,
            notes: detail.notes,
            ...(detail.file ? { file: detail.file } : {}),
            stones: detail.stones ?? [],
        })),
    };
}

export function JewelCadForm({
    title,
    formDocumentNo,
    submitLabel,
    cancelHref,
    submitUrl,
    method,
    isNew = false,
    operatorOptions,
    approvalFooter = [],
    approval,
    submitToManagerUrl,
    initialValues,
}: JewelCadFormProps) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<JewelCadFormValues>(initialValues);

    const [addSpkOpen, setAddSpkOpen] = useState(false);
    const [submittingToManager, setSubmittingToManager] = useState(false);
    const [editingDetail, setEditingDetail] =
        useState<JewelCadAddedSpk | null>(null);
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
                .filter((id) => {
                    if (!Number.isFinite(id) || id <= 0) {
                        return false;
                    }

                    if (
                        editingDetail &&
                        String(id) === editingDetail.spk_id
                    ) {
                        return false;
                    }

                    return true;
                }),
        [data.details, editingDetail],
    );

    const detailsByMaterial = useMemo(() => {
        const groups = new Map<
            string,
            Array<{ detail: JewelCadDetailForm; index: number }>
        >();

        data.details.forEach((detail, index) => {
            const key = materialGroupKey(detail.material);
            const rows = groups.get(key) ?? [];
            rows.push({ detail, index });
            groups.set(key, rows);
        });

        return Array.from(groups.entries()).map(([material, rows]) => ({
            material,
            rows,
        }));
    }, [data.details]);

    const detailError = (
        index: number,
        field: keyof JewelCadDetailForm,
    ): string => errors[`details.${index}.${field}`] ?? '';

    const updateDetail = (
        index: number,
        field: keyof JewelCadDetailForm,
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

    const handleSpkAdded = (detail: JewelCadAddedSpk) => {
        const alreadyExists = data.details.some(
            (row) => row.spk_id === detail.spk_id,
        );

        if (alreadyExists) {
            setData(
                'details',
                data.details.map((row) =>
                    row.spk_id === detail.spk_id ? { ...row, ...detail } : row,
                ),
            );
            setEditingDetail(null);

            return;
        }

        setData('details', [...data.details, detail]);
        setEditingDetail(null);
    };

    const openAddSpk = () => {
        setEditingDetail(null);
        setAddSpkOpen(true);
    };

    const openEditSpk = (detail: JewelCadDetailForm) => {
        setEditingDetail({
            spk_id: detail.spk_id,
            spk_no: detail.spk_no,
            spk_type: detail.spk_type,
            order_type_label: detail.order_type_label,
            material: detail.material,
            gold_weight: detail.gold_weight,
            jwcad_3d: detail.jwcad_3d,
            file: detail.file,
            image_url: detail.image_url,
            file_name: detail.file_name,
            qty: detail.qty,
            notes: detail.notes,
            estimation_brj: detail.estimation_brj,
            stones: detail.stones ?? [],
        });
        setAddSpkOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        transform((formData) => serializeJewelCadFormPayload(formData));

        if (method === 'put') {
            put(submitUrl, {
                forceFormData: true,
            });

            return;
        }

        post(submitUrl, {
            forceFormData: true,
        });
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

    return (
        <>
            <div className="spkDetailShell">
                <div className="spkDetailStack">
                    <form
                        id="jewelcad-form"
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

                            <Form
                                accessibleMode="Edit"
                                layout="S1 M2 L2 XL2"
                                labelSpan="S12 M4 L4 XL4"
                                itemSpacing="Normal"
                            >
                                <FormGroup
                                    headerText="Informasi Request"
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
                                                Operator JewelCAD
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <ComboBox
                                                accessibleName="Operator JewelCAD"
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
                                                Tanggal Request
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
                                        onClick={openAddSpk}
                                    >
                                        Tambah SPK
                                    </Button>
                                </div>

                                <div className="spkTableScroll">
                                    <table className="spkTable masterDataTable">
                                        <thead>
                                            <tr>
                                                <th>SPK</th>
                                                <th className="spkTableColCenter spkTableColTipeProduksi">
                                                    Tipe Produksi
                                                </th>
                                                <th>Berat Emas</th>
                                                <th>Qty</th>
                                                <th>Catatan</th>
                                                <th>Estimasi BRJ</th>
                                                <th className="spkTableActionCol">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {data.details.length === 0 ? (
                                                <tr>
                                                    <td colSpan={7}>
                                                        Belum ada SPK. Klik
                                                        Tambah SPK untuk
                                                        memilih.
                                                    </td>
                                                </tr>
                                            ) : (
                                                detailsByMaterial.map(
                                                    (group) => (
                                                        <Fragment
                                                            key={`group-${group.material}`}
                                                        >
                                                            <tr className="jewelCadMaterialGroupRow">
                                                                <td colSpan={7}>
                                                                    <em>
                                                                        {group.material.toUpperCase()}
                                                                    </em>
                                                                </td>
                                                            </tr>
                                                            {group.rows.map(
                                                                ({
                                                                    detail,
                                                                    index,
                                                                }) => (
                                                                    <tr
                                                                        key={`detail-${detail.spk_id}-${index}`}
                                                                        className="masterDataRowForm"
                                                                    >
                                                                        <td>
                                                                            <div className="spkFioriFieldStack">
                                                                                <strong>
                                                                                    {detail.spk_no ||
                                                                                        '—'}
                                                                                </strong>
                                                                                {detail.material.trim() !==
                                                                                '' ? (
                                                                                    <span className="spkFioriHint">
                                                                                        {
                                                                                            detail.material
                                                                                        }
                                                                                    </span>
                                                                                ) : null}
                                                                                {detailError(
                                                                                    index,
                                                                                    'spk_id',
                                                                                ) ? (
                                                                                    <Text className="spkFioriError">
                                                                                        {detailError(
                                                                                            index,
                                                                                            'spk_id',
                                                                                        )}
                                                                                    </Text>
                                                                                ) : null}
                                                                                {detailError(
                                                                                    index,
                                                                                    'material',
                                                                                ) ? (
                                                                                    <Text className="spkFioriError">
                                                                                        {detailError(
                                                                                            index,
                                                                                            'material',
                                                                                        )}
                                                                                    </Text>
                                                                                ) : null}
                                                                            </div>
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
                                                                            {detail.gold_weight ||
                                                                                '—'}
                                                                        </td>
                                                                        <td>
                                                                            {detail.qty ||
                                                                                '—'}
                                                                        </td>
                                                                        <td>
                                                                            {detail.notes ||
                                                                                '—'}
                                                                        </td>
                                                                        <td>
                                                                            <div className="spkFioriFieldStack">
                                                                                <Input
                                                                                    type="Number"
                                                                                    value={
                                                                                        detail.estimation_brj
                                                                                    }
                                                                                    valueState={fieldState(
                                                                                        detailError(
                                                                                            index,
                                                                                            'estimation_brj',
                                                                                        ),
                                                                                    )}
                                                                                    onInput={(
                                                                                        event,
                                                                                    ) =>
                                                                                        updateDetail(
                                                                                            index,
                                                                                            'estimation_brj',
                                                                                            event
                                                                                                .target
                                                                                                .value ??
                                                                                                '',
                                                                                        )
                                                                                    }
                                                                                />
                                                                                {detailError(
                                                                                    index,
                                                                                    'estimation_brj',
                                                                                ) ? (
                                                                                    <Text className="spkFioriError">
                                                                                        {detailError(
                                                                                            index,
                                                                                            'estimation_brj',
                                                                                        )}
                                                                                    </Text>
                                                                                ) : null}
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div className="spkFioriFieldStack">
                                                                                <Button
                                                                                    design="Default"
                                                                                    type="Button"
                                                                                    onClick={() =>
                                                                                        openEditSpk(
                                                                                            detail,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Ubah
                                                                                </Button>
                                                                                <Button
                                                                                    design="Negative"
                                                                                    type="Button"
                                                                                    onClick={() =>
                                                                                        setData(
                                                                                            'details',
                                                                                            data.details.filter(
                                                                                                (
                                                                                                    _detail,
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
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                ),
                                                            )}
                                                        </Fragment>
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

                            {approvalFooter.length > 0 ? (
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
                                                        {column.name.trim() !==
                                                        ''
                                                            ? column.name
                                                            : '-'}
                                                    </span>
                                                </div>
                                                <div className="spkApprovalFooterMetaRow">
                                                    <span className="spkApprovalFooterMetaLabel">
                                                        Tanggal
                                                    </span>
                                                    <span className="spkApprovalFooterMetaValue">
                                                        {column.date.trim() !==
                                                        ''
                                                            ? column.date
                                                            : '-'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </footer>
                            ) : null}
                        </section>
                    </form>
                </div>
            </div>

            <JewelCadAddSpkDialog
                open={addSpkOpen}
                onOpenChange={(open) => {
                    setAddSpkOpen(open);

                    if (!open) {
                        setEditingDetail(null);
                    }
                }}
                excludeSpkIds={excludeSpkIds}
                editDetail={editingDetail}
                onAdded={handleSpkAdded}
            />
        </>
    );
}
