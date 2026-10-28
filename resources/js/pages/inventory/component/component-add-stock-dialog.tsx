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
import { ComponentStockFormFields } from '@/pages/inventory/component/component-stock-form-fields';
import { store } from '@/routes/inventory/components/stock';

export type ComponentAddStockItem = {
    id: number;
    item: string;
    itemCode: string;
    itemName: string | null;
};

type ComponentAddStockDialogProps = {
    item: ComponentAddStockItem | null;
    colorOptions: string[];
    open: boolean;
    returnTo: 'index' | 'show';
    onOpenChange: (open: boolean) => void;
};

export function ComponentAddStockDialog({
    item,
    colorOptions,
    open,
    returnTo,
    onOpenChange,
}: ComponentAddStockDialogProps) {
    const title =
        item !== null ? (item.itemName ?? item.itemCode) : '';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Tambah stok komponen</DialogTitle>
                    <DialogDescription>
                        {item !== null
                            ? `${title} · isi ukuran fisik unit yang masuk gudang.`
                            : 'Pilih master komponen untuk menambah stok.'}
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
                                <ComponentStockFormFields
                                    idPrefix={`component-stock-${item.id}`}
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
                                        Simpan stok
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
