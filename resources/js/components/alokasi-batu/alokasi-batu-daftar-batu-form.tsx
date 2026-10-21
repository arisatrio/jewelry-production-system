import { useMemo, useState } from 'react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { Text } from '@ui5/webcomponents-react/Text';
import {
    type AllocationsBySpkStoneId,
    isDossierSpkStone,
} from '@/components/alokasi-batu/alokasi-batu-allocation-state';
import { allocationProgress } from '@/components/alokasi-batu/alokasi-batu-row-metrics';
import { AlokasiBatuOtherAllocationDialog } from '@/components/alokasi-batu/alokasi-batu-other-allocation-dialog';
import { AlokasiBatuStoneRowCard } from '@/components/alokasi-batu/alokasi-batu-stone-row-card';
import {
    fetchStockRecommendationForSpkStone,
    mergeStockRecommendation,
} from '@/components/alokasi-batu/alokasi-batu-stock-recommendation';
import {
    emptyDiamondLine,
    emptySettingLine,
    type PasangBatuDiamondOption,
    type PasangBatuStoneOption,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import {
    SpkStoneStockDialog,
    type SpkStoneStockDialogStone,
} from '@/components/spk/spk-stone-stock-dialog';
import type { SpkStoneItem } from '@/components/spk/types';

type AlokasiBatuDaftarBatuFormProps = {
    skuStones: SpkStoneItem[];
    stoneOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    allocations: AllocationsBySpkStoneId;
    errors: Record<string, string>;
    onAllocationsChange: (next: AllocationsBySpkStoneId) => void;
};

export function AlokasiBatuDaftarBatuForm({
    skuStones,
    stoneOptions,
    diamondOptions,
    allocations,
    errors,
    onAllocationsChange,
}: AlokasiBatuDaftarBatuFormProps) {
    const [loadingStoneId, setLoadingStoneId] = useState<string | null>(null);
    const [fillAllLoading, setFillAllLoading] = useState(false);
    const [rowMessages, setRowMessages] = useState<Record<string, string>>({});
    const [stockDialogStone, setStockDialogStone] =
        useState<SpkStoneStockDialogStone | null>(null);
    const [otherAllocationStoneId, setOtherAllocationStoneId] = useState<
        string | null
    >(null);

    const progress = useMemo(
        () => allocationProgress(skuStones, allocations, diamondOptions),
        [skuStones, allocations, diamondOptions],
    );

    const updateStoneBucket = (
        spkStoneId: string,
        patch: Partial<AllocationsBySpkStoneId[string]>,
    ) => {
        const current = allocations[spkStoneId] ?? {
            settingStones: [],
            diamonds: [],
        };

        onAllocationsChange({
            ...allocations,
            [spkStoneId]: { ...current, ...patch },
        });
    };

    const applyRecommendationToSnapshot = async (
        stone: SpkStoneItem,
        snapshot: AllocationsBySpkStoneId,
    ): Promise<{
        allocations: AllocationsBySpkStoneId;
        message?: string;
        changed: boolean;
    }> => {
        const dossier = isDossierSpkStone(stone);
        const recommendation = await fetchStockRecommendationForSpkStone(
            stone,
            dossier,
        );

        if (!recommendation.ok) {
            return {
                allocations: snapshot,
                message: recommendation.message,
                changed: false,
            };
        }

        const current = snapshot[stone.id] ?? {
            settingStones: [],
            diamonds: [],
        };
        const merged = mergeStockRecommendation(current, recommendation);

        if (
            merged.settingStones.length === current.settingStones.length &&
            merged.diamonds.length === current.diamonds.length
        ) {
            return {
                allocations: snapshot,
                message: 'Semua rekomendasi stok sudah ditambahkan.',
                changed: false,
            };
        }

        return {
            allocations: {
                ...snapshot,
                [stone.id]: merged,
            },
            changed: true,
        };
    };

    const handleApplyForStone = async (stone: SpkStoneItem) => {
        setLoadingStoneId(stone.id);
        setRowMessages((current) => {
            const next = { ...current };
            delete next[stone.id];

            return next;
        });

        try {
            const result = await applyRecommendationToSnapshot(
                stone,
                allocations,
            );

            if (result.message) {
                setRowMessages((current) => ({
                    ...current,
                    [stone.id]: result.message,
                }));
            }

            if (result.changed) {
                onAllocationsChange(result.allocations);
            }
        } finally {
            setLoadingStoneId(null);
        }
    };

    const handleFillAll = async () => {
        setFillAllLoading(true);
        let snapshot = allocations;
        const messages: Record<string, string> = {};

        try {
            for (const stone of skuStones) {
                const result = await applyRecommendationToSnapshot(
                    stone,
                    snapshot,
                );

                snapshot = result.allocations;

                if (result.message) {
                    messages[stone.id] = result.message;
                }
            }

            onAllocationsChange(snapshot);
            setRowMessages(messages);
        } finally {
            setFillAllLoading(false);
        }
    };

    if (skuStones.length === 0) {
        return (
            <div className="spkItemStoneSection">
                <div className="spkStoneEmpty">
                    <Text>Tidak ada batu pada SPK ini.</Text>
                </div>
            </div>
        );
    }

    return (
        <div className="spkItemStoneSection alokasiBatuFormSection">
            <div className="spkStoneCardHeader alokasiBatuFormHeader">
                <div>
                    <h2 className="spkFioriDetailBlockTitle">Alokasi batu</h2>
                    <Text className="spkFioriDetailBlockMeta">
                        {progress.filled} / {progress.total} baris batu sudah
                        memiliki cadangan sesuai kebutuhan
                    </Text>
                </div>
                <Button
                    design="Emphasized"
                    icon={addIcon}
                    type="Button"
                    disabled={fillAllLoading || loadingStoneId !== null}
                    onClick={() => {
                        void handleFillAll();
                    }}
                >
                    {fillAllLoading
                        ? 'Mengisi…'
                        : 'Isi semua dari rekomendasi stok'}
                </Button>
            </div>

            <MessageStrip design="Information" className="alokasiBatuGuideStrip">
                <strong>Kebutuhan SPK</strong> = spesifikasi dari daftar batu
                SKU. <strong>Stok Inventory</strong> =
                ketersediaan inventory saat ini. <strong>Alokasi Batu SPK ini</strong>{' '}
                = batu yang Anda lock untuk SPK ini; stok fisik keluar saat
                submit SPK Diamond.
            </MessageStrip>

            {errors._setting_stones ? (
                <Text className="spkFioriError px-4">
                    {errors._setting_stones}
                </Text>
            ) : null}
            {errors._diamonds ? (
                <Text className="spkFioriError px-4">{errors._diamonds}</Text>
            ) : null}

            <div className="alokasiBatuStoneCardList">
                {skuStones.map((stone) => {
                    const bucket = allocations[stone.id] ?? {
                        settingStones: [],
                        diamonds: [],
                    };

                    return (
                        <AlokasiBatuStoneRowCard
                            key={stone.id}
                            stone={stone}
                            bucket={bucket}
                            stoneOptions={stoneOptions}
                            diamondOptions={diamondOptions}
                            errors={errors}
                            loading={loadingStoneId === stone.id}
                            rowMessage={rowMessages[stone.id]}
                            onApplyFromStock={() => {
                                void handleApplyForStone(stone);
                            }}
                            onAddOtherAllocation={() => {
                                const dossier = isDossierSpkStone(stone);

                                updateStoneBucket(stone.id, dossier
                                    ? {
                                          diamonds: [
                                              ...bucket.diamonds,
                                              emptyDiamondLine(),
                                          ],
                                      }
                                    : {
                                          settingStones: [
                                              ...bucket.settingStones,
                                              emptySettingLine(),
                                          ],
                                      });

                                setOtherAllocationStoneId(stone.id);
                                setRowMessages((current) => {
                                    const next = { ...current };
                                    delete next[stone.id];

                                    return next;
                                });
                            }}
                            onShowStock={() =>
                                setStockDialogStone({
                                    ...stone,
                                    shapeId: stone.shapeId,
                                })
                            }
                            onRemoveSetting={(key) =>
                                updateStoneBucket(stone.id, {
                                    settingStones:
                                        bucket.settingStones.filter(
                                            (line) => line.key !== key,
                                        ),
                                })
                            }
                            onRemoveDiamond={(key) =>
                                updateStoneBucket(stone.id, {
                                    diamonds: bucket.diamonds.filter(
                                        (line) => line.key !== key,
                                    ),
                                })
                            }
                        />
                    );
                })}
            </div>

            <SpkStoneStockDialog
                stone={stockDialogStone}
                onOpenChange={(open) => {
                    if (!open) {
                        setStockDialogStone(null);
                    }
                }}
            />

            <AlokasiBatuOtherAllocationDialog
                stone={
                    otherAllocationStoneId !== null
                        ? (skuStones.find(
                              (item) => item.id === otherAllocationStoneId,
                          ) ?? null)
                        : null
                }
                bucket={
                    otherAllocationStoneId !== null
                        ? (allocations[otherAllocationStoneId] ?? {
                              settingStones: [],
                              diamonds: [],
                          })
                        : { settingStones: [], diamonds: [] }
                }
                stoneOptions={stoneOptions}
                diamondOptions={diamondOptions}
                errors={errors}
                onOpenChange={(open) => {
                    if (!open) {
                        setOtherAllocationStoneId(null);
                    }
                }}
                onSettingLinesChange={(lines) => {
                    if (otherAllocationStoneId === null) {
                        return;
                    }

                    updateStoneBucket(otherAllocationStoneId, {
                        settingStones: lines,
                    });
                }}
                onDiamondLinesChange={(lines) => {
                    if (otherAllocationStoneId === null) {
                        return;
                    }

                    updateStoneBucket(otherAllocationStoneId, {
                        diamonds: lines,
                    });
                }}
            />
        </div>
    );
}
