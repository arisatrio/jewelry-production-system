import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InventoryMasterPhotoField } from '@/pages/inventory/inventory-master-photo-field';

export type ChainFormItem = {
    id: number;
    chainCategory: string;
    item: string;
    itemCode: string;
    length: string;
    description: string | null;
    photo?: string | null;
};

type ChainFormFieldsProps = {
    categories: string[];
    item?: ChainFormItem;
    errors: Partial<Record<string, string>>;
    idPrefix?: string;
    autoFocus?: boolean;
};

export function ChainFormFields({
    categories,
    item,
    errors,
    idPrefix = '',
    autoFocus = false,
}: ChainFormFieldsProps) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}chain_category`}>Kategori</Label>
                <select
                    id={`${idPrefix}chain_category`}
                    name="chain_category"
                    required
                    defaultValue={item?.chainCategory ?? categories[0]}
                    className="masterDataFormSelect"
                >
                    {categories.map((category) => (
                        <option key={category} value={category}>
                            {category}
                        </option>
                    ))}
                </select>
                <InputError message={errors.chain_category} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}item`}>Item</Label>
                <Input
                    id={`${idPrefix}item`}
                    name="item"
                    required
                    maxLength={255}
                    defaultValue={item?.item ?? ''}
                    placeholder="Contoh: Chain HK"
                    autoFocus={autoFocus}
                />
                <InputError message={errors.item} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}item_code`}>Kode Item</Label>
                <Input
                    id={`${idPrefix}item_code`}
                    name="item_code"
                    required
                    maxLength={255}
                    defaultValue={item?.itemCode ?? ''}
                    placeholder="Contoh: F-83 (K18SHM5P18)"
                />
                <InputError message={errors.item_code} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}length`}>Panjang (cm)</Label>
                <Input
                    id={`${idPrefix}length`}
                    name="length"
                    type="number"
                    required
                    min="0.01"
                    step="0.01"
                    defaultValue={item?.length ?? ''}
                    placeholder="18"
                />
                <InputError message={errors.length} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}description`}>Keterangan</Label>
                <textarea
                    id={`${idPrefix}description`}
                    name="description"
                    maxLength={5000}
                    defaultValue={item?.description ?? ''}
                    className="masterDataTextarea"
                    placeholder="Keterangan chain"
                />
                <InputError message={errors.description} />
            </div>

            <InventoryMasterPhotoField
                idPrefix={idPrefix}
                currentPhotoUrl={item?.photo}
                errors={errors}
            />
        </>
    );
}
