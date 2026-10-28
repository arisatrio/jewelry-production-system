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
import { ComponentFormFields } from '@/pages/inventory/component/component-form-fields';
import { store } from '@/routes/inventory/components';

type ComponentAddDialogProps = {
    categories: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ComponentAddDialog({
    categories,
    open,
    onOpenChange,
}: ComponentAddDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Tambah stok komponen</DialogTitle>
                    <DialogDescription>
                        Tambahkan master komponen baru ke mscomponent.
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
                            <ComponentFormFields
                                idPrefix="component-add-"
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
