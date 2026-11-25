import { stoneAllocationStatusBadgeClass } from '@/components/spk/spk-stone-status-labels-cell';
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

export function spkProsesTerakhirLabelIsEmpty(label: string): boolean {
    const trimmed = label.trim();

    return trimmed === '' || trimmed === '-' || trimmed === '—';
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
    /** Shown when there is no last process (default `-`). */
    emptyLabel?: string;
};

export function SpkProsesTerakhirDisplay({
    row,
    wrapperClassName = 'spkProsesTerakhirValue',
    dateClassName = 'spkProsesTerakhirDate',
    emptyLabel,
}: SpkProsesTerakhirDisplayProps) {
    const label = spkProsesTerakhirLabel(row);
    const date = (row.prosesTerakhirDate ?? '').trim();

    if (spkProsesTerakhirLabelIsEmpty(label)) {
        if (emptyLabel !== undefined && emptyLabel.trim() !== '') {
            return (
                <div className="spkChainStatusCell spkChainStatusCell--labelsOnly">
                    <div className="spkChainStatusLabels">
                        <div className="spkChainStatusLabelRow">
                            <span
                                className={stoneAllocationStatusBadgeClass(
                                    'not_allocated',
                                )}
                            >
                                {emptyLabel}
                            </span>
                        </div>
                    </div>
                </div>
            );
        }

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
