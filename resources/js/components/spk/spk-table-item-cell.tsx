import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import { SpkTableDescriptionCell } from '@/components/spk/spk-list-cells';

export type SpkTableItemCellFields = {
    spkNo: string;
    spkImageUrl: string | null;
    typeSkuLabel?: string | null;
    itemDescription?: string | null;
    skuAssigned?: boolean;
};

export function SpkTableItemCell({
    spkNo,
    spkImageUrl,
    typeSkuLabel,
    itemDescription,
    skuAssigned = true,
}: SpkTableItemCellFields) {
    return (
        <div className="flex items-start gap-3">
            <SpkItemThumbnail imageUrl={spkImageUrl} spkNo={spkNo} />
            <SpkTableDescriptionCell
                row={{
                    typeSkuLabel: typeSkuLabel ?? null,
                    itemDescription: itemDescription ?? null,
                    skuAssigned,
                }}
            />
        </div>
    );
}