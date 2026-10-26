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
import { ChainStockFormFields } from '@/pages/inventory/chain/chain-stock-form-fields';
import { store } from '@/routes/inventory/chains/stock';

export type ChainAddStockItem = {
    id: number;
    item: string;
    itemCode: string;
    itemName: string | null;
    length: string;
};

type ChainAddStockDialogProps = {
    item: ChainAddStockItem | null;
    colorOptions: string[];
    open: boolean;
    returnTo: 'index' | 'show';
    onOpenChange: (open: boolean) => void;
};

export function ChainAddStockDialog({
    item,
    colorOptions,
    open,
    returnTo,
    onOpenChange,
}: ChainAddStockDialogProps) {
    const title =
        item !== null ? (item.itemName ?? item.itemCode) : '';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Tambah stok chain</DialogTitle>
                    <DialogDescription>
                        {item !== null
                            ? `${title} · panjang master ${item.length} cm`
                            : 'Pilih master chain untuk menambah stok.'}
                    </DialogDescription>
                </DialogHeader>

                {item !== null && (
                    <Form
                        key={item.id}
                        action={store.url(item.id)}
                        method="post"
                        className="grid gap-4"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="return_to"
                                    value={returnTo}
                                />
                                <ChainStockFormFields
                                    idPrefix={`chain-stock-${item.id}`}
                                    colorOptions={colorOptions}
                                    errors={errors}
                                    autoFocus
                                />
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
                                        Simpan
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
