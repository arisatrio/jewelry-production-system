import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InventoryMasterPhotoField } from '@/pages/inventory/inventory-master-photo-field';

export type ComponentFormItem = {
    id: number;
    componentCategory: string;
    item: string;
    itemCode: string;
    description: string | null;
    photo?: string | null;
};

type ComponentFormFieldsProps = {
    categories: string[];
    item?: ComponentFormItem;
    errors: Partial<Record<string, string>>;
    idPrefix?: string;
    autoFocus?: boolean;
};

export function ComponentFormFields({
    categories,
    item,
    errors,
    idPrefix = '',
    autoFocus = false,
}: ComponentFormFieldsProps) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}component_category`}>Kategori</Label>
                <select
                    id={`${idPrefix}component_category`}
                    name="component_category"
                    required
                    defaultValue={item?.componentCategory ?? categories[0]}
                    className="masterDataFormSelect"
                >
                    {categories.map((category) => (
                        <option key={category} value={category}>
                            {category}
                        </option>
                    ))}
                </select>
                <InputError message={errors.component_category} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}item`}>Item</Label>
                <Input
                    id={`${idPrefix}item`}
                    name="item"
                    required
                    maxLength={255}
                    defaultValue={item?.item ?? ''}
                    placeholder="Contoh: Clasp HK"
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
                <Label htmlFor={`${idPrefix}description`}>Keterangan</Label>
                <textarea
                    id={`${idPrefix}description`}
                    name="description"
                    maxLength={5000}
                    defaultValue={item?.description ?? ''}
                    className="masterDataTextarea"
                    placeholder="Keterangan komponen"
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
