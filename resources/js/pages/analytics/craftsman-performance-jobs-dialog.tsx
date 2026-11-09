import { useEffect, useState } from 'react';
import { SpkItemNoLink } from '@/components/spk/spk-item-no-link';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { craftsmanPerformance } from '@/routes/analytics';

export type CraftsmanPerformanceJobsDialogTarget = {
    craftsmanId: number;
    craftsmanName: string;
    processKey: string | null;
    processLabel: string;
    month: string;
};

type CraftsmanPerformanceJobListItem = {
    no: number;
    id: number;
    spkId: number | null;
    spkNo: string | null;
    process: string;
    sentAt: string | null;
    receivedAt: string | null;
    workDuration: string | null;
    shrink: string | null;
};

type CraftsmanPerformanceJobsPayload = {
    craftsmanName: string;
    processLabel: string;
    period: {
        label: string;
    };
    items: CraftsmanPerformanceJobListItem[];
};

type CraftsmanPerformanceJobsDialogProps = {
    target: CraftsmanPerformanceJobsDialogTarget | null;
    onOpenChange: (open: boolean) => void;
};

function formatShrink(value: string | null): string {
    if (value === null || value === '') {
        return '—';
    }

    return `${Number(value).toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} g`;
}

export function CraftsmanPerformanceJobsDialog({
    target,
    onOpenChange,
}: CraftsmanPerformanceJobsDialogProps) {
    const open = target !== null;
    const requestKey = target
        ? `${target.craftsmanId}|${target.processKey ?? 'all'}|${target.month}`
        : null;
    const [resultKey, setResultKey] = useState<string | null>(null);
    const [payload, setPayload] = useState<CraftsmanPerformanceJobsPayload | null>(
        null,
    );
    const [failed, setFailed] = useState(false);
    const loading = open && requestKey !== null && resultKey !== requestKey;

    useEffect(() => {
        if (!open || target === null || requestKey === null) {
            return;
        }

        const controller = new AbortController();
        const url = craftsmanPerformance.jobs.url({
            query: {
                craftsman_id: String(target.craftsmanId),
                process: target.processKey ?? 'all',
                month: target.month,
            },
        });

        fetch(url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return (await response.json()) as CraftsmanPerformanceJobsPayload;
            })
            .then((data) => {
                setPayload(data);
                setResultKey(requestKey);
                setFailed(false);
            })
            .catch(() => {
                if (controller.signal.aborted) {
                    return;
                }

                setPayload(null);
                setResultKey(requestKey);
                setFailed(true);
            });

        return () => controller.abort();
    }, [open, requestKey, target]);

    useEffect(() => {
        if (!open) {
            setPayload(null);
            setResultKey(null);
            setFailed(false);
        }
    }, [open]);

    const items = payload?.items ?? [];
    const showProcessColumn = target?.processKey === null;

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    onOpenChange(false);
                }
            }}
        >
            <DialogContent className="dashStatusModal sm:max-w-[min(72rem,calc(100vw-2rem))]">
                <DialogHeader>
                    <DialogTitle>
                        Daftar Proses · {target?.craftsmanName ?? '—'}
                    </DialogTitle>
                    <DialogDescription>
                        {target?.processLabel ?? '—'}
                        {payload?.period.label
                            ? ` · ${payload.period.label}`
                            : ''}
                        {' · '}
                        {loading
                            ? 'Memuat…'
                            : `${items.length.toLocaleString('id-ID')} job`}
                    </DialogDescription>
                </DialogHeader>

                {loading ? (
                    <p className="dashEmpty">Memuat daftar proses…</p>
                ) : failed ? (
                    <p className="dashEmpty">
                        Gagal memuat daftar proses. Coba lagi.
                    </p>
                ) : items.length === 0 ? (
                    <p className="dashEmpty">
                        Tidak ada proses untuk filter ini.
                    </p>
                ) : (
                    <div className="dashStatusTableWrap">
                        <table className="dashStatusTable">
                            <thead>
                                <tr>
                                    <th className="is-text-center">#</th>
                                    <th>SPK</th>
                                    {showProcessColumn ? <th>Proses</th> : null}
                                    <th className="is-text-center">
                                        Serah Pengrajin
                                    </th>
                                    <th className="is-text-center">
                                        Terima Pengrajin
                                    </th>
                                    <th className="is-text-center">
                                        Waktu Kerja
                                    </th>
                                    <th className="is-text-center">Susut</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={`${item.process}-${item.id}`}>
                                        <td className="is-text-center text-center tabular-nums">
                                            {item.no}
                                        </td>
                                        <td>
                                            {item.spkNo ? (
                                                <SpkItemNoLink
                                                    spkNo={item.spkNo}
                                                />
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        {showProcessColumn ? (
                                            <td>{item.process}</td>
                                        ) : null}
                                        <td className="is-text-center text-center">
                                            {item.sentAt ?? '—'}
                                        </td>
                                        <td className="is-text-center text-center">
                                            {item.receivedAt ?? '—'}
                                        </td>
                                        <td className="is-text-center text-center">
                                            {item.workDuration ?? '—'}
                                        </td>
                                        <td className="is-text-center text-center tabular-nums">
                                            {formatShrink(item.shrink)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
