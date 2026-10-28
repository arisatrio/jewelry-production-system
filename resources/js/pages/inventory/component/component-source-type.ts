export function sourceTypeLabel(sourceType: string): string {
    switch (sourceType) {
        case 'purchase':
            return 'Pembelian';
        case 'remnant':
            return 'Sisa potongan';
        case 'adjustment':
            return 'Penyesuaian';
        default:
            return sourceType;
    }
}
