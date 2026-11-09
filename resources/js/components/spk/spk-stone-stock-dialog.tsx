import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { check as checkStoneStock } from '@/actions/App/Http/Controllers/SpkStoneStockController';
import type {
    SpkStoneAllocatedItem,
    SpkStoneItem,
    SpkStoneStock,
} from '@/components/spk/types';
import { SpkStoneAllocatedItemsTable } from '@/components/spk/spk-stone-allocated-items-table';
import {
    InventoryCertificateLink,
    InventoryDossierCodeLink,
} from '@/components/spk/spk-stone-allocated-display-cells';
import { formatDecimal3Id } from '@/components/spk/spk-stone-decimal-format';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    dossier as storeDossierAllocation,
    micro as storeMicroAllocation,
    revoke as revokeStoneAllocations,
} from '@/routes/spk/stones/allocations';
import { revoke as revokeStoneAllocationItem } from '@/routes/spk/stones/allocations/items';
import { stock as stoneStock } from '@/routes/spk/stones';

type DossierStockRow = {
    id: number;
    code: string | null;
    diamondType: string | null;
    shape: string | null;
    color: string | null;
    crt: string | null;
    certificate: string | null;
    supplier: string | null;
    entryDate: string | null;
    matchesSpkRequirement?: boolean;
};

type MicroStockRow = {
    id: number;
    name: string;
    parcel: string | null;
    size: string | null;
    shape: string | null;
    balancePcs: string;
    reservedPcs?: string;
    availablePcs?: string;
    balanceCrt: string;
    matchesSpkRequirement?: boolean;
};

type StoneStockPayload = {
    stock: SpkStoneStock;
    rows: Array<DossierStockRow | MicroStockRow>;
    dossier?: {
        stock: SpkStoneStock;
        rows: DossierStockRow[];
    } | null;
    micro?: {
        stock: SpkStoneStock;
        rows: MicroStockRow[];
    } | null;
    allocations?: SpkStoneAllocatedItem[];
    allocatedPcs?: number;
};

export type SpkStoneStockDialogStone = SpkStoneItem & {
    shapeId?: string;
    isPreview?: boolean;
};

type SpkStoneStockDialogProps = {
    stone: SpkStoneStockDialogStone | null;
    spk?: { rowId: number; produksiNo: string };
    onOpenChange: (open: boolean) => void;
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

function isPreviewStone(stone: SpkStoneStockDialogStone): boolean {
    if (stone.isPreview) {
        return true;
    }

    return !/^\d+$/.test(String(stone.id));
}

function displayValue(value: string | number | null | undefined): string {
    const text = value === null || value === undefined ? '' : String(value);

    return text.trim() !== '' ? text.trim() : '—';
}

function formatNumberId(
    value: string | number | null | undefined,
    fractionDigits: number,
): string {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    if (value === null || value === undefined || !Number.isFinite(parsed)) {
        return '—';
    }

    return parsed.toLocaleString('id-ID', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
}

const SPEC_MISMATCH_ALLOCATION_CONFIRM =
    'Batu ini tidak sesuai spek permintaan SPK. Tetap tambahkan ke alokasi?';

function confirmSpecMismatchProceed(
    matchesSpkRequirement: boolean | undefined,
): boolean {
    if (matchesSpkRequirement === true) {
        return true;
    }

    return window.confirm(SPEC_MISMATCH_ALLOCATION_CONFIRM);
}

type StonePickFormState = {
    stockId: number;
    label: string;
    totalPcs: string;
    totalCrt: string;
    maxPcs: number;
    matchesSpkRequirement?: boolean;
};

function parseDecimalInput(value: string): number | null {
    const normalized = value.trim().replace(',', '.');

    if (normalized === '') {
        return null;
    }

    const parsed = Number(normalized);

    return Number.isFinite(parsed) ? parsed : null;
}

function microAvailablePcs(row: MicroStockRow): number {
    const parsed = parseDecimalInput(
        String(row.availablePcs ?? row.balancePcs ?? '0'),
    );

    return parsed ?? 0;
}

function microTersediaIsZero(row: MicroStockRow): boolean {
    return microAvailablePcs(row) <= 0;
}

function microStockRowClassName(row: MicroStockRow): string {
    const classes = ['spkAlertModalRow'];

    if (microTersediaIsZero(row)) {
        classes.push('spkChainAllocateRowUnavailable');
    } else if (row.matchesSpkRequirement === true) {
        classes.push('spkChainAllocateRowMatch');
    }

    return classes.join(' ');
}

function microCrtPerPcsAverageValue(row: MicroStockRow): number | null {
    const pcs = parseDecimalInput(String(row.balancePcs ?? ''));

    if (pcs === null || pcs <= 0) {
        return null;
    }

    const crt = parseDecimalInput(String(row.balanceCrt ?? ''));

    if (crt === null) {
        return null;
    }

    return crt / pcs;
}

function microCrtPerPcsAverage(row: MicroStockRow): string {
    const value = microCrtPerPcsAverageValue(row);

    if (value === null) {
        return '—';
    }

    return formatDecimal3Id(value);
}

function formatDecimalField(
    value: number,
    fractionDigits: number,
): string {
    return value.toLocaleString('id-ID', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
}

function formatPcsField(value: number): string {
    return formatDecimalField(Math.round(value), 0);
}

function remainingNeedPcs(requiredPcs: number, allocatedPcs: number): number {
    return Math.max(0, requiredPcs - allocatedPcs);
}

function requiredPcsForStoneLine(
    stone: SpkStoneStockDialogStone,
    stock: SpkStoneStock | null,
): number {
    const fromStone = Number(stone.pcs ?? 0);

    if (Number.isFinite(fromStone) && fromStone > 0) {
        return fromStone;
    }

    const fromStock = Number(stock?.requiredPcs ?? 0);

    return Number.isFinite(fromStock) && fromStock > 0 ? fromStock : 0;
}

function allocatedPcsForStoneLine(
    stone: SpkStoneStockDialogStone | null,
    payload: StoneStockPayload | null,
): number {
    if (payload?.allocatedPcs !== undefined) {
        return payload.allocatedPcs;
    }

    if (stone === null) {
        return 0;
    }

    if (stone.allocation?.allocatedPcs !== undefined) {
        return stone.allocation.allocatedPcs;
    }

    let total = 0;

    for (const item of stone.allocatedItems ?? []) {
        const itemPcs = Number(String(item.reservedPcs).replace(',', '.'));

        if (Number.isFinite(itemPcs)) {
            total += itemPcs;
        }
    }

    return total;
}

function remainingNeedCarat(
    stone: SpkStoneStockDialogStone,
    requiredPcs: number,
    allocatedPcs: number,
): number {
    const total = parseDecimalInput(String(stone.totalCarat ?? ''));

    if (total === null || requiredPcs <= 0) {
        return 0;
    }

    const remainingPcs = remainingNeedPcs(requiredPcs, allocatedPcs);

    return (total * remainingPcs) / requiredPcs;
}

function buildMicroPickForm(
    row: MicroStockRow,
    stone: SpkStoneStockDialogStone,
    requiredPcs: number,
    allocatedPcs: number,
): StonePickFormState {
    const available = parseDecimalInput(
        String(row.availablePcs ?? row.balancePcs ?? '0'),
    );
    const needPcs = remainingNeedPcs(requiredPcs, allocatedPcs);
    const defaultPcs = Math.min(
        needPcs,
        Math.floor(available ?? needPcs),
    );
    const crtPerPcs = microCrtPerPcsAverageValue(row);
    const needCrt = remainingNeedCarat(stone, requiredPcs, allocatedPcs);
    const defaultCrt =
        crtPerPcs !== null && defaultPcs > 0
            ? crtPerPcs * defaultPcs
            : requiredPcs > 0 && needPcs > 0
              ? (needCrt * defaultPcs) / needPcs
              : needCrt;

    return {
        stockId: row.id,
        label: displayValue(row.name),
        totalPcs: formatPcsField(defaultPcs),
        totalCrt: formatDecimalField(defaultCrt, 3),
        maxPcs: Math.floor(available ?? needPcs),
        matchesSpkRequirement: row.matchesSpkRequirement === true,
    };
}

function SpkStoneAllocatePickForm({
    form,
    submitting,
    onChange,
    onCancel,
    onConfirm,
}: {
    form: StonePickFormState;
    submitting: boolean;
    onChange: (next: StonePickFormState) => void;
    onCancel: () => void;
    onConfirm: () => void;
}) {
    return (
        <div className="spkStoneAllocatePickForm" role="form">
            <p className="spkStoneAllocatePickFormTitle">
                Alokasi: <strong>{form.label}</strong>
            </p>
            <div className="spkStoneAllocatePickFormFields">
                <div className="spkChainAllocateFilterField">
                    <Label htmlFor="spk-stone-allocate-pcs">
                        Total Pcs
                    </Label>
                    <input
                        id="spk-stone-allocate-pcs"
                        type="text"
                        inputMode="numeric"
                        className="spkChainAllocateFilterSelect"
                        value={form.totalPcs}
                        onChange={(event) => {
                            onChange({
                                ...form,
                                totalPcs: event.target.value,
                            });
                        }}
                    />
                </div>
                <div className="spkChainAllocateFilterField">
                    <Label htmlFor="spk-stone-allocate-crt">
                        Total Carat
                    </Label>
                    <input
                        id="spk-stone-allocate-crt"
                        type="text"
                        inputMode="decimal"
                        className="spkChainAllocateFilterSelect"
                        value={form.totalCrt}
                        onChange={(event) => {
                            onChange({
                                ...form,
                                totalCrt: event.target.value,
                            });
                        }}
                    />
                </div>
            </div>
            <div className="spkStoneAllocatePickFormActions">
                <button
                    type="button"
                    className="spkTableHeaderSortClear--finishing"
                    disabled={submitting}
                    onClick={onCancel}
                >
                    Batal
                </button>
                <button
                    type="button"
                    className="spkChainFulfillmentActionBtn"
                    disabled={submitting}
                    onClick={onConfirm}
                >
                    {submitting ? 'Menyimpan…' : 'Simpan alokasi'}
                </button>
            </div>
        </div>
    );
}

function stoneSummary(stone: SpkStoneStockDialogStone): string {
    return [
        stone.positionName,
        stone.shapeName ?? stone.shape,
        stone.size,
        stone.pcs !== undefined && String(stone.pcs).trim() !== ''
            ? `${stone.pcs} pcs`
            : '',
        stone.totalCarat !== undefined &&
        String(stone.totalCarat).trim() !== ''
            ? `${formatDecimal3Id(stone.totalCarat)} crt`
            : '',
    ]
        .map((part) => String(part ?? '').trim())
        .filter((part) => part !== '' && part !== '-')
        .join(' · ');
}

function dossierSearchHaystack(row: DossierStockRow): string {
    return [
        row.code,
        row.diamondType,
        row.shape,
        row.color,
        row.crt,
        row.certificate,
        row.supplier,
        row.entryDate,
    ]
        .map((part) => String(part ?? '').trim())
        .filter((part) => part !== '')
        .join(' ')
        .toLowerCase();
}

function microSearchHaystack(row: MicroStockRow): string {
    return [row.name, row.parcel, row.size, row.shape, row.balancePcs, row.balanceCrt]
        .map((part) => String(part ?? '').trim())
        .filter((part) => part !== '')
        .join(' ')
        .toLowerCase();
}

function DossierStockTable({
    rows,
    remainingNeedPcs,
    pickingDossierId,
    pickFormOpen,
    onPick,
}: {
    rows: DossierStockRow[];
    remainingNeedPcs: number;
    pickingDossierId: number | null;
    pickFormOpen: boolean;
    onPick: (row: DossierStockRow) => void;
}) {
    return (
        <table className="spkAlertModalTable spkChainAllocateTable spkStoneAllocateTable spkStoneAllocateTable--dossier">
            <thead>
                <tr>
                    <th className="spkChainAllocateColAction">Aksi</th>
                    <th className="spkChainAllocateColUnit">Kode</th>
                    <th className="spkStoneAllocateColType">Tipe</th>
                    <th className="spkStoneAllocateColShape">Bentuk</th>
                    <th className="spkChainAllocateColColor">Warna</th>
                    <th className="spkTableColCenter spkChainAllocateColWeight">
                        CRT
                    </th>
                    <th className="spkStoneAllocateColCert">Sertifikat</th>
                    <th className="spkStoneAllocateColSupplier">Supplier</th>
                    <th className="spkTableColCenter spkStoneAllocateColDate">
                        Tgl Masuk
                    </th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr
                        key={row.id}
                        className={
                            row.matchesSpkRequirement === true
                                ? 'spkAlertModalRow spkChainAllocateRowMatch'
                                : 'spkAlertModalRow'
                        }
                    >
                        <td className="spkChainAllocateColAction">
                            <button
                                type="button"
                                className="spkChainFulfillmentActionBtn"
                                disabled={
                                    remainingNeedPcs <= 0 ||
                                    pickFormOpen ||
                                    pickingDossierId !== null
                                }
                                title="Alokasikan batu dossier ke SPK"
                                onClick={() => onPick(row)}
                            >
                                {pickingDossierId === row.id ? '…' : 'Pilih'}
                            </button>
                        </td>
                        <td className="spkChainAllocateColUnit tabular-nums spkAlertModalTableIdentifier">
                            <InventoryDossierCodeLink
                                diamondId={row.id}
                                code={row.code}
                            />
                        </td>
                        <td className="spkStoneAllocateColType">
                            {displayValue(row.diamondType)}
                        </td>
                        <td className="spkStoneAllocateColShape">
                            {displayValue(row.shape)}
                        </td>
                        <td className="spkChainAllocateColColor">
                            {displayValue(row.color)}
                        </td>
                        <td className="spkTableColCenter spkChainAllocateColWeight tabular-nums">
                            {formatNumberId(row.crt, 3)}
                        </td>
                        <td className="spkStoneAllocateColCert">
                            <InventoryCertificateLink
                                certificate={row.certificate}
                            />
                        </td>
                        <td className="spkStoneAllocateColSupplier">
                            {displayValue(row.supplier)}
                        </td>
                        <td className="spkTableColCenter spkStoneAllocateColDate tabular-nums">
                            {displayValue(row.entryDate)}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function MicroStockTable({
    rows,
    remainingNeedPcs,
    activeMicroPickId,
    pickBlocked,
    onPick,
}: {
    rows: MicroStockRow[];
    remainingNeedPcs: number;
    activeMicroPickId: number | null;
    pickBlocked: boolean;
    onPick: (row: MicroStockRow) => void;
}) {
    return (
        <table className="spkAlertModalTable spkChainAllocateTable spkStoneAllocateTable spkStoneAllocateTable--micro">
            <thead>
                <tr>
                    <th className="spkChainAllocateColAction">Aksi</th>
                    <th className="spkChainAllocateColName">Nama Batu</th>
                    <th className="spkStoneAllocateColParcel">Parcel</th>
                    <th className="spkTableColCenter spkChainAllocateColLength">
                        Ukuran
                    </th>
                    <th className="spkStoneAllocateColShape">Bentuk</th>
                    <th className="spkTableColCenter spkChainAllocateColWeight">
                        Saldo Pcs
                    </th>
                    <th className="spkTableColCenter spkChainAllocateColWeight">
                        Reserved
                    </th>
                    <th className="spkTableColCenter spkChainAllocateColWeight">
                        Tersedia
                    </th>
                    <th className="spkTableColCenter spkChainAllocateColWeight spkStoneAllocateColBalanceCrt">
                        Saldo Crt
                    </th>
                    <th className="spkTableColCenter spkChainAllocateColWeight spkStoneAllocateColCrtPerPcs">
                        Crt per butir (avg)
                    </th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.id} className={microStockRowClassName(row)}>
                        <td className="spkChainAllocateColAction">
                            <button
                                type="button"
                                className="spkChainFulfillmentActionBtn"
                                disabled={
                                    remainingNeedPcs <= 0 ||
                                    pickBlocked ||
                                    microTersediaIsZero(row) ||
                                    (activeMicroPickId !== null &&
                                        activeMicroPickId !== row.id)
                                }
                                title="Alokasikan saldo mikro ke SPK"
                                onClick={() => onPick(row)}
                            >
                                {activeMicroPickId === row.id ? '…' : 'Pilih'}
                            </button>
                        </td>
                        <td className="spkChainAllocateColName">
                            {displayValue(row.name)}
                        </td>
                        <td className="spkStoneAllocateColParcel">
                            {displayValue(row.parcel)}
                        </td>
                        <td className="spkTableColCenter spkChainAllocateColLength tabular-nums">
                            {displayValue(row.size)}
                        </td>
                        <td className="spkStoneAllocateColShape">
                            {displayValue(row.shape)}
                        </td>
                        <td className="spkTableColCenter spkChainAllocateColWeight tabular-nums">
                            {formatNumberId(row.balancePcs, 0)}
                        </td>
                        <td className="spkTableColCenter spkChainAllocateColWeight tabular-nums">
                            {formatNumberId(row.reservedPcs ?? '0', 0)}
                        </td>
                        <td className="spkTableColCenter spkChainAllocateColWeight tabular-nums">
                            {formatNumberId(
                                row.availablePcs ?? row.balancePcs,
                                0,
                            )}
                        </td>
                        <td className="spkTableColCenter spkChainAllocateColWeight spkStoneAllocateColBalanceCrt tabular-nums">
                            {formatNumberId(row.balanceCrt, 3)}
                        </td>
                        <td className="spkTableColCenter spkChainAllocateColWeight spkStoneAllocateColCrtPerPcs tabular-nums">
                            {microCrtPerPcsAverage(row)}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/** Modal alokasi stok batu (dossier / mikro) untuk satu baris batu SPK. */
export function SpkStoneStockDialog({
    stone,
    spk,
    onOpenChange,
}: SpkStoneStockDialogProps) {
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [payload, setPayload] = useState<StoneStockPayload | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [reloadToken, setReloadToken] = useState(0);
    const open = stone !== null;
    const stoneId = stone?.id ?? null;

    useEffect(() => {
        if (!open) {
            setSearchQuery('');
            setPickForm(null);
            setPickingDossierId(null);
        }
    }, [open]);

    useEffect(() => {
        if (stone === null) {
            return;
        }

        setSearchQuery('');

        const controller = new AbortController();

        const load = async (): Promise<void> => {
            setLoading(true);
            setFailed(false);
            setPayload(null);

            try {
                const response = isPreviewStone(stone)
                    ? await fetch(checkStoneStock.url(), {
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
                              shape_id: Number(stone.shapeId ?? 0),
                              pcs: Number(stone.pcs ?? 0),
                              carat_per_pcs:
                                  stone.caratPerPcs === undefined ||
                                  String(stone.caratPerPcs).trim() === ''
                                      ? null
                                      : Number(
                                            String(stone.caratPerPcs).replace(
                                                ',',
                                                '.',
                                            ),
                                        ),
                              size:
                                  stone.size === undefined ||
                                  String(stone.size).trim() === '' ||
                                  stone.size === '-'
                                      ? null
                                      : String(stone.size).trim(),
                          }),
                      })
                    : await fetch(stoneStock.url(Number(stoneId)), {
                          headers: {
                              Accept: 'application/json',
                              'X-Requested-With': 'XMLHttpRequest',
                          },
                          credentials: 'same-origin',
                          signal: controller.signal,
                      });

                if (!response.ok) {
                    setFailed(true);

                    return;
                }

                const json = (await response.json()) as {
                    data?: StoneStockPayload;
                };

                setPayload(json.data ?? null);
            } catch (error: unknown) {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }

                setFailed(true);
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        };

        void load();

        return () => controller.abort();
    }, [stone, stoneId, reloadToken]);

    const [pickForm, setPickForm] = useState<StonePickFormState | null>(null);
    const [submittingPick, setSubmittingPick] = useState(false);
    const [pickingDossierId, setPickingDossierId] = useState<number | null>(
        null,
    );
    const [revokingAllocationId, setRevokingAllocationId] = useState<
        number | null
    >(null);
    const [revokingAll, setRevokingAll] = useState(false);

    const stock = payload?.stock ?? stone?.stock ?? null;
    const legacyRows = payload?.rows ?? [];
    const dossierSection = payload?.dossier ?? null;
    const microSection = payload?.micro ?? null;
    const dossierRows =
        dossierSection?.rows ??
        (stock?.source === 'dossier'
            ? (legacyRows as DossierStockRow[])
            : []);
    const microRows =
        microSection?.rows ??
        (stock?.source === 'micro' ? (legacyRows as MicroStockRow[]) : []);
    const allocations = payload?.allocations ?? [];
    const requiredPcs =
        stone !== null
            ? requiredPcsForStoneLine(stone, stock)
            : Number(stock?.requiredPcs ?? 0);
    const allocatedPcs = allocatedPcsForStoneLine(stone, payload);
    const remainingNeed = remainingNeedPcs(requiredPcs, allocatedPcs);
    const isFullyAllocated = requiredPcs > 0 && remainingNeed <= 0;
    const canAddMoreStones =
        stone !== null && !isPreviewStone(stone) && !isFullyAllocated;
    const hasStockTables = dossierRows.length > 0 || microRows.length > 0;

    const reloadStoneStock = (): void => {
        setReloadToken((value) => value + 1);
        if (stone !== null && !isPreviewStone(stone)) {
            router.reload({ only: ['stones'], preserveScroll: true });
        }
    };

    const handlePickDossier = (row: DossierStockRow): void => {
        if (stone === null || isPreviewStone(stone)) {
            return;
        }

        if (!confirmSpecMismatchProceed(row.matchesSpkRequirement)) {
            return;
        }

        const crt = parseDecimalInput(String(row.crt ?? ''));
        const body: Record<string, number> = {
            diamond_id: row.id,
            reserved_pcs: 1,
        };

        if (crt !== null && crt >= 0) {
            body.reserved_crt = Math.round(crt * 1000) / 1000;
        }

        setPickingDossierId(row.id);
        router.post(storeDossierAllocation.url(Number(stone.id)), body, {
            preserveScroll: true,
            onFinish: () => {
                setPickingDossierId(null);
                reloadStoneStock();
            },
        });
    };

    const handlePickMicro = (row: MicroStockRow): void => {
        if (stone === null) {
            return;
        }

        if (!confirmSpecMismatchProceed(row.matchesSpkRequirement)) {
            return;
        }

        setPickForm(
            buildMicroPickForm(row, stone, requiredPcs, allocatedPcs),
        );
    };

    const handleConfirmPick = (): void => {
        if (stone === null || isPreviewStone(stone) || pickForm === null) {
            return;
        }

        const pcsRaw = parseDecimalInput(pickForm.totalPcs);
        const pcs = pcsRaw === null ? null : Math.round(pcsRaw);
        const crt = parseDecimalInput(pickForm.totalCrt);

        if (pcs === null || pcs <= 0) {
            return;
        }

        if (pcs - pickForm.maxPcs > 0.0001) {
            return;
        }

        const needPcs = remainingNeedPcs(requiredPcs, allocatedPcs);

        if (pcs - needPcs > 0.0001) {
            return;
        }

        const payload: Record<string, number> = {
            reserved_pcs: pcs,
            msstone_id: pickForm.stockId,
        };

        if (crt !== null && crt >= 0) {
            payload.reserved_crt = Math.round(crt * 1000) / 1000;
        }

        setSubmittingPick(true);

        router.post(
            storeMicroAllocation.url(Number(stone.id)),
            payload,
            {
                preserveScroll: true,
                onFinish: () => {
                    setSubmittingPick(false);
                    setPickForm(null);
                    reloadStoneStock();
                },
            },
        );
    };

    const activeMicroPickId = pickForm?.stockId ?? null;
    const microPickBlocked =
        pickingDossierId !== null || submittingPick;

    const handleRevokeItem = (item: SpkStoneAllocatedItem): void => {
        if (stone === null || isPreviewStone(stone)) {
            return;
        }

        if (
            !window.confirm('Hapus alokasi batu ini dari SPK?')
        ) {
            return;
        }

        setRevokingAllocationId(item.id);
        router.post(
            revokeStoneAllocationItem.url([Number(stone.id), item.id]),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setRevokingAllocationId(null);
                    reloadStoneStock();
                },
            },
        );
    };

    const handleRevokeAll = (): void => {
        if (stone === null || isPreviewStone(stone)) {
            return;
        }

        setRevokingAll(true);
        router.post(
            revokeStoneAllocations.url(Number(stone.id)),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setRevokingAll(false);
                    reloadStoneStock();
                },
            },
        );
    };

    const filteredDossierRows = useMemo(() => {
        const needle = searchQuery.trim().toLowerCase();

        if (needle === '') {
            return dossierRows;
        }

        return dossierRows.filter((row) =>
            dossierSearchHaystack(row).includes(needle),
        );
    }, [dossierRows, searchQuery]);

    const filteredMicroRows = useMemo(() => {
        const needle = searchQuery.trim().toLowerCase();

        if (needle === '') {
            return microRows;
        }

        return microRows.filter((row) =>
            microSearchHaystack(row).includes(needle),
        );
    }, [microRows, searchQuery]);

    const searchPlaceholder =
        dossierRows.length > 0 && microRows.length > 0
            ? 'Cari stok dossier atau mikro…'
            : dossierRows.length > 0
              ? 'Kode, tipe, bentuk, warna, CRT, sertifikat, supplier…'
              : 'Nama, parcel, ukuran, bentuk, saldo pcs, saldo crt…';
    const filteredRowsEmpty =
        filteredDossierRows.length === 0 && filteredMicroRows.length === 0;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStoneStockModal spkChainAllocateDialog">
                <DialogHeader>
                    <DialogTitle>Alokasi Batu</DialogTitle>
                    <DialogDescription>
                        {stone !== null
                            ? spk !== undefined
                                ? `Pilih stok batu untuk SPK ${spk.produksiNo} · ${stoneSummary(stone)}`
                                : `Pilih stok batu · ${stoneSummary(stone)}`
                            : 'Pilih baris batu pada SPK.'}
                    </DialogDescription>
                </DialogHeader>

                {stone === null ? null : loading ? (
                    <p className="spkAlertModalEmpty">Memuat unit stok…</p>
                ) : failed ? (
                    <p className="spkAlertModalEmpty">
                        Gagal memuat data. Silakan coba lagi.
                    </p>
                ) : isPreviewStone(stone) ? (
                    <p className="spkAlertModalEmpty">
                        Simpan SPK terlebih dahulu sebelum mengalokasikan batu.
                    </p>
                ) : !hasStockTables && allocations.length === 0 ? (
                    <p className="spkAlertModalEmpty">
                        {stock?.note ??
                            'Tidak ada stok batu yang cocok untuk baris ini.'}
                    </p>
                ) : (
                    <>
                        {allocations.length > 0 ? (
                            <div className="spkStoneAllocateAllocatedBlock">
                                <div className="spkChainIssuedRowInner spkStoneAllocateAllocatedInner">
                                    <div className="spkChainIssuedMeta">
                                        <strong>
                                            Batu yang dialokasikan:
                                        </strong>
                                        <span className="spkChainIssuedMetaAudit">
                                            {allocatedPcs}/{requiredPcs} pcs
                                        </span>
                                        <span className="spkChainIssuedMetaDetail">
                                            <SpkStoneAllocatedItemsTable
                                                items={allocations}
                                                revokingAllocationId={
                                                    revokingAllocationId
                                                }
                                                onRevokeItem={handleRevokeItem}
                                            />
                                        </span>
                                    </div>
                                    <div className="spkStoneAllocateSummaryActions">
                                        <button
                                            type="button"
                                            className="spkTableHeaderSortClear--finishing"
                                            disabled={
                                                revokingAll ||
                                                revokingAllocationId !== null
                                            }
                                            onClick={handleRevokeAll}
                                        >
                                            Batalkan semua alokasi
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ) : null}
                        {isFullyAllocated ? (
                            <p className="spkAlertModalEmpty">
                                Kebutuhan batu SPK sudah terpenuhi.
                            </p>
                        ) : null}
                        {!canAddMoreStones ? null : (
                            <>
                        {pickForm !== null ? (
                            <SpkStoneAllocatePickForm
                                form={pickForm}
                                submitting={submittingPick}
                                onChange={setPickForm}
                                onCancel={() => {
                                    if (!submittingPick) {
                                        setPickForm(null);
                                    }
                                }}
                                onConfirm={handleConfirmPick}
                            />
                        ) : null}
                        <div className="spkChainAllocateFilters">
                            <div className="spkChainAllocateFilterField spkChainAllocateSearchField">
                                <div className="spkTableHeaderSearch--finishing spkChainAllocateSearch">
                                    <Icon
                                        name={searchIcon}
                                        className="spkTableHeaderSearchIcon--finishing"
                                    />
                                    <input
                                        id="spk-stone-allocate-search"
                                        type="search"
                                        className="spkTableHeaderSearchInput--finishing"
                                        placeholder={searchPlaceholder}
                                        value={searchQuery}
                                        onChange={(event) => {
                                            setSearchQuery(event.target.value);
                                        }}
                                        autoComplete="off"
                                    />
                                </div>
                            </div>
                        </div>

                        {filteredRowsEmpty ? (
                            <p className="spkAlertModalEmpty">
                                Tidak ada stok yang cocok dengan pencarian.
                            </p>
                        ) : (
                            <div className="spkAlertModalBody spkChainAllocateBody">
                                {filteredDossierRows.length > 0 ? (
                                    <>
                                        {dossierRows.length > 0 &&
                                        microRows.length > 0 ? (
                                            <p className="spkStoneAllocateSectionTitle">
                                                Stok dossier
                                            </p>
                                        ) : null}
                                        <DossierStockTable
                                            rows={filteredDossierRows}
                                            remainingNeedPcs={remainingNeed}
                                            pickingDossierId={pickingDossierId}
                                            pickFormOpen={pickForm !== null}
                                            onPick={handlePickDossier}
                                        />
                                    </>
                                ) : null}
                                {filteredMicroRows.length > 0 ? (
                                    <>
                                        {dossierRows.length > 0 &&
                                        microRows.length > 0 ? (
                                            <p className="spkStoneAllocateSectionTitle">
                                                Stok mikro
                                            </p>
                                        ) : null}
                                        <MicroStockTable
                                            rows={filteredMicroRows}
                                            remainingNeedPcs={remainingNeed}
                                            activeMicroPickId={activeMicroPickId}
                                            pickBlocked={microPickBlocked}
                                            onPick={handlePickMicro}
                                        />
                                    </>
                                ) : null}
                            </div>
                        )}
                            </>
                        )}
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
