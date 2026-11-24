import { useHttp } from '@inertiajs/react';
import { Button } from '@ui5/webcomponents-react/Button';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import {
    emptyFakturReceiptForm,
    FakturBarangJadiReceiptFormFields,
    isKnownFakturEmployee,
    type FakturBarangJadiReceiptFormValues,
} from '@/components/spk/faktur-barang-jadi-receipt-form-fields';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store as storeFakturBarangJadiReceipt } from '@/routes/faktur-barang-jadi/print/receipt';

type ReceiptStoreResponse = {
    id: number;
    docNo: string;
    printUrl: string;
    workflowStatus?: 'created' | null;
};

type FakturBarangJadiReceiptPrintDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    selectedIds: number[];
    employeeOptions: string[];
    onSaved?: () => void;
};

export function FakturBarangJadiReceiptPrintDialog({
    open,
    onOpenChange,
    selectedIds,
    employeeOptions,
    onSaved,
}: FakturBarangJadiReceiptPrintDialogProps) {
    const receiptForm = useHttp<
        FakturBarangJadiReceiptFormValues,
        ReceiptStoreResponse
    >(emptyFakturReceiptForm());
    const values = receiptForm.data;
    const errorMessages = Array.from(
        new Set(Object.values(receiptForm.errors).filter(Boolean)),
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        receiptForm.setData(emptyFakturReceiptForm());
        receiptForm.clearErrors();
    }, [open]);

    const setField = (
        field: keyof FakturBarangJadiReceiptFormValues,
        value: string,
    ): void => {
        receiptForm.setData((current) => ({ ...current, [field]: value }));
    };

    const canSubmit =
        !receiptForm.processing &&
        selectedIds.length > 0 &&
        values.tanggal !== '' &&
        values.dari !== '' &&
        values.untuk !== '' &&
        isKnownFakturEmployee(
            values.diserahkan_oleh,
            employeeOptions,
        ) &&
        isKnownFakturEmployee(values.diketahui_oleh, employeeOptions);

    const submitReceipt = (): void => {
        if (!canSubmit) {
            return;
        }

        receiptForm.clearErrors();
        receiptForm.transform((data) => ({
            ids: selectedIds,
            ...Object.fromEntries(
                Object.entries(data)
                    .map(([key, value]) => [key, String(value).trim()])
                    .filter(([, value]) => value !== ''),
            ),
        }));

        void receiptForm
            .post(storeFakturBarangJadiReceipt.url(), {
                onSuccess: (response) => {
                    toast.success(
                        `Faktur ${response.docNo} disimpan. Status faktur: Faktur dibuat. Untuk mengirim, gunakan daftar Faktur Barang Jadi.`,
                    );
                    onOpenChange(false);
                    onSaved?.();
                },
                onHttpException: () => {
                    toast.error(
                        'Gagal menyimpan faktur barang jadi. Silakan coba lagi.',
                    );
                },
                onNetworkError: () => {
                    toast.error(
                        'Koneksi terputus saat menyimpan faktur. Silakan coba lagi.',
                    );
                },
            })
            .catch(() => undefined);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkReceiptPrintDialog">
                <form
                    className="grid gap-4"
                    onSubmit={(event: FormEvent<HTMLFormElement>) => {
                        event.preventDefault();
                        submitReceipt();
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Buat Faktur Barang Jadi</DialogTitle>
                        <DialogDescription>
                            {selectedIds.length} SPK selesai terpilih. Nomor
                            faktur dibuat otomatis.
                        </DialogDescription>
                    </DialogHeader>

                    {errorMessages.length > 0 ? (
                        <ul className="list-disc rounded-md border border-red-200 bg-red-50 py-2 pr-3 pl-7 text-xs text-red-700">
                            {errorMessages.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    ) : null}

                    <FakturBarangJadiReceiptFormFields
                        idPrefix="faktur-bj-dialog"
                        values={values}
                        employeeOptions={employeeOptions}
                        onFieldChange={setField}
                    />

                    <DialogFooter>
                        <Button
                            design="Transparent"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            design="Emphasized"
                            disabled={!canSubmit}
                            onClick={submitReceipt}
                        >
                            {receiptForm.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}