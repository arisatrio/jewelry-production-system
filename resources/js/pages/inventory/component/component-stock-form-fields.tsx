import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ReactNode } from 'react';

type ComponentStockFormFieldsProps = {
    idPrefix: string;
    colorOptions: string[];
    errors: Partial<Record<string, string>>;
    entryDateDefault?: string;
    autoFocus?: boolean;
};

function RequiredLabel({
    htmlFor,
    children,
}: {
    htmlFor: string;
    children: ReactNode;
}) {
    return (
        <Label htmlFor={htmlFor}>
            {children}{' '}
            <span className="text-red-600" aria-hidden="true">
                *
            </span>
        </Label>
    );
}

export function ComponentStockFormFields({
    idPrefix,
    colorOptions,
    errors,
    entryDateDefault,
    autoFocus = false,
}: ComponentStockFormFieldsProps) {
    const today =
        entryDateDefault ?? new Date().toISOString().slice(0, 10);

    return (
        <>
            <div className="grid gap-2">
                <RequiredLabel htmlFor={`${idPrefix}-supplier`}>
                    Supplier
                </RequiredLabel>
                <Input
                    id={`${idPrefix}-supplier`}
                    name="supplier"
                    required
                    maxLength={255}
                    autoFocus={autoFocus}
                />
                <InputError message={errors.supplier} />
            </div>

            <div className="grid gap-2">
                <RequiredLabel htmlFor={`${idPrefix}-weight`}>
                    Berat per Pcs (g)
                </RequiredLabel>
                <Input
                    id={`${idPrefix}-weight`}
                    name="weight"
                    type="number"
                    required
                    min="0.01"
                    step="0.01"
                />
                <InputError message={errors.weight} />
            </div>

            <div className="grid gap-2">
                <RequiredLabel htmlFor={`${idPrefix}-color`}>Warna</RequiredLabel>
                <select
                    id={`${idPrefix}-color`}
                    name="color"
                    required
                    defaultValue=""
                    className="masterDataFormSelect"
                >
                    <option value="" disabled>
                        Pilih warna
                    </option>
                    {colorOptions.map((color) => (
                        <option key={color} value={color}>
                            {color}
                        </option>
                    ))}
                </select>
                <InputError message={errors.color} />
            </div>

            <div className="grid gap-2">
                <RequiredLabel htmlFor={`${idPrefix}-pcs`}>Jumlah (pcs)</RequiredLabel>
                <Input
                    id={`${idPrefix}-pcs`}
                    name="pcs"
                    type="number"
                    required
                    min={1}
                    max={200}
                    step={1}
                    defaultValue={1}
                />
                <InputError message={errors.pcs} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-entry_date`}>Tanggal masuk</Label>
                <Input
                    id={`${idPrefix}-entry_date`}
                    name="entry_date"
                    type="date"
                    defaultValue={today}
                />
                <InputError message={errors.entry_date} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-purchase_price`}>
                    Harga beli per Pcs (USD)
                </Label>
                <Input
                    id={`${idPrefix}-purchase_price`}
                    name="purchase_price"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                />
                <InputError message={errors.purchase_price} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-component_size`}>Ukuran (cm)</Label>
                <Input
                    id={`${idPrefix}-component_size`}
                    name="component_size"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Contoh: 2"
                />
                <InputError message={errors.component_size} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-notes`}>Catatan</Label>
                <textarea
                    id={`${idPrefix}-notes`}
                    name="notes"
                    maxLength={5000}
                    className="masterDataTextarea"
                />
                <InputError message={errors.notes} />
            </div>
        </>
    );
}
