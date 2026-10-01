import { Head, useForm } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/master-data/diamond-crt-matrix';

type ShapeOption = {
    id: number;
    code: string | null;
    name: string | null;
};

type MatrixRow = {
    shapeId: number;
    crtMin: string;
    crtMax: string;
};

type DiamondCrtMatrixEditProps = {
    shapes: ShapeOption[];
    rows: MatrixRow[];
    lastUpdate: { by: string | null; at: string } | null;
};

type MatrixFormRow = {
    uid: string;
    shape_id: number;
    crt_min: string;
    crt_max: string;
};

type MatrixFormData = {
    rows: MatrixFormRow[];
};

function shapeLabel(shape: ShapeOption | undefined, shapeId: number): string {
    if (!shape) {
        return `Shape #${shapeId}`;
    }

    const name = shape.name ?? `Shape #${shapeId}`;

    return shape.code ? `${name} (${shape.code})` : name;
}

export default function DiamondCrtMatrixEdit({
    shapes,
    rows,
    lastUpdate,
}: DiamondCrtMatrixEditProps) {
    const uidCounter = useRef(0);
    const nextUid = (): string => {
        uidCounter.current += 1;

        return `row-${uidCounter.current}`;
    };

    const form = useForm<MatrixFormData>({
        rows: rows.map((row, index) => ({
            uid: `saved-${index}`,
            shape_id: row.shapeId,
            crt_min: row.crtMin,
            crt_max: row.crtMax,
        })),
    });
    const [shapeToAdd, setShapeToAdd] = useState('');

    const shapesById = useMemo(
        () => new Map(shapes.map((shape) => [shape.id, shape])),
        [shapes],
    );

    const usedShapeIds = new Set(form.data.rows.map((row) => row.shape_id));
    const availableShapes = shapes.filter(
        (shape) => !usedShapeIds.has(shape.id),
    );

    const updateRow = (
        index: number,
        field: 'crt_min' | 'crt_max',
        value: string,
    ): void => {
        form.setData(
            'rows',
            form.data.rows.map((row, rowIndex) =>
                rowIndex === index ? { ...row, [field]: value } : row,
            ),
        );
    };

    const addRange = (shapeId: number): void => {
        const lastIndex = form.data.rows.findLastIndex(
            (row) => row.shape_id === shapeId,
        );
        const nextRows = [...form.data.rows];

        nextRows.splice(lastIndex + 1, 0, {
            uid: nextUid(),
            shape_id: shapeId,
            crt_min: '',
            crt_max: '',
        });

        form.setData('rows', nextRows);
    };

    const removeRange = (index: number): void => {
        form.setData(
            'rows',
            form.data.rows.filter((_, rowIndex) => rowIndex !== index),
        );
    };

    const removeShape = (shapeId: number): void => {
        const label = shapeLabel(shapesById.get(shapeId), shapeId);

        if (!window.confirm(`Hapus semua range CRT untuk shape ${label}?`)) {
            return;
        }

        form.setData(
            'rows',
            form.data.rows.filter((row) => row.shape_id !== shapeId),
        );
    };

    const addShape = (): void => {
        const shapeId = Number.parseInt(shapeToAdd, 10);

        if (!Number.isFinite(shapeId)) {
            return;
        }

        form.setData('rows', [
            ...form.data.rows,
            { uid: nextUid(), shape_id: shapeId, crt_min: '', crt_max: '' },
        ]);
        setShapeToAdd('');
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.put(update.url(), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    const rowCountByShape = new Map<number, number>();
    const rangeNumbers = form.data.rows.map((row) => {
        const rangeNumber = (rowCountByShape.get(row.shape_id) ?? 0) + 1;
        rowCountByShape.set(row.shape_id, rangeNumber);

        return rangeNumber;
    });
    const shapeCount = usedShapeIds.size;

    return (
        <>
            <Head title="Matrix CRT Dossier" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Matrix CRT Dossier</h1>
                        <p className="masterDataSubtitle">
                            Atur range CRT (min–max) batu dossier untuk setiap
                            shape.
                            {lastUpdate
                                ? ` Terakhir diubah ${lastUpdate.at}${lastUpdate.by ? ` · ${lastUpdate.by}` : ''}.`
                                : ''}
                        </p>
                    </div>
                </div>

                <div className="spkTableCard">
                    <form onSubmit={handleSubmit}>
                        <div className="spkTableToolbar">
                            <div className="spkTableToolbarLeft">
                                <span className="masterDataSubtitle">
                                    <strong>{shapeCount}</strong> shape ·{' '}
                                    <strong>{form.data.rows.length}</strong>{' '}
                                    range
                                </span>
                            </div>
                            <div className="spkTableToolbarRight">
                                <select
                                    className="masterDataSelect"
                                    value={shapeToAdd}
                                    onChange={(event) =>
                                        setShapeToAdd(event.target.value)
                                    }
                                    aria-label="Pilih shape yang akan ditambahkan"
                                    disabled={availableShapes.length === 0}
                                >
                                    <option value="">
                                        {availableShapes.length === 0
                                            ? 'Semua shape sudah ada'
                                            : 'Pilih shape…'}
                                    </option>
                                    {availableShapes.map((shape) => (
                                        <option key={shape.id} value={shape.id}>
                                            {shapeLabel(shape, shape.id)}
                                        </option>
                                    ))}
                                </select>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={shapeToAdd === ''}
                                    onClick={addShape}
                                >
                                    Tambah Shape
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={form.processing || !form.isDirty}
                                    onClick={() => {
                                        form.reset();
                                        form.clearErrors();
                                    }}
                                >
                                    Batalkan
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing || !form.isDirty}
                                >
                                    {form.processing
                                        ? 'Menyimpan...'
                                        : 'Simpan Matrix'}
                                </Button>
                            </div>
                        </div>

                        <InputError message={form.errors.rows} />

                        <div className="spkTableScroll">
                            <table className="spkTable masterDataTable">
                                <thead>
                                    <tr>
                                        <th>Shape</th>
                                        <th>No</th>
                                        <th>CRT Min</th>
                                        <th>CRT Max</th>
                                        <th className="spkTableActionCol">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.rows.length === 0 ? (
                                        <tr>
                                            <td colSpan={5}>
                                                Belum ada range CRT. Tambahkan
                                                shape terlebih dahulu.
                                            </td>
                                        </tr>
                                    ) : (
                                        form.data.rows.map((row, index) => {
                                            const isFirstOfShape =
                                                form.data.rows[index - 1]
                                                    ?.shape_id !== row.shape_id;
                                            const shapeRowCount =
                                                rowCountByShape.get(
                                                    row.shape_id,
                                                ) ?? 1;
                                            const rangeNumber =
                                                rangeNumbers[index];
                                            const label = shapeLabel(
                                                shapesById.get(row.shape_id),
                                                row.shape_id,
                                            );
                                            const minError =
                                                form.errors[
                                                    `rows.${index}.crt_min`
                                                ];
                                            const maxError =
                                                form.errors[
                                                    `rows.${index}.crt_max`
                                                ];
                                            const shapeError =
                                                form.errors[
                                                    `rows.${index}.shape_id`
                                                ];

                                            return (
                                                <tr key={row.uid}>
                                                    {isFirstOfShape ? (
                                                        <td
                                                            rowSpan={
                                                                shapeRowCount
                                                            }
                                                        >
                                                            <strong>
                                                                {label}
                                                            </strong>
                                                            <InputError
                                                                message={
                                                                    shapeError
                                                                }
                                                            />
                                                            <div className="masterDataActions">
                                                                <button
                                                                    type="button"
                                                                    className="masterDataLinkBtn"
                                                                    onClick={() =>
                                                                        addRange(
                                                                            row.shape_id,
                                                                        )
                                                                    }
                                                                >
                                                                    Tambah range
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="masterDataDangerBtn"
                                                                    onClick={() =>
                                                                        removeShape(
                                                                            row.shape_id,
                                                                        )
                                                                    }
                                                                >
                                                                    Hapus shape
                                                                </button>
                                                            </div>
                                                        </td>
                                                    ) : null}
                                                    <td>{rangeNumber}</td>
                                                    <td>
                                                        <Input
                                                            type="number"
                                                            min={0}
                                                            step={0.001}
                                                            required
                                                            className="masterDataSearch"
                                                            value={row.crt_min}
                                                            onChange={(event) =>
                                                                updateRow(
                                                                    index,
                                                                    'crt_min',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            aria-label={`CRT min ${label} range ${rangeNumber}`}
                                                        />
                                                        <InputError
                                                            message={minError}
                                                        />
                                                    </td>
                                                    <td>
                                                        <Input
                                                            type="number"
                                                            min={0}
                                                            step={0.001}
                                                            required
                                                            className="masterDataSearch"
                                                            value={row.crt_max}
                                                            onChange={(event) =>
                                                                updateRow(
                                                                    index,
                                                                    'crt_max',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            aria-label={`CRT max ${label} range ${rangeNumber}`}
                                                        />
                                                        <InputError
                                                            message={maxError}
                                                        />
                                                    </td>
                                                    <td>
                                                        <button
                                                            type="button"
                                                            className="masterDataDangerBtn"
                                                            onClick={() =>
                                                                removeRange(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            Hapus
                                                        </button>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

DiamondCrtMatrixEdit.layout = {
    activeMenu: 'Matrix CRT Dossier',
    pageTitle: 'Matrix CRT Dossier',
};
