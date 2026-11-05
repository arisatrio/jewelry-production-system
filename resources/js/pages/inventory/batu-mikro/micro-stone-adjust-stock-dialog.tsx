import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { MicroStoneTransactionFormFields } from '@/pages/inventory/batu-mikro/micro-stone-transaction-form-fields';
import { store } from '@/routes/inventory/stone-transactions';

export type MicroStoneAdjustTarget = {
    id: number;
    name: string;
    balancePcs: string;
    availablePcs: string;
    balanceCrt: string;
};

export type MicroStoneStockDialogKind = 'addition' | 'deduction';

type MicroStoneAdjustStockDialogProps = {
    stone: MicroStoneAdjustTarget | null;
    kind: MicroStoneStockDialogKind | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const COPY: Record<
    MicroStoneStockDialogKind,
    { title: string; description: string; submitLabel: string }
> = {
    addition: {
        title: 'Tambah stok batu',
        description: 'Penyesuaian stok',
        submitLabel: 'Simpan',
    },
    deduction: {
        title: 'Pengurangan batu',
        description: 'Penyesuaian stok',
        submitLabel: 'Simpan',
    },
};

export function MicroStoneAdjustStockDialog({
    stone,
    kind,
    open,
    onOpenChange,
}: MicroStoneAdjustStockDialogProps) {
    const copy = kind !== null ? COPY[kind] : null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {copy?.title ?? 'Stok batu mikro'}
                    </DialogTitle>
                    <DialogDescription>
                        {copy?.description ?? '—'}
                    </DialogDescription>
                </DialogHeader>

                {stone !== null && kind !== null && copy !== null && (
                    <Form
                        key={`${kind}-${stone.id}`}
                        action={store.url()}
                        method="post"
                        className="grid gap-4"
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="transaction_kind"
                                    value={kind}
                                />
                                <input
                                    type="hidden"
                                    name="stone_id"
                                    value={stone.id}
                                />
                                <MicroStoneTransactionFormFields
                                    idPrefix={`micro-stone-${kind}-${stone.id}`}
                                    stoneLabel={stone.name}
                                    balancePcs={stone.balancePcs}
                                    availablePcs={stone.availablePcs}
                                    balanceCrt={stone.balanceCrt}
                                    errors={errors}
                                    autoFocus
                                />
                                <DialogFooter className="flex flex-row justify-end gap-3 pt-1">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => onOpenChange(false)}
                                    >
                                        Batal
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {copy.submitLabel}
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
