import { Fragment, useEffect, useId, useMemo, useState } from 'react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Input } from '@ui5/webcomponents-react/Input';
import { Label } from '@ui5/webcomponents-react/Label';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { Text } from '@ui5/webcomponents-react/Text';
import { TextArea } from '@ui5/webcomponents-react/TextArea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatGram } from '@/lib/utils';

export type CoranMaterialOption = {
    value: string;
    label: string;
    stock: string;
};

export type CoranMaterialFormLine = {
    key: string;
    section: string;
    materialgold_id: string;
    weight: string;
    notes: string;
};

type MaterialGroupConfig = {
    section: string;
    label: string;
};

type MaterialBucketConfig = {
    title: string;
    groups: MaterialGroupConfig[];
};

type ModalDraft = {
    section: string;
    materialgold_id: string;
    weight: string;
    notes: string;
};

const BAHAN_BUCKET: MaterialBucketConfig = {
    title: 'Bahan',
    groups: [
        { section: 'bahan_rosegold', label: 'Rose Gold' },
        { section: 'bahan_whitegold', label: 'White Gold' },
        { section: 'bahan_yellowgold', label: 'Yellow Gold' },
    ],
};

const SISA_BUCKET: MaterialBucketConfig = {
    title: 'Sisa',
    groups: [
        { section: 'sisa_rosegold', label: 'Rose Gold' },
        { section: 'sisa_whitegold', label: 'White Gold' },
        { section: 'sisa_yellowgold', label: 'Yellow Gold' },
    ],
};

const EMPTY_DRAFT: ModalDraft = {
    section: '',
    materialgold_id: '',
    weight: '',
    notes: '',
};

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function toNumber(value: string): number {
    const numeric = Number(value.trim().replace(',', '.'));

    return Number.isFinite(numeric) ? numeric : 0;
}

function linesTotal(lines: CoranMaterialFormLine[]): number {
    return lines.reduce((sum, line) => sum + toNumber(line.weight), 0);
}

function resolveMaterialLabel(
    options: CoranMaterialOption[],
    materialId: string,
): string {
    if (materialId.trim() === '') {
        return '';
    }

    return options.find((option) => option.value === materialId)?.label ?? '';
}

function resolveMaterialStock(
    options: CoranMaterialOption[],
    materialId: string,
): number | null {
    if (materialId.trim() === '') {
        return null;
    }

    const option = options.find((item) => item.value === materialId);

    if (!option) {
        return null;
    }

    const stock = Number(option.stock);

    return Number.isFinite(stock) ? stock : null;
}

/**
 * Perkiraan sisa stok setelah transaksi form disimpan:
 * stok saat ini - total bahan (OUT) + total sisa (IN) untuk material yang sama.
 */
function estimatedRemainingStock(
    materials: CoranMaterialFormLine[],
    materialOptions: CoranMaterialOption[],
    materialId: string,
): number | null {
    const stock = resolveMaterialStock(materialOptions, materialId);

    if (stock === null) {
        return null;
    }

    let bahanTotal = 0;
    let sisaTotal = 0;

    for (const line of materials) {
        if (line.materialgold_id !== materialId) {
            continue;
        }

        const weight = toNumber(line.weight);

        if (line.section.startsWith('bahan_')) {
            bahanTotal += weight;
        } else if (line.section.startsWith('sisa_')) {
            sisaTotal += weight;
        }
    }

    return stock - bahanTotal + sisaTotal;
}

function MaterialGoldComboBox({
    accessibleName,
    value,
    options,
    error,
    disabled,
    onChange,
}: {
    accessibleName: string;
    value: string;
    options: CoranMaterialOption[];
    error?: string;
    disabled?: boolean;
    onChange: (materialId: string) => void;
}) {
    const selectedLabel = resolveMaterialLabel(options, value);
    const [text, setText] = useState(selectedLabel);

    useEffect(() => {
        setText(selectedLabel);
    }, [selectedLabel, value]);

    const clearSelection = () => {
        setText('');
        onChange('');
    };

    const selectMaterial = (materialId: string, label: string) => {
        setText(label);
        onChange(materialId);
    };

    return (
        <ComboBox
            accessibleName={accessibleName}
            className="spkCoranMaterialComboBox"
            placeholder="Cari / pilih bahan emas"
            filter="Contains"
            showClearIcon
            noTypeahead
            disabled={disabled}
            value={text}
            valueState={fieldState(error)}
            onInput={(event) => {
                const nextText = event.target.value ?? '';
                setText(nextText);

                if (!nextText.trim()) {
                    clearSelection();

                    return;
                }

                if (selectedLabel && selectedLabel !== nextText) {
                    onChange('');
                }
            }}
            onSelectionChange={(event) => {
                const item = event.detail.item;

                if (!item?.value) {
                    return;
                }

                selectMaterial(String(item.value), item.text ?? '');
            }}
            onChange={(event) => {
                const typed = event.target.value?.trim() ?? '';

                if (!typed) {
                    clearSelection();

                    return;
                }

                const matched = options.find(
                    (option) =>
                        option.label.toLowerCase() === typed.toLowerCase(),
                );

                if (matched) {
                    selectMaterial(matched.value, matched.label);

                    return;
                }

                setText(typed);
                onChange('');
            }}
        >
            {options.map((option) => (
                <ComboBoxItem
                    key={option.value}
                    text={option.label}
                    additionalText={`Stok: ${formatGram(Number(option.stock))}`}
                    value={option.value}
                />
            ))}
        </ComboBox>
    );
}

function MaterialBucketTable({
    bucket,
    materials,
    materialOptions,
    errors,
    disabled,
    onAddRequest,
    onRemove,
}: {
    bucket: MaterialBucketConfig;
    materials: CoranMaterialFormLine[];
    materialOptions: CoranMaterialOption[];
    errors: Record<string, string>;
    disabled?: boolean;
    onAddRequest: () => void;
    onRemove: (key: string) => void;
}) {
    const bucketLines = materials.filter((line) =>
        bucket.groups.some((group) => group.section === line.section),
    );
    const total = linesTotal(bucketLines);

    return (
        <div className="spkCoranMaterialEditorPanel">
            <div className="spkCoranMaterialEditorPanelHeader">
                <Button
                    design="Emphasized"
                    icon={addIcon}
                    type="Button"
                    disabled={disabled}
                    accessibleName={`Tambah ${bucket.title}`}
                    onClick={onAddRequest}
                >
                    Tambah {bucket.title}
                </Button>
            </div>

            <table className="spkCoranMaterialEditorTable">
                <thead>
                    <tr>
                        <th scope="col">Bahan emas</th>
                        <th scope="col">Berat (g)</th>
                        <th scope="col" className="spkTableActionCol">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {bucket.groups.map((group) => {
                        const groupLines = materials.filter(
                            (line) => line.section === group.section,
                        );
                        const groupTotal = linesTotal(groupLines);

                        return (
                            <Fragment key={group.section}>
                                <tr className="spkCoranCategoryRow spkCoranMaterialEditorGroupRow">
                                    <th scope="row">{group.label}</th>
                                    <td>{formatGram(groupTotal)}</td>
                                    <td />
                                </tr>
                                {groupLines.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={3}
                                            className="spkCoranBreakdownEmpty"
                                        >
                                            Tidak ada data
                                        </td>
                                    </tr>
                                ) : (
                                    groupLines.map((line) => {
                                        const materialLabel =
                                            resolveMaterialLabel(
                                                materialOptions,
                                                line.materialgold_id,
                                            ) || 'Bahan';
                                        const remainingStock =
                                            estimatedRemainingStock(
                                                materials,
                                                materialOptions,
                                                line.materialgold_id,
                                            );
                                        const materialError =
                                            errors[
                                                `materials.${line.key}.materialgold_id`
                                            ];
                                        const weightError =
                                            errors[
                                                `materials.${line.key}.weight`
                                            ];

                                        return (
                                            <tr
                                                key={line.key}
                                                className="spkCoranLineRow"
                                            >
                                                <td>
                                                    <div className="spkCoranMaterialLineMain">
                                                        <span>
                                                            {materialLabel}
                                                        </span>
                                                        {line.notes.trim() !==
                                                        '' ? (
                                                            <span className="spkCoranMaterialLineNotes">
                                                                {line.notes}
                                                            </span>
                                                        ) : null}
                                                        {remainingStock !==
                                                        null ? (
                                                            <span className="spkCoranMaterialLineStock">
                                                                Perkiraan sisa
                                                                stok:{' '}
                                                                {formatGram(
                                                                    remainingStock,
                                                                )}
                                                            </span>
                                                        ) : null}
                                                        {materialError ? (
                                                            <Text className="spkFioriError">
                                                                {materialError}
                                                            </Text>
                                                        ) : null}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div className="spkFioriFieldStack">
                                                        <span>
                                                            {formatGram(
                                                                toNumber(
                                                                    line.weight,
                                                                ),
                                                            )}
                                                        </span>
                                                        {weightError ? (
                                                            <Text className="spkFioriError">
                                                                {weightError}
                                                            </Text>
                                                        ) : null}
                                                    </div>
                                                </td>
                                                <td className="spkTableActionCol">
                                                    <Button
                                                        design="Transparent"
                                                        icon={deleteIcon}
                                                        type="Button"
                                                        disabled={disabled}
                                                        accessibleName="Hapus bahan"
                                                        onClick={() =>
                                                            onRemove(line.key)
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </Fragment>
                        );
                    })}
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">Total</th>
                        <td>{formatGram(total)}</td>
                        <td />
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

export function CoranMaterialEditor({
    materials,
    materialOptions,
    errors,
    disabled,
    onChange,
}: {
    materials: CoranMaterialFormLine[];
    materialOptions: CoranMaterialOption[];
    errors: Record<string, string>;
    disabled?: boolean;
    onChange: (materials: CoranMaterialFormLine[]) => void;
}) {
    const idPrefix = useId();
    const [modalOpen, setModalOpen] = useState(false);
    const [modalBucket, setModalBucket] =
        useState<MaterialBucketConfig>(BAHAN_BUCKET);
    const [draft, setDraft] = useState<ModalDraft>(EMPTY_DRAFT);
    const [draftErrors, setDraftErrors] = useState<{
        section?: string;
        materialgold_id?: string;
        weight?: string;
    }>({});

    const errorMap = useMemo(() => {
        const mapped: Record<string, string> = { ...errors };

        materials.forEach((line, index) => {
            const materialError = errors[`materials.${index}.materialgold_id`];
            const weightError = errors[`materials.${index}.weight`];
            const sectionError = errors[`materials.${index}.section`];

            if (materialError) {
                mapped[`materials.${line.key}.materialgold_id`] = materialError;
            }

            if (weightError) {
                mapped[`materials.${line.key}.weight`] = weightError;
            }

            if (sectionError) {
                mapped[`materials.${line.key}.section`] = sectionError;
            }
        });

        return mapped;
    }, [errors, materials]);

    const openAddModal = (bucket: MaterialBucketConfig) => {
        setModalBucket(bucket);
        setDraft({
            ...EMPTY_DRAFT,
            section: bucket.groups[0]?.section ?? '',
        });
        setDraftErrors({});
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setDraft(EMPTY_DRAFT);
        setDraftErrors({});
    };

    const submitModal = () => {
        const nextErrors: {
            section?: string;
            materialgold_id?: string;
            weight?: string;
        } = {};

        if (
            draft.section.trim() === '' ||
            !modalBucket.groups.some((group) => group.section === draft.section)
        ) {
            nextErrors.section = 'Warna bahan emas wajib dipilih.';
        }

        if (draft.materialgold_id.trim() === '') {
            nextErrors.materialgold_id = 'Jenis bahan emas wajib dipilih.';
        }

        const weight = draft.weight.trim().replace(',', '.');

        if (
            weight === '' ||
            Number.isNaN(Number(weight)) ||
            Number(weight) < 0
        ) {
            nextErrors.weight = 'Gramasi wajib diisi dengan angka valid.';
        }

        if (Object.keys(nextErrors).length > 0) {
            setDraftErrors(nextErrors);

            return;
        }

        onChange([
            ...materials,
            {
                key: `${idPrefix}-${draft.section}-${Date.now()}-${materials.length}`,
                section: draft.section,
                materialgold_id: draft.materialgold_id,
                weight,
                notes: draft.notes.trim(),
            },
        ]);

        closeModal();
    };

    const removeLine = (key: string) => {
        onChange(materials.filter((line) => line.key !== key));
    };

    return (
        <div className="spkFioriDetailBlock">
            <div className="spkStoneCardHeader">
                <div className="spkFioriDetailBlockTitle">
                    Detail Transaksi Bahan
                </div>
            </div>

            {errors.materials ? (
                <Text className="spkFioriError">{errors.materials}</Text>
            ) : null}

            <div className="spkCoranMaterialEditorSplit">
                <MaterialBucketTable
                    bucket={BAHAN_BUCKET}
                    materials={materials}
                    materialOptions={materialOptions}
                    errors={errorMap}
                    disabled={disabled}
                    onAddRequest={() => openAddModal(BAHAN_BUCKET)}
                    onRemove={removeLine}
                />
                <MaterialBucketTable
                    bucket={SISA_BUCKET}
                    materials={materials}
                    materialOptions={materialOptions}
                    errors={errorMap}
                    disabled={disabled}
                    onAddRequest={() => openAddModal(SISA_BUCKET)}
                    onRemove={removeLine}
                />
            </div>

            <Dialog
                open={modalOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
            >
                <DialogContent className="spkCoranMaterialDialog">
                    <DialogHeader>
                        <DialogTitle>Tambah {modalBucket.title}</DialogTitle>
                        <DialogDescription>
                            Isi warna, jenis bahan emas, gramasi, dan catatan
                            transaksi.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="spkCoranMaterialDialogForm">
                        <div className="spkFioriFieldStack">
                            <Label showColon required>
                                Warna
                            </Label>
                            <Select
                                accessibleName="Warna bahan emas"
                                disabled={disabled}
                                valueState={fieldState(draftErrors.section)}
                                onChange={(event) => {
                                    setDraft((current) => ({
                                        ...current,
                                        section:
                                            event.detail.selectedOption.value ??
                                            '',
                                    }));
                                    setDraftErrors((current) => ({
                                        ...current,
                                        section: undefined,
                                    }));
                                }}
                            >
                                {modalBucket.groups.map((group) => (
                                    <Option
                                        key={group.section}
                                        value={group.section}
                                        selected={
                                            draft.section === group.section
                                        }
                                    >
                                        {group.label}
                                    </Option>
                                ))}
                            </Select>
                            {draftErrors.section ? (
                                <Text className="spkFioriError">
                                    {draftErrors.section}
                                </Text>
                            ) : null}
                        </div>

                        <div className="spkFioriFieldStack">
                            <Label showColon required>
                                Jenis bahan emas
                            </Label>
                            <MaterialGoldComboBox
                                accessibleName="Jenis bahan emas"
                                value={draft.materialgold_id}
                                options={materialOptions}
                                error={draftErrors.materialgold_id}
                                disabled={disabled}
                                onChange={(materialId) => {
                                    setDraft((current) => ({
                                        ...current,
                                        materialgold_id: materialId,
                                    }));
                                    setDraftErrors((current) => ({
                                        ...current,
                                        materialgold_id: undefined,
                                    }));
                                }}
                            />
                            {draftErrors.materialgold_id ? (
                                <Text className="spkFioriError">
                                    {draftErrors.materialgold_id}
                                </Text>
                            ) : null}
                        </div>

                        <div className="spkFioriFieldStack">
                            <Label showColon required>
                                Gramasi
                            </Label>
                            <Input
                                type="Number"
                                accessibleName="Gramasi"
                                placeholder="0.000"
                                value={draft.weight}
                                disabled={disabled}
                                valueState={fieldState(draftErrors.weight)}
                                onInput={(event) => {
                                    setDraft((current) => ({
                                        ...current,
                                        weight: event.target.value ?? '',
                                    }));
                                    setDraftErrors((current) => ({
                                        ...current,
                                        weight: undefined,
                                    }));
                                }}
                            />
                            {draftErrors.weight ? (
                                <Text className="spkFioriError">
                                    {draftErrors.weight}
                                </Text>
                            ) : null}
                        </div>

                        <div className="spkFioriFieldStack">
                            <Label showColon>Catatan</Label>
                            <TextArea
                                accessibleName="Catatan"
                                placeholder="Catatan bahan emas"
                                rows={3}
                                value={draft.notes}
                                disabled={disabled}
                                onInput={(event) =>
                                    setDraft((current) => ({
                                        ...current,
                                        notes: event.target.value ?? '',
                                    }))
                                }
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            design="Transparent"
                            type="Button"
                            disabled={disabled}
                            onClick={closeModal}
                        >
                            Batal
                        </Button>
                        <Button
                            design="Emphasized"
                            type="Button"
                            disabled={disabled}
                            onClick={submitModal}
                        >
                            Tambah
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
