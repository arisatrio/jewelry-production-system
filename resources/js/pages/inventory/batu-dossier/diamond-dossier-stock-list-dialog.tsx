import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useState } from 'react';
import {
    DiamondDossierStockTable,
    type DossierDiamondRow,
} from '@/components/inventory/diamond-dossier-stock-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { stockReportDiamonds } from '@/routes/inventory/diamond-dossiers';

export type StockListDialogRequest = {
    shapeId: number | null;
    crtMin: string | null;
    crtMax: string | null;
    status:
        | 'available'
        | 'reserved'
        | 'used'
        | 'actual_available'
        | null;
    diamondType: string | null;
    title: string;
    subtitle: string;
};

type DiamondDossierStockListDialogProps = {
    open: boolean;
    request: StockListDialogRequest | null;
    onOpenChange: (open: boolean) => void;
};

export function DiamondDossierStockListDialog({
    open,
    request,
    onOpenChange,
}: DiamondDossierStockListDialogProps) {
    const [loading, setLoading] = useState(false);
    const [diamonds, setDiamonds] = useState<DossierDiamondRow[]>([]);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open || request === null) {
            return;
        }

        let cancelled = false;

        const load = async () => {
            setLoading(true);
            setError(null);
            setDiamonds([]);

            try {
                const response = await fetch(
                    stockReportDiamonds.url({
                        query: {
                            shape_id: request.shapeId ?? undefined,
                            crt_min: request.crtMin ?? undefined,
                            crt_max: request.crtMax ?? undefined,
                            status: request.status ?? undefined,
                            diamond_type: request.diamondType || undefined,
                        },
                    }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    throw new Error('Gagal memuat daftar batu.');
                }

                const payload = (await response.json()) as {
                    diamonds?: DossierDiamondRow[];
                };

                if (!cancelled) {
                    setDiamonds(payload.diamonds ?? []);
                }
            } catch {
                if (!cancelled) {
                    setError('Gagal memuat daftar batu. Silakan coba lagi.');
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [open, request]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[92vh] w-[min(96vw,72rem)] max-w-[72rem] flex-col gap-4 overflow-hidden p-0 sm:max-w-[72rem]">
                <DialogHeader className="space-y-1 px-6 pt-6">
                    <DialogTitle className="flex items-center gap-2">
                        <Icon name={listIcon} mode="Decorative" />
                        {request?.title ?? 'Daftar batu dossier'}
                    </DialogTitle>
                    {request?.subtitle ? (
                        <DialogDescription>{request.subtitle}</DialogDescription>
                    ) : null}
                </DialogHeader>
                <div className="min-h-0 flex-1 px-6 pb-6">
                    {loading ? (
                        <p className="text-muted-foreground text-sm">
                            Memuat daftar batu...
                        </p>
                    ) : error ? (
                        <p className="text-destructive text-sm">{error}</p>
                    ) : (
                        <DiamondDossierStockTable diamonds={diamonds} />
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
