type SpkOrderTypeColumnProps = {
    spkType: string | null;
    orderTypeLabel?: string | null;
    /** Tampilkan label pesanan/customer (default: true). */
    showOrderLabel?: boolean;
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
    orderTypeLabel = null,
    showOrderLabel = true,
}: SpkOrderTypeColumnProps) {
    const typeLabel = displayValue(spkType);

    return (
        <div className="spkOrderTypeColumn">
            <span
                className={`spkTableBadge ${tipeProduksiBadgeClass(typeLabel)}`}
            >
                {typeLabel}
            </span>
            {showOrderLabel ? (
                <span>{displayValue(orderTypeLabel)}</span>
            ) : null}
        </div>
    );
}
