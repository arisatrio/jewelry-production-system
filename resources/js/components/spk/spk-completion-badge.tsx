export type CompletionStatus = {
    completed: boolean;
    label: 'Selesai' | 'Belum selesai';
};

type SpkCompletionBadgeProps = {
    completed: boolean;
    className?: string;
};

export function resolveCompletionStatus(
    completed: unknown,
): CompletionStatus {
    const isCompleted =
        completed === true || completed === 1 || completed === '1';

    return {
        completed: isCompleted,
        label: isCompleted ? 'Selesai' : 'Belum selesai',
    };
}

export function SpkCompletionBadge({
    completed,
    className = '',
}: SpkCompletionBadgeProps) {
    const status = resolveCompletionStatus(completed);

    return (
        <span
            className={[
                'spkCompletionBadge',
                status.completed
                    ? 'spkCompletionBadge--done'
                    : 'spkCompletionBadge--pending',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            {status.label}
        </span>
    );
}
