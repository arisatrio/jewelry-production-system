import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { FileUploader } from '@ui5/webcomponents-react/FileUploader';
import { FormGroup } from '@ui5/webcomponents-react/FormGroup';
import { Input } from '@ui5/webcomponents-react/Input';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { TextArea } from '@ui5/webcomponents-react/TextArea';
import { FioriFormDialog } from '@/components/fiori/fiori-form-dialog';
import { fieldState } from '@/components/fiori/fiori-form-utils';
import {
    FioriFormField,
    FioriSimpleForm,
} from '@/components/fiori/fiori-simple-form';
import { store, update } from '@/routes/master-data/varian-item';

export type ItemOption = {
    id: number;
    name: string;
};

export type VarianItemStoneSummary = {
    id: number;
    shapeId: number | null;
    shapeName: string | null;
    pcs: number | null;
    caratPerPcs: string | null;
    totalCarat: string | null;
    size: string | null;
};

export type VarianItemRow = {
    id: number;
    itemId: number;
    itemName: string | null;
    name: string | null;
    description: string | null;
    diameter: string | null;
    dimensi: string | null;
    ringSize: string | null;
    goldWeight: string | null;
    goldColor: string | null;
    jwcad3d: string | null;
    image: string | null;
    imageUrl: string | null;
    stones?: VarianItemStoneSummary[];
};

type VarianItemFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    itemOptions: ItemOption[];
    goldColorOptions: string[];
    variance?: VarianItemRow | null;
};

const FORM_ID = 'varian-item-form';

const emptyForm = {
    item_id: '',
    name: '',
    description: '',
    diameter: '',
    dimensi: '',
    ring_size: '',
    gold_weight: '',
    gold_color: '',
    jwcad_3d: '',
    image: null as File | null,
};

export function VarianItemFormDialog({
    open,
    onOpenChange,
    mode,
    itemOptions,
    goldColorOptions,
    variance = null,
}: VarianItemFormDialogProps) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        clearErrors,
        transform,
    } = useForm(emptyForm);
    const [itemTypeText, setItemTypeText] = useState('');
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    const dialogTitle =
        mode === 'edit'
            ? 'Form Edit Master Item Product'
            : 'Form Tambah Master Item Product';

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();
        setPreviewUrl(null);
        transform((form) => form);

        if (mode === 'edit' && variance) {
            setData({
                item_id: String(variance.itemId),
                name: variance.name ?? '',
                description: variance.description ?? '',
                diameter: variance.diameter ?? '',
                dimensi: variance.dimensi ?? '',
                ring_size: variance.ringSize ?? '',
                gold_weight: variance.goldWeight ?? '',
                gold_color: variance.goldColor ?? '',
                jwcad_3d: variance.jwcad3d ?? '',
                image: null,
            });
            setItemTypeText(
                itemOptions.find((item) => item.id === variance.itemId)?.name ??
                    variance.itemName ??
                    '',
            );

            return;
        }

        reset();
        setData(emptyForm);
        setItemTypeText('');
    }, [open, mode, variance]);

    useEffect(() => {
        if (!data.image) {
            setPreviewUrl(null);

            return;
        }

        const objectUrl = URL.createObjectURL(data.image);
        setPreviewUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [data.image]);

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        if (processing) {
            return;
        }

        // Laravel cannot parse multipart bodies on PUT, so spoof via POST + _method.
        if (mode === 'edit' && variance) {
            transform((form) => ({
                ...form,
                _method: 'put',
            }));
            post(update.url(variance.id), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => transform((form) => form),
            });

            return;
        }

        transform((form) => form);
        post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    const currentImageUrl = previewUrl ?? (mode === 'edit' ? variance?.imageUrl : null) ?? null;

    return (
        <FioriFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={dialogTitle}
            formId={FORM_ID}
            processing={processing}
        >
            <FioriSimpleForm id={FORM_ID} onSubmit={handleSubmit}>
                <FormGroup headerText="Informasi Item">
                    <FioriFormField
                        label="Tipe Item"
                        required
                        error={errors.item_id}
                    >
                        <ComboBox
                            accessibleName="Tipe Item"
                            className="fioriSimpleFormControl"
                            placeholder="Cari / pilih tipe item"
                            filter="Contains"
                            showClearIcon
                            noTypeahead
                            value={itemTypeText}
                            selectedValue={data.item_id || undefined}
                            valueState={fieldState(errors.item_id)}
                            onInput={(event) => {
                                setItemTypeText(event.target.value ?? '');
                            }}
                            onSelectionChange={(event) => {
                                const item = event.detail.item;
                                const nextId = item?.value
                                    ? String(item.value)
                                    : '';
                                setData('item_id', nextId);
                                setItemTypeText(item?.text ?? '');
                            }}
                            onChange={(event) => {
                                const nextId = event.target.selectedValue ?? '';
                                setData('item_id', nextId);

                                if (!nextId) {
                                    setItemTypeText('');

                                    return;
                                }

                                const matched = itemOptions.find(
                                    (item) => String(item.id) === nextId,
                                );
                                setItemTypeText(
                                    matched?.name ?? event.target.value ?? '',
                                );
                            }}
                        >
                            {itemOptions.map((item) => (
                                <ComboBoxItem
                                    key={item.id}
                                    text={item.name}
                                    value={String(item.id)}
                                />
                            ))}
                        </ComboBox>
                    </FioriFormField>

                    <FioriFormField
                        label="Nama Varian"
                        required
                        error={errors.name}
                    >
                        <Input
                            accessibleName="Nama Varian"
                            className="fioriSimpleFormControl"
                            value={data.name}
                            placeholder="Masukkan nama varian"
                            maxlength={100}
                            valueState={fieldState(errors.name)}
                            onInput={(event) =>
                                setData('name', event.target.value ?? '')
                            }
                        />
                    </FioriFormField>

                    <FioriFormField
                        label="Berat Emas (g)"
                        required
                        error={errors.gold_weight}
                    >
                        <Input
                            accessibleName="Berat Emas"
                            className="fioriSimpleFormControl"
                            type="Number"
                            value={data.gold_weight}
                            placeholder="Masukkan berat emas"
                            valueState={fieldState(errors.gold_weight)}
                            onInput={(event) =>
                                setData('gold_weight', event.target.value ?? '')
                            }
                        />
                    </FioriFormField>

                    <FioriFormField
                        label="Warna Emas"
                        required
                        error={errors.gold_color}
                    >
                        <Select
                            accessibleName="Warna Emas"
                            className="fioriSimpleFormControl"
                            valueState={fieldState(errors.gold_color)}
                            onChange={(event) =>
                                setData(
                                    'gold_color',
                                    event.detail.selectedOption?.value ?? '',
                                )
                            }
                        >
                            <Option value="" selected={data.gold_color === ''}>
                                Pilih warna emas
                            </Option>
                            {goldColorOptions.map((color) => (
                                <Option
                                    key={color}
                                    value={color}
                                    selected={data.gold_color === color}
                                >
                                    {color}
                                </Option>
                            ))}
                        </Select>
                    </FioriFormField>

                    <FioriFormField label="Gambar" error={errors.image}>
                        <div className="masterDataImageUpload">
                            {currentImageUrl ? (
                                <img
                                    src={currentImageUrl}
                                    alt={
                                        variance?.name
                                            ? `Gambar ${variance.name}`
                                            : 'Preview gambar varian'
                                    }
                                    className="masterDataImagePreview"
                                />
                            ) : null}
                            <FileUploader
                                accept=".jpg,.jpeg,.png,.webp"
                                placeholder="Upload gambar"
                                valueState={fieldState(errors.image)}
                                onChange={(event) => {
                                    const files = event.target.files;
                                    setData('image', files?.[0] ?? null);
                                }}
                            />
                            {mode === 'edit' &&
                            variance?.imageUrl &&
                            !data.image ? (
                                <span className="masterDataMuted">
                                    Biarkan kosong untuk mempertahankan gambar
                                    saat ini.
                                </span>
                            ) : null}
                        </div>
                    </FioriFormField>

                    <FioriFormField
                        label="File JewelCAD 3D"
                        error={errors.jwcad_3d}
                    >
                        <Input
                            accessibleName="JewelCAD 3D"
                            className="fioriSimpleFormControl"
                            value={data.jwcad_3d}
                            placeholder="Masukkan nama file JewelCAD 3D"
                            maxlength={100}
                            valueState={fieldState(errors.jwcad_3d)}
                            onInput={(event) =>
                                setData('jwcad_3d', event.target.value ?? '')
                            }
                        />
                    </FioriFormField>

                    <FioriFormField
                        label="Deskripsi"
                        error={errors.description}
                    >
                        <TextArea
                            accessibleName="Deskripsi"
                            className="fioriSimpleFormControl"
                            value={data.description}
                            placeholder="Masukkan deskripsi"
                            rows={3}
                            growing={false}
                            valueState={fieldState(errors.description)}
                            onInput={(event) =>
                                setData('description', event.target.value ?? '')
                            }
                        />
                    </FioriFormField>
                </FormGroup>

                <FormGroup headerText="Ukuran">
                    <FioriFormField
                        label="Diameter (mm)"
                        error={errors.diameter}
                    >
                        <Input
                            accessibleName="Diameter"
                            className="fioriSimpleFormControl"
                            value={data.diameter}
                            placeholder="Masukkan diameter"
                            maxlength={100}
                            valueState={fieldState(errors.diameter)}
                            onInput={(event) =>
                                setData('diameter', event.target.value ?? '')
                            }
                        />
                    </FioriFormField>

                    <FioriFormField
                        label="Dimensi PxL (mm)"
                        error={errors.dimensi}
                    >
                        <Input
                            accessibleName="Dimensi"
                            className="fioriSimpleFormControl"
                            value={data.dimensi}
                            placeholder="Masukkan dimensi"
                            maxlength={100}
                            valueState={fieldState(errors.dimensi)}
                            onInput={(event) =>
                                setData('dimensi', event.target.value ?? '')
                            }
                        />
                    </FioriFormField>

                    <FioriFormField
                        label="Ring Size"
                        error={errors.ring_size}
                    >
                        <Input
                            accessibleName="Ring Size"
                            className="fioriSimpleFormControl"
                            value={data.ring_size}
                            placeholder="Masukkan ring size"
                            maxlength={100}
                            valueState={fieldState(errors.ring_size)}
                            onInput={(event) =>
                                setData('ring_size', event.target.value ?? '')
                            }
                        />
                    </FioriFormField>
                </FormGroup>
            </FioriSimpleForm>
        </FioriFormDialog>
    );
}
