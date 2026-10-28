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
import { ComponentIssueSpkPicker } from '@/pages/inventory/component/component-issue-spk-picker';
import { issue } from '@/routes/inventory/components/stock';

export type ComponentIssueUnit = {
    id: number;
    unitNo: string | null;
    name: string;
    componentSize: string;
    weight: string | null;
    color: string | null;
};

type ComponentIssueStockDialogProps = {
    unit: ComponentIssueUnit | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    fixedSpk?: {
        rowId: number;
        produksiNo: string;
    };
    fixedSpkComponentLineId?: number;
    /** Setelah keluarkan stok dari alokasi SPK (tutup modal induk + refresh data). */
    onAllocated?: () => void;
};

export function ComponentIssueStockDialog({
    unit,
    open,
    onOpenChange,
    fixedSpk,
    fixedSpkComponentLineId,
    onAllocated,
}: ComponentIssueStockDialogProps) {
    const today = new Date().toISOString().slice(0, 10);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Alokasikan stok komponen</DialogTitle>
                    <DialogDescription>
                        {unit !== null
                            ? `Unit: ${unit.unitNo ?? unit.id} · ${unit.name} · ${unit.componentSize} cm${
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
                                    <Label htmlFor="component-issue-out-date">
                                        Tanggal keluar
                                    </Label>
                                    <Input
                                        id="component-issue-out-date"
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
                                        {fixedSpkComponentLineId !== undefined ? (
                                            <input
                                                type="hidden"
                                                name="spk_chain_line_id"
                                                value={fixedSpkComponentLineId}
                                            />
                                        ) : null}
                                        <p className="text-sm font-medium">
                                            {fixedSpk.produksiNo}
                                        </p>
                                    </div>
                                ) : (
                                    <ComponentIssueSpkPicker
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
