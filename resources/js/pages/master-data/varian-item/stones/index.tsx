import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Input } from '@ui5/webcomponents-react/Input';
import { Text } from '@ui5/webcomponents-react/Text';
import { fieldState } from '@/components/fiori/fiori-form-utils';
import { index as varianItemIndex } from '@/routes/master-data/varian-item';
import {
    destroy as destroyStone,
    store as storeStone,
    update as updateStone,
} from '@/routes/master-data/varian-item/stones';

type Option = {
    id: number;
    name: string;
};

type VarianceDetail = {
    id: number;
    name: string | null;
    itemName: string | null;
    diameter: string | null;
    dimensi: string | null;
    ringSize: string | null;
    goldWeight: string | null;
    goldColor: string | null;
    jwcad3d: string | null;
};

type StoneRow = {
    id: number;
    positionId: number | null;
    positionName: string | null;
    shapeId: number | null;
    shapeName: string | null;
    pcs: number | null;
    caratPerPcs: string | null;
    totalCarat: string | null;
    size: string | null;
};

type StoneFormValues = {
    position_id: string;
    position_nama: string;
    shape_id: string;
    pcs: string;
    carat_per_pcs: string;
    size: string;
};

type StonesIndexProps = {
    variance: VarianceDetail;
    stones: StoneRow[];
    shapeOptions: Option[];
    positionOptions: Option[];
};

const emptyForm: StoneFormValues = {
    position_id: '',
    position_nama: '',
    shape_id: '',
    pcs: '',
    carat_per_pcs: '',
    size: '',
};

function resolvePositionLabel(
    values: Pick<StoneFormValues, 'position_id' | 'position_nama'>,
    positionOptions: Option[],
): string {
    if (values.position_nama) {
        return values.position_nama;
    }

    if (!values.position_id) {
        return '';
    }

    return (
        positionOptions.find((item) => String(item.id) === values.position_id)
            ?.name ?? ''
    );
}

function resolveShapeLabel(
    shapeId: string,
    shapeOptions: Option[],
): string {
    if (!shapeId) {
        return '';
    }

    return (
        shapeOptions.find((item) => String(item.id) === shapeId)?.name ?? ''
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <Text className="masterDataRowError">{message}</Text>;
}

function formatTotalCarat(pcs: string, caratPerPcs: string): string {
    if (pcs === '' || caratPerPcs === '') {
        return '—';
    }

    const pcsValue = Number(pcs);
    const caratValue = Number(caratPerPcs);

    if (!Number.isFinite(pcsValue) || !Number.isFinite(caratValue)) {
        return '—';
    }

    return (pcsValue * caratValue).toFixed(3);
}

function formatDetailValue(
    label: string,
    value: string | null | undefined,
): string {
    const trimmed = value?.trim() ?? '';

    if (!trimmed) {
        return '—';
    }

    if (
        (label === 'Diameter' || label === 'Dimensi PxL') &&
        !/\bmm\b/i.test(trimmed)
    ) {
        return `${trimmed} mm`;
    }

    if (label === 'Berat Emas' && !/\bg\b/i.test(trimmed)) {
        return `${trimmed} g`;
    }

    return trimmed;
}

function sizeDetailRows(variance: VarianceDetail): Array<{
    label: string;
    value: string | null;
}> {
    const rows: Array<{ label: string; value: string | null }> = [];

    if (variance.diameter?.trim()) {
        rows.push({ label: 'Diameter', value: variance.diameter });
    }

    if (variance.dimensi?.trim()) {
        rows.push({ label: 'Dimensi PxL', value: variance.dimensi });
    }

    if (variance.ringSize?.trim()) {
        rows.push({ label: 'Ring Size', value: variance.ringSize });
    }

    if (rows.length === 0) {
        return [{ label: 'Dimensi PxL', value: null }];
    }

    return rows;
}

function VarianceDetailCard({ variance }: { variance: VarianceDetail }) {
    const rows = [
        { label: 'Tipe Item', value: variance.itemName },
        { label: 'Nama Varian', value: variance.name },
        ...sizeDetailRows(variance),
        { label: 'Berat Emas', value: variance.goldWeight },
        { label: 'Warna Emas', value: variance.goldColor },
        { label: 'File JewelCAD 3D', value: variance.jwcad3d },
    ];

    return (
        <div className="fioriVarianceDetailCard">
            <Text className="fioriVarianceDetailTitle">Detail Varian</Text>
            <dl className="fioriVarianceDetailGrid">
                {rows.map((row) => (
                    <div key={row.label} className="fioriVarianceDetailRow">
                        <dt>{row.label}</dt>
                        <dd>{formatDetailValue(row.label, row.value)}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

function StoneFormCells({
    values,
    onChange,
    onChangeMany,
    errors,
    shapeOptions,
    positionOptions,
}: {
    values: StoneFormValues;
    onChange: (field: keyof StoneFormValues, value: string) => void;
    onChangeMany: (data: Partial<StoneFormValues>) => void;
    errors: Partial<Record<keyof StoneFormValues, string>>;
    shapeOptions: Option[];
    positionOptions: Option[];
}) {
    const [positionText, setPositionText] = useState(() =>
        resolvePositionLabel(values, positionOptions),
    );
    const [shapeText, setShapeText] = useState(() =>
        resolveShapeLabel(values.shape_id, shapeOptions),
    );

    const selectExistingPosition = (nextId: string, nextLabel?: string) => {
        const matched = positionOptions.find(
            (item) => String(item.id) === nextId,
        );

        onChangeMany({
            position_id: nextId,
            position_nama: '',
        });
        setPositionText(nextLabel ?? matched?.name ?? '');
    };

    const selectNewPosition = (typedName: string) => {
        const nama = typedName.trim();

        onChangeMany({
            position_id: '',
            position_nama: nama,
        });
        setPositionText(nama);
    };

    const clearPosition = () => {
        onChangeMany({
            position_id: '',
            position_nama: '',
        });
        setPositionText('');
    };

    const selectShape = (nextId: string, nextLabel?: string) => {
        const matched = shapeOptions.find((item) => String(item.id) === nextId);

        onChange('shape_id', nextId);
        setShapeText(nextLabel ?? matched?.name ?? '');
    };

    const clearShape = () => {
        onChange('shape_id', '');
        setShapeText('');
    };

    return (
        <>
            <td>
                <div className="masterDataRowComboStack">
                    <ComboBox
                        accessibleName="Posisi"
                        className="masterDataRowControl masterDataRowControlWide"
                        placeholder="Cari / ketik posisi baru"
                        filter="Contains"
                        showClearIcon
                        noTypeahead
                        value={positionText}
                        valueState={fieldState(
                            errors.position_id || errors.position_nama,
                        )}
                        onInput={(event) => {
                            const nextText = event.target.value ?? '';
                            setPositionText(nextText);

                            if (!nextText.trim()) {
                                clearPosition();

                                return;
                            }

                            const selected = positionOptions.find(
                                (item) =>
                                    String(item.id) === values.position_id,
                            );

                            if (selected && selected.name !== nextText) {
                                onChangeMany({
                                    position_id: '',
                                    position_nama: '',
                                });
                            }
                        }}
                        onSelectionChange={(event) => {
                            const item = event.detail.item;
                            if (!item?.value) {
                                return;
                            }

                            selectExistingPosition(
                                String(item.value),
                                item.text ?? '',
                            );
                        }}
                        onChange={(event) => {
                            const typed = event.target.value?.trim() ?? '';

                            if (!typed) {
                                clearPosition();

                                return;
                            }

                            const matched = positionOptions.find(
                                (item) =>
                                    item.name.toLowerCase() ===
                                    typed.toLowerCase(),
                            );

                            if (matched) {
                                selectExistingPosition(
                                    String(matched.id),
                                    matched.name,
                                );

                                return;
                            }

                            selectNewPosition(typed);
                        }}
                    >
                        {positionOptions.map((item) => (
                            <ComboBoxItem
                                key={item.id}
                                text={item.name}
                                value={String(item.id)}
                            />
                        ))}
                    </ComboBox>
                    <FieldError
                        message={errors.position_id ?? errors.position_nama}
                    />
                    {values.position_nama ? (
                        <Text className="masterDataRowHint">
                            Posisi baru &quot;{values.position_nama}&quot; akan
                            ditambahkan ke master data saat batu disimpan.
                        </Text>
                    ) : null}
                </div>
            </td>
            <td>
                <div className="masterDataRowComboStack">
                    <ComboBox
                        accessibleName="Bentuk"
                        className="masterDataRowControl masterDataRowControlWide"
                        placeholder="Cari / pilih bentuk"
                        filter="Contains"
                        showClearIcon
                        noTypeahead
                        value={shapeText}
                        valueState={fieldState(errors.shape_id)}
                        onInput={(event) => {
                            const nextText = event.target.value ?? '';
                            setShapeText(nextText);

                            if (!nextText.trim()) {
                                clearShape();

                                return;
                            }

                            const selected = shapeOptions.find(
                                (item) => String(item.id) === values.shape_id,
                            );

                            if (selected && selected.name !== nextText) {
                                onChange('shape_id', '');
                            }
                        }}
                        onSelectionChange={(event) => {
                            const item = event.detail.item;
                            if (!item?.value) {
                                return;
                            }

                            selectShape(String(item.value), item.text ?? '');
                        }}
                        onChange={(event) => {
                            const typed = event.target.value?.trim() ?? '';

                            if (!typed) {
                                clearShape();

                                return;
                            }

                            const matched = shapeOptions.find(
                                (item) =>
                                    item.name.toLowerCase() ===
                                    typed.toLowerCase(),
                            );

                            if (matched) {
                                selectShape(String(matched.id), matched.name);

                                return;
                            }

                            setShapeText(typed);
                            onChange('shape_id', '');
                        }}
                    >
                        {shapeOptions.map((item) => (
                            <ComboBoxItem
                                key={item.id}
                                text={item.name}
                                value={String(item.id)}
                            />
                        ))}
                    </ComboBox>
                    <FieldError message={errors.shape_id} />
                </div>
            </td>
            <td>
                <div className="masterDataRowComboStack">
                    <Input
                        accessibleName="Diameter / Dimensi PxL"
                        className="masterDataRowControl masterDataRowControlNarrow"
                        type="Number"
                        value={values.size}
                        placeholder="mm"
                        valueState={fieldState(errors.size)}
                        onInput={(event) =>
                            onChange('size', event.target.value ?? '')
                        }
                    />
                    <FieldError message={errors.size} />
                </div>
            </td>
            <td>
                <div className="masterDataRowComboStack">
                    <Input
                        accessibleName="Jumlah Butir"
                        className="masterDataRowControl masterDataRowControlNarrow"
                        type="Number"
                        value={values.pcs}
                        placeholder="Jumlah butir"
                        valueState={fieldState(errors.pcs)}
                        onInput={(event) =>
                            onChange('pcs', event.target.value ?? '')
                        }
                    />
                    <FieldError message={errors.pcs} />
                </div>
            </td>
            <td>
                <div className="masterDataRowComboStack">
                    <Input
                        accessibleName="Carat per Pcs"
                        className="masterDataRowControl masterDataRowControlNarrow"
                        type="Number"
                        value={values.carat_per_pcs}
                        placeholder="Crt/pcs"
                        valueState={fieldState(errors.carat_per_pcs)}
                        onInput={(event) =>
                            onChange('carat_per_pcs', event.target.value ?? '')
                        }
                    />
                    <FieldError message={errors.carat_per_pcs} />
                </div>
            </td>
            <td className="masterDataRowReadonly">
                <Text>{formatTotalCarat(values.pcs, values.carat_per_pcs)}</Text>
            </td>
        </>
    );
}

export default function VarianItemStonesIndex({
    variance,
    stones,
    shapeOptions,
    positionOptions,
}: StonesIndexProps) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [createFormKey, setCreateFormKey] = useState(0);

    const createForm = useForm(emptyForm);
    const editForm = useForm(emptyForm);

    const startEdit = (stone: StoneRow) => {
        setEditingId(stone.id);
        editForm.clearErrors();
        editForm.setData({
            position_id:
                stone.positionId !== null ? String(stone.positionId) : '',
            position_nama: '',
            shape_id: stone.shapeId !== null ? String(stone.shapeId) : '',
            pcs: stone.pcs !== null ? String(stone.pcs) : '',
            carat_per_pcs: stone.caratPerPcs ?? '',
            size: stone.size ?? '',
        });
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.clearErrors();
        editForm.reset();
    };

    const submitCreate = () => {
        createForm.post(storeStone.url(variance.id), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                createForm.clearErrors();
                setCreateFormKey((current) => current + 1);
            },
        });
    };

    const submitEdit = () => {
        if (editingId === null) {
            return;
        }

        editForm.put(
            updateStone.url({
                msItemVariance: variance.id,
                msItemVarianceStone: editingId,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingId(null);
                    editForm.reset();
                    editForm.clearErrors();
                },
            },
        );
    };

    const handleDelete = (stone: StoneRow) => {
        if (
            !window.confirm(
                `Hapus batu "${stone.shapeName ?? `#${stone.id}`}"?`,
            )
        ) {
            return;
        }

        router.delete(
            destroyStone.url({
                msItemVariance: variance.id,
                msItemVarianceStone: stone.id,
            }),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head
                title={`Batu Varian · ${variance.name ?? `#${variance.id}`}`}
            />

            <div className="spkTableShell">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">Kelola Batu Varian</h1>
                        <p className="dashPageSubtitle">
                            CRUD batu untuk varian{' '}
                            {variance.name ?? `#${variance.id}`}.
                        </p>
                    </div>
                    <Link
                        href={varianItemIndex.url()}
                        className="masterDataBackLink"
                    >
                        Kembali ke Master Item Product
                    </Link>
                </header>

                <VarianceDetailCard variance={variance} />

                <div className="spkTablePanel">
                    <div className="spkTableScroll">
                        <table className="spkTable masterDataTable">
                            <thead>
                                <tr>
                                    <th>Posisi</th>
                                    <th>Bentuk</th>
                                    <th>Diameter / Dimensi PxL (mm)</th>
                                    <th>Jumlah Butir (pcs)</th>
                                    <th>Carat per Butir (pcs)</th>
                                    <th>Total Carat</th>
                                    <th className="spkTableActionCol">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr className="masterDataRowForm">
                                    <StoneFormCells
                                        key={`create-${createFormKey}`}
                                        values={createForm.data}
                                        onChange={(field, value) =>
                                            createForm.setData(field, value)
                                        }
                                        onChangeMany={(data) =>
                                            createForm.setData({
                                                ...createForm.data,
                                                ...data,
                                            })
                                        }
                                        errors={{
                                            position_id:
                                                createForm.errors.position_id,
                                            position_nama:
                                                createForm.errors.position_nama,
                                            shape_id: createForm.errors.shape_id,
                                            pcs: createForm.errors.pcs,
                                            carat_per_pcs:
                                                createForm.errors.carat_per_pcs,
                                            size: createForm.errors.size,
                                        }}
                                        shapeOptions={shapeOptions}
                                        positionOptions={positionOptions}
                                    />
                                    <td>
                                        <div className="masterDataActions">
                                            <button
                                                type="button"
                                                className="masterDataLinkBtn"
                                                disabled={createForm.processing}
                                                onClick={submitCreate}
                                            >
                                                {createForm.processing
                                                    ? 'Menyimpan...'
                                                    : 'Tambah'}
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                {stones.length === 0 ? (
                                    <tr>
                                        <td colSpan={7}>
                                            Belum ada batu pada varian ini.
                                        </td>
                                    </tr>
                                ) : (
                                    stones.map((stone) =>
                                        editingId === stone.id ? (
                                            <tr
                                                key={stone.id}
                                                className="masterDataRowForm"
                                            >
                                                <StoneFormCells
                                                    key={`edit-${stone.id}`}
                                                    values={editForm.data}
                                                    onChange={(field, value) =>
                                                        editForm.setData(
                                                            field,
                                                            value,
                                                        )
                                                    }
                                                    onChangeMany={(data) =>
                                                        editForm.setData({
                                                            ...editForm.data,
                                                            ...data,
                                                        })
                                                    }
                                                    errors={{
                                                        position_id:
                                                            editForm.errors
                                                                .position_id,
                                                        position_nama:
                                                            editForm.errors
                                                                .position_nama,
                                                        shape_id:
                                                            editForm.errors
                                                                .shape_id,
                                                        pcs: editForm.errors.pcs,
                                                        carat_per_pcs:
                                                            editForm.errors
                                                                .carat_per_pcs,
                                                        size: editForm.errors
                                                            .size,
                                                    }}
                                                    shapeOptions={shapeOptions}
                                                    positionOptions={
                                                        positionOptions
                                                    }
                                                />
                                                <td>
                                                    <div className="masterDataActions">
                                                        <button
                                                            type="button"
                                                            className="masterDataLinkBtn"
                                                            disabled={
                                                                editForm.processing
                                                            }
                                                            onClick={submitEdit}
                                                        >
                                                            {editForm.processing
                                                                ? 'Menyimpan...'
                                                                : 'Simpan'}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="masterDataLinkBtn"
                                                            disabled={
                                                                editForm.processing
                                                            }
                                                            onClick={cancelEdit}
                                                        >
                                                            Batal
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ) : (
                                            <tr key={stone.id}>
                                                <td>
                                                    {stone.positionName ?? '—'}
                                                </td>
                                                <td>
                                                    {stone.shapeName ?? '—'}
                                                </td>
                                                <td>{stone.size ?? '—'}</td>
                                                <td>{stone.pcs ?? '—'}</td>
                                                <td>
                                                    {stone.caratPerPcs ?? '—'}
                                                </td>
                                                <td>
                                                    {stone.totalCarat ?? '—'}
                                                </td>
                                                <td>
                                                    <div className="masterDataActions">
                                                        <button
                                                            type="button"
                                                            className="masterDataLinkBtn"
                                                            disabled={
                                                                editingId !==
                                                                null
                                                            }
                                                            onClick={() =>
                                                                startEdit(stone)
                                                            }
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="masterDataDangerBtn"
                                                            disabled={
                                                                editingId !==
                                                                null
                                                            }
                                                            onClick={() =>
                                                                handleDelete(
                                                                    stone,
                                                                )
                                                            }
                                                        >
                                                            Hapus
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

VarianItemStonesIndex.layout = {
    activeMenu: 'Master Item Product',
    pageTitle: 'Kelola Batu Varian',
};
