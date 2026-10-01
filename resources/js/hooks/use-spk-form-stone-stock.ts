import { useEffect, useRef, useState } from 'react';
import { check as checkStoneStock } from '@/actions/App/Http/Controllers/SpkStoneStockController';
import type { SpkStoneStock } from '@/components/spk/types';

const DEBOUNCE_MS = 400;

type FormStoneStockInput = {
    id: string;
    shapeId: string;
    pcs: string;
    caratPerPcs: string;
    size: string;
};

export type FormStoneStockState = {
    stock: SpkStoneStock | null;
    loading: boolean;
    failed: boolean;
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

export function canCheckFormStoneStock(stone: FormStoneStockInput): boolean {
    return stone.shapeId.trim() !== '' && Number(stone.pcs) > 0;
}

function stockRequestKey(stone: FormStoneStockInput): string {
    return [
        stone.shapeId,
        stone.pcs,
        stone.caratPerPcs,
        stone.size,
    ].join('|');
}

/** Cek stok batu form SPK (debounced) untuk setiap baris batu. */
export function useSpkFormStoneStock(
    stones: FormStoneStockInput[],
): Record<string, FormStoneStockState> {
    const [stateById, setStateById] = useState<
        Record<string, FormStoneStockState>
    >({});
    const requestKeysRef = useRef<Record<string, string>>({});

    useEffect(() => {
        const controllers: Record<string, AbortController> = {};
        const timeouts: Record<string, number> = {};

        stones.forEach((stone) => {
            if (!canCheckFormStoneStock(stone)) {
                setStateById((current) => {
                    if (!(stone.id in current)) {
                        return current;
                    }

                    const next = { ...current };
                    delete next[stone.id];

                    return next;
                });

                return;
            }

            const requestKey = stockRequestKey(stone);

            if (requestKeysRef.current[stone.id] === requestKey) {
                return;
            }

            setStateById((current) => ({
                ...current,
                [stone.id]: {
                    stock: current[stone.id]?.stock ?? null,
                    loading: true,
                    failed: false,
                },
            }));

            timeouts[stone.id] = window.setTimeout(() => {
                controllers[stone.id]?.abort();
                const controller = new AbortController();
                controllers[stone.id] = controller;

                void (async (): Promise<void> => {
                    try {
                        const response = await fetch(checkStoneStock.url(), {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-XSRF-TOKEN': readXsrfToken(),
                            },
                            credentials: 'same-origin',
                            signal: controller.signal,
                            body: JSON.stringify({
                                shape_id: Number(stone.shapeId),
                                pcs: Number(stone.pcs),
                                carat_per_pcs:
                                    stone.caratPerPcs.trim() === ''
                                        ? null
                                        : Number(
                                              String(
                                                  stone.caratPerPcs,
                                              ).replace(',', '.'),
                                          ),
                                size:
                                    stone.size.trim() === ''
                                        ? null
                                        : stone.size.trim(),
                            }),
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }

                        const json = (await response.json()) as {
                            data?: { stock?: SpkStoneStock };
                        };

                        if (controller.signal.aborted) {
                            return;
                        }

                        requestKeysRef.current[stone.id] = requestKey;

                        setStateById((current) => ({
                            ...current,
                            [stone.id]: {
                                stock: json.data?.stock ?? null,
                                loading: false,
                                failed: false,
                            },
                        }));
                    } catch {
                        if (controller.signal.aborted) {
                            return;
                        }

                        setStateById((current) => ({
                            ...current,
                            [stone.id]: {
                                stock: null,
                                loading: false,
                                failed: true,
                            },
                        }));
                    }
                })();
            }, DEBOUNCE_MS);
        });

        const stoneIds = new Set(stones.map((stone) => stone.id));

        Object.keys(requestKeysRef.current).forEach((id) => {
            if (!stoneIds.has(id)) {
                delete requestKeysRef.current[id];
            }
        });

        setStateById((current) => {
            const next = { ...current };
            let changed = false;

            Object.keys(next).forEach((id) => {
                if (!stoneIds.has(id)) {
                    delete next[id];
                    changed = true;
                }
            });

            return changed ? next : current;
        });

        return () => {
            Object.values(timeouts).forEach((timeoutId) => {
                window.clearTimeout(timeoutId);
            });
            Object.values(controllers).forEach((controller) => {
                controller.abort();
            });
        };
    }, [stones]);

    return stateById;
}
