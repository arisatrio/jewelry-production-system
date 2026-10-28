import { useHttp, usePage } from '@inertiajs/react';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { useEffect, type FormEvent } from 'react';
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
import { store as storeChainReceipt } from '@/routes/spk/print/chain-receipt';
import type { Auth } from '@/types';

type ReceiptFormValues = {
    tanggal: string;
    dari: string;
    untuk: string;
    diserahkan_oleh: string;
    diterima_oleh: string;
};

type ReceiptStoreResponse = {
    id: number;
    docNo: string;
    printUrl: string;
};

type SpkChainReceiptPrintDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onPrinted?: () => void;
    spkRowId: number;
    chainLineIds: number[];
    employeeOptions: string[];
    defaultDiterimaOleh?: string;
};

type ReceiptNameField = 'diserahkan_oleh' | 'diterima_oleh';

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
    { name: 'diterima_oleh', label: 'Diterima oleh' },
];

function todayInputValue(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

function resolveLoggedInEmployeeName(
    employeeOptions: string[],
    loggedInName: string | undefined,
): string {
    const trimmed = loggedInName?.trim() ?? '';

    if (trimmed === '') {
        return '';
    }

    if (employeeOptions.includes(trimmed)) {
        return trimmed;
    }

    const match = employeeOptions.find(
        (name) => name.toLowerCase() === trimmed.toLowerCase(),
    );

    return match ?? '';
}

function defaultReceiptFormValues(
    employeeOptions: string[],
    loggedInName: string | undefined,
    defaultDiterimaOleh = '',
): ReceiptFormValues {
    return {
        tanggal: todayInputValue(),
        dari: 'Head Office',
        untuk: 'Workshop',
        diserahkan_oleh: resolveLoggedInEmployeeName(
            employeeOptions,
            loggedInName,
        ),
        diterima_oleh: defaultDiterimaOleh.trim(),
    };
}

function focusCurrentWindow(): void {
    window.focus();
}

export function openChainReceiptPrintPreview(printUrl: string): boolean {
    const tab = window.open(printUrl, '_blank');

    if (tab === null) {
        window.alert(
            'Gagal membuka tanda terima. Izinkan pop-up untuk situs ini, lalu coba lagi.',
        );

        return false;
    }

    focusCurrentWindow();

    return true;
}

function openReceiptPrintTab(): Window | null {
    const tab = window.open('about:blank', '_blank');

    if (tab === null) {
        return null;
    }

    try {
        tab.document.title = 'Menyiapkan tanda terima...';
        tab.document.body.textContent = 'Menyiapkan tanda terima...';
    } catch {
        // Tab terbuka; akses document bisa gagal di beberapa browser.
    }

    focusCurrentWindow();

    return tab;
}

function navigateReceiptPrintTab(tab: Window | null, url: string): void {
    if (tab !== null && !tab.closed) {
        tab.location.href = url;
        focusCurrentWindow();

        return;
    }

    openChainReceiptPrintPreview(url);
}

export function SpkChainReceiptPrintDialog({
    open,
    onOpenChange,
    onPrinted,
    spkRowId,
    chainLineIds,
    employeeOptions,
    defaultDiterimaOleh = '',
}: SpkChainReceiptPrintDialogProps) {
    const { auth } = usePage<{ auth: Auth | { user: Auth['user'] | null } }>()
        .props;
    const loggedInName = auth.user?.name;

    const receiptForm = useHttp<ReceiptFormValues, ReceiptStoreResponse>(
        defaultReceiptFormValues(
            employeeOptions,
            loggedInName,
            defaultDiterimaOleh,
        ),
    );
    const values = receiptForm.data;

    useEffect(() => {
        if (!open) {
            return;
        }

        receiptForm.setData(
            defaultReceiptFormValues(
                employeeOptions,
                loggedInName,
                defaultDiterimaOleh,
            ),
        );
        receiptForm.clearErrors();
    }, [open, employeeOptions, loggedInName, defaultDiterimaOleh]);
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
        spkRowId > 0 &&
        chainLineIds.length > 0 &&
        values.tanggal !== '' &&
        RECEIPT_NAME_FIELDS.every((field) =>
            isKnownEmployee(values[field.name]),
        );

    const saveAndOpenReceipt = (): void => {
        if (!canSubmit) {
            return;
        }

        const receiptWindow = openReceiptPrintTab();

        if (receiptWindow === null) {
            window.alert(
                'Gagal membuka tanda terima. Izinkan pop-up untuk situs ini, lalu coba lagi.',
            );

            return;
        }

        const closeReceiptWindow = (): void => {
            if (!receiptWindow.closed) {
                receiptWindow.close();
            }
        };

        receiptForm.clearErrors();
        receiptForm.transform((data) => ({
            spk_row_id: spkRowId,
            chain_line_ids: chainLineIds,
            ...Object.fromEntries(
                Object.entries(data)
                    .map(([key, value]) => [key, String(value).trim()])
                    .filter(([, value]) => value !== ''),
            ),
        }));

        void receiptForm
            .post(storeChainReceipt.url(), {
                onSuccess: (response) => {
                    navigateReceiptPrintTab(
                        receiptWindow,
                        response.printUrl,
                    );
                    onOpenChange(false);
                    onPrinted?.();
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
                        <DialogTitle>Print Tanda Terima Rantai</DialogTitle>
                        <DialogDescription>
                            {chainLineIds.length} baris rantai pada SPK ini.
                            Detail cetak menampilkan item chain, unit, dan
                            warna.
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
                            <Label htmlFor="spk-chain-receipt-tanggal">
                                Tanggal
                            </Label>
                            <Input
                                id="spk-chain-receipt-tanggal"
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
                                        htmlFor={`spk-chain-receipt-${field.name}`}
                                    >
                                        {field.label}
                                    </Label>
                                    <Select
                                        id={`spk-chain-receipt-${field.name}`}
                                        value={values[field.name]}
                                        onChange={(event) =>
                                            setField(
                                                field.name,
                                                event.target.value ?? '',
                                            )
                                        }
                                    >
                                        <Option value="" />
                                        {RECEIPT_LOCATIONS.map((location) => (
                                            <Option
                                                key={location}
                                                value={location}
                                                disabled={
                                                    location === otherLocation
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
                                        htmlFor={`spk-chain-receipt-${field.name}`}
                                    >
                                        {field.label}
                                    </Label>
                                    <ComboBox
                                        id={`spk-chain-receipt-${field.name}`}
                                        value={values[field.name]}
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
                                : 'Print'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
