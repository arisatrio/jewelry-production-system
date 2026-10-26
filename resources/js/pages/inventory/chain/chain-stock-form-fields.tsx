import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ChainStockFormFieldsProps = {
    idPrefix: string;
    colorOptions: string[];
    errors: Partial<Record<string, string>>;
    entryDateDefault?: string;
    autoFocus?: boolean;
};

export function ChainStockFormFields({
    idPrefix,
    colorOptions,
    errors,
    entryDateDefault,
    autoFocus = false,
}: ChainStockFormFieldsProps) {
    const today =
        entryDateDefault ?? new Date().toISOString().slice(0, 10);

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-entry_date`}>Tanggal masuk</Label>
                <Input
                    id={`${idPrefix}-entry_date`}
                    name="entry_date"
                    type="date"
                    required
                    defaultValue={today}
                    autoFocus={autoFocus}
                />
                <InputError message={errors.entry_date} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-supplier`}>Supplier</Label>
                <Input
                    id={`${idPrefix}-supplier`}
                    name="supplier"
                    required
                    maxLength={255}
                />
                <InputError message={errors.supplier} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-weight`}>Berat (g)</Label>
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
                <Label htmlFor={`${idPrefix}-purchase_price`}>
                    Harga beli (USD)
                </Label>
                <Input
                    id={`${idPrefix}-purchase_price`}
                    name="purchase_price"
                    type="number"
                    required
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                />
                <InputError message={errors.purchase_price} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-color`}>Warna</Label>
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
