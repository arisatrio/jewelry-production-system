import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    ChainIssueStockDialog,
    type ChainIssueUnit,
} from '@/pages/inventory/chain/chain-issue-stock-dialog';
import type { SpkChainAllocation, SpkChainItem } from '@/components/spk/types';
import { allocationUnits } from '@/routes/spk/chains';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type SpkChainAllocateDialogProps = {
    chain: SpkChainItem | null;
    spk: { rowId: number; produksiNo: string };
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

type AllocationPayload = {
    allocation: SpkChainAllocation;
    units: ChainIssueUnit[];
    colorOptions: string[];
    suggestedColor: string | null;
};

function chainSummary(chain: SpkChainItem): string {
    return [chain.category, chain.item, chain.color, chain.size]
        .map((part) => String(part ?? '').trim())
        .filter((part) => part !== '')
        .join(' · ');
}

export function SpkChainAllocateDialog({
    chain,
    spk,
    open,
    onOpenChange,
}: SpkChainAllocateDialogProps) {
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [payload, setPayload] = useState<AllocationPayload | null>(null);
    const [issuingUnit, setIssuingUnit] = useState<ChainIssueUnit | null>(null);
    const [colorFilter, setColorFilter] = useState('');

    useEffect(() => {
        if (!open || chain === null) {
            return;
        }

        setColorFilter(chain.color?.trim() ?? '');
    }, [open, chain?.id, chain?.color]);

    useEffect(() => {
        if (!open || chain === null) {
            return;
        }

        const controller = new AbortController();
        setLoading(true);
        setFailed(false);
        setPayload(null);
        setIssuingUnit(null);

        const query =
            colorFilter.trim() !== ''
                ? { color: colorFilter.trim() }
                : undefined;

        void fetch(
            allocationUnits.url(Number(chain.id), {
                query,
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat unit stok');
                }

                const body = (await response.json()) as {
                    data?: AllocationPayload;
                };

                setPayload(body.data ?? null);
            })
            .catch((error: unknown) => {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }

                setFailed(true);
            })
            .finally(() => {
                setLoading(false);
            });

        return () => {
            controller.abort();
        };
    }, [open, chain, colorFilter]);

    const allocation = payload?.allocation;
    const units = payload?.units ?? [];
    const colorOptions = payload?.colorOptions ?? [];
    const isUnlinked = allocation?.status === 'unlinked';
    const canAllocate = allocation?.nextAction === 'allocate';

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="spkChainAllocateDialog sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Alokasi Chain</DialogTitle>
                        <DialogDescription>
                            {chain !== null
                                ? `Pilih unit stok untuk SPK ${spk.produksiNo} · ${chainSummary(chain)}`
                                : 'Pilih baris rantai pada SPK.'}
                        </DialogDescription>
                    </DialogHeader>

                    {chain === null ? null : loading ? (
                        <p className="text-sm text-muted-foreground">
                            Memuat unit stok…
                        </p>
                    ) : failed ? (
                        <p className="text-sm text-muted-foreground">
                            Gagal memuat data. Silakan coba lagi.
                        </p>
                    ) : isUnlinked ? (
                        <p className="text-sm text-muted-foreground">
                            Master chain belum terhubung pada baris ini.
                        </p>
                    ) : !canAllocate ? (
                        <p className="text-sm text-muted-foreground">
                            {allocation?.label ??
                                'Status rantai tidak memerlukan alokasi stok.'}
                        </p>
                    ) : (
                        <>
                            <div className="spkChainAllocateFilters">
                                <div className="spkChainAllocateFilterField">
                                    <Label
                                        htmlFor="spk-chain-allocate-color-filter"
                                        className="spkChainAllocateFilterLabel"
                                    >
                                        Warna
                                    </Label>
                                    <select
                                        id="spk-chain-allocate-color-filter"
                                        className="spkChainAllocateFilterSelect"
                                        value={colorFilter}
                                        onChange={(event) => {
                                            setColorFilter(event.target.value);
                                        }}
                                    >
                                        <option value="">Semua warna</option>
                                        {colorOptions.map((color) => (
                                            <option
                                                key={color}
                                                value={color}
                                            >
                                                {color}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {units.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Tidak ada unit chain siap pakai untuk filter
                                    warna ini.
                                </p>
                            ) : (
                                <div className="spkStoneTableWrap">
                                    <table
                                        className="spkStoneTable spkChainTable"
                                    >
                                        <thead>
                                            <tr>
                                                <th>No. Unit</th>
                                                <th>Nama</th>
                                                <th
                                                    className="spkTableColCenter"
                                                >
                                                    Panjang
                                                </th>
                                                <th>Warna</th>
                                                <th
                                                    className="spkStoneTableActionCol spkChainTableActionCol"
                                                >
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {units.map((unit) => (
                                                <tr key={unit.id}>
                                                    <td className="tabular-nums">
                                                        {unit.unitNo ??
                                                            unit.id}
                                                    </td>
                                                    <td>{unit.name}</td>
                                                    <td
                                                        className="spkTableColCenter tabular-nums"
                                                    >
                                                        {unit.chainLength} cm
                                                    </td>
                                                    <td>
                                                        {unit.color ?? '—'}
                                                    </td>
                                                    <td>
                                                        <button
                                                            type="button"
                                                            className="spkChainFulfillmentActionBtn"
                                                            onClick={() =>
                                                                setIssuingUnit(
                                                                    unit,
                                                                )
                                                            }
                                                        >
                                                            Pilih
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <ChainIssueStockDialog
                unit={issuingUnit}
                open={issuingUnit !== null}
                fixedSpk={spk}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setIssuingUnit(null);
                    }
                }}
                onAllocated={() => {
                    setIssuingUnit(null);
                    onOpenChange(false);
                    router.reload({
                        only: ['chains'],
                        preserveScroll: true,
                    });
                }}
            />
        </>
    );
}
