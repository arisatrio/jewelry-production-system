import { Form } from '@inertiajs/react';
import excelAttachmentIcon from '@ui5/webcomponents-icons/dist/excel-attachment.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    importMethod as importDiamondDossiers,
    importTemplate,
} from '@/routes/inventory/diamond-dossiers';

type DiamondDossierImportDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DiamondDossierImportDialog({
    open,
    onOpenChange,
}: DiamondDossierImportDialogProps) {
    const [inputKey, setInputKey] = useState(0);

    const downloadTemplate = () => {
        window.location.href = importTemplate.url();
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    setInputKey((current) => current + 1);
                }

                onOpenChange(nextOpen);
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Import batu dossier</DialogTitle>
                    <DialogDescription>
                        Unggah file Excel sesuai template. Kolom: Tanggal
                        Masuk, Kode/Bentuk, CRT, Warna, GIA, Supplier.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    key={open ? `import-open-${inputKey}` : 'import-closed'}
                    action={importDiamondDossiers.url()}
                    method="post"
                    encType="multipart/form-data"
                    options={{
                        forceFormData: true,
                    }}
                    className="grid gap-4"
                    onSuccess={() => {
                        setInputKey((current) => current + 1);
                        onOpenChange(false);
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="justify-start gap-2"
                                    onClick={downloadTemplate}
                                >
                                    <Icon
                                        name={excelAttachmentIcon}
                                        mode="Decorative"
                                    />
                                    Unduh template Excel
                                </Button>
                                <p className="text-muted-foreground text-sm">
                                    Isi baris data di bawah header. Kode/Bentuk
                                    bisa kode atau nama bentuk dari master (mis.
                                    RD atau Round (RD)).
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="dossier-import-file">
                                    File Excel
                                </Label>
                                <Input
                                    id="dossier-import-file"
                                    key={inputKey}
                                    type="file"
                                    name="file"
                                    accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                                    disabled={processing}
                                />
                                <InputError message={errors.file} />
                            </div>

                            <DialogFooter className="flex flex-row justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                    onClick={() => onOpenChange(false)}
                                >
                                    Batal
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    Import
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
