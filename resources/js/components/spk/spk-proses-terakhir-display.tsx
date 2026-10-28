import type { SpkRow } from '@/components/spk/types';

export type SpkProsesTerakhirFields = Pick<
    SpkRow,
    | 'prosesTerakhir'
    | 'prosesTerakhirDate'
    | 'prosesTerakhirDisplay'
    | 'prosesTerakhirCompleted'
>;

export function spkProsesTerakhirLabel(row: SpkProsesTerakhirFields): string {
    const display = (row.prosesTerakhirDisplay ?? '').trim();

    return display !== '' ? display : row.prosesTerakhir.trim();
}

export function spkProsesTerakhirBadgeClass(
    completed: boolean | null | undefined,
): string {
    return [
        'spkProsesTerakhirBadge',
        completed === true ? 'spkProsesTerakhirBadge--done' : '',
        completed === false ? 'spkProsesTerakhirBadge--pending' : '',
    ]
        .filter(Boolean)
        .join(' ');
}

type SpkProsesTerakhirDisplayProps = {
    row: SpkProsesTerakhirFields;
    wrapperClassName?: string;
    dateClassName?: string;
};

export function SpkProsesTerakhirDisplay({
    row,
    wrapperClassName = 'spkProsesTerakhirValue',
    dateClassName = 'spkProsesTerakhirDate',
}: SpkProsesTerakhirDisplayProps) {
    const label = spkProsesTerakhirLabel(row);
    const date = (row.prosesTerakhirDate ?? '').trim();

    if (label === '') {
        return <>-</>;
    }

    return (
        <span className={wrapperClassName}>
            <span
                className={spkProsesTerakhirBadgeClass(
                    row.prosesTerakhirCompleted,
                )}
            >
                {label}
            </span>
            {date !== '' ? (
                <span className={dateClassName}>pada {date}</span>
            ) : null}
        </span>
    );
}
