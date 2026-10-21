import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type TrKomponenFormValues = {
    entry_date: string | null;
    out_date: string | null;
    jenis_komponen: string | null;
    name: string | null;
    item_code: string | null;
    supplier: string | null;
    length: string | null;
    color: string | null;
    weight: string | null;
    spk_id: number | null;
    is_used: number | null;
    is_reserved: number | null;
};

type TrKomponenFormFieldsProps = {
    values: TrKomponenFormValues;
    errors: Record<string, string | undefined>;
};

function flagSelectValue(value: number | null): string {
    if (value === 0) {
        return '0';
    }

    if (value === 1) {
        return '1';
    }

    return '';
}

export function TrKomponenFormFields({
    values,
    errors,
}: TrKomponenFormFieldsProps) {
    return (
        <div className="spkFormGrid">
            <div className="grid gap-2">
                <Label htmlFor="jenis_komponen">Jenis Komponen</Label>
                <Input
                    id="jenis_komponen"
                    name="jenis_komponen"
                    maxLength={100}
                    defaultValue={values.jenis_komponen ?? ''}
                />
                <InputError message={errors.jenis_komponen} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Nama</Label>
                <Input
                    id="name"
                    name="name"
                    maxLength={100}
                    defaultValue={values.name ?? ''}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="item_code">Kode Item</Label>
                <Input
                    id="item_code"
                    name="item_code"
                    maxLength={100}
                    defaultValue={values.item_code ?? ''}
                />
                <InputError message={errors.item_code} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="supplier">Supplier</Label>
                <Input
                    id="supplier"
                    name="supplier"
                    maxLength={100}
                    defaultValue={values.supplier ?? ''}
                />
                <InputError message={errors.supplier} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="entry_date">Tanggal Masuk</Label>
                <Input
                    id="entry_date"
                    name="entry_date"
                    type="date"
                    defaultValue={values.entry_date ?? ''}
                />
                <InputError message={errors.entry_date} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="out_date">Tanggal Keluar</Label>
                <Input
                    id="out_date"
                    name="out_date"
                    type="date"
                    defaultValue={values.out_date ?? ''}
                />
                <InputError message={errors.out_date} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="length">Panjang</Label>
                <Input
                    id="length"
                    name="length"
                    inputMode="decimal"
                    defaultValue={values.length ?? ''}
                />
                <InputError message={errors.length} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="color">Warna</Label>
                <Input
                    id="color"
                    name="color"
                    maxLength={1000}
                    defaultValue={values.color ?? ''}
                />
                <InputError message={errors.color} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="weight">Berat</Label>
                <Input
                    id="weight"
                    name="weight"
                    inputMode="decimal"
                    defaultValue={values.weight ?? ''}
                />
                <InputError message={errors.weight} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="spk_id">SPK ID</Label>
                <Input
                    id="spk_id"
                    name="spk_id"
                    type="number"
                    min={0}
                    step={1}
                    defaultValue={values.spk_id ?? ''}
                />
                <InputError message={errors.spk_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="is_used">Terpakai</Label>
                <select
                    id="is_used"
                    name="is_used"
                    className="masterDataSelect w-full"
                    defaultValue={flagSelectValue(values.is_used)}
                >
                    <option value="">—</option>
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
                <InputError message={errors.is_used} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="is_reserved">Reserved</Label>
                <select
                    id="is_reserved"
                    name="is_reserved"
                    className="masterDataSelect w-full"
                    defaultValue={flagSelectValue(values.is_reserved)}
                >
                    <option value="">—</option>
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
                <InputError message={errors.is_reserved} />
            </div>
        </div>
    );
}
