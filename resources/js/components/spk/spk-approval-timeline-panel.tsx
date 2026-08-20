import { ArrowLeft, Check } from 'lucide-react';

export type SpkApprovalTimelineEvent = {
    source: string;
    status: string;
    statusLabel: string;
    approve: string;
    notes: string | null;
    createdBy: string | null;
    createdAt: string | null;
};

function approveState(approve: string): 'ok' | 'not-ok' | 'other' {
    const value = approve.trim().toUpperCase();

    if (value === 'OK' || value === 'APPROVED') {
        return 'ok';
    }

    if (
        value === 'NOK' ||
        value === 'NOT OK' ||
        value === 'NOTOK' ||
        value === 'REJECTED'
    ) {
        return 'not-ok';
    }

    return 'other';
}

export function SpkApprovalTimelinePanel({
    events,
}: {
    events: SpkApprovalTimelineEvent[];
}) {
    if (events.length === 0) {
        return (
            <div
                role="tabpanel"
                aria-label="Timeline"
                className="spkMainTabPanel"
            >
                <p className="spkCraftsmanReportEmpty">
                    Belum ada riwayat approval di sysapproval.
                </p>
            </div>
        );
    }

    return (
        <div role="tabpanel" aria-label="Timeline" className="spkMainTabPanel">
            <ol className="spkPageTimeline">
                {events.map((row, index) => {
                    const state = approveState(row.approve);
                    const isLast = index === events.length - 1;
                    const notes = row.notes?.trim() || null;

                    return (
                        <li
                            key={`${row.source}-${row.status}-${row.createdAt ?? index}-${index}`}
                            className={[
                                'spkPageTimelineItem',
                                state === 'ok' ? 'is-ok' : '',
                                state === 'not-ok' ? 'is-not-ok' : '',
                                isLast ? 'is-last' : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                        >
                            <div
                                className="spkPageTimelineMarker"
                                aria-hidden="true"
                            >
                                <span className="spkPageTimelineDot">
                                    {state === 'ok' ? (
                                        <Check
                                            className="spkPageTimelineIcon"
                                            strokeWidth={3}
                                        />
                                    ) : null}
                                    {state === 'not-ok' ? (
                                        <ArrowLeft
                                            className="spkPageTimelineIcon"
                                            strokeWidth={3}
                                        />
                                    ) : null}
                                </span>
                            </div>
                            <div className="spkPageTimelineContent">
                                <p className="spkPageTimelineSource">
                                    {row.source}
                                </p>
                                <p className="spkPageTimelineStatus">
                                    {row.statusLabel || row.status || '—'}
                                    {row.approve && row.approve !== '—'
                                        ? ` · ${row.approve}`
                                        : ''}
                                </p>
                                <p className="spkPageTimelineMeta">
                                    {`${row.createdBy ?? '—'} · ${row.createdAt ?? '—'}`}
                                </p>
                                {notes ? (
                                    <p className="spkPageTimelineNotes">
                                        {notes}
                                    </p>
                                ) : null}
                            </div>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
