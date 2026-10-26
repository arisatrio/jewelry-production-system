import { Text } from '@ui5/webcomponents-react/Text';
import { useState } from 'react';
import type { SpkComponentItem } from '@/components/spk/types';

export type SpkFormComponentRow = {
    id: string;
    category: string;
    item: string;
    color: string;
    size: string;
};

type SpkFormComponentListCardProps = {
    components: SpkFormComponentRow[];
    onChange: (components: SpkFormComponentRow[]) => void;
    errors?: Record<string, string | undefined>;
};

type ComponentDraft = {
    category: string;
    item: string;
    color: string;
    size: string;
};

const COMPONENT_CATEGORY_OPTIONS = ['Komponen', 'Stopper'] as const;

const COMPONENT_COLOR_OPTIONS = [
    'White Gold',
    'Rose Gold',
    'Yellow Gold',
    'Two Tones',
] as const;

const emptyDraft = (): ComponentDraft => ({
    category: '',
    item: '',
    color: '',
    size: '',
});

function selectOptionsWithLegacy(
    allowed: readonly string[],
    currentValue: string,
): string[] {
    const value = currentValue.trim();

    if (value === '' || allowed.includes(value)) {
        return [...allowed];
    }

    return [value, ...allowed];
}

type ComponentFieldSelectProps = {
    value: string;
    ariaLabel: string;
    placeholder: string;
    options: readonly string[];
    onChange: (value: string) => void;
};

function ComponentFieldSelect({
    value,
    ariaLabel,
    placeholder,
    options,
    onChange,
}: ComponentFieldSelectProps) {
    return (
        <select
            className="spkStoneRowSelect"
            value={value}
            aria-label={ariaLabel}
            onChange={(event) => onChange(event.target.value)}
        >
            <option value="">{placeholder}</option>
            {selectOptionsWithLegacy(options, value).map((option) => (
                <option key={option} value={option}>
                    {option}
                </option>
            ))}
        </select>
    );
}

function newRowId(): string {
    return `component-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

function componentSizeInputValue(value: string): string {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '';
    }

    const normalized = trimmed.replace(',', '.');

    if (/^\d+(\.\d+)?$/.test(normalized)) {
        return normalized;
    }

    const match = trimmed.match(/(\d+(?:[.,]\d+)?)/);

    if (match === null) {
        return '';
    }

    return match[1].replace(',', '.');
}

function formatComponentSizeDisplay(value: string): string {
    const text = value.trim();

    if (text === '') {
        return '-';
    }

    const numeric = text.replace(',', '.');

    if (/^\d+(\.\d+)?$/.test(numeric)) {
        return `${numeric} cm`;
    }

    return text;
}

type ComponentSizeInputProps = {
    value: string;
    ariaLabel: string;
    onChange: (value: string) => void;
};

function ComponentSizeInput({ value, ariaLabel, onChange }: ComponentSizeInputProps) {
    return (
        <input
            className="spkStoneRowInput"
            type="number"
            min="0"
            step="0.01"
            inputMode="decimal"
            value={componentSizeInputValue(value)}
            placeholder="Ukuran (cm)"
            aria-label={ariaLabel}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

export function mapProductionComponentsToForm(
    components: Array<{
        id: string;
        category: string;
        item: string;
        color: string;
        size: string;
    }>,
): SpkFormComponentRow[] {
    return components.map((component) => ({
        id: component.id,
        category: component.category ?? '',
        item: component.item ?? '',
        color: component.color ?? '',
        size: component.size ?? '',
    }));
}

export function SpkFormComponentListCard({
    components,
    onChange,
    errors = {},
}: SpkFormComponentListCardProps) {
    const [draft, setDraft] = useState(emptyDraft);
    const [draftKey, setDraftKey] = useState(0);

    const updateDraft = (field: keyof ComponentDraft, value: string) => {
        setDraft((current) => ({ ...current, [field]: value }));
    };

    const draftHasValue =
        draft.category.trim() !== '' ||
        draft.item.trim() !== '' ||
        draft.color.trim() !== '' ||
        draft.size.trim() !== '';

    const handleAdd = () => {
        if (!draftHasValue) {
            return;
        }

        onChange([
            ...components,
            {
                id: newRowId(),
                category: draft.category.trim(),
                item: draft.item.trim(),
                color: draft.color.trim(),
                size: draft.size.trim(),
            },
        ]);
        setDraft(emptyDraft());
        setDraftKey((current) => current + 1);
    };

    const handleRemove = (id: string) => {
        onChange(components.filter((component) => component.id !== id));
    };

    const updateComponent = (id: string, field: keyof ComponentDraft, value: string) => {
        onChange(
            components.map((component) =>
                component.id === id ? { ...component, [field]: value } : component,
            ),
        );
    };

    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader">
                <h2 className="spkFioriDetailBlockTitle">Komponen</h2>
                <Text className="spkFioriDetailBlockMeta">
                    {components.length} baris
                </Text>
            </div>

            <div className="spkStoneTableWrap">
                <table className="spkStoneTable spkStoneTable--editable spkComponentTable">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Jenis Item Komponen</th>
                            <th>Warna</th>
                            <th>Ukuran (cm)</th>
                            <th className="spkStoneTableActionCol">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="spkStoneTableDraftRow">
                            <td>
                                <ComponentFieldSelect
                                    key={`draft-category-${draftKey}`}
                                    value={draft.category}
                                    ariaLabel="Kategori komponen baru"
                                    placeholder="Pilih kategori"
                                    options={COMPONENT_CATEGORY_OPTIONS}
                                    onChange={(next) =>
                                        updateDraft('category', next)
                                    }
                                />
                            </td>
                            <td>
                                <input
                                    className="spkStoneRowInput"
                                    type="text"
                                    value={draft.item}
                                    placeholder="Jenis item komponen"
                                    aria-label="Jenis item komponen baru"
                                    onChange={(event) =>
                                        updateDraft('item', event.target.value)
                                    }
                                />
                            </td>
                            <td>
                                <ComponentFieldSelect
                                    key={`draft-color-${draftKey}`}
                                    value={draft.color}
                                    ariaLabel="Warna komponen baru"
                                    placeholder="Pilih warna"
                                    options={COMPONENT_COLOR_OPTIONS}
                                    onChange={(next) =>
                                        updateDraft('color', next)
                                    }
                                />
                            </td>
                            <td>
                                <ComponentSizeInput
                                    value={draft.size}
                                    ariaLabel="Ukuran (cm) komponen baru"
                                    onChange={(next) =>
                                        updateDraft('size', next)
                                    }
                                />
                            </td>
                            <td>
                                <button
                                    type="button"
                                    className="spkStoneRowActionBtn"
                                    disabled={!draftHasValue}
                                    onClick={handleAdd}
                                >
                                    Tambah
                                </button>
                            </td>
                        </tr>

                        {components.length === 0 ? (
                            <tr>
                                <td colSpan={5}>
                                    <div className="spkStoneEmpty">
                                        <Text>
                                            Belum ada komponen. Isi baris di atas
                                            lalu klik Tambah.
                                        </Text>
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            components.map((component, index) => (
                                <tr key={component.id}>
                                    <td>
                                        <ComponentFieldSelect
                                            value={component.category}
                                            ariaLabel={`Kategori komponen ${index + 1}`}
                                            placeholder="Pilih kategori"
                                            options={COMPONENT_CATEGORY_OPTIONS}
                                            onChange={(next) =>
                                                updateComponent(
                                                    component.id,
                                                    'category',
                                                    next,
                                                )
                                            }
                                        />
                                        {errors[`components.${index}.category`] ? (
                                            <Text className="spkFioriError">
                                                {
                                                    errors[
                                                        `components.${index}.category`
                                                    ]
                                                }
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <input
                                            className="spkStoneRowInput"
                                            type="text"
                                            value={component.item}
                                            aria-label={`Jenis item komponen ${index + 1}`}
                                            onChange={(event) =>
                                                updateComponent(
                                                    component.id,
                                                    'item',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {errors[`components.${index}.item`] ? (
                                            <Text className="spkFioriError">
                                                {errors[`components.${index}.item`]}
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <ComponentFieldSelect
                                            value={component.color}
                                            ariaLabel={`Warna komponen ${index + 1}`}
                                            placeholder="Pilih warna"
                                            options={COMPONENT_COLOR_OPTIONS}
                                            onChange={(next) =>
                                                updateComponent(
                                                    component.id,
                                                    'color',
                                                    next,
                                                )
                                            }
                                        />
                                        {errors[`components.${index}.color`] ? (
                                            <Text className="spkFioriError">
                                                {errors[`components.${index}.color`]}
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <ComponentSizeInput
                                            value={component.size}
                                            ariaLabel={`Ukuran (cm) komponen ${index + 1}`}
                                            onChange={(next) =>
                                                updateComponent(
                                                    component.id,
                                                    'size',
                                                    next,
                                                )
                                            }
                                        />
                                        {errors[`components.${index}.size`] ? (
                                            <Text className="spkFioriError">
                                                {
                                                    errors[
                                                        `components.${index}.size`
                                                    ]
                                                }
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            className="spkStoneRowActionBtn spkStoneRowActionBtn--danger"
                                            onClick={() =>
                                                handleRemove(component.id)
                                            }
                                        >
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
            {errors.components ? (
                <Text className="spkFioriError">{errors.components}</Text>
            ) : null}
        </div>
    );
}

function emptyDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const text = String(value).trim();

    return text !== '' ? text : '-';
}

export function SpkComponentListCard({ components }: { components: SpkComponentItem[] }) {
    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader">
                <h2 className="spkFioriDetailBlockTitle">Komponen</h2>
                <Text className="spkFioriDetailBlockMeta">
                    {components.length} baris
                </Text>
            </div>

            {components.length === 0 ? (
                <div className="spkStoneEmpty">
                    <Text>Tidak ada komponen pada SPK ini.</Text>
                </div>
            ) : (
                <div className="spkStoneTableWrap">
                    <table className="spkStoneTable spkComponentTable">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Jenis Item Komponen</th>
                                <th>Warna</th>
                                <th>Ukuran (cm)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {components.map((component) => (
                                <tr key={component.id}>
                                    <td>{emptyDash(component.category)}</td>
                                    <td>{emptyDash(component.item)}</td>
                                    <td>{emptyDash(component.color)}</td>
                                    <td>{formatComponentSizeDisplay(component.size)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
