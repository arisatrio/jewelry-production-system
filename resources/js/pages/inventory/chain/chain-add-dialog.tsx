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
import { ChainFormFields } from '@/pages/inventory/chain/chain-form-fields';
import { store } from '@/routes/inventory/chains';

type ChainAddDialogProps = {
    categories: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ChainAddDialog({
    categories,
    open,
    onOpenChange,
}: ChainAddDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Tambah stok rantai</DialogTitle>
                    <DialogDescription>
                        Tambahkan master chain baru ke mschain.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    key={open ? 'open' : 'closed'}
                    action={store.url()}
                    method="post"
                    encType="multipart/form-data"
                    options={{
                        forceFormData: true,
                    }}
                    className="grid gap-4"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <ChainFormFields
                                idPrefix="chain-add-"
                                categories={categories}
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
            </DialogContent>
        </Dialog>
    );
}
