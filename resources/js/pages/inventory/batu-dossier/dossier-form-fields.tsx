import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type DossierShapeOption = {
    value: string;
    label: string;
};

export type DossierTypeOption = DossierShapeOption;

export type DossierFormValues = {
    docNo?: string | null;
    diamondType?: string | null;
    shapeId?: number | null;
    color?: string | null;
    crt?: string | null;
    certificate?: string | null;
    supplier?: string | null;
    entryDate?: string | null;
};

type DossierFormFieldsProps = {
    shapeOptions: DossierShapeOption[];
    diamondTypeOptions: DossierTypeOption[];
    values?: DossierFormValues;
    errors: Partial<Record<string, string>>;
    idPrefix?: string;
    autoFocus?: boolean;
};

function DossierFieldLabel({
    htmlFor,
    required = false,
    children,
}: {
    htmlFor: string;
    required?: boolean;
    children: string;
}) {
    return (
        <Label htmlFor={htmlFor}>
            {children}
            {required ? (
                <span className="text-red-600" aria-hidden="true">
                    {' '}
                    *
                </span>
            ) : null}
        </Label>
    );
}

export function DossierFormFields({
    shapeOptions,
    diamondTypeOptions,
    values,
    errors,
    idPrefix = '',
    autoFocus = false,
}: DossierFormFieldsProps) {
    const selectedType =
        values?.diamondType && values.diamondType !== ''
            ? values.diamondType
            : 'Dossier';

    const typeOptions =
        selectedType !== '' &&
        !diamondTypeOptions.some((option) => option.value === selectedType)
            ? [
                  { value: selectedType, label: selectedType },
                  ...diamondTypeOptions,
              ]
            : diamondTypeOptions;

    return (
        <>
            <div className="grid gap-2">
                <DossierFieldLabel
                    htmlFor={`${idPrefix}entry_date`}
                    required
                >
                    Tanggal masuk
                </DossierFieldLabel>
                <Input
                    id={`${idPrefix}entry_date`}
                    name="entry_date"
                    type="date"
                    required
                    defaultValue={values?.entryDate ?? ''}
                    autoFocus={autoFocus}
                />
                <InputError message={errors.entry_date} />
            </div>

            <div className="grid gap-2">
                <DossierFieldLabel
                    htmlFor={`${idPrefix}diamond_type`}
                    required
                >
                    Tipe
                </DossierFieldLabel>
                <select
                    id={`${idPrefix}diamond_type`}
                    name="diamond_type"
                    required
                    defaultValue={selectedType}
                    className="masterDataFormSelect"
                >
                    {typeOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.diamond_type} />
            </div>

            <div className="grid gap-2">
                <DossierFieldLabel htmlFor={`${idPrefix}shape_id`} required>
                    Bentuk
                </DossierFieldLabel>
                <select
                    id={`${idPrefix}shape_id`}
                    name="shape_id"
                    required
                    defaultValue={
                        values?.shapeId !== null && values?.shapeId !== undefined
                            ? String(values.shapeId)
                            : ''
                    }
                    className="masterDataFormSelect"
                >
                    <option value="" disabled>
                        Pilih bentuk
                    </option>
                    {shapeOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.shape_id} />
            </div>

            <div className="grid gap-2">
                <DossierFieldLabel htmlFor={`${idPrefix}color`} required>
                    Warna
                </DossierFieldLabel>
                <Input
                    id={`${idPrefix}color`}
                    name="color"
                    required
                    maxLength={20}
                    defaultValue={values?.color ?? ''}
                    placeholder="Contoh: D"
                />
                <InputError message={errors.color} />
            </div>

            <div className="grid gap-2">
                <DossierFieldLabel htmlFor={`${idPrefix}crt`} required>
                    CRT
                </DossierFieldLabel>
                <Input
                    id={`${idPrefix}crt`}
                    name="crt"
                    required
                    inputMode="decimal"
                    defaultValue={values?.crt ?? ''}
                    placeholder="Contoh: 0.500"
                />
                <InputError message={errors.crt} />
            </div>

            <div className="grid gap-2">
                <DossierFieldLabel
                    htmlFor={`${idPrefix}certificate`}
                    required
                >
                    No. Sertifikat
                </DossierFieldLabel>
                <Input
                    id={`${idPrefix}certificate`}
                    name="certificate"
                    maxLength={100}
                    defaultValue={values?.certificate ?? ''}
                />
                <InputError message={errors.certificate} />
            </div>

            <div className="grid gap-2">
                <DossierFieldLabel htmlFor={`${idPrefix}supplier`}>
                    Supplier
                </DossierFieldLabel>
                <Input
                    id={`${idPrefix}supplier`}
                    name="supplier"
                    maxLength={100}
                    defaultValue={values?.supplier ?? ''}
                    placeholder="Contoh: RJ"
                />
                <InputError message={errors.supplier} />
            </div>
        </>
    );
}
