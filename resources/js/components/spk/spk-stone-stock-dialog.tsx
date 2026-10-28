import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useMemo, useState } from 'react';
import { check as checkStoneStock } from '@/actions/App/Http/Controllers/SpkStoneStockController';
import type { SpkStoneItem, SpkStoneStock } from '@/components/spk/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    balanceCrt: string;
    matchesSpkRequirement?: boolean;
};

type StoneStockPayload = {
    stock: SpkStoneStock;
    rows: Array<DossierStockRow | MicroStockRow>;
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
            ? `${stone.totalCarat} crt`
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

function DossierStockTable({ rows }: { rows: DossierStockRow[] }) {
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
                                disabled
                                title="Alokasi stok batu dossier belum tersedia"
                            >
                                Pilih
                            </button>
                        </td>
                        <td className="spkChainAllocateColUnit tabular-nums spkAlertModalTableIdentifier">
                            {displayValue(row.code)}
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
                            {displayValue(row.certificate)}
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

function MicroStockTable({ rows }: { rows: MicroStockRow[] }) {
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
                    <th className="spkTableColCenter spkChainAllocateColWeight spkStoneAllocateColBalanceCrt">
                        Saldo Crt
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
                                disabled
                                title="Alokasi stok batu mikro belum tersedia"
                            >
                                Pilih
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
                            {displayValue(row.balancePcs)}
                        </td>
                        <td className="spkTableColCenter spkChainAllocateColWeight spkStoneAllocateColBalanceCrt tabular-nums">
                            {formatNumberId(row.balanceCrt, 4)}
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
    const open = stone !== null;
    const stoneId = stone?.id ?? null;

    useEffect(() => {
        if (!open) {
            setSearchQuery('');
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
    }, [stone, stoneId]);

    const stock = payload?.stock ?? stone?.stock ?? null;
    const rows = payload?.rows ?? [];
    const isDossier = stock?.source === 'dossier';
    const allocation = stone?.allocation;
    const isAllocated =
        allocation?.status === 'allocated' &&
        (allocation.allocatedPcs ?? 0) > 0;
    const canAllocate =
        stock?.status === 'available' && !isAllocated;

    const filteredRows = useMemo(() => {
        const needle = searchQuery.trim().toLowerCase();

        if (needle === '') {
            return rows;
        }

        if (isDossier) {
            return (rows as DossierStockRow[]).filter((row) =>
                dossierSearchHaystack(row).includes(needle),
            );
        }

        return (rows as MicroStockRow[]).filter((row) =>
            microSearchHaystack(row).includes(needle),
        );
    }, [isDossier, rows, searchQuery]);

    const searchPlaceholder = isDossier
        ? 'Kode, tipe, bentuk, warna, CRT, sertifikat, supplier…'
        : 'Nama, parcel, ukuran, bentuk, saldo pcs, saldo crt…';

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
                ) : isAllocated ? (
                    <p className="spkAlertModalEmpty">
                        {allocation?.label ?? 'Batu sudah dialokasikan.'}
                    </p>
                ) : rows.length === 0 ? (
                    <p className="spkAlertModalEmpty">
                        {stock?.note ??
                            (isDossier
                                ? 'Tidak ada batu dossier siap pakai untuk bentuk ini.'
                                : 'Tidak ada master batu mikro untuk bentuk ini.')}
                    </p>
                ) : (
                    <>
                        {!canAllocate && stock?.note ? (
                            <p
                                className="spkAlertModalEmpty spkStoneAllocateStockNote"
                                role="status"
                            >
                                {stock.note}
                            </p>
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

                        {filteredRows.length === 0 ? (
                            <p className="spkAlertModalEmpty">
                                Tidak ada stok yang cocok dengan pencarian.
                            </p>
                        ) : (
                            <div className="spkAlertModalBody spkChainAllocateBody">
                                {isDossier ? (
                                    <DossierStockTable
                                        rows={filteredRows as DossierStockRow[]}
                                    />
                                ) : (
                                    <MicroStockTable
                                        rows={filteredRows as MicroStockRow[]}
                                    />
                                )}
                            </div>
                        )}
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
