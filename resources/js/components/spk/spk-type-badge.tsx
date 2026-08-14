type SpkTypeBadgeProps = {
    type: string;
    className?: string;
};

function resolveTypeVariant(
    type: string,
): 'stock' | 'pesanan' | 'refund-exchange' | 'reparasi' | 'default' {
    const normalized = type.trim().toLowerCase();

    if (normalized === 'stock') {
        return 'stock';
    }

    if (normalized === 'pesanan') {
        return 'pesanan';
    }

    if (
        normalized === 'refund' ||
        normalized === 'exchange' ||
        normalized.includes('refund') ||
        normalized.includes('exchange')
    ) {
        return 'refund-exchange';
    }

    if (normalized === 'reparasi' || normalized.includes('reparasi')) {
        return 'reparasi';
    }

    return 'default';
}

export function SpkTypeBadge({ type, className = '' }: SpkTypeBadgeProps) {
    const variant = resolveTypeVariant(type);
    const label = type.trim() || '—';

    return (
        <span
            className={[
                'spkTypeBadge',
                `spkTypeBadge--${variant}`,
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            {label}
        </span>
    );
}
