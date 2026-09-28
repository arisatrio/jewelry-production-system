import { router } from '@inertiajs/react';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useCallback, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { show as spkShow } from '@/routes/spk';
import type { RouteQueryOptions } from '@/wayfinder';

export type SpkQueueKey = 'pending' | 'inProgress' | 'completed';

export type SpkStatusCounts = Record<SpkQueueKey, number>;

type SpkStatusRow = {
    rowId: number;
    spkNo: string;
    docNo: string | null;
    customer: string;
    item: string;
    goldColor: string;
    qty: number;
    [key: string]: unknown;
};

type SpkProcessStatusCardsProps = {
    counts: SpkStatusCounts;
    processLabel: string;
    searchUrl: (options?: RouteQueryOptions) => string;
    /** Nama field ID dokumen modul pada response `select.spks`, mis. `finishingId`. */
    documentIdKey: string;
    documentUrl: (documentId: number) => string;
    variant?: 'cards' | 'alerts';
};

type ActiveModal = {
    queue: SpkQueueKey;
    title: string;
    total: number;
} | null;

type CardConfig = {
    key: SpkQueueKey;
    label: string;
    hint: string;
    className: string;
    modalTitle: string;
    alertDesign: 'Information' | 'Critical' | 'Positive';
};

function buildCardConfig(processLabel: string): CardConfig[] {
    return [
        {
            key: 'pending',
            label: `Belum ${processLabel}`,
            hint: `SPK belum masuk proses ${processLabel}`,
            className: 'jewelcadPending',
            modalTitle: `SPK Belum Proses ${processLabel}`,
            alertDesign: 'Information',
        },
        {
            key: 'inProgress',
            label: 'Sedang Proses',
            hint: `SPK sedang dalam proses ${processLabel}`,
            className: 'inProgress',
            modalTitle: `SPK Sedang Proses ${processLabel}`,
            alertDesign: 'Critical',
        },
        {
            key: 'completed',
            label: 'Selesai',
            hint: `SPK sudah selesai proses ${processLabel}`,
            className: 'jewelcadDone',
            modalTitle: `SPK Selesai Proses ${processLabel}`,
            alertDesign: 'Positive',
        },
    ];
}

function displayValue(value: string | null | undefined): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

function resolveDocumentId(row: SpkStatusRow, key: string): number | null {
    const value = row[key];

    return typeof value === 'number' && value > 0 ? value : null;
}

export function SpkProcessStatusCards({
    counts,
    processLabel,
    searchUrl,
    documentIdKey,
    documentUrl,
    variant = 'cards',
}: SpkProcessStatusCardsProps) {
    const [activeModal, setActiveModal] = useState<ActiveModal>(null);
    const [loading, setLoading] = useState(false);
    const [rows, setRows] = useState<SpkStatusRow[]>([]);
    const cardConfig = buildCardConfig(processLabel);

    const loadRows = useCallback(
        async (queue: SpkQueueKey) => {
            setLoading(true);

            try {
                const response = await fetch(
                    searchUrl({
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
                    data?: SpkStatusRow[];
                };

                setRows(Array.isArray(payload.data) ? payload.data : []);
            } finally {
                setLoading(false);
            }
        },
        [searchUrl],
    );

    const openModal = (config: CardConfig) => {
        setActiveModal({
            queue: config.key,
            title: config.modalTitle,
            total: counts[config.key],
        });
        void loadRows(config.key);
    };

    return (
        <>
            {variant === 'alerts' ? (
                <div
                    className="spkTableStatusAlerts--finishing"
                    role="group"
                    aria-label={`Ringkasan status SPK ${processLabel.toLowerCase()}`}
                >
                    {cardConfig.map((config) => (
                        <button
                            key={config.key}
                            type="button"
                            className="spkTableStatusAlertBtn--finishing"
                            onClick={() => openModal(config)}
                            aria-label={`${counts[config.key].toLocaleString('id-ID')} ${config.hint}. Klik untuk lihat daftar.`}
                            title={config.hint}
                        >
                            <MessageStrip
                                design={config.alertDesign}
                                hideCloseButton
                                className="spkTableStatusAlertStrip--finishing"
                            >
                                <span className="spkTableStatusAlertLabel--finishing">
                                    {config.label}
                                </span>
                                <strong className="spkTableStatusAlertCount--finishing">
                                    {counts[config.key].toLocaleString('id-ID')}
                                </strong>
                            </MessageStrip>
                        </button>
                    ))}
                </div>
            ) : (
                <div
                    className="spkStatusCards spkStatusCards--3"
                    role="status"
                    aria-live="polite"
                >
                    {cardConfig.map((config) => (
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
                            <span className="spkStatusCardHint">
                                {config.hint}
                            </span>
                        </button>
                    ))}
                </div>
            )}

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
                        <DialogTitle>{activeModal?.title ?? ''}</DialogTitle>
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
                                    {rows.map((row, index) => {
                                        const documentId = resolveDocumentId(
                                            row,
                                            documentIdKey,
                                        );

                                        return (
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
                                                    {documentId !== null &&
                                                    row.docNo !== null ? (
                                                        <button
                                                            type="button"
                                                            className="spkAlertModalLink"
                                                            onClick={(
                                                                event,
                                                            ) => {
                                                                event.stopPropagation();
                                                                setActiveModal(
                                                                    null,
                                                                );
                                                                router.visit(
                                                                    documentUrl(
                                                                        documentId,
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
                                                <td>
                                                    {displayValue(row.item)}
                                                </td>
                                                <td>
                                                    {displayValue(row.customer)}
                                                </td>
                                                <td>
                                                    {displayValue(
                                                        row.goldColor,
                                                    )}
                                                </td>
                                                <td>
                                                    {Number(
                                                        row.qty,
                                                    ).toLocaleString('id-ID')}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        )}
                        {!loading &&
                        rows.length > 0 &&
                        activeModal !== null &&
                        activeModal.total > rows.length ? (
                            <p className="spkAlertModalFootnote">
                                Menampilkan{' '}
                                {rows.length.toLocaleString('id-ID')} dari{' '}
                                {activeModal.total.toLocaleString('id-ID')} SPK.
                            </p>
                        ) : null}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
