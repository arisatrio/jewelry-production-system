import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Form } from '@ui5/webcomponents-react/Form';
import { FormGroup } from '@ui5/webcomponents-react/FormGroup';
import { FormItem } from '@ui5/webcomponents-react/FormItem';
import { Label } from '@ui5/webcomponents-react/Label';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { Input } from '@/components/ui/input';

export type FakturBarangJadiReceiptFormValues = {
    tanggal: string;
    dari: string;
    untuk: string;
    diserahkan_oleh: string;
    diketahui_oleh: string;
};

type ReceiptNameField = 'diserahkan_oleh' | 'diketahui_oleh';

export const RECEIPT_LOCATIONS = ['Head Office', 'Workshop', 'Store'] as const;
export const DEFAULT_FROM_LOCATION = 'Workshop';

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
];

type FakturBarangJadiReceiptFormFieldsProps = {
    idPrefix: string;
    values: FakturBarangJadiReceiptFormValues;
    employeeOptions: string[];
    onFieldChange: (
        field: keyof FakturBarangJadiReceiptFormValues,
        value: string,
    ) => void;
};

export function isKnownFakturEmployee(
    name: string,
    employeeOptions: string[],
): boolean {
    return name.trim() === '' || employeeOptions.includes(name.trim());
}

export function todayFakturInputValue(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

export function emptyFakturReceiptForm(): FakturBarangJadiReceiptFormValues {
    return {
        tanggal: todayFakturInputValue(),
        dari: DEFAULT_FROM_LOCATION,
        untuk: '',
        diserahkan_oleh: '',
        diketahui_oleh: '',
    };
}

export function FakturBarangJadiReceiptFormFields({
    idPrefix,
    values,
    employeeOptions,
    onFieldChange,
}: FakturBarangJadiReceiptFormFieldsProps) {
    return (
        <Form
            accessibleMode="Edit"
            layout="S1 M2 L2 XL2"
            labelSpan="S12 M4 L4 XL4"
            itemSpacing="Normal"
        >
            <FormGroup headerText="Data Faktur" columnSpan={2}>
                <FormItem
                    labelContent={
                        <Label showColon required>
                            Tanggal
                        </Label>
                    }
                >
                    <Input
                        id={`${idPrefix}-tanggal`}
                        type="date"
                        className="w-full max-w-sm"
                        value={values.tanggal}
                        onChange={(event) =>
                            onFieldChange('tanggal', event.target.value)
                        }
                    />
                </FormItem>
                {RECEIPT_LOCATION_FIELDS.map((field) => {
                    const otherLocation =
                        field.name === 'dari' ? values.untuk : values.dari;

                    return (
                        <FormItem
                            key={field.name}
                            labelContent={
                                <Label showColon required>
                                    {field.label}
                                </Label>
                            }
                        >
                            <Select
                                id={`${idPrefix}-${field.name}`}
                                className="w-full max-w-sm"
                                accessibleName={field.label}
                                onChange={(event) =>
                                    onFieldChange(
                                        field.name,
                                        event.detail.selectedOption?.value ?? '',
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
                                    (location) => location !== otherLocation,
                                ).map((location) => (
                                    <Option
                                        key={location}
                                        value={location}
                                        selected={
                                            values[field.name] === location
                                        }
                                    >
                                        {location}
                                    </Option>
                                ))}
                            </Select>
                        </FormItem>
                    );
                })}
                {RECEIPT_NAME_FIELDS.map((field) => {
                    const isInvalid = !isKnownFakturEmployee(
                        values[field.name],
                        employeeOptions,
                    );

                    return (
                        <FormItem
                            key={field.name}
                            labelContent={
                                <Label showColon required>
                                    {field.label}
                                </Label>
                            }
                        >
                            <div className="grid max-w-md gap-1">
                                <ComboBox
                                    id={`${idPrefix}-${field.name}`}
                                    className="w-full"
                                    filter="Contains"
                                    placeholder="Cari karyawan"
                                    value={values[field.name]}
                                    valueState={
                                        isInvalid ? 'Negative' : 'None'
                                    }
                                    onInput={(event) =>
                                        onFieldChange(
                                            field.name,
                                            event.target.value ?? '',
                                        )
                                    }
                                    onChange={(event) =>
                                        onFieldChange(
                                            field.name,
                                            event.target.value ?? '',
                                        )
                                    }
                                >
                                    {employeeOptions.map((name) => (
                                        <ComboBoxItem key={name} text={name} />
                                    ))}
                                </ComboBox>
                                {isInvalid ? (
                                    <span className="text-xs text-red-600">
                                        Pilih nama dari daftar karyawan aktif.
                                    </span>
                                ) : null}
                            </div>
                        </FormItem>
                    );
                })}
            </FormGroup>
        </Form>
    );
}