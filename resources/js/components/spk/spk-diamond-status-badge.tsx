export type SpkDiamondStatusKey =
    | 'draft'
    | 'sent_to_workshop'
    | 'received_by_workshop'
    | 'installed';

export function spkDiamondStatusBadgeClass(
    status: SpkDiamondStatusKey | string,
): string {
    const base = 'spkStoneStockBadge';

    switch (status) {
        case 'draft':
            return `${base} is-unavailable`;
        case 'sent_to_workshop':
            return `${base} spkChainFulfillmentBadge--progress`;
        case 'received_by_workshop':
            return `${base} spkChainFulfillmentBadge--done`;
        case 'installed':
            return `${base} is-available`;
        default:
            return `${base} is-unavailable`;
    }
}

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    sent_to_workshop: 'Kirim ke Workshop',
    received_by_workshop: 'Diterima Workshop',
    installed: 'Terpasang',
};

function resolveStatusLabel(
    label: string | null | undefined,
    status: string | null | undefined,
): string {
    const trimmed = label?.trim() ?? '';

    if (trimmed !== '') {
        return trimmed;
    }

    const key = (status ?? '').trim().toLowerCase();

    return STATUS_LABELS[key] ?? 'Draft';
}

export function SpkDiamondStatusBadge({
    label,
    status = 'draft',
}: {
    label?: string | null;
    status?: SpkDiamondStatusKey | string | null;
}) {
    const normalizedStatus = (status ?? 'draft').trim().toLowerCase() || 'draft';
    const text = resolveStatusLabel(label, normalizedStatus);

    return (
        <span className={spkDiamondStatusBadgeClass(normalizedStatus)}>
            {text}
        </span>
    );
}
