import { Text } from '@ui5/webcomponents-react/Text';
import { useState } from 'react';
import type { SpkChainItem } from '@/components/spk/types';

export type SpkFormChainRow = {
    id: string;
    category: string;
    item: string;
    color: string;
    size: string;
};

type SpkFormChainListCardProps = {
    chains: SpkFormChainRow[];
    onChange: (chains: SpkFormChainRow[]) => void;
    errors?: Record<string, string | undefined>;
};

type ChainDraft = {
    category: string;
    item: string;
    color: string;
    size: string;
};

const CHAIN_CATEGORY_OPTIONS = ['Bracelet Chain', 'Pendant Chain'] as const;

const CHAIN_COLOR_OPTIONS = [
    'White Gold',
    'Rose Gold',
    'Yellow Gold',
    'Two Tones',
] as const;

const emptyDraft = (): ChainDraft => ({
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

type ChainFieldSelectProps = {
    value: string;
    ariaLabel: string;
    placeholder: string;
    options: readonly string[];
    onChange: (value: string) => void;
};

function ChainFieldSelect({
    value,
    ariaLabel,
    placeholder,
    options,
    onChange,
}: ChainFieldSelectProps) {
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
    return `chain-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

function chainSizeInputValue(value: string): string {
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

function formatChainSizeDisplay(value: string): string {
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

type ChainSizeInputProps = {
    value: string;
    ariaLabel: string;
    onChange: (value: string) => void;
};

function ChainSizeInput({ value, ariaLabel, onChange }: ChainSizeInputProps) {
    return (
        <input
            className="spkStoneRowInput"
            type="number"
            min="0"
            step="0.01"
            inputMode="decimal"
            value={chainSizeInputValue(value)}
            placeholder="Ukuran (cm)"
            aria-label={ariaLabel}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

export function mapProductionChainsToForm(
    chains: Array<{
        id: string;
        category: string;
        item: string;
        color: string;
        size: string;
    }>,
): SpkFormChainRow[] {
    return chains.map((chain) => ({
        id: chain.id,
        category: chain.category ?? '',
        item: chain.item ?? '',
        color: chain.color ?? '',
        size: chain.size ?? '',
    }));
}

export function SpkFormChainListCard({
    chains,
    onChange,
    errors = {},
}: SpkFormChainListCardProps) {
    const [draft, setDraft] = useState(emptyDraft);
    const [draftKey, setDraftKey] = useState(0);

    const updateDraft = (field: keyof ChainDraft, value: string) => {
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
            ...chains,
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
        onChange(chains.filter((chain) => chain.id !== id));
    };

    const updateChain = (id: string, field: keyof ChainDraft, value: string) => {
        onChange(
            chains.map((chain) =>
                chain.id === id ? { ...chain, [field]: value } : chain,
            ),
        );
    };

    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader">
                <h2 className="spkFioriDetailBlockTitle">Rantai</h2>
                <Text className="spkFioriDetailBlockMeta">
                    {chains.length} baris
                </Text>
            </div>

            <div className="spkStoneTableWrap">
                <table className="spkStoneTable spkStoneTable--editable spkChainTable">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Jenis Item Chain</th>
                            <th>Warna</th>
                            <th>Ukuran (cm)</th>
                            <th className="spkStoneTableActionCol">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="spkStoneTableDraftRow">
                            <td>
                                <ChainFieldSelect
                                    key={`draft-category-${draftKey}`}
                                    value={draft.category}
                                    ariaLabel="Kategori rantai baru"
                                    placeholder="Pilih kategori"
                                    options={CHAIN_CATEGORY_OPTIONS}
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
                                    placeholder="Jenis item chain"
                                    aria-label="Jenis item chain baru"
                                    onChange={(event) =>
                                        updateDraft('item', event.target.value)
                                    }
                                />
                            </td>
                            <td>
                                <ChainFieldSelect
                                    key={`draft-color-${draftKey}`}
                                    value={draft.color}
                                    ariaLabel="Warna rantai baru"
                                    placeholder="Pilih warna"
                                    options={CHAIN_COLOR_OPTIONS}
                                    onChange={(next) =>
                                        updateDraft('color', next)
                                    }
                                />
                            </td>
                            <td>
                                <ChainSizeInput
                                    value={draft.size}
                                    ariaLabel="Ukuran (cm) rantai baru"
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

                        {chains.length === 0 ? (
                            <tr>
                                <td colSpan={5}>
                                    <div className="spkStoneEmpty">
                                        <Text>
                                            Belum ada rantai. Isi baris di atas
                                            lalu klik Tambah.
                                        </Text>
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            chains.map((chain, index) => (
                                <tr key={chain.id}>
                                    <td>
                                        <ChainFieldSelect
                                            value={chain.category}
                                            ariaLabel={`Kategori rantai ${index + 1}`}
                                            placeholder="Pilih kategori"
                                            options={CHAIN_CATEGORY_OPTIONS}
                                            onChange={(next) =>
                                                updateChain(
                                                    chain.id,
                                                    'category',
                                                    next,
                                                )
                                            }
                                        />
                                        {errors[`chains.${index}.category`] ? (
                                            <Text className="spkFioriError">
                                                {
                                                    errors[
                                                        `chains.${index}.category`
                                                    ]
                                                }
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <input
                                            className="spkStoneRowInput"
                                            type="text"
                                            value={chain.item}
                                            aria-label={`Jenis item chain ${index + 1}`}
                                            onChange={(event) =>
                                                updateChain(
                                                    chain.id,
                                                    'item',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {errors[`chains.${index}.item`] ? (
                                            <Text className="spkFioriError">
                                                {errors[`chains.${index}.item`]}
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <ChainFieldSelect
                                            value={chain.color}
                                            ariaLabel={`Warna rantai ${index + 1}`}
                                            placeholder="Pilih warna"
                                            options={CHAIN_COLOR_OPTIONS}
                                            onChange={(next) =>
                                                updateChain(
                                                    chain.id,
                                                    'color',
                                                    next,
                                                )
                                            }
                                        />
                                        {errors[`chains.${index}.color`] ? (
                                            <Text className="spkFioriError">
                                                {errors[`chains.${index}.color`]}
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <ChainSizeInput
                                            value={chain.size}
                                            ariaLabel={`Ukuran (cm) rantai ${index + 1}`}
                                            onChange={(next) =>
                                                updateChain(
                                                    chain.id,
                                                    'size',
                                                    next,
                                                )
                                            }
                                        />
                                        {errors[`chains.${index}.size`] ? (
                                            <Text className="spkFioriError">
                                                {
                                                    errors[
                                                        `chains.${index}.size`
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
                                                handleRemove(chain.id)
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
            {errors.chains ? (
                <Text className="spkFioriError">{errors.chains}</Text>
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

export function SpkChainListCard({ chains }: { chains: SpkChainItem[] }) {
    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader">
                <h2 className="spkFioriDetailBlockTitle">Rantai</h2>
                <Text className="spkFioriDetailBlockMeta">
                    {chains.length} baris
                </Text>
            </div>

            {chains.length === 0 ? (
                <div className="spkStoneEmpty">
                    <Text>Tidak ada rantai pada SPK ini.</Text>
                </div>
            ) : (
                <div className="spkStoneTableWrap">
                    <table className="spkStoneTable spkChainTable">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Jenis Item Chain</th>
                                <th>Warna</th>
                                <th>Ukuran (cm)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {chains.map((chain) => (
                                <tr key={chain.id}>
                                    <td>{emptyDash(chain.category)}</td>
                                    <td>{emptyDash(chain.item)}</td>
                                    <td>{emptyDash(chain.color)}</td>
                                    <td>{formatChainSizeDisplay(chain.size)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
