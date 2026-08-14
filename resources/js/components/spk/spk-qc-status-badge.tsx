export type QcStatusVariant = 'ok' | 'not-ok';

export type QcStatus = {
    variant: QcStatusVariant;
    label: 'OK' | 'Not OK';
};

type SpkQcStatusBadgeProps = {
    status: QcStatus;
    className?: string;
};

export function isKoreksiQcNotOk(value: unknown): boolean {
    return value === 1 || value === '1' || value === true;
}

export function resolveQcStatusFromCoranStatus(
    status: unknown,
): QcStatus | null {
    if (status === null || status === undefined || status === '') {
        return null;
    }

    const normalized = String(status).trim().toUpperCase();

    if (normalized === 'OK') {
        return { variant: 'ok', label: 'OK' };
    }

    if (
        normalized === 'NOK' ||
        normalized === 'NOT OK' ||
        normalized === 'NOTOK'
    ) {
        return { variant: 'not-ok', label: 'Not OK' };
    }

    return { variant: 'not-ok', label: 'Not OK' };
}

export function resolveQcStatusFromKoreksiQc(
    koreksiQc: unknown,
): QcStatus | null {
    if (koreksiQc === null || koreksiQc === undefined || koreksiQc === '') {
        return null;
    }

    if (isKoreksiQcNotOk(koreksiQc)) {
        return { variant: 'not-ok', label: 'Not OK' };
    }

    return { variant: 'ok', label: 'OK' };
}

export function resolveQcStatusFromStatusItem(
    statusItem: unknown,
): QcStatus | null {
    return resolveQcStatusFromCoranStatus(statusItem);
}

export function resolveProcessQcStatus(
    record: Record<string, unknown>,
    column: 'status' | 'status_item' | 'koreksi_qc',
): QcStatus | null {
    if (column === 'status') {
        return resolveQcStatusFromCoranStatus(record.status);
    }

    if (column === 'status_item') {
        return resolveQcStatusFromStatusItem(record.status_item);
    }

    return resolveQcStatusFromKoreksiQc(record.koreksi_qc);
}

export function SpkQcStatusBadge({
    status,
    className = '',
}: SpkQcStatusBadgeProps) {
    return (
        <span
            className={[
                'spkQcStatusBadge',
                `spkQcStatusBadge--${status.variant}`,
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            {status.label}
        </span>
    );
}
