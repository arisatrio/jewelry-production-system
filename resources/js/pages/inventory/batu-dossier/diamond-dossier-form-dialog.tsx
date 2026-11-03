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
import {
    DossierFormFields,
    type DossierFormValues,
    type DossierShapeOption,
} from '@/pages/inventory/batu-dossier/dossier-form-fields';
import { store, update } from '@/routes/inventory/diamond-dossiers';

type DiamondDossierCreateDialogProps = {
    shapeOptions: DossierShapeOption[];
    diamondTypeOptions: DossierShapeOption[];
    defaults: DossierFormValues;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DiamondDossierCreateDialog({
    shapeOptions,
    diamondTypeOptions,
    defaults,
    open,
    onOpenChange,
}: DiamondDossierCreateDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Tambah batu dossier</DialogTitle>
                    <DialogDescription>
                        Catat batu dossier baru ke stok.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    key={open ? 'create-open' : 'create-closed'}
                    action={store.url()}
                    method="post"
                    className="grid gap-4"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <DossierFormFields
                                idPrefix="dossier-create-"
                                shapeOptions={shapeOptions}
                                diamondTypeOptions={diamondTypeOptions}
                                values={defaults}
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

type DiamondDossierEditDialogProps = {
    diamondId: number;
    shapeOptions: DossierShapeOption[];
    diamondTypeOptions: DossierShapeOption[];
    form: DossierFormValues;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DiamondDossierEditDialog({
    diamondId,
    shapeOptions,
    diamondTypeOptions,
    form,
    open,
    onOpenChange,
}: DiamondDossierEditDialogProps) {
    const title = form.docNo ?? `Batu #${diamondId}`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit batu dossier</DialogTitle>
                    <DialogDescription>{title}</DialogDescription>
                </DialogHeader>

                <Form
                    key={open ? `edit-open-${diamondId}` : 'edit-closed'}
                    action={update.url(diamondId)}
                    method="put"
                    className="grid gap-4"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <DossierFormFields
                                idPrefix="dossier-edit-"
                                shapeOptions={shapeOptions}
                                diamondTypeOptions={diamondTypeOptions}
                                values={form}
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
                                    Simpan Perubahan
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function dossierRowToFormValues(diamond: {
    code: string | null;
    diamondType: string | null;
    shapeId: number | null;
    color: string | null;
    crt: string | null;
    certificate: string | null;
    supplier: string | null;
    entryDate: string | null;
}): DossierFormValues {
    return {
        docNo: diamond.code,
        diamondType: diamond.diamondType,
        shapeId: diamond.shapeId,
        color: diamond.color,
        crt: diamond.crt,
        certificate: diamond.certificate,
        supplier: diamond.supplier,
        entryDate: diamond.entryDate,
    };
}
