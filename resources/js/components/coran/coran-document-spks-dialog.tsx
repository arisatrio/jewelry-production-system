import { useCallback, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { spks as documentSpks } from '@/routes/coran';
import { show as spkShow } from '@/routes/spk';

type DocumentSpkRow = {
    spkNo: string;
    spkId: number | null;
    item: string | null;
    customer: string | null;
    weight: string | null;
    status: string | null;
    statusLabel: string | null;
};

type CoranDocumentSpksDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    coranId: number | null;
    docNo: string | null;
};

function displayValue(value: string | null | undefined): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

function formatStatusLabel(
    statusLabel: string | null,
    status: string | null,
): string {
    const label = statusLabel?.trim() ?? '';

    if (label !== '') {
        return label;
    }

    const code = status?.trim() ?? '';

    return code !== '' ? code : '—';
}

export function CoranDocumentSpksDialog({
    open,
    onOpenChange,
    coranId,
    docNo,
}: CoranDocumentSpksDialogProps) {
    const [loading, setLoading] = useState(false);
    const [rows, setRows] = useState<DocumentSpkRow[]>([]);

    const loadRows = useCallback(async (id: number) => {
        setLoading(true);

        try {
            const response = await fetch(documentSpks.url(id));

            if (!response.ok) {
                setRows([]);

                return;
            }

            const payload = (await response.json()) as {
                status?: boolean;
                data?: DocumentSpkRow[];
            };

            setRows(Array.isArray(payload.data) ? payload.data : []);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!open || coranId === null) {
            return;
        }

        void loadRows(coranId);
    }, [open, coranId, loadRows]);

    useEffect(() => {
        if (!open) {
            setRows([]);
            setLoading(false);
        }
    }, [open]);

    const titleDoc = displayValue(docNo);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal">
                <DialogHeader>
                    <DialogTitle>SPK dokumen {titleDoc}</DialogTitle>
                </DialogHeader>
                <div className="spkAlertModalBody">
                    {loading ? (
                        <p className="spkAlertModalEmpty">Memuat data...</p>
                    ) : rows.length === 0 ? (
                        <p className="spkAlertModalEmpty">
                            Tidak ada SPK pada dokumen ini.
                        </p>
                    ) : (
                        <table className="spkAlertModalTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>SPK</th>
                                    <th>Item</th>
                                    <th>Customer</th>
                                    <th>Berat</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, index) => (
                                    <tr
                                        key={`${row.spkId ?? row.spkNo}-${index}`}
                                        className="spkAlertModalRow"
                                    >
                                        <td>{index + 1}</td>
                                        <td>
                                            {row.spkNo ? (
                                                <button
                                                    type="button"
                                                    className="spkAlertModalLink"
                                                    onClick={() => {
                                                        onOpenChange(false);
                                                        router.visit(
                                                            spkShow.url(
                                                                row.spkNo,
                                                            ),
                                                        );
                                                    }}
                                                >
                                                    {row.spkNo}
                                                </button>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td>{displayValue(row.item)}</td>
                                        <td>{displayValue(row.customer)}</td>
                                        <td>{displayValue(row.weight)}</td>
                                        <td>
                                            {formatStatusLabel(
                                                row.statusLabel,
                                                row.status,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
