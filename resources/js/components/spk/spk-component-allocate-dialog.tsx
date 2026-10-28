import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    ComponentIssueStockDialog,
    type ComponentIssueUnit,
} from '@/pages/inventory/component/component-issue-stock-dialog';
import type { SpkComponentAllocation, SpkComponentItem } from '@/components/spk/types';
import { allocationUnits } from '@/routes/spk/components';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type SpkComponentAllocateDialogProps = {
    component: SpkComponentItem | null;
    spk: { rowId: number; produksiNo: string };
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

type AllocationUnit = ComponentIssueUnit & {
    matchesMasterComponent?: boolean;
    masterItem?: string | null;
    masterItemCode?: string | null;
    msComponentId?: number;
};

type AllocationPayload = {
    allocation: SpkComponentAllocation;
    units: AllocationUnit[];
};

function componentSummary(component: SpkComponentItem): string {
    return [component.category, component.item, component.color]
        .map((part) => String(part ?? '').trim())
        .filter((part) => part !== '')
        .join(' · ');
}

function unitSearchHaystack(unit: AllocationUnit): string {
    return [
        unit.unitNo,
        unit.id,
        unit.name,
        unit.componentSize,
        unit.weight,
        unit.color,
        unit.masterItem,
        unit.masterItemCode,
    ]
        .map((part) => String(part ?? '').trim())
        .filter((part) => part !== '')
        .join(' ')
        .toLowerCase();
}

function formatAllocateUnitWeight(
    weight: string | null | undefined,
): string {
    if (weight === null || weight === undefined) {
        return '—';
    }

    const trimmed = weight.trim();

    return trimmed !== '' ? `${trimmed} g` : '—';
}

export function SpkComponentAllocateDialog({
    component,
    spk,
    open,
    onOpenChange,
}: SpkComponentAllocateDialogProps) {
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [payload, setPayload] = useState<AllocationPayload | null>(null);
    const [issuingUnit, setIssuingUnit] = useState<ComponentIssueUnit | null>(null);
    const [searchQuery, setSearchQuery] = useState('');

    useEffect(() => {
        if (!open) {
            setSearchQuery('');
        }
    }, [open]);

    useEffect(() => {
        if (!open || component === null) {
            return;
        }

        setSearchQuery('');

        const controller = new AbortController();
        setLoading(true);
        setFailed(false);
        setPayload(null);
        setIssuingUnit(null);

        void fetch(allocationUnits.url(Number(component.id)), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        })
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
    }, [open, component]);

    const allocation = payload?.allocation;
    const units = payload?.units ?? [];
    const filteredUnits = useMemo(() => {
        const needle = searchQuery.trim().toLowerCase();

        if (needle === '') {
            return units;
        }

        return units.filter((unit) =>
            unitSearchHaystack(unit).includes(needle),
        );
    }, [units, searchQuery]);
    const isUnlinked = allocation?.status === 'unlinked';
    const canAllocate = allocation?.nextAction === 'allocate';

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="spkAlertModal spkStoneStockModal spkChainAllocateDialog">
                    <DialogHeader>
                        <DialogTitle>Alokasi Komponen</DialogTitle>
                        <DialogDescription>
                            {component !== null
                                ? `Pilih unit stok untuk SPK ${spk.produksiNo} · ${componentSummary(component)}`
                                : 'Pilih baris komponen pada SPK.'}
                        </DialogDescription>
                    </DialogHeader>

                    {component === null ? null : loading ? (
                        <p className="spkAlertModalEmpty">Memuat unit stok…</p>
                    ) : failed ? (
                        <p className="spkAlertModalEmpty">
                            Gagal memuat data. Silakan coba lagi.
                        </p>
                    ) : isUnlinked ? (
                        <p className="spkAlertModalEmpty">
                            Master komponen belum terhubung pada baris ini.
                        </p>
                    ) : !canAllocate ? (
                        <p className="spkAlertModalEmpty">
                            {allocation?.label ??
                                'Status komponen tidak memerlukan alokasi stok.'}
                        </p>
                    ) : units.length === 0 ? (
                        <p className="spkAlertModalEmpty">
                            Tidak ada unit komponen siap pakai untuk master ini.
                        </p>
                    ) : (
                        <>
                            <div className="spkChainAllocateFilters">
                                <div className="spkChainAllocateFilterField spkChainAllocateSearchField">
                                    <div className="spkTableHeaderSearch--finishing spkChainAllocateSearch">
                                        <Icon
                                            name={searchIcon}
                                            className="spkTableHeaderSearchIcon--finishing"
                                        />
                                        <input
                                            id="spk-component-allocate-search"
                                            type="search"
                                            className="spkTableHeaderSearchInput--finishing"
                                            placeholder="Unit, nama, item master, ukuran, berat, warna…"
                                            value={searchQuery}
                                            onChange={(event) => {
                                                setSearchQuery(
                                                    event.target.value,
                                                );
                                            }}
                                            autoComplete="off"
                                        />
                                    </div>
                                </div>
                            </div>

                            {filteredUnits.length === 0 ? (
                                <p className="spkAlertModalEmpty">
                                    Tidak ada unit yang cocok dengan pencarian.
                                </p>
                            ) : (
                                <div className="spkAlertModalBody spkChainAllocateBody">
                                    <table className="spkAlertModalTable spkChainAllocateTable">
                                        <thead>
                                            <tr>
                                                <th className="spkChainAllocateColAction">
                                                    Aksi
                                                </th>
                                                <th className="spkChainAllocateColUnit">
                                                    ID
                                                </th>
                                                <th className="spkChainAllocateColName">
                                                    Nama
                                                </th>
                                                <th
                                                    className="spkTableColCenter spkChainAllocateColLength"
                                                    title="Ukuran (cm)"
                                                >
                                                    Ukuran
                                                </th>
                                                <th
                                                    className="spkTableColCenter spkChainAllocateColWeight"
                                                    title="Berat (g)"
                                                >
                                                    Berat
                                                </th>
                                                <th className="spkChainAllocateColColor">
                                                    Warna
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filteredUnits.map((unit) => (
                                                <tr
                                                    key={unit.id}
                                                    className={
                                                        unit.matchesMasterComponent
                                                            ? 'spkAlertModalRow spkChainAllocateRowMatch'
                                                            : 'spkAlertModalRow'
                                                    }
                                                >
                                                    <td className="spkChainAllocateColAction">
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
                                                    <td className="spkChainAllocateColUnit tabular-nums spkAlertModalTableIdentifier">
                                                        {unit.unitNo ??
                                                            unit.id}
                                                    </td>
                                                    <td className="spkChainAllocateColName">
                                                        <span>{unit.name}</span>
                                                        {unit.masterItem !==
                                                            null &&
                                                        unit.masterItem !==
                                                            undefined &&
                                                        unit.masterItem !==
                                                            '' ? (
                                                            <span className="spkAlertModalTableSubText">
                                                                {
                                                                    unit.masterItem
                                                                }
                                                                {unit.masterItemCode !==
                                                                    null &&
                                                                unit.masterItemCode !==
                                                                    undefined &&
                                                                unit.masterItemCode !==
                                                                    ''
                                                                    ? ` · ${unit.masterItemCode}`
                                                                    : ''}
                                                            </span>
                                                        ) : null}
                                                    </td>
                                                    <td className="spkTableColCenter spkChainAllocateColLength tabular-nums">
                                                        {unit.componentSize}
                                                    </td>
                                                    <td className="spkTableColCenter spkChainAllocateColWeight tabular-nums">
                                                        {formatAllocateUnitWeight(
                                                            unit.weight,
                                                        )}
                                                    </td>
                                                    <td className="spkChainAllocateColColor">
                                                        {unit.color ?? '—'}
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

            <ComponentIssueStockDialog
                unit={issuingUnit}
                open={issuingUnit !== null}
                fixedSpk={spk}
                fixedSpkComponentLineId={
                    component !== null ? Number(component.id) : undefined
                }
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setIssuingUnit(null);
                    }
                }}
                onAllocated={() => {
                    setIssuingUnit(null);
                    onOpenChange(false);
                    router.reload({
                        only: ['components'],
                        preserveScroll: true,
                    });
                }}
            />
        </>
    );
}
