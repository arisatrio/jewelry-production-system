import type {
    DiamondLine,
    MountedStoneLine,
    SettingStoneLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import type { PasangBatuStones } from '@/components/pasang-batu/pasang-batu-stone-tables';
import type { PasangBatuDiamondOption } from '@/components/pasang-batu/pasang-batu-stone-editor';

type StoneOption = {
    value: string;
    label: string;
};

function stoneLabel(stoneOptions: StoneOption[], stoneId: string): string {
    if (stoneId.trim() === '') {
        return '—';
    }

    return (
        stoneOptions.find((option) => option.value === stoneId)?.label ??
        `Batu #${stoneId}`
    );
}

function shapeLabel(
    shapeOptions: StoneOption[],
    shapeId: string,
    fallback?: string,
): string {
    if (shapeId.trim() !== '') {
        return (
            shapeOptions.find((option) => option.value === shapeId)?.label ??
            fallback ??
            '—'
        );
    }

    return fallback?.trim() !== '' ? (fallback ?? '—') : '—';
}

function diamondMeta(
    diamondOptions: PasangBatuDiamondOption[],
    diamondId: string,
): PasangBatuDiamondOption | null {
    if (diamondId.trim() === '') {
        return null;
    }

    return diamondOptions.find((option) => option.value === diamondId) ?? null;
}

/**
 * Maps live form state to the same four-panel batch shape shown on SPK Pasang Batu.
 */
export function pasangBatuStonesFromFormState(input: {
    settingStones: SettingStoneLine[];
    returnStones: SettingStoneLine[];
    diamonds: DiamondLine[];
    mountedStones: MountedStoneLine[];
    stoneOptions: StoneOption[];
    shapeOptions: StoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
}): PasangBatuStones {
    const mountedByBatch = new Map(
        input.mountedStones.map((line) => [line.batch_key, line]),
    );

    const diamonds: PasangBatuStones['diamonds'] = [];
    const mounted: PasangBatuStones['mounted'] = [];

    for (const diamond of input.diamonds) {
        const mountedLine = mountedByBatch.get(diamond.batch_key);

        if (mountedLine?.disposition !== 'terpasang') {
            continue;
        }

        const meta = diamondMeta(input.diamondOptions, diamond.diamond_id);

        diamonds.push({
            kode: meta?.code ?? meta?.label ?? '—',
            diamond: meta?.label ?? '—',
            bentuk: meta?.shape ?? '—',
            sertifikat: meta?.certificate ?? '—',
            crt: meta?.crt ?? mountedLine.crt ?? null,
        });

        mounted.push({
            kode:
                mountedLine.diamond_code.trim() !== ''
                    ? mountedLine.diamond_code.trim()
                    : (meta?.label ?? '—'),
            shape: shapeLabel(
                input.shapeOptions,
                mountedLine.shape_id,
                meta?.shape ?? undefined,
            ),
            pcs:
                mountedLine.pcs.trim() !== ''
                    ? mountedLine.pcs.trim()
                    : '1',
            crt: mountedLine.crt.trim() !== '' ? mountedLine.crt : null,
            size: mountedLine.size.trim() !== '' ? mountedLine.size : '—',
        });
    }

    return {
        setting: input.settingStones.map((line) => ({
            batu: stoneLabel(input.stoneOptions, line.stone_id),
            pcs: line.pcs.trim() !== '' ? line.pcs : null,
            crt: line.crt.trim() !== '' ? line.crt : null,
            notes: line.notes.trim() !== '' ? line.notes : null,
        })),
        return: input.returnStones.map((line) => ({
            batu: stoneLabel(input.stoneOptions, line.stone_id),
            pcs: line.pcs.trim() !== '' ? line.pcs : null,
            crt: line.crt.trim() !== '' ? line.crt : null,
            notes: line.notes.trim() !== '' ? line.notes : null,
        })),
        diamonds,
        mounted,
    };
}