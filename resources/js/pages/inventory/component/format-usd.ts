export function formatUsd(amount: string | null | undefined): string {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }

    const value = Number(amount);

    if (Number.isNaN(value)) {
        return amount;
    }

    return `$${value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}
