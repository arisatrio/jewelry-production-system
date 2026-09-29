import { useId, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Checkbox } from '@/components/ui/checkbox';

export type FilterMultiSelectOption = {
    value: string;
    label: string;
};

type FilterMultiSelectProps = {
    label: string;
    options: FilterMultiSelectOption[];
    value: string[];
    onChange: (value: string[]) => void;
    allLabel?: string;
    searchThreshold?: number;
};

const DEFAULT_SEARCH_THRESHOLD = 8;

export const ALL_FILTER_VALUE = 'all';

export function haveSameFilterValues(
    first: ReadonlyArray<string | number>,
    second: ReadonlyArray<string | number>,
): boolean {
    if (first.length !== second.length) {
        return false;
    }

    const secondValues = new Set(second.map(String));

    return first.every((item) => secondValues.has(String(item)));
}

export function buildDefaultableFilterQuery(
    values: string[],
    defaults: string[],
): string[] | string | undefined {
    if (values.length === 0) {
        return defaults.length > 0 ? ALL_FILTER_VALUE : undefined;
    }

    if (haveSameFilterValues(values, defaults)) {
        return undefined;
    }

    return values;
}

export function FilterMultiSelect({
    label,
    options,
    value,
    onChange,
    allLabel = 'Semua',
    searchThreshold = DEFAULT_SEARCH_THRESHOLD,
}: FilterMultiSelectProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const labelId = useId();
    const panelId = useId();

    const selectedOption =
        value.length === 1
            ? options.find((option) => option.value === value[0])
            : undefined;
    const summary =
        value.length === 0
            ? allLabel
            : value.length === 1
              ? (selectedOption?.label ?? value[0])
              : `${value.length} dipilih`;

    const isSearchable = options.length > searchThreshold;
    const normalizedQuery = query.trim().toLowerCase();
    const visibleOptions =
        normalizedQuery === ''
            ? options
            : options.filter((option) =>
                  option.label.toLowerCase().includes(normalizedQuery),
              );

    const toggleOption = (optionValue: string, checked: boolean) => {
        const nextValues = new Set(value);

        if (checked) {
            nextValues.add(optionValue);
        } else {
            nextValues.delete(optionValue);
        }

        onChange(
            options
                .map((option) => option.value)
                .filter((optionKey) => nextValues.has(optionKey)),
        );
    };

    const keepKeysInsidePanel = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key !== 'Escape') {
            event.stopPropagation();
        }
    };

    return (
        <div
            className="spkTableHeaderSortField--finishing"
            onClick={(event) => event.stopPropagation()}
        >
            <span id={labelId}>{label}</span>
            <button
                type="button"
                className="spkFilterMultiSelectTrigger"
                aria-labelledby={labelId}
                aria-expanded={open}
                aria-controls={panelId}
                title={
                    value.length > 1
                        ? options
                              .filter((option) => value.includes(option.value))
                              .map((option) => option.label)
                              .join(', ')
                        : undefined
                }
                onClick={() => setOpen((current) => !current)}
            >
                <span className="spkFilterMultiSelectSummary">{summary}</span>
            </button>
            {open ? (
                <div
                    id={panelId}
                    className="spkFilterMultiSelectPanel"
                    onKeyDown={keepKeysInsidePanel}
                >
                    {isSearchable ? (
                        <input
                            type="search"
                            className="spkFilterMultiSelectSearch"
                            placeholder={`Cari ${label.toLowerCase()}`}
                            aria-label={`Cari ${label.toLowerCase()}`}
                            value={query}
                            autoComplete="off"
                            onChange={(event) => setQuery(event.target.value)}
                        />
                    ) : null}
                    <div
                        className="spkFilterMultiSelectList"
                        role="group"
                        aria-labelledby={labelId}
                    >
                        {normalizedQuery === '' ? (
                            <label className="spkFilterMultiSelectOption">
                                <Checkbox
                                    checked={value.length === 0}
                                    onCheckedChange={() => onChange([])}
                                />
                                <span>{allLabel}</span>
                            </label>
                        ) : null}
                        {visibleOptions.map((option) => (
                            <label
                                key={option.value}
                                className="spkFilterMultiSelectOption"
                            >
                                <Checkbox
                                    checked={value.includes(option.value)}
                                    onCheckedChange={(checked) =>
                                        toggleOption(
                                            option.value,
                                            checked === true,
                                        )
                                    }
                                />
                                <span>{option.label}</span>
                            </label>
                        ))}
                        {visibleOptions.length === 0 ? (
                            <p className="spkFilterMultiSelectEmpty">
                                Tidak ada pilihan.
                            </p>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </div>
    );
}
