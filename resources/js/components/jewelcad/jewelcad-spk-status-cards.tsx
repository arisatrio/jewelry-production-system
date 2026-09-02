import { useCallback, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { spks as searchJewelCadSpks } from '@/routes/jewelcad/select';
import { show as jewelCadShow } from '@/routes/jewelcad';
import { show as spkShow } from '@/routes/spk';

type SpkQueueKey = 'pending' | 'inProgress' | 'completed';

type SpkStatusCounts = Record<SpkQueueKey, number>;

type SpkApiRow = {
    rowId: number;
    spkNo: string;
    requestId: number | null;
    docNo: string | null;
    customer: string;
    item: string;
    goldColor: string;
    goldWeight: string;
    qty: number;
};

type JewelCadSpkStatusCardsProps = {
    counts: SpkStatusCounts;
};

type ActiveModal = {
    queue: SpkQueueKey;
    title: string;
    total: number;
} | null;

const CARD_CONFIG: Array<{
    key: SpkQueueKey;
    queue: SpkQueueKey;
    label: string;
    hint: string;
    className: string;
    modalTitle: string;
}> = [
    {
        key: 'pending',
        queue: 'pending',
        label: 'Belum JewelCAD',
        hint: 'SPK belum masuk proses JewelCAD',
        className: 'jewelcadPending',
        modalTitle: 'SPK Belum Proses JewelCAD',
    },
    {
        key: 'inProgress',
        queue: 'inProgress',
        label: 'Sedang Proses',
        hint: 'SPK sedang dalam proses JewelCAD',
        className: 'inProgress',
        modalTitle: 'SPK Sedang Proses JewelCAD',
    },
    {
        key: 'completed',
        queue: 'completed',
        label: 'Selesai',
        hint: 'SPK sudah selesai proses JewelCAD',
        className: 'jewelcadDone',
        modalTitle: 'SPK Selesai Proses JewelCAD',
    },
];

function displayValue(value: string | null | undefined): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

export function JewelCadSpkStatusCards({ counts }: JewelCadSpkStatusCardsProps) {
    const [activeModal, setActiveModal] = useState<ActiveModal>(null);
    const [loading, setLoading] = useState(false);
    const [rows, setRows] = useState<SpkApiRow[]>([]);

    const loadRows = useCallback(async (queue: SpkQueueKey) => {
        setLoading(true);

        try {
            const response = await fetch(
                searchJewelCadSpks.url({
                    query: {
                        queue,
                        limit: 50,
                    },
                }),
            );

            if (!response.ok) {
                setRows([]);

                return;
            }

            const payload = (await response.json()) as {
                status?: boolean;
                data?: SpkApiRow[];
            };

            setRows(Array.isArray(payload.data) ? payload.data : []);
        } finally {
            setLoading(false);
        }
    }, []);

    const openModal = (config: (typeof CARD_CONFIG)[number]) => {
        setActiveModal({
            queue: config.queue,
            title: config.modalTitle,
            total: counts[config.key],
        });
        void loadRows(config.queue);
    };

    return (
        <>
            <div className="spkStatusCards spkStatusCards--3" role="status" aria-live="polite">
                {CARD_CONFIG.map((config) => (
                    <button
                        key={config.key}
                        type="button"
                        className={`spkStatusCard spkStatusCard--${config.className}`}
                        onClick={() => openModal(config)}
                        aria-label={`${counts[config.key].toLocaleString('id-ID')} ${config.hint}. Klik untuk lihat daftar.`}
                    >
                        <span className="spkStatusCardLabel">
                            {config.label}
                        </span>
                        <strong className="spkStatusCardCount">
                            {counts[config.key].toLocaleString('id-ID')}
                        </strong>
                        <span className="spkStatusCardHint">{config.hint}</span>
                    </button>
                ))}
            </div>

            <Dialog
                open={activeModal !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setActiveModal(null);
                    }
                }}
            >
                <DialogContent className="spkAlertModal">
                    <DialogHeader>
                        <DialogTitle>
                            {activeModal?.title ?? ''}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="spkAlertModalBody">
                        {loading ? (
                            <p className="spkAlertModalEmpty">Memuat data...</p>
                        ) : rows.length === 0 ? (
                            <p className="spkAlertModalEmpty">
                                Tidak ada SPK pada kategori ini.
                            </p>
                        ) : (
                            <table className="spkAlertModalTable">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>No Dokumen</th>
                                        <th>SPK</th>
                                        <th>Item</th>
                                        <th>Customer</th>
                                        <th>Material</th>
                                        <th>Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row, index) => (
                                        <tr
                                            key={row.rowId}
                                            className="spkAlertModalRow"
                                            onClick={() => {
                                                setActiveModal(null);
                                                router.visit(
                                                    spkShow.url(row.spkNo),
                                                );
                                            }}
                                        >
                                            <td>{index + 1}</td>
                                            <td>
                                                {row.requestId !== null &&
                                                row.docNo !== null ? (
                                                    <button
                                                        type="button"
                                                        className="spkAlertModalLink"
                                                        onClick={(event) => {
                                                            event.stopPropagation();
                                                            setActiveModal(null);
                                                            router.visit(
                                                                jewelCadShow.url(
                                                                    row.requestId!,
                                                                ),
                                                            );
                                                        }}
                                                    >
                                                        {row.docNo}
                                                    </button>
                                                ) : (
                                                    displayValue(row.docNo)
                                                )}
                                            </td>
                                            <td className="spkAlertModalLink">
                                                {row.spkNo}
                                            </td>
                                            <td>{displayValue(row.item)}</td>
                                            <td>
                                                {displayValue(row.customer)}
                                            </td>
                                            <td>
                                                {displayValue(row.goldColor)}
                                            </td>
                                            <td>{row.qty}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        {activeModal &&
                        !loading &&
                        activeModal.total > rows.length ? (
                            <p className="spkAlertModalFootnote">
                                Menampilkan {rows.length} dari{' '}
                                {activeModal.total.toLocaleString('id-ID')}{' '}
                                SPK.
                            </p>
                        ) : null}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
