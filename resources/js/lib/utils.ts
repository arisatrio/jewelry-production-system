import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Format a gram weight with Indonesian decimal comma (e.g. 1,204 g).
 */
export function formatGram(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const numeric = typeof value === 'number' ? value : Number(value);

    if (Number.isNaN(numeric)) {
        return `${String(value).replace('.', ',')} g`;
    }

    return `${numeric.toFixed(3).replace('.', ',')} g`;
}
