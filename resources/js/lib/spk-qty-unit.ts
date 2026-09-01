export type SpkQtyUnitOption = {
    value: string;
    label: string;
    qty: number;
    satuan: string;
};

export const SPK_QTY_UNIT_OPTIONS: SpkQtyUnitOption[] = [
    { value: '1|Pcs', label: '1 Pcs', qty: 1, satuan: 'Pcs' },
    { value: '1|Pasang', label: '1 Pasang', qty: 1, satuan: 'Pasang' },
    {
        value: '1|Setengah Pasang',
        label: '1/2 Pasang',
        qty: 1,
        satuan: 'Setengah Pasang',
    },
];

export function spkQtyUnitOptionValue(qty: number | string, satuan: string): string {
    const normalizedQty = Math.max(1, Number(qty) || 1);
    const normalizedSatuan = satuan.trim() !== '' ? satuan.trim() : 'Pcs';

    return `${normalizedQty}|${normalizedSatuan}`;
}

export function spkQtyUnitLabel(qty: number | string, satuan: string): string {
    const value = spkQtyUnitOptionValue(qty, satuan);
    const matched = SPK_QTY_UNIT_OPTIONS.find((option) => option.value === value);

    if (matched) {
        return matched.label;
    }

    const normalizedQty = String(qty).trim() !== '' ? String(qty) : '-';
    const normalizedSatuan = satuan.trim() !== '' ? satuan.trim() : 'Pcs';

    return `${normalizedQty} ${normalizedSatuan}`;
}

export function spkQtyUnitOptionsFor(
    qty: number | string,
    satuan: string,
): SpkQtyUnitOption[] {
    const value = spkQtyUnitOptionValue(qty, satuan);
    const hasPreset = SPK_QTY_UNIT_OPTIONS.some((option) => option.value === value);

    if (hasPreset) {
        return SPK_QTY_UNIT_OPTIONS;
    }

    return [
        ...SPK_QTY_UNIT_OPTIONS,
        {
            value,
            label: spkQtyUnitLabel(qty, satuan),
            qty: Math.max(1, Number(qty) || 1),
            satuan: satuan.trim() !== '' ? satuan.trim() : 'Pcs',
        },
    ];
}

export function parseSpkQtyUnitValue(
    value: string,
): { qty: number; satuan: string } | null {
    const preset = SPK_QTY_UNIT_OPTIONS.find((option) => option.value === value);

    if (preset) {
        return {
            qty: preset.qty,
            satuan: preset.satuan,
        };
    }

    const [qtyPart, satuanPart] = value.split('|', 2);

    if (!qtyPart || !satuanPart) {
        return null;
    }

    const qty = Number(qtyPart);

    if (!Number.isFinite(qty) || qty < 1) {
        return null;
    }

    return {
        qty,
        satuan: satuanPart.trim(),
    };
}
