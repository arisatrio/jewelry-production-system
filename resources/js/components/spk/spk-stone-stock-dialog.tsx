import { useEffect, useState } from 'react';
import type { SpkStoneItem, SpkStoneStock } from '@/components/spk/types';
import {
    Dialog,
    DialogContent,
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
};

type MicroStockRow = {
    id: number;
    name: string;
    parcel: string | null;
    size: string | null;
    shape: string | null;
    balancePcs: string;
    balanceCrt: string;
};

type StoneStockPayload = {
    stock: SpkStoneStock;
    rows: Array<DossierStockRow | MicroStockRow>;
};

type SpkStoneStockDialogProps = {
    stone: SpkStoneItem | null;
    onOpenChange: (open: boolean) => void;
};

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

function DossierStockTable({ rows }: { rows: DossierStockRow[] }) {
    return (
        <table className="spkAlertModalTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Tipe</th>
                    <th>Bentuk</th>
                    <th>Warna</th>
                    <th>CRT</th>
                    <th>Sertifikat</th>
                    <th>Supplier</th>
                    <th>Tgl Masuk</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row, index) => (
                    <tr key={row.id} className="spkAlertModalRow">
                        <td>{index + 1}</td>
                        <td>{displayValue(row.code)}</td>
                        <td>{displayValue(row.diamondType)}</td>
                        <td>{displayValue(row.shape)}</td>
                        <td>{displayValue(row.color)}</td>
                        <td>{formatNumberId(row.crt, 3)}</td>
                        <td>{displayValue(row.certificate)}</td>
                        <td>{displayValue(row.supplier)}</td>
                        <td>{displayValue(row.entryDate)}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function MicroStockTable({ rows }: { rows: MicroStockRow[] }) {
    return (
        <table className="spkAlertModalTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Batu</th>
                    <th>Parcel</th>
                    <th>Ukuran</th>
                    <th>Bentuk</th>
                    <th>Saldo Pcs</th>
                    <th>Saldo Crt</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row, index) => (
                    <tr key={row.id} className="spkAlertModalRow">
                        <td>{index + 1}</td>
                        <td>{displayValue(row.name)}</td>
                        <td>{displayValue(row.parcel)}</td>
                        <td>{displayValue(row.size)}</td>
                        <td>{displayValue(row.shape)}</td>
                        <td>{displayValue(row.balancePcs)}</td>
                        <td>{formatNumberId(row.balanceCrt, 4)}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/** Modal daftar stok batu (dossier / mikro) yang cocok dengan satu baris batu SPK. */
export function SpkStoneStockDialog({
    stone,
    onOpenChange,
}: SpkStoneStockDialogProps) {
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [payload, setPayload] = useState<StoneStockPayload | null>(null);
    const stoneId = stone?.id ?? null;

    useEffect(() => {
        if (stoneId === null) {
            return;
        }

        const controller = new AbortController();

        const load = async (): Promise<void> => {
            setLoading(true);
            setFailed(false);
            setPayload(null);

            try {
                const response = await fetch(stoneStock.url(Number(stoneId)), {
                    headers: { Accept: 'application/json' },
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
            } catch {
                if (!controller.signal.aborted) {
                    setFailed(true);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        };

        void load();

        return () => controller.abort();
    }, [stoneId]);

    const stock = payload?.stock ?? stone?.stock ?? null;
    const rows = payload?.rows ?? [];
    const isDossier = stock?.source === 'dossier';
    const stoneLabel = [
        displayValue(stone?.shapeName || stone?.shape),
        displayValue(stone?.size),
    ]
        .filter((part) => part !== '—')
        .join(' · ');

    return (
        <Dialog open={stone !== null} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStoneStockModal">
                <DialogHeader>
                    <DialogTitle>
                        {`Stok ${isDossier ? 'Batu Dossier' : 'Batu Mikro'}${stoneLabel !== '' ? ` — ${stoneLabel}` : ''}`}
                    </DialogTitle>
                </DialogHeader>

                {stock ? (
                    <div className="spkStoneStockSummary">
                        <span
                            className={
                                stock.status === 'available'
                                    ? 'spkStoneStockBadge is-available'
                                    : 'spkStoneStockBadge is-unavailable'
                            }
                        >
                            {stock.status === 'available'
                                ? 'Tersedia'
                                : 'Tidak Tersedia'}
                        </span>
                        <span>
                            Carat per butir{' '}
                            <strong>
                                {formatNumberId(stone?.caratPerPcs, 3)}
                            </strong>
                        </span>
                        <span>
                            Kebutuhan{' '}
                            <strong>
                                {stock.requiredPcs.toLocaleString('id-ID')} pcs
                            </strong>
                        </span>
                        <span>
                            Stok{' '}
                            <strong>
                                {stock.availablePcs.toLocaleString('id-ID')} pcs
                            </strong>
                        </span>
                        {stock.note ? (
                            <span className="spkStoneStockMeta">
                                {stock.note}
                            </span>
                        ) : null}
                    </div>
                ) : null}

                <div className="spkAlertModalBody">
                    {loading ? (
                        <p className="spkAlertModalEmpty">Memuat data...</p>
                    ) : failed ? (
                        <p className="spkAlertModalEmpty">
                            Gagal memuat daftar stok batu.
                        </p>
                    ) : rows.length === 0 ? (
                        <p className="spkAlertModalEmpty">
                            Tidak ada stok batu yang cocok.
                        </p>
                    ) : isDossier ? (
                        <DossierStockTable rows={rows as DossierStockRow[]} />
                    ) : (
                        <MicroStockTable rows={rows as MicroStockRow[]} />
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
