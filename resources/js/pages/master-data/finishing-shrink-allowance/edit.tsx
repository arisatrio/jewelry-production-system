import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/master-data/finishing-shrink-allowance';

type AllowanceRow = {
    workCategory: string;
    workType: string;
    percents: Record<string, string>;
};

type FinishingShrinkAllowanceEditProps = {
    itemCategories: string[];
    rows: AllowanceRow[];
    lastUpdate: { by: string | null; at: string } | null;
};

type AllowanceCell = {
    work_type: string;
    item_category: string;
    allowance_percent: string;
};

function buildCells(
    rows: AllowanceRow[],
    itemCategories: string[],
): AllowanceCell[] {
    return rows.flatMap((row) =>
        itemCategories.map((itemCategory) => ({
            work_type: row.workType,
            item_category: itemCategory,
            allowance_percent: row.percents[itemCategory] ?? '',
        })),
    );
}

function isSmallCategory(itemCategory: string): boolean {
    return itemCategory.startsWith('Barang Kecil');
}

export default function FinishingShrinkAllowanceEdit({
    itemCategories,
    rows,
    lastUpdate,
}: FinishingShrinkAllowanceEditProps) {
    const form = useForm<{ cells: AllowanceCell[] }>({
        cells: buildCells(rows, itemCategories),
    });

    const updatePercent = (
        rowIndex: number,
        categoryIndex: number,
        value: string,
    ): void => {
        const index = rowIndex * itemCategories.length + categoryIndex;

        form.setData(
            'cells',
            form.data.cells.map((cell, cellIndex) =>
                cellIndex === index
                    ? { ...cell, allowance_percent: value }
                    : cell,
            ),
        );
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.put(update.url(), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Jatah Susut Finishing" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">
                            Jatah Susut Proses Finishing
                        </h1>
                        <p className="masterDataSubtitle">
                            Persentase jatah susut untuk setiap jenis pekerjaan
                            dan kategori barang finishing.
                            {lastUpdate
                                ? ` Terakhir diubah ${lastUpdate.at}${lastUpdate.by ? ` · ${lastUpdate.by}` : ''}.`
                                : ' Nilai awal mengikuti tabel standar proses finishing.'}
                        </p>
                    </div>
                </div>

                <div className="spkTableCard">
                    <form onSubmit={handleSubmit}>
                        <div className="spkTableToolbar">
                            <div className="spkTableToolbarLeft">
                                <span className="masterDataSubtitle">
                                    <strong>{rows.length}</strong> jenis
                                    pekerjaan ·{' '}
                                    <strong>{itemCategories.length}</strong>{' '}
                                    kategori barang
                                </span>
                            </div>
                            <div className="spkTableToolbarRight">
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

                        <InputError message={form.errors.cells} />

                        <div className="spkTableScroll shrinkMatrixScroll">
                            <table className="spkTable shrinkMatrixTable">
                                <thead>
                                    <tr>
                                        <th className="shrinkMatrixSticky shrinkMatrixSticky--no">
                                            No
                                        </th>
                                        <th className="shrinkMatrixSticky shrinkMatrixSticky--category">
                                            Kategori Pekerjaan
                                        </th>
                                        <th className="shrinkMatrixSticky shrinkMatrixSticky--work">
                                            Pekerjaan
                                        </th>
                                        {itemCategories.map((itemCategory) => (
                                            <th
                                                key={itemCategory}
                                                className={`shrinkMatrixPercent ${
                                                    isSmallCategory(
                                                        itemCategory,
                                                    )
                                                        ? 'shrinkMatrixColSmall'
                                                        : 'shrinkMatrixColLarge'
                                                }`}
                                            >
                                                {itemCategory}
                                                <div>Jatah Susut (%)</div>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row, rowIndex) => {
                                        const previous = rows[rowIndex - 1];
                                        const showCategory =
                                            previous?.workCategory !==
                                            row.workCategory;
                                        let categorySpan = 1;

                                        if (showCategory) {
                                            while (
                                                rows[rowIndex + categorySpan]
                                                    ?.workCategory ===
                                                row.workCategory
                                            ) {
                                                categorySpan += 1;
                                            }
                                        }

                                        return (
                                            <tr key={row.workType}>
                                                <td className="shrinkMatrixSticky shrinkMatrixSticky--no">
                                                    {rowIndex + 1}
                                                </td>
                                                {showCategory ? (
                                                    <td
                                                        className="shrinkMatrixSticky shrinkMatrixSticky--category"
                                                        rowSpan={categorySpan}
                                                    >
                                                        {row.workCategory}
                                                    </td>
                                                ) : null}
                                                <td className="shrinkMatrixSticky shrinkMatrixSticky--work">
                                                    {row.workType}
                                                </td>
                                                {itemCategories.map(
                                                    (
                                                        itemCategory,
                                                        categoryIndex,
                                                    ) => {
                                                        const cellIndex =
                                                            rowIndex *
                                                                itemCategories.length +
                                                            categoryIndex;
                                                        const fieldError =
                                                            form.errors[
                                                                `cells.${cellIndex}.allowance_percent`
                                                            ];

                                                        return (
                                                            <td
                                                                key={
                                                                    itemCategory
                                                                }
                                                                className={
                                                                    isSmallCategory(
                                                                        itemCategory,
                                                                    )
                                                                        ? 'shrinkMatrixColSmall'
                                                                        : 'shrinkMatrixColLarge'
                                                                }
                                                            >
                                                                <input
                                                                    className="masterDataRowInput shrinkMatrixInput"
                                                                    inputMode="decimal"
                                                                    required
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .cells[
                                                                            cellIndex
                                                                        ]
                                                                            ?.allowance_percent ??
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updatePercent(
                                                                            rowIndex,
                                                                            categoryIndex,
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    aria-label={`Jatah susut ${row.workType} ${itemCategory}`}
                                                                />
                                                                <InputError
                                                                    message={
                                                                        fieldError
                                                                    }
                                                                />
                                                            </td>
                                                        );
                                                    },
                                                )}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

FinishingShrinkAllowanceEdit.layout = {
    activeMenu: 'Jatah Susut Finishing',
    pageTitle: 'Jatah Susut Finishing',
};
