import { Button } from '@ui5/webcomponents-react/Button';
import { DatePicker } from '@ui5/webcomponents-react/DatePicker';
import { Label } from '@ui5/webcomponents-react/Label';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { Text } from '@ui5/webcomponents-react/Text';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type FinishingExportValues = {
    craftsman: string;
    date_from: string;
    date_to: string;
};

type FinishingExportErrors = Partial<
    Record<keyof FinishingExportValues, string>
>;

type FinishingExportDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    craftsmanOptions: { value: string; label: string }[];
    initialValues: FinishingExportValues;
    onSubmit: (values: FinishingExportValues) => void;
};

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function validate(values: FinishingExportValues): FinishingExportErrors {
    const errors: FinishingExportErrors = {};

    if (values.date_from === '') {
        errors.date_from = 'Tanggal dari wajib diisi.';
    }

    if (values.date_to === '') {
        errors.date_to = 'Tanggal sampai wajib diisi.';
    }

    if (
        values.date_from !== '' &&
        values.date_to !== '' &&
        values.date_to < values.date_from
    ) {
        errors.date_to = 'Tanggal sampai tidak boleh sebelum tanggal dari.';
    }

    return errors;
}

export function FinishingExportDialog({
    open,
    onOpenChange,
    craftsmanOptions,
    initialValues,
    onSubmit,
}: FinishingExportDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkCoranMaterialDialog">
                <DialogHeader>
                    <DialogTitle>Export Laporan Finishing</DialogTitle>
                    <DialogDescription>
                        Hanya dokumen yang sudah di-approve (Serahkan ke PPIC
                        dan Completed) yang akan diexport ke Excel.
                    </DialogDescription>
                </DialogHeader>

                <FinishingExportForm
                    craftsmanOptions={craftsmanOptions}
                    initialValues={initialValues}
                    onCancel={() => onOpenChange(false)}
                    onSubmit={onSubmit}
                />
            </DialogContent>
        </Dialog>
    );
}

function FinishingExportForm({
    craftsmanOptions,
    initialValues,
    onCancel,
    onSubmit,
}: Pick<
    FinishingExportDialogProps,
    'craftsmanOptions' | 'initialValues' | 'onSubmit'
> & {
    onCancel: () => void;
}) {
    const [values, setValues] = useState(initialValues);
    const [errors, setErrors] = useState<FinishingExportErrors>({});

    const updateValue = (field: keyof FinishingExportValues, value: string) => {
        setValues((current) => ({ ...current, [field]: value }));
        setErrors((current) => ({ ...current, [field]: undefined }));
    };

    const handleSubmit = () => {
        const nextErrors = validate(values);

        if (Object.keys(nextErrors).length > 0) {
            setErrors(nextErrors);

            return;
        }

        onSubmit(values);
    };

    return (
        <>
            <div className="spkCoranMaterialDialogForm">
                <div className="spkFioriFieldStack">
                    <Label showColon>Pengrajin</Label>
                    <Select
                        accessibleName="Pengrajin"
                        onChange={(event) =>
                            updateValue(
                                'craftsman',
                                event.detail.selectedOption.value ?? '',
                            )
                        }
                    >
                        <Option value="" selected={values.craftsman === ''}>
                            Semua Pengrajin
                        </Option>
                        {craftsmanOptions.map((option) => (
                            <Option
                                key={option.value}
                                value={option.value}
                                selected={values.craftsman === option.value}
                            >
                                {option.label}
                            </Option>
                        ))}
                    </Select>
                </div>

                <div className="spkFioriFieldStack">
                    <Label showColon required>
                        Tanggal dari
                    </Label>
                    <DatePicker
                        accessibleName="Tanggal dari"
                        value={values.date_from}
                        valueFormat="yyyy-MM-dd"
                        displayFormat="dd/MM/yyyy"
                        valueState={fieldState(errors.date_from)}
                        onChange={(event) =>
                            updateValue('date_from', event.detail.value ?? '')
                        }
                    />
                    {errors.date_from ? (
                        <Text className="spkFioriError">
                            {errors.date_from}
                        </Text>
                    ) : null}
                </div>

                <div className="spkFioriFieldStack">
                    <Label showColon required>
                        Tanggal sampai
                    </Label>
                    <DatePicker
                        accessibleName="Tanggal sampai"
                        value={values.date_to}
                        valueFormat="yyyy-MM-dd"
                        displayFormat="dd/MM/yyyy"
                        valueState={fieldState(errors.date_to)}
                        onChange={(event) =>
                            updateValue('date_to', event.detail.value ?? '')
                        }
                    />
                    {errors.date_to ? (
                        <Text className="spkFioriError">{errors.date_to}</Text>
                    ) : null}
                </div>
            </div>

            <DialogFooter>
                <Button design="Default" type="Button" onClick={onCancel}>
                    Batal
                </Button>
                <Button
                    design="Emphasized"
                    type="Button"
                    onClick={handleSubmit}
                >
                    Export
                </Button>
            </DialogFooter>
        </>
    );
}
