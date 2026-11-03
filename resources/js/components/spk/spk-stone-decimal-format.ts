export function formatDecimal3Id(
    value: string | number | null | undefined,
): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const text = String(value).trim();

    if (text === '' || text === '-') {
        return '-';
    }

    const parsed = Number(text.replace(',', '.'));

    if (!Number.isFinite(parsed)) {
        return text;
    }

    return parsed.toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}
