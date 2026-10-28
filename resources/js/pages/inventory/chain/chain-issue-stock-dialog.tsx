import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ChainIssueSpkPicker } from '@/pages/inventory/chain/chain-issue-spk-picker';
import { issue } from '@/routes/inventory/chains/stock';

export type ChainIssueUnit = {
    id: number;
    unitNo: string | null;
    name: string;
    chainLength: string;
    weight: string | null;
    color: string | null;
};

type ChainIssueStockDialogProps = {
    unit: ChainIssueUnit | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    fixedSpk?: {
        rowId: number;
        produksiNo: string;
    };
    fixedSpkChainLineId?: number;
    /** Setelah keluarkan stok dari alokasi SPK (tutup modal induk + refresh data). */
    onAllocated?: () => void;
};

export function ChainIssueStockDialog({
    unit,
    open,
    onOpenChange,
    fixedSpk,
    fixedSpkChainLineId,
    onAllocated,
}: ChainIssueStockDialogProps) {
    const today = new Date().toISOString().slice(0, 10);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Alokasikan stok chain</DialogTitle>
                    <DialogDescription>
                        {unit !== null
                            ? `Unit: ${unit.unitNo ?? unit.id} · ${unit.name} · ${unit.chainLength} cm${
                                  unit.weight !== null
                                      ? ` · ${unit.weight} g`
                                      : ''
                              }${unit.color !== null ? ` · ${unit.color}` : ''}`
                            : 'Pilih unit stok untuk dikurangi.'}
                    </DialogDescription>
                </DialogHeader>

                {unit !== null && (
                    <Form
                        key={unit.id}
                        action={issue.url(unit.id)}
                        method="post"
                        className="grid gap-4"
                        onSuccess={() => {
                            onOpenChange(false);
                            if (fixedSpk !== undefined) {
                                onAllocated?.();
                            }
                        }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="chain-issue-out-date">
                                        Tanggal keluar
                                    </Label>
                                    <Input
                                        id="chain-issue-out-date"
                                        type="date"
                                        name="out_date"
                                        required
                                        defaultValue={today}
                                    />
                                    <InputError message={errors.out_date} />
                                </div>

                                {fixedSpk !== undefined ? (
                                    <div className="grid gap-1">
                                        <Label className="text-xs">SPK</Label>
                                        <input
                                            type="hidden"
                                            name="spk_id"
                                            value={fixedSpk.rowId}
                                        />
                                        <input
                                            type="hidden"
                                            name="return_to"
                                            value="spk"
                                        />
                                        {fixedSpkChainLineId !== undefined ? (
                                            <input
                                                type="hidden"
                                                name="spk_chain_line_id"
                                                value={fixedSpkChainLineId}
                                            />
                                        ) : null}
                                        <p className="text-sm font-medium">
                                            {fixedSpk.produksiNo}
                                        </p>
                                    </div>
                                ) : (
                                    <ChainIssueSpkPicker
                                        error={errors.spk_id}
                                    />
                                )}

                                <DialogFooter className="gap-2 sm:gap-0">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => onOpenChange(false)}
                                    >
                                        Batal
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        Keluarkan stok
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
