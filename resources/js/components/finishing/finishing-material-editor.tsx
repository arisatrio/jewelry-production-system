import { useEffect, useMemo, useState } from 'react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Input } from '@ui5/webcomponents-react/Input';
import { Label } from '@ui5/webcomponents-react/Label';
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

export type FinishingMaterialOption = {
    value: string;
    label: string;
    stock: string;
};

export type FinishingMaterialFormLine = {
    key: string;
    section: 'bahan' | 'sisa';
    materialgold_id: string;
    weight: string;
    notes: string;
};

type ModalDraft = {
    section: 'bahan' | 'sisa';
    materialgold_id: string;
    weight: string;
    notes: string;
};

const EMPTY_DRAFT: ModalDraft = {
    section: 'bahan',
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

function linesTotal(lines: FinishingMaterialFormLine[]): number {
    return lines.reduce((sum, line) => sum + toNumber(line.weight), 0);
}

function resolveMaterialLabel(
    options: FinishingMaterialOption[],
    materialId: string,
): string {
    if (materialId.trim() === '') {
        return '';
    }

    return options.find((option) => option.value === materialId)?.label ?? '';
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
    options: FinishingMaterialOption[];
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
    title,
    section,
    materials,
    materialOptions,
    errors,
    disabled,
    onAddRequest,
    onRemove,
}: {
    title: string;
    section: 'bahan' | 'sisa';
    materials: FinishingMaterialFormLine[];
    materialOptions: FinishingMaterialOption[];
    errors: Record<string, string>;
    disabled?: boolean;
    onAddRequest: () => void;
    onRemove: (key: string) => void;
}) {
    const bucketLines = materials.filter((line) => line.section === section);
    const total = linesTotal(bucketLines);

    return (
        <div className="spkCoranMaterialEditorPanel">
            <div className="spkCoranMaterialEditorPanelHeader">
                <Button
                    design="Emphasized"
                    icon={addIcon}
                    type="Button"
                    disabled={disabled}
                    accessibleName={`Tambah ${title}`}
                    onClick={onAddRequest}
                >
                    Tambah {title}
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
                    {bucketLines.length === 0 ? (
                        <tr>
                            <td colSpan={3} className="spkCoranBreakdownEmpty">
                                Tidak ada data
                            </td>
                        </tr>
                    ) : (
                        bucketLines.map((line) => {
                            const index = materials.findIndex(
                                (item) => item.key === line.key,
                            );
                            const materialLabel =
                                resolveMaterialLabel(
                                    materialOptions,
                                    line.materialgold_id,
                                ) || 'Bahan';
                            const materialError =
                                errors[`materials.${index}.materialgold_id`];
                            const weightError =
                                errors[`materials.${index}.weight`];

                            return (
                                <tr
                                    key={line.key}
                                    className="spkCoranLineRow"
                                >
                                    <td>
                                        <div className="spkCoranMaterialLineMain">
                                            <span>{materialLabel}</span>
                                            {line.notes.trim() !== '' ? (
                                                <span className="spkCoranMaterialLineNotes">
                                                    {line.notes}
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
                                                    toNumber(line.weight),
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
                                            onClick={() => onRemove(line.key)}
                                        />
                                    </td>
                                </tr>
                            );
                        })
                    )}
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

type FinishingMaterialEditorProps = {
    materials: FinishingMaterialFormLine[];
    materialOptions: FinishingMaterialOption[];
    errors: Record<string, string>;
    disabled?: boolean;
    onChange: (materials: FinishingMaterialFormLine[]) => void;
};

export function FinishingMaterialEditor({
    materials,
    materialOptions,
    errors,
    disabled = false,
    onChange,
}: FinishingMaterialEditorProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [draft, setDraft] = useState<ModalDraft>(EMPTY_DRAFT);
    const [draftErrors, setDraftErrors] = useState<{
        materialgold_id?: string;
        weight?: string;
    }>({});

    const stockById = useMemo(
        () =>
            new Map(
                materialOptions.map((option) => [option.value, option.stock]),
            ),
        [materialOptions],
    );

    const openAdd = (section: 'bahan' | 'sisa') => {
        setDraft({ ...EMPTY_DRAFT, section });
        setDraftErrors({});
        setModalOpen(true);
    };

    const handleSaveDraft = () => {
        const nextErrors: { materialgold_id?: string; weight?: string } = {};

        if (draft.materialgold_id.trim() === '') {
            nextErrors.materialgold_id = 'Bahan emas wajib dipilih.';
        }

        if (draft.weight.trim() === '') {
            nextErrors.weight = 'Berat wajib diisi.';
        } else if (!Number.isFinite(toNumber(draft.weight))) {
            nextErrors.weight = 'Berat harus berupa angka.';
        }

        if (Object.keys(nextErrors).length > 0) {
            setDraftErrors(nextErrors);

            return;
        }

        onChange([
            ...materials,
            {
                key: `material-${Date.now()}-${draft.section}-${draft.materialgold_id}`,
                section: draft.section,
                materialgold_id: draft.materialgold_id,
                weight: draft.weight.trim().replace(',', '.'),
                notes: draft.notes.trim(),
            },
        ]);
        setModalOpen(false);
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
                    title="Bahan"
                    section="bahan"
                    materials={materials}
                    materialOptions={materialOptions}
                    errors={errors}
                    disabled={disabled}
                    onAddRequest={() => openAdd('bahan')}
                    onRemove={(key) =>
                        onChange(materials.filter((line) => line.key !== key))
                    }
                />
                <MaterialBucketTable
                    title="Sisa"
                    section="sisa"
                    materials={materials}
                    materialOptions={materialOptions}
                    errors={errors}
                    disabled={disabled}
                    onAddRequest={() => openAdd('sisa')}
                    onRemove={(key) =>
                        onChange(materials.filter((line) => line.key !== key))
                    }
                />
            </div>

            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="spkCoranMaterialDialog">
                    <DialogHeader>
                        <DialogTitle>
                            Tambah{' '}
                            {draft.section === 'bahan' ? 'Bahan' : 'Sisa'}
                        </DialogTitle>
                        <DialogDescription>
                            Pilih bahan emas dan isi berat untuk batch finishing.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="spkFioriFieldStack">
                        <Label showColon required>
                            Bahan emas
                        </Label>
                        <MaterialGoldComboBox
                            accessibleName="Bahan emas finishing"
                            value={draft.materialgold_id}
                            options={materialOptions}
                            error={draftErrors.materialgold_id}
                            disabled={disabled}
                            onChange={(materialId) =>
                                setDraft((current) => ({
                                    ...current,
                                    materialgold_id: materialId,
                                }))
                            }
                        />
                        {draft.materialgold_id ? (
                            <Text>
                                Stok:{' '}
                                {formatGram(
                                    Number(
                                        stockById.get(draft.materialgold_id) ??
                                            0,
                                    ),
                                )}
                            </Text>
                        ) : null}
                        {draftErrors.materialgold_id ? (
                            <Text className="spkFioriError">
                                {draftErrors.materialgold_id}
                            </Text>
                        ) : null}
                    </div>

                    <div className="spkFioriFieldStack">
                        <Label showColon required>
                            Berat (g)
                        </Label>
                        <Input
                            type="Number"
                            accessibleName="Berat bahan emas"
                            value={draft.weight}
                            valueState={fieldState(draftErrors.weight)}
                            disabled={disabled}
                            onInput={(event) =>
                                setDraft((current) => ({
                                    ...current,
                                    weight: event.target.value ?? '',
                                }))
                            }
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
                            accessibleName="Catatan bahan emas"
                            value={draft.notes}
                            rows={3}
                            disabled={disabled}
                            onInput={(event) =>
                                setDraft((current) => ({
                                    ...current,
                                    notes: event.target.value ?? '',
                                }))
                            }
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            design="Default"
                            type="Button"
                            onClick={() => setModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            design="Emphasized"
                            type="Button"
                            disabled={disabled}
                            onClick={handleSaveDraft}
                        >
                            Tambah
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
