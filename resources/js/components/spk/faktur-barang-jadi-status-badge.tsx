export type FakturBarangJadiWorkflowStatus =
    | 'created'
    | 'sent'
    | 'received';

function badgeClass(status: FakturBarangJadiWorkflowStatus | null): string {
    const base = 'spkTableBadge';

    switch (status) {
        case 'created':
            return `${base} spkTableBadge--fakturCreated`;
        case 'sent':
            return `${base} spkTableBadge--fakturSent`;
        case 'received':
            return `${base} spkTableBadge--fakturReceived`;
        default:
            return `${base} spkTableBadge--fakturPending`;
    }
}

const LABELS: Record<FakturBarangJadiWorkflowStatus, string> = {
    created: 'Faktur dibuat',
    sent: 'Dikirim',
    received: 'Diterima',
};

export function fakturBarangJadiStatusLabel(
    status: FakturBarangJadiWorkflowStatus | null | undefined,
): string {
    if (status === 'created' || status === 'sent' || status === 'received') {
        return LABELS[status];
    }

    return 'Belum';
}

type FakturBarangJadiStatusBadgeProps = {
    status?: FakturBarangJadiWorkflowStatus | null;
};

export function FakturBarangJadiStatusBadge({
    status = null,
}: FakturBarangJadiStatusBadgeProps) {
    const normalized =
        status === 'created' || status === 'sent' || status === 'received'
            ? status
            : null;

    return (
        <span className={badgeClass(normalized)}>{fakturBarangJadiStatusLabel(normalized)}</span>
    );
}
