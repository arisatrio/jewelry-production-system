type SpkOrderTypeColumnProps = {
    spkType: string | null;
    orderTypeLabel: string | null;
};

function displayValue(value: string | null | undefined): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

function tipeProduksiBadgeClass(tipe: string): string {
    const lower = tipe.toLowerCase();

    if (lower.includes('pesanan')) {
        return 'spkTableBadge--pesanan';
    }

    if (lower.includes('stock') || lower.includes('stok')) {
        return 'spkTableBadge--stock';
    }

    if (lower.includes('refund') || lower.includes('exchange')) {
        return 'spkTableBadge--refundExchange';
    }

    if (lower.includes('reparasi')) {
        return 'spkTableBadge--reparasi';
    }

    return 'spkTableBadge--default';
}

export function SpkOrderTypeColumn({
    spkType,
    orderTypeLabel,
}: SpkOrderTypeColumnProps) {
    const typeLabel = displayValue(spkType);

    return (
        <div className="spkOrderTypeColumn">
            <span
                className={`spkTableBadge ${tipeProduksiBadgeClass(typeLabel)}`}
            >
                {typeLabel}
            </span>
            <span>{displayValue(orderTypeLabel)}</span>
        </div>
    );
}
