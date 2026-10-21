import type { MountedStoneLine } from '@/components/pasang-batu/pasang-batu-stone-editor';
import type { SettingAwalLine } from '@/components/pasang-batu/pasang-batu-setting-awal-panel';

function toNumber(value: number | string | null | undefined): number {
    if (value === null || value === undefined) {
        return 0;
    }

    const parsed = Number(String(value).replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
}

export function buildMountedStonesFromSettingAwal(
    lines: SettingAwalLine[],
): MountedStoneLine[] {
    return lines
        .filter((line) => line.sourceType === 'stone' && toNumber(line.pcsTerpasang) > 0)
        .map((line) => ({
            key: `awal-mounted-${line.key}`,
            diamond_code: line.diamondCode?.trim() || line.batu,
            shape_id:
                line.shapeId !== undefined && line.shapeId > 0
                    ? String(line.shapeId)
                    : '',
            pcs: String(toNumber(line.pcsTerpasang)),
            crt:
                line.crtTerpasang !== null && line.crtTerpasang !== undefined
                    ? String(line.crtTerpasang)
                    : '',
            size: line.size?.trim() ?? '',
        }));
}

export function settingAwalReturnedDiamondIds(lines: SettingAwalLine[]): Set<number> {
    return new Set(
        lines
            .filter(
                (line) =>
                    line.sourceType === 'diamond' &&
                    line.trdiamondId !== undefined &&
                    toNumber(line.pcsRetur) > 0,
            )
            .map((line) => line.trdiamondId as number),
    );
}
