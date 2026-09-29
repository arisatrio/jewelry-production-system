import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import { Icon } from '@ui5/webcomponents-react/Icon';

export type ActiveFilterChip = {
    key: string;
    label: string;
    value: string;
    onRemove: () => void;
};

type ActiveFilterOption = {
    value: string;
    label: string;
};

type ActiveFilterSummaryProps = {
    filters: ReadonlyArray<ActiveFilterChip | null | false>;
    sort: ActiveFilterChip | null | false;
    onClearAll: () => void;
};

const MONTH_LABELS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
] as const;

function formatFilterDate(value: string): string {
    const [year, month, day] = value.split('-');
    const monthLabel = MONTH_LABELS[Number(month) - 1];

    if (!year || !day || !monthLabel) {
        return value;
    }

    return `${day.padStart(2, '0')}-${monthLabel}-${year}`;
}

export function describeFilterOptions(
    values: ReadonlyArray<string | number>,
    options: ReadonlyArray<ActiveFilterOption>,
): string {
    return values
        .map(
            (value) =>
                options.find((option) => option.value === String(value))
                    ?.label ?? String(value),
        )
        .join(', ');
}

export function describeDateRange(
    from: string | null,
    to: string | null,
): string {
    if (from && to) {
        return `${formatFilterDate(from)} s/d ${formatFilterDate(to)}`;
    }

    if (from) {
        return `Mulai ${formatFilterDate(from)}`;
    }

    if (to) {
        return `Sampai ${formatFilterDate(to)}`;
    }

    return '';
}

export function describeSort(
    sort: string,
    direction: string,
    sortOptions: ReadonlyArray<ActiveFilterOption>,
    directionOptions: ReadonlyArray<ActiveFilterOption>,
): string {
    const sortLabel = describeFilterOptions([sort], sortOptions);
    const directionLabel = describeFilterOptions([direction], directionOptions);

    return `${sortLabel} (${directionLabel})`;
}

function ActiveFilterChipItem({ chip }: { chip: ActiveFilterChip }) {
    return (
        <span
            className="spkActiveFilterChip"
            title={`${chip.label}: ${chip.value}`}
        >
            <span className="spkActiveFilterChipLabel">{chip.label}:</span>
            <span className="spkActiveFilterChipValue">{chip.value}</span>
            <button
                type="button"
                className="spkActiveFilterChipRemove"
                aria-label={`Hapus ${chip.label}`}
                onClick={chip.onRemove}
            >
                <Icon name={declineIcon} mode="Decorative" />
            </button>
        </span>
    );
}

export function ActiveFilterSummary({
    filters,
    sort,
    onClearAll,
}: ActiveFilterSummaryProps) {
    const activeFilters = filters.filter((chip): chip is ActiveFilterChip =>
        Boolean(chip),
    );

    if (activeFilters.length === 0 && !sort) {
        return null;
    }

    return (
        <div
            className="spkActiveFilterBar"
            role="region"
            aria-label="Filter dan urutan aktif"
        >
            <div className="spkActiveFilterGroups">
                {activeFilters.length > 0 ? (
                    <div className="spkActiveFilterGroup">
                        <span className="spkActiveFilterGroupTitle">
                            Filter aktif
                        </span>
                        {activeFilters.map((chip) => (
                            <ActiveFilterChipItem key={chip.key} chip={chip} />
                        ))}
                    </div>
                ) : null}
                {sort ? (
                    <div className="spkActiveFilterGroup">
                        <span className="spkActiveFilterGroupTitle">
                            Urutan aktif
                        </span>
                        <ActiveFilterChipItem chip={sort} />
                    </div>
                ) : null}
            </div>
            <button
                type="button"
                className="spkTableHeaderSortClear--finishing"
                onClick={onClearAll}
            >
                Hapus semua
            </button>
        </div>
    );
}
