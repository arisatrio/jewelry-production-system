import { Form } from '@inertiajs/react';
import {
    PengrajinFields,
    type PengrajinEmployeeOption,
    type PengrajinFormValues,
} from '@/components/master-data/pengrajin-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store, update } from '@/routes/master-data/pengrajin';

export type PengrajinFormTarget = PengrajinFormValues & {
    id: number;
};

type PengrajinFormDialogProps = {
    open: boolean;
    craftsman: PengrajinFormTarget | null;
    employees: PengrajinEmployeeOption[];
    onOpenChange: (open: boolean) => void;
};

export function PengrajinFormDialog({
    open,
    craftsman,
    employees,
    onOpenChange,
}: PengrajinFormDialogProps) {
    const isEdit = craftsman !== null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit Pengrajin' : 'Tambah Pengrajin'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Hubungkan pengrajin ke pegawai.'
                            : 'Pilih pegawai produksi. Nama pengrajin terisi dari pegawai tersebut.'}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <Form
                        key={isEdit ? `edit-${craftsman.id}` : 'create'}
                        {...(isEdit ? update.form(craftsman.id) : store.form())}
                        className="grid gap-4"
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <PengrajinFields
                                    craftsman={craftsman ?? undefined}
                                    employees={employees}
                                    errors={errors}
                                />
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onOpenChange(false)}
                                    >
                                        Batal
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {isEdit ? 'Simpan Perubahan' : 'Simpan'}
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
