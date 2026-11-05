export function formatMicroStonePcs(value: string): string {
    const numeric = Number(value);
    const formatted = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 2,
    }).format(Math.abs(numeric));

    return numeric < 0 ? `(${formatted})` : formatted;
}

export function formatMicroStoneBalanceCrt(value: string): string {
    const numeric = Number(value);
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }).format(Math.abs(numeric));

    return numeric < 0 ? `(${formatted})` : formatted;
}
