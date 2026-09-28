import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useState } from 'react';
import { SpkStatusListDialog } from '@/components/spk/spk-status-list-dialog';
import { processQueue } from '@/routes/spk';

export type SpkQueueKey = 'pending' | 'inProgress' | 'completed';

export type SpkStatusCounts = Record<SpkQueueKey, number>;

export type SpkProcessQueueModule =
    | 'jewelcad'
    | 'resin'
    | 'coran'
    | 'finishing'
    | 'poles-rangka'
    | 'pasang-batu'
    | 'poles-chrome';

type SpkProcessStatusCardsProps = {
    counts: SpkStatusCounts;
    processLabel: string;
    module: SpkProcessQueueModule;
    documentUrl: (documentId: number) => string;
    variant?: 'cards' | 'alerts';
};

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

export function SpkProcessStatusCards({
    counts,
    processLabel,
    module,
    documentUrl,
    variant = 'cards',
}: SpkProcessStatusCardsProps) {
    const [activeQueue, setActiveQueue] = useState<{
        key: SpkQueueKey;
        requestId: number;
    } | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const cardConfig = buildCardConfig(processLabel);
    const activeConfig = cardConfig.find(
        (config) => config.key === activeQueue?.key,
    );

    const openModal = (config: CardConfig) => {
        setActiveQueue((current) => ({
            key: config.key,
            requestId: (current?.requestId ?? 0) + 1,
        }));
        setModalOpen(true);
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
                            aria-haspopup="dialog"
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
                            aria-haspopup="dialog"
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

            <SpkStatusListDialog
                open={modalOpen}
                onOpenChange={setModalOpen}
                listUrl={
                    activeConfig
                        ? processQueue.url({ module, queue: activeConfig.key })
                        : null
                }
                requestId={activeQueue?.requestId ?? 0}
                title={activeConfig?.modalTitle ?? ''}
                hint={activeConfig?.hint ?? ''}
                documentUrl={documentUrl}
            />
        </>
    );
}
