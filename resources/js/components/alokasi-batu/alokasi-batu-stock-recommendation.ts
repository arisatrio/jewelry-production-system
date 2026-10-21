import { check as checkStoneStock } from '@/actions/App/Http/Controllers/SpkStoneStockController';
import type {
    DiamondLine,
    SettingStoneLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import {
    emptyDiamondLine,
    emptySettingLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import type { SpkStoneItem, SpkStoneStock } from '@/components/spk/types';
import { stock as stoneStock } from '@/routes/spk/stones';

type DossierStockRow = {
    id: number;
    code: string | null;
    crt: string | null;
};

type MicroStockRow = {
    id: number;
    name: string;
    balancePcs: string;
    balanceCrt: string;
};

type StoneStockPayload = {
    stock: SpkStoneStock;
    rows: Array<DossierStockRow | MicroStockRow>;
};

function readXsrfToken(): string {
    const row = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    if (!row) {
        return '';
    }

    return decodeURIComponent(row.slice('XSRF-TOKEN='.length));
}

function parsePcs(value: string | number | null | undefined): number {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(parsed) ? Math.max(0, Math.floor(parsed)) : 0;
}

function parseCaratPerPcs(stone: SpkStoneItem): number {
    const fromField = stone.caratPerPcs ?? stone.carat;
    const parsed = Number(String(fromField ?? '').replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
}

function requiredPcs(stone: SpkStoneItem, stock: SpkStoneStock): number {
    if (stock.requiredPcs > 0) {
        return stock.requiredPcs;
    }

    return parsePcs(stone.pcs);
}

async function fetchStoneStockPayload(
    stone: SpkStoneItem,
): Promise<StoneStockPayload | null> {
    const stoneId = String(stone.id).trim();

    const response = /^\d+$/.test(stoneId)
        ? await fetch(stoneStock.url(Number(stoneId)), {
              headers: { Accept: 'application/json' },
          })
        : await fetch(checkStoneStock.url(), {
              method: 'POST',
              headers: {
                  Accept: 'application/json',
                  'Content-Type': 'application/json',
                  'X-Requested-With': 'XMLHttpRequest',
                  'X-XSRF-TOKEN': readXsrfToken(),
              },
              credentials: 'same-origin',
              body: JSON.stringify({
                  shape_id: Number(stone.shapeId ?? 0),
                  pcs: parsePcs(stone.pcs),
                  carat_per_pcs: parseCaratPerPcs(stone) || null,
                  size:
                      stone.size === undefined ||
                      String(stone.size).trim() === '' ||
                      stone.size === '-'
                          ? null
                          : String(stone.size).trim(),
              }),
          });

    if (!response.ok) {
        return null;
    }

    const json = (await response.json()) as { data?: StoneStockPayload };

    return json.data ?? null;
}

function buildMicroLines(
    stone: SpkStoneItem,
    rows: MicroStockRow[],
    stock: SpkStoneStock,
): SettingStoneLine[] {
    let remaining = requiredPcs(stone, stock);
    const caratPerPcs = parseCaratPerPcs(stone);
    const lines: SettingStoneLine[] = [];

    for (const row of rows) {
        if (remaining <= 0) {
            break;
        }

        const available = parsePcs(row.balancePcs);

        if (available <= 0) {
            continue;
        }

        const pcs = Math.min(remaining, available);
        const crt = (pcs * caratPerPcs).toFixed(3);

        lines.push({
            ...emptySettingLine(),
            stone_id: String(row.id),
            pcs: String(pcs),
            crt,
            notes: '',
        });

        remaining -= pcs;
    }

    return lines;
}

function buildDiamondLines(
    stone: SpkStoneItem,
    rows: DossierStockRow[],
    stock: SpkStoneStock,
): DiamondLine[] {
    const needed = requiredPcs(stone, stock);

    return rows.slice(0, needed).map((row) => ({
        ...emptyDiamondLine(),
        diamond_id: String(row.id),
    }));
}

export type StockRecommendationResult =
    | {
          ok: true;
          settingStones: SettingStoneLine[];
          diamonds: DiamondLine[];
      }
    | { ok: false; message: string };

export async function fetchStockRecommendationForSpkStone(
    stone: SpkStoneItem,
    dossier: boolean,
): Promise<StockRecommendationResult> {
    const payload = await fetchStoneStockPayload(stone);

    if (payload === null) {
        return {
            ok: false,
            message: 'Gagal memuat rekomendasi stok. Coba lagi.',
        };
    }

    if (payload.rows.length === 0) {
        return {
            ok: false,
            message: 'Tidak ada stok yang cocok untuk baris batu ini.',
        };
    }

    if (dossier) {
        const diamonds = buildDiamondLines(
            stone,
            payload.rows as DossierStockRow[],
            payload.stock,
        );

        if (diamonds.length === 0) {
            return {
                ok: false,
                message: 'Tidak ada dossier tersedia untuk kebutuhan SPK.',
            };
        }

        return { ok: true, settingStones: [], diamonds };
    }

    const settingStones = buildMicroLines(
        stone,
        payload.rows as MicroStockRow[],
        payload.stock,
    );

    if (settingStones.length === 0) {
        return {
            ok: false,
            message: 'Stok mikro tidak mencukupi untuk kebutuhan SPK.',
        };
    }

    return { ok: true, settingStones, diamonds: [] };
}

export function mergeStockRecommendation(
    current: {
        settingStones: SettingStoneLine[];
        diamonds: DiamondLine[];
    },
    recommendation: StockRecommendationResult & { ok: true },
): {
    settingStones: SettingStoneLine[];
    diamonds: DiamondLine[];
} {
    const existingStoneIds = new Set(
        current.settingStones.map((line) => line.stone_id),
    );
    const existingDiamondIds = new Set(
        current.diamonds.map((line) => line.diamond_id),
    );

    return {
        settingStones: [
            ...current.settingStones,
            ...recommendation.settingStones.filter(
                (line) => !existingStoneIds.has(line.stone_id),
            ),
        ],
        diamonds: [
            ...current.diamonds,
            ...recommendation.diamonds.filter(
                (line) => !existingDiamondIds.has(line.diamond_id),
            ),
        ],
    };
}
