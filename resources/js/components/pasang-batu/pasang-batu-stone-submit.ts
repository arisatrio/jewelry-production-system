import type {
    DiamondLine,
    MountedStoneLine,
    SettingStoneLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';

export type PasangBatuStoneSubmitLine = {
    stone_id: number;
    pcs: string | null;
    crt: string | null;
    notes: string | null;
};

export type PasangBatuMountedSubmitLine = {
    diamond_code: string | null;
    shape_id: number | null;
    pcs: string | null;
    crt: string | null;
    size: string | null;
};

export type PasangBatuStoneSubmitPayload = {
    /** Mikro terpasang → `trstone` transtype setting (7). */
    setting_stones: PasangBatuStoneSubmitLine[];
    /** Mikro return → `trstone` transtype return (8). */
    return_stones: PasangBatuStoneSubmitLine[];
    /** Dossier terpasang → `trdiamond.diamondmounting_id`. */
    diamonds: Array<{ diamond_id: number }>;
    /** Detail terpasang → `diamondmountingdetail`. */
    mounted_stones: PasangBatuMountedSubmitLine[];
};

function normalizeNullableNumber(value: string): string | null {
    const trimmed = value.trim().replace(',', '.');

    return trimmed !== '' ? trimmed : null;
}

function normalizeOptionalId(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function parseQuantity(value: string | null | undefined): number | null {
    if (value === null || value === undefined) {
        return null;
    }

    const trimmed = value.trim().replace(',', '.');

    if (trimmed === '') {
        return null;
    }

    const parsed = Number.parseFloat(trimmed);

    return Number.isFinite(parsed) ? parsed : null;
}

function aggregateMicroStoneLines(
    lines: SettingStoneLine[],
): PasangBatuStoneSubmitLine[] {
    const merged = new Map<
        number,
        {
            pcs: number;
            crt: number;
            notes: string[];
        }
    >();

    for (const line of lines) {
        const stoneId = Number(line.stone_id.trim());

        if (!Number.isFinite(stoneId) || stoneId <= 0) {
            continue;
        }

        const pcs = parseQuantity(line.pcs) ?? 0;
        const crt = parseQuantity(line.crt) ?? 0;
        const note = line.notes.trim();

        const existing = merged.get(stoneId);

        if (existing === undefined) {
            merged.set(stoneId, {
                pcs,
                crt,
                notes: note !== '' ? [note] : [],
            });

            continue;
        }

        existing.pcs += pcs;
        existing.crt += crt;

        if (note !== '' && !existing.notes.includes(note)) {
            existing.notes.push(note);
        }
    }

    return [...merged.entries()]
        .sort(([left], [right]) => left - right)
        .map(([stoneId, totals]) => ({
            stone_id: stoneId,
            pcs:
                totals.pcs > 0
                    ? String(
                          Number.isInteger(totals.pcs)
                              ? totals.pcs
                              : totals.pcs,
                      )
                    : null,
            crt:
                totals.crt > 0
                    ? totals.crt.toFixed(3)
                    : null,
            notes:
                totals.notes.length > 0 ? totals.notes.join(', ') : null,
        }));
}

/**
 * Maps form batch state to the four stone payloads consumed by
 * `DiamondMountingStoneSynchronizer` (same tables as form sections legacy).
 */
export function buildPasangBatuStoneSubmitPayload(formData: {
    setting_stones: SettingStoneLine[];
    return_stones: SettingStoneLine[];
    diamonds: DiamondLine[];
    mounted_stones: MountedStoneLine[];
}): PasangBatuStoneSubmitPayload {
    const diamondsByBatch = new Map(
        formData.diamonds.map((line) => [line.batch_key, line]),
    );

    const mountedTerpasang = formData.mounted_stones.filter(
        (line) => line.disposition === 'terpasang',
    );

    const diamonds: Array<{ diamond_id: number }> = [];
    const mounted_stones: PasangBatuMountedSubmitLine[] = [];

    const linkedBatchKeys = new Set<string>();

    for (const mounted of mountedTerpasang) {
        const diamond = diamondsByBatch.get(mounted.batch_key);

        if (diamond !== undefined) {
            const diamondId = Number(diamond.diamond_id.trim());

            if (Number.isFinite(diamondId) && diamondId > 0) {
                diamonds.push({ diamond_id: diamondId });
                linkedBatchKeys.add(mounted.batch_key);
            }
        }

        const hasDetail =
            mounted.diamond_code.trim() !== '' ||
            mounted.shape_id.trim() !== '' ||
            mounted.pcs.trim() !== '' ||
            mounted.crt.trim() !== '' ||
            mounted.size.trim() !== '';

        if (!hasDetail) {
            continue;
        }

        mounted_stones.push({
            diamond_code:
                mounted.diamond_code.trim() !== ''
                    ? mounted.diamond_code.trim()
                    : null,
            shape_id: normalizeOptionalId(mounted.shape_id),
            pcs: normalizeNullableNumber(mounted.pcs),
            crt: normalizeNullableNumber(mounted.crt),
            size: mounted.size.trim() !== '' ? mounted.size.trim() : null,
        });
    }

    return {
        setting_stones: aggregateMicroStoneLines(formData.setting_stones),
        return_stones: aggregateMicroStoneLines(formData.return_stones),
        diamonds,
        mounted_stones,
    };
}
