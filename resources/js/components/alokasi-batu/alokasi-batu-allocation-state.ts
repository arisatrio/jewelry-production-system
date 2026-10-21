import type {
    DiamondLine,
    SettingStoneLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import {
    emptyDiamondLine,
    emptySettingLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import type { SpkStoneItem } from '@/components/spk/types';

export type SpkStoneRowAllocation = {
    settingStones: SettingStoneLine[];
    diamonds: DiamondLine[];
};

export type AllocationsBySpkStoneId = Record<string, SpkStoneRowAllocation>;

function lineKey(prefix: string): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${Math.random().toString(36).slice(2, 9)}`;
}

export function isDossierSpkStone(stone: SpkStoneItem): boolean {
    if (stone.stoneKind === 'dossier') {
        return true;
    }

    if (stone.stoneKind === 'micro') {
        return false;
    }

    return stone.stock?.source === 'dossier';
}

export function emptyAllocationsForStones(
    skuStones: SpkStoneItem[],
): AllocationsBySpkStoneId {
    return Object.fromEntries(
        skuStones.map((stone) => [
            stone.id,
            { settingStones: [], diamonds: [] },
        ]),
    );
}

export function distributeInitialAllocations(
    skuStones: SpkStoneItem[],
    settingStones: Array<{
        stone_id: string;
        pcs: string;
        crt: string;
        notes: string;
    }>,
    diamonds: Array<{ diamond_id: string }>,
): AllocationsBySpkStoneId {
    const allocations = emptyAllocationsForStones(skuStones);
    const microRows = skuStones.filter((stone) => !isDossierSpkStone(stone));
    const dossierRows = skuStones.filter(isDossierSpkStone);

    settingStones.forEach((line, index) => {
        const target =
            microRows[index % microRows.length] ??
            microRows[0] ??
            skuStones[0];

        if (!target) {
            return;
        }

        allocations[target.id].settingStones.push({
            key: lineKey('setting'),
            stone_id: line.stone_id,
            pcs: line.pcs,
            crt: line.crt,
            notes: line.notes,
        });
    });

    diamonds.forEach((line, index) => {
        const target =
            dossierRows[index % dossierRows.length] ??
            dossierRows[0] ??
            skuStones[0];

        if (!target) {
            return;
        }

        allocations[target.id].diamonds.push({
            key: lineKey('diamond'),
            diamond_id: line.diamond_id,
        });
    });

    return allocations;
}

export function flattenAllocationsForSubmit(
    skuStones: SpkStoneItem[],
    allocations: AllocationsBySpkStoneId,
): {
    setting_stones: SettingStoneLine[];
    diamonds: DiamondLine[];
} {
    const setting_stones: SettingStoneLine[] = [];
    const diamonds: DiamondLine[] = [];

    for (const stone of skuStones) {
        const bucket = allocations[stone.id];

        if (!bucket) {
            continue;
        }

        setting_stones.push(...bucket.settingStones);
        diamonds.push(...bucket.diamonds);
    }

    return { setting_stones, diamonds };
}

export function remapAllocationFormErrors(
    errors: Record<string, string>,
    skuStones: SpkStoneItem[],
    allocations: AllocationsBySpkStoneId,
): Record<string, string> {
    const { setting_stones, diamonds } = flattenAllocationsForSubmit(
        skuStones,
        allocations,
    );
    const remapped: Record<string, string> = { ...errors };

    for (const [key, message] of Object.entries(errors)) {
        const settingMatch = key.match(/^setting_stones\.(\d+)\.(.+)$/);

        if (settingMatch) {
            const flatIndex = Number(settingMatch[1]);
            const field = settingMatch[2];
            const located = locateFlatSettingLine(
                skuStones,
                allocations,
                flatIndex,
            );

            if (located) {
                remapped[
                    `allocation.${located.stoneId}.setting_stones.${located.localIndex}.${field}`
                ] = message;
            }

            delete remapped[key];

            continue;
        }

        const diamondMatch = key.match(/^diamonds\.(\d+)\.(.+)$/);

        if (diamondMatch) {
            const flatIndex = Number(diamondMatch[1]);
            const field = diamondMatch[2];
            const located = locateFlatDiamondLine(
                skuStones,
                allocations,
                flatIndex,
            );

            if (located) {
                remapped[
                    `allocation.${located.stoneId}.diamonds.${located.localIndex}.${field}`
                ] = message;
            }

            delete remapped[key];

            continue;
        }
    }

    if (errors.setting_stones && setting_stones.length === 0) {
        remapped._setting_stones = errors.setting_stones;
    }

    if (errors.diamonds && diamonds.length === 0) {
        remapped._diamonds = errors.diamonds;
    }

    return remapped;
}

function locateFlatSettingLine(
    skuStones: SpkStoneItem[],
    allocations: AllocationsBySpkStoneId,
    flatIndex: number,
): { stoneId: string; localIndex: number } | null {
    let cursor = 0;

    for (const stone of skuStones) {
        const lines = allocations[stone.id]?.settingStones ?? [];

        for (let localIndex = 0; localIndex < lines.length; localIndex++) {
            if (cursor === flatIndex) {
                return { stoneId: stone.id, localIndex };
            }

            cursor += 1;
        }
    }

    return null;
}

function locateFlatDiamondLine(
    skuStones: SpkStoneItem[],
    allocations: AllocationsBySpkStoneId,
    flatIndex: number,
): { stoneId: string; localIndex: number } | null {
    let cursor = 0;

    for (const stone of skuStones) {
        const lines = allocations[stone.id]?.diamonds ?? [];

        for (let localIndex = 0; localIndex < lines.length; localIndex++) {
            if (cursor === flatIndex) {
                return { stoneId: stone.id, localIndex };
            }

            cursor += 1;
        }
    }

    return null;
}

export function addSettingLineToStone(
    allocations: AllocationsBySpkStoneId,
    spkStoneId: string,
): AllocationsBySpkStoneId {
    const current = allocations[spkStoneId] ?? {
        settingStones: [],
        diamonds: [],
    };

    return {
        ...allocations,
        [spkStoneId]: {
            ...current,
            settingStones: [...current.settingStones, emptySettingLine()],
        },
    };
}

export function addDiamondLineToStone(
    allocations: AllocationsBySpkStoneId,
    spkStoneId: string,
): AllocationsBySpkStoneId {
    const current = allocations[spkStoneId] ?? {
        settingStones: [],
        diamonds: [],
    };

    return {
        ...allocations,
        [spkStoneId]: {
            ...current,
            diamonds: [...current.diamonds, emptyDiamondLine()],
        },
    };
}
