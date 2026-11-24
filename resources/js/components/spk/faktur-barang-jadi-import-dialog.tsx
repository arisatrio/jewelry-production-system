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
    importMethod as importFakturBarangJadi,
    importTemplate,
} from '@/routes/faktur-barang-jadi';

type FakturBarangJadiImportDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function FakturBarangJadiImportDialog({
    open,
    onOpenChange,
}: FakturBarangJadiImportDialogProps) {
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
                    <DialogTitle>Import faktur barang jadi</DialogTitle>
                    <DialogDescription>
                        Unggah file Excel sesuai template. Satu faktur bisa
                        berisi banyak baris SPK dengan header yang sama, atau
                        gunakan kolom No Faktur untuk mengelompokkan.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    key={open ? `faktur-import-open-${inputKey}` : 'faktur-import-closed'}
                    action={importFakturBarangJadi.url()}
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
                                    Wajib: Tanggal, Dari, Untuk, No SPK. PLU
                                    opsional. SPK yang belum selesai produksi
                                    akan ditandai DONE otomatis saat import.
                                    Import
                                    otomatis menandai faktur{' '}
                                    <span className="font-medium">selesai</span>{' '}
                                    (diterima) dan memperbarui lokasi SPK ke
                                    kolom Untuk. Tanggal faktur mengisi{' '}
                                    <span className="font-medium">
                                        send_to_merch_date
                                    </span>
                                    .
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="faktur-bj-import-file">
                                    File Excel
                                </Label>
                                <Input
                                    id="faktur-bj-import-file"
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