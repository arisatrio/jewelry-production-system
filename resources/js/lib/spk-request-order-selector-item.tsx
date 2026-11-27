import { SpkTableDescriptionCell } from '@/components/spk/spk-list-cells';
import type { ReactNode } from 'react';

export type SpkRequestOrderSelectorItemFields = {
    typeSkuLabel?: string | null;
    itemDescription?: string | null;
    skuAssigned?: boolean;
};

export function spkRequestOrderSelectorItemCell(
    row: SpkRequestOrderSelectorItemFields,
): ReactNode {
    return (
        <SpkTableDescriptionCell
            row={{
                typeSkuLabel: row.typeSkuLabel ?? null,
                itemDescription: row.itemDescription ?? null,
                skuAssigned: row.skuAssigned ?? false,
            }}
        />
    );
}