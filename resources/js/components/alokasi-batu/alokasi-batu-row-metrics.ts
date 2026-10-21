import type { PasangBatuDiamondOption } from '@/components/pasang-batu/pasang-batu-stone-editor';
import {
    type AllocationsBySpkStoneId,
    isDossierSpkStone,
    type SpkStoneRowAllocation,
} from '@/components/alokasi-batu/alokasi-batu-allocation-state';
import type { SpkStoneItem } from '@/components/spk/types';

function parseNumber(value: string | number | null | undefined): number {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
}

export function requiredPcsForStone(stone: SpkStoneItem): number {
    const fromStock = stone.stock?.requiredPcs;

    if (fromStock !== undefined && fromStock > 0) {
        return fromStock;
    }

    return Math.max(0, Math.floor(parseNumber(stone.pcs)));
}

export function requiredTotalCrtForStone(stone: SpkStoneItem): number {
    return parseNumber(stone.totalCarat);
}

export function allocatedTotalsForRow(
    stone: SpkStoneItem,
    bucket: SpkStoneRowAllocation,
    diamondOptions: PasangBatuDiamondOption[],
): { pcs: number; crt: number } {
    if (isDossierSpkStone(stone)) {
        const diamondById = new Map(
            diamondOptions.map((option) => [option.value, option]),
        );

        let crt = 0;

        for (const line of bucket.diamonds) {
            const option = diamondById.get(line.diamond_id);
            crt += parseNumber(option?.crt ?? 0);
        }

        return { pcs: bucket.diamonds.length, crt };
    }

    let pcs = 0;
    let crt = 0;

    for (const line of bucket.settingStones) {
        pcs += Math.floor(parseNumber(line.pcs));
        crt += parseNumber(line.crt);
    }

    return { pcs, crt };
}

export function isRowAllocationComplete(
    stone: SpkStoneItem,
    bucket: SpkStoneRowAllocation,
    diamondOptions: PasangBatuDiamondOption[],
): boolean {
    const requiredPcs = requiredPcsForStone(stone);
    const { pcs } = allocatedTotalsForRow(stone, bucket, diamondOptions);

    if (requiredPcs <= 0) {
        return pcs > 0;
    }

    return pcs >= requiredPcs;
}

export function allocationProgress(
    skuStones: SpkStoneItem[],
    allocations: AllocationsBySpkStoneId,
    diamondOptions: PasangBatuDiamondOption[],
): { filled: number; total: number } {
    const total = skuStones.length;
    let filled = 0;

    for (const stone of skuStones) {
        const bucket = allocations[stone.id] ?? {
            settingStones: [],
            diamonds: [],
        };

        if (isRowAllocationComplete(stone, bucket, diamondOptions)) {
            filled += 1;
        }
    }

    return { filled, total };
}

export function matchesRequirement(
    stone: SpkStoneItem,
    bucket: SpkStoneRowAllocation,
    diamondOptions: PasangBatuDiamondOption[],
): 'empty' | 'partial' | 'complete' | 'over' {
    const requiredPcs = requiredPcsForStone(stone);
    const { pcs } = allocatedTotalsForRow(stone, bucket, diamondOptions);

    if (pcs <= 0) {
        return 'empty';
    }

    if (requiredPcs <= 0) {
        return 'complete';
    }

    if (pcs < requiredPcs) {
        return 'partial';
    }

    if (pcs > requiredPcs) {
        return 'over';
    }

    return 'complete';
}
