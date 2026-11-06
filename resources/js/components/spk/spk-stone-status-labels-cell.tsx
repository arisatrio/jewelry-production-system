import type {
    SpkStoneIndexAllocationStatus,
    SpkStoneStock,
} from '@/components/spk/types';

export type { SpkStoneIndexStatus } from '@/components/spk/types';

export type SpkStoneStatusLabelsAllocation = {
    status: SpkStoneIndexAllocationStatus;
    label: string;
    allocatedPcs: number;
};

function stoneStockStatusLabel(
    stock?: SpkStoneStock | null,
): { text: string; available: boolean } | null {
    if (stock === undefined || stock === null) {
        return null;
    }

    const available = stock.status === 'available';

    return {
        text: available ? 'Stok Ada' : 'Stok Tidak Ada',
        available,
    };
}

export function stoneAllocationStatusBadgeClass(
    status: SpkStoneIndexAllocationStatus,
): string {
    const base = 'spkStoneStockBadge';

    switch (status) {
        case 'allocated':
            return `${base} is-available`;
        case 'ordering':
        case 'ordered':
            return `${base} spkChainFulfillmentBadge--progress`;
        case 'shipping':
            return `${base} spkChainFulfillmentBadge--shipping`;
        case 'partial':
            return `${base} spkStoneIndexStatusBadge--partial`;
        case 'not_allocated':
        default:
            return `${base} is-unavailable`;
    }
}

function stoneAllocationStatusLabel(
    allocation: SpkStoneStatusLabelsAllocation | null | undefined,
): { text: string; allocated: boolean; badgeClass: string } | null {
    if (allocation === undefined || allocation === null) {
        return null;
    }

    const allocated =
        allocation.status === 'allocated' && allocation.allocatedPcs > 0;

    const text =
        allocation.label.trim() !== ''
            ? allocation.label
            : allocated
              ? 'Sudah Dialokasikan'
              : 'Belum Dialokasikan';

    return {
        text,
        allocated,
        badgeClass: stoneAllocationStatusBadgeClass(allocation.status),
    };
}

export function SpkStoneStatusLabelsCell({
    stock,
    allocation,
    showStockStatus = true,
}: {
    stock?: SpkStoneStock | null;
    allocation?: SpkStoneStatusLabelsAllocation | null;
    showStockStatus?: boolean;
}) {
    const stockLabel = showStockStatus ? stoneStockStatusLabel(stock) : null;
    const allocationLabel = stoneAllocationStatusLabel(allocation);
    const showStockLabel =
        showStockStatus &&
        stockLabel !== null &&
        !(allocationLabel?.allocated === true && !stockLabel.available);

    return (
        <div className="spkChainStatusCell spkChainStatusCell--labelsOnly">
            <div className="spkChainStatusLabels">
                {showStockLabel ? (
                    <div className="spkChainStatusLabelRow">
                        <span
                            className={
                                stockLabel.available
                                    ? 'spkStoneStockBadge is-available'
                                    : 'spkStoneStockBadge is-unavailable'
                            }
                            title={stock?.note ?? undefined}
                        >
                            {stockLabel.text}
                        </span>
                    </div>
                ) : showStockStatus && stockLabel === null ? (
                    <div className="spkChainStatusLabelRow">
                        <span className="spkStoneStockMeta">—</span>
                    </div>
                ) : null}
                <div className="spkChainStatusLabelRow">
                    {allocationLabel !== null ? (
                        <span className={allocationLabel.badgeClass}>
                            {allocationLabel.text}
                        </span>
                    ) : (
                        <span className="spkStoneStockMeta">—</span>
                    )}
                </div>
            </div>
        </div>
    );
}
