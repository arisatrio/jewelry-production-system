import { useHttp } from '@inertiajs/react';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import type { FormEvent } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeSpkReceipt } from '@/routes/spk/print/receipt';

type ReceiptFormValues = {
    tanggal: string;
    dari: string;
    untuk: string;
    diserahkan_oleh: string;
    diketahui_oleh: string;
    diterima_oleh: string;
};

type ReceiptStoreResponse = {
    id: number;
    docNo: string;
    printUrl: string;
};

type SpkReceiptPrintDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    selectedIds: number[];
    employeeOptions: string[];
};

type ReceiptNameField = 'diserahkan_oleh' | 'diketahui_oleh' | 'diterima_oleh';

const RECEIPT_LOCATIONS = ['Head Office', 'Workshop', 'Store'] as const;

const RECEIPT_LOCATION_FIELDS: Array<{
    name: 'dari' | 'untuk';
    label: string;
}> = [
    { name: 'dari', label: 'Dari' },
    { name: 'untuk', label: 'Untuk' },
];

const RECEIPT_NAME_FIELDS: Array<{
    name: ReceiptNameField;
    label: string;
}> = [
    { name: 'diserahkan_oleh', label: 'Diserahkan oleh' },
    { name: 'diketahui_oleh', label: 'Diketahui oleh' },
    { name: 'diterima_oleh', label: 'Diterima oleh' },
];

function todayInputValue(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

function emptyReceiptForm(): ReceiptFormValues {
    return {
        tanggal: todayInputValue(),
        dari: '',
        untuk: '',
        diserahkan_oleh: '',
        diketahui_oleh: '',
        diterima_oleh: '',
    };
}

export function SpkReceiptPrintDialog({
    open,
    onOpenChange,
    selectedIds,
    employeeOptions,
}: SpkReceiptPrintDialogProps) {
    const receiptForm = useHttp<ReceiptFormValues, ReceiptStoreResponse>(
        emptyReceiptForm(),
    );
    const values = receiptForm.data;
    const errorMessages = Array.from(
        new Set(Object.values(receiptForm.errors).filter(Boolean)),
    );

    const setField = (field: keyof ReceiptFormValues, value: string): void => {
        receiptForm.setData((current) => ({ ...current, [field]: value }));
    };

    const isKnownEmployee = (name: string): boolean =>
        name.trim() === '' || employeeOptions.includes(name.trim());

    const canSubmit =
        !receiptForm.processing &&
        selectedIds.length > 0 &&
        values.tanggal !== '' &&
        RECEIPT_NAME_FIELDS.every((field) =>
            isKnownEmployee(values[field.name]),
        );

    const saveAndOpenReceipt = (): void => {
        if (!canSubmit) {
            return;
        }

        const receiptWindow = window.open('', '_blank');

        if (!receiptWindow) {
            window.alert(
                'Gagal membuka tanda terima. Izinkan pop-up untuk situs ini, lalu coba lagi.',
            );

            return;
        }

        receiptWindow.document.title = 'Menyiapkan tanda terima...';
        receiptWindow.document.body.textContent =
            'Menyiapkan tanda terima...';

        const closeReceiptWindow = (): void => {
            if (!receiptWindow.closed) {
                receiptWindow.close();
            }
        };

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
            .post(storeSpkReceipt.url(), {
                onSuccess: (response) => {
                    receiptWindow.location.href = response.printUrl;
                    onOpenChange(false);
                },
                onError: closeReceiptWindow,
                onHttpException: () => {
                    closeReceiptWindow();
                    window.alert(
                        'Gagal menyimpan tanda terima. Silakan coba lagi.',
                    );
                },
                onNetworkError: () => {
                    closeReceiptWindow();
                    window.alert(
                        'Koneksi terputus saat menyimpan tanda terima. Silakan coba lagi.',
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
                        saveAndOpenReceipt();
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Print Tanda Terima</DialogTitle>
                        <DialogDescription>
                            {selectedIds.length} SPK terpilih. Nomor form serah
                            terima dibuat otomatis saat dicetak.
                        </DialogDescription>
                    </DialogHeader>

                    {errorMessages.length > 0 ? (
                        <ul className="list-disc rounded-md border border-red-200 bg-red-50 py-2 pr-3 pl-7 text-xs text-red-700">
                            {errorMessages.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    ) : null}

                    <div className="grid gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="spk-receipt-tanggal">Tanggal</Label>
                            <Input
                                id="spk-receipt-tanggal"
                                type="date"
                                value={values.tanggal}
                                onChange={(event) =>
                                    setField('tanggal', event.target.value)
                                }
                            />
                        </div>
                        {RECEIPT_LOCATION_FIELDS.map((field) => {
                            const otherLocation =
                                field.name === 'dari'
                                    ? values.untuk
                                    : values.dari;

                            return (
                                <div key={field.name} className="grid gap-1.5">
                                    <Label
                                        htmlFor={`spk-receipt-${field.name}`}
                                    >
                                        {field.label}
                                    </Label>
                                    <Select
                                        id={`spk-receipt-${field.name}`}
                                        className="w-full"
                                        accessibleName={field.label}
                                        onChange={(event) =>
                                            setField(
                                                field.name,
                                                event.detail.selectedOption
                                                    ?.value ?? '',
                                            )
                                        }
                                    >
                                        <Option
                                            value=""
                                            selected={values[field.name] === ''}
                                        >
                                            Pilih lokasi
                                        </Option>
                                        {RECEIPT_LOCATIONS.filter(
                                            (location) =>
                                                location !== otherLocation,
                                        ).map((location) => (
                                            <Option
                                                key={location}
                                                value={location}
                                                selected={
                                                    values[field.name] ===
                                                    location
                                                }
                                            >
                                                {location}
                                            </Option>
                                        ))}
                                    </Select>
                                </div>
                            );
                        })}
                        {RECEIPT_NAME_FIELDS.map((field) => {
                            const isInvalid = !isKnownEmployee(
                                values[field.name],
                            );

                            return (
                                <div key={field.name} className="grid gap-1.5">
                                    <Label
                                        htmlFor={`spk-receipt-${field.name}`}
                                    >
                                        {field.label}
                                    </Label>
                                    <ComboBox
                                        id={`spk-receipt-${field.name}`}
                                        className="w-full"
                                        filter="Contains"
                                        placeholder="Cari karyawan"
                                        value={values[field.name]}
                                        valueState={
                                            isInvalid ? 'Negative' : 'None'
                                        }
                                        onInput={(event) =>
                                            setField(
                                                field.name,
                                                event.target.value ?? '',
                                            )
                                        }
                                        onChange={(event) =>
                                            setField(
                                                field.name,
                                                event.target.value ?? '',
                                            )
                                        }
                                    >
                                        {employeeOptions.map((name) => (
                                            <ComboBoxItem
                                                key={name}
                                                text={name}
                                            />
                                        ))}
                                    </ComboBox>
                                    {isInvalid ? (
                                        <span className="text-xs text-red-600">
                                            Pilih nama dari daftar karyawan
                                            aktif.
                                        </span>
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>

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
                            onClick={saveAndOpenReceipt}
                        >
                            {receiptForm.processing
                                ? 'Menyimpan...'
                                : 'Preview Print'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
