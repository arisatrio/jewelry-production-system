import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Text } from '@ui5/webcomponents-react/Text';
import { AlokasiBatuAllocationSummary } from '@/components/alokasi-batu/alokasi-batu-allocation-summary';
import {
    allocatedTotalsForRow,
    matchesRequirement,
    requiredPcsForStone,
    requiredTotalCrtForStone,
} from '@/components/alokasi-batu/alokasi-batu-row-metrics';
import {
    type SpkStoneRowAllocation,
    isDossierSpkStone,
} from '@/components/alokasi-batu/alokasi-batu-allocation-state';
import type {
    PasangBatuDiamondOption,
    PasangBatuStoneOption,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import {
    formatDecimal3Id,
    spkStoneKindLabel,
} from '@/components/spk/spk-stone-list';
import type { SpkStoneItem } from '@/components/spk/types';

function emptyDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const text = String(value).trim();

    return text !== '' ? text : '—';
}

type AlokasiBatuStoneRowCardProps = {
    stone: SpkStoneItem;
    bucket: SpkStoneRowAllocation;
    stoneOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    errors: Record<string, string>;
    loading: boolean;
    rowMessage?: string;
    onApplyFromStock: () => void;
    onAddOtherAllocation: () => void;
    onShowStock: () => void;
    onRemoveSetting: (key: string) => void;
    onRemoveDiamond: (key: string) => void;
};

function matchLabel(
    match: ReturnType<typeof matchesRequirement>,
): { text: string; className: string } {
    switch (match) {
        case 'complete':
            return {
                text: 'Sesuai kebutuhan',
                className: 'alokasiBatuMatchBadge is-complete',
            };
        case 'partial':
            return {
                text: 'Belum lengkap',
                className: 'alokasiBatuMatchBadge is-partial',
            };
        case 'over':
            return {
                text: 'Melebihi kebutuhan',
                className: 'alokasiBatuMatchBadge is-over',
            };
        default:
            return {
                text: 'Belum diisi',
                className: 'alokasiBatuMatchBadge is-empty',
            };
    }
}

export function AlokasiBatuStoneRowCard({
    stone,
    bucket,
    stoneOptions,
    diamondOptions,
    errors,
    loading,
    rowMessage,
    onApplyFromStock,
    onAddOtherAllocation,
    onShowStock,
    onRemoveSetting,
    onRemoveDiamond,
}: AlokasiBatuStoneRowCardProps) {
    const dossier = isDossierSpkStone(stone);
    const requiredPcs = requiredPcsForStone(stone);
    const requiredCrt = requiredTotalCrtForStone(stone);
    const allocated = allocatedTotalsForRow(stone, bucket, diamondOptions);
    const match = matchesRequirement(stone, bucket, diamondOptions);
    const matchBadge = matchLabel(match);
    const hasAllocation = dossier
        ? bucket.diamonds.length > 0
        : bucket.settingStones.length > 0;
    const stock = stone.stock;
    const stockAvailable = stock?.status === 'available';

    return (
        <article className="alokasiBatuStoneCard">
            <div className="alokasiBatuStoneCardZones">
                <section className="alokasiBatuZone alokasiBatuZone--requirement">
                    <div className="alokasiBatuZoneHeader">
                        <span className="alokasiBatuZoneBadge">
                            Kebutuhan SPK
                        </span>
                        <span className="alokasiBatuZoneMeta">
                            Dari daftar batu SKU
                        </span>
                    </div>
                    <p className="alokasiBatuZoneTitle">
                        {emptyDash(stone.positionName)} ·{' '}
                        {emptyDash(stone.shapeName || stone.shape)} ·{' '}
                        {spkStoneKindLabel(
                            stone.stoneKind ?? stone.stock?.source,
                        )}
                    </p>
                    <dl className="alokasiBatuMetrics">
                        <div>
                            <dt>Ukuran</dt>
                            <dd>{emptyDash(stone.size)}</dd>
                        </div>
                        <div>
                            <dt>Carat / butir</dt>
                            <dd>
                                {formatDecimal3Id(
                                    stone.caratPerPcs ?? stone.carat,
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt>Butir</dt>
                            <dd>{requiredPcs}</dd>
                        </div>
                        <div>
                            <dt>Total carat</dt>
                            <dd>{formatDecimal3Id(requiredCrt)}</dd>
                        </div>
                    </dl>
                </section>

                <section className="alokasiBatuZone alokasiBatuZone--stock">
                    <div className="alokasiBatuZoneHeader">
                        <span className="alokasiBatuZoneBadge alokasiBatuZoneBadge--stock">
                            Stok Inventory
                        </span>
                    </div>
                    {stock ? (
                        <>
                            <span
                                className={
                                    stockAvailable
                                        ? 'spkStoneStockBadge is-available'
                                        : 'spkStoneStockBadge is-unavailable'
                                }
                            >
                                {stockAvailable
                                    ? 'Stok tersedia'
                                    : 'Stok tidak mencukupi'}
                            </span>
                            <p className="alokasiBatuZoneDetail">
                                Butuh {stock.requiredPcs} pcs · tersedia{' '}
                                {stock.availablePcs} pcs
                            </p>
                            {stock.note ? (
                                <p className="alokasiBatuZoneNote">{stock.note}</p>
                            ) : null}
                        </>
                    ) : (
                        <Text>Stok belum dicek.</Text>
                    )}
                    <Button
                        design="Transparent"
                        icon={listIcon}
                        type="Button"
                        className="alokasiBatuZoneAction"
                        onClick={onShowStock}
                    >
                        Lihat detail stok
                    </Button>
                </section>

                <section className="alokasiBatuZone alokasiBatuZone--allocation">
                    <div className="alokasiBatuZoneHeader">
                        <span className="alokasiBatuZoneBadge alokasiBatuZoneBadge--allocation">
                            Alokasi Batu SPK ini
                        </span>
                        <span className={matchBadge.className}>
                            {matchBadge.text}
                        </span>
                    </div>
                    <dl className="alokasiBatuMetrics alokasiBatuMetrics--compare">
                        <div>
                            <dt>Butir (cadangan / kebutuhan)</dt>
                            <dd>
                                <strong>{allocated.pcs}</strong>
                                <span className="text-muted-foreground">
                                    {' '}
                                    / {requiredPcs}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt>Total carat (cadangan / kebutuhan)</dt>
                            <dd>
                                <strong>
                                    {formatDecimal3Id(allocated.crt)}
                                </strong>
                                <span className="text-muted-foreground">
                                    {' '}
                                    / {formatDecimal3Id(requiredCrt)}
                                </span>
                            </dd>
                        </div>
                    </dl>
                    {hasAllocation ? (
                        <AlokasiBatuAllocationSummary
                            compact
                            dossier={dossier}
                            settingStones={bucket.settingStones}
                            diamonds={bucket.diamonds}
                            stoneOptions={stoneOptions}
                            diamondOptions={diamondOptions}
                            errorPrefix={`allocation.${stone.id}`}
                            errors={errors}
                            onRemoveSetting={onRemoveSetting}
                            onRemoveDiamond={onRemoveDiamond}
                        />
                    ) : (
                        <Text className="alokasiBatuZoneEmpty">
                            Belum ada alokasi batu. Ambil dari rekomendasi stok
                            gudang.
                        </Text>
                    )}
                    <div className="alokasiBatuZoneActions">
                        <Button
                            design="Emphasized"
                            icon={addIcon}
                            type="Button"
                            disabled={loading}
                            onClick={onApplyFromStock}
                        >
                            {loading
                                ? 'Memuat…'
                                : 'Ambil alokasi batu dari stok'}
                        </Button>
                        <Button
                            design="Default"
                            icon={addIcon}
                            type="Button"
                            disabled={loading}
                            onClick={onAddOtherAllocation}
                        >
                            Ambil alokasi batu lain
                        </Button>
                    </div>
                    {rowMessage ? (
                        <Text className="spkFioriError alokasiBatuZoneError">
                            {rowMessage}
                        </Text>
                    ) : null}
                </section>
            </div>
        </article>
    );
}
