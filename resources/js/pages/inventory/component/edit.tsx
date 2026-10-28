import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ComponentFormFields } from '@/pages/inventory/component/component-form-fields';
import type { ComponentFormItem } from '@/pages/inventory/component/component-form-fields';
import { index as componentsIndex, update } from '@/routes/inventory/components';

type ComponentEditProps = {
    item: ComponentFormItem;
    categories: string[];
};

export default function ComponentEdit({ item, categories }: ComponentEditProps) {
    return (
        <>
            <Head title={`Edit Komponen · ${item.item ?? item.itemCode}`} />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Edit Komponen</h1>
                        <p className="masterDataSubtitle">
                            Perbarui master data komponen pada tabel mscomponent.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={componentsIndex.url()}>Kembali</Link>
                    </Button>
                </div>

                <div className="masterDataFormCard">
                    <Form
                        action={update.url(item.id)}
                        method="post"
                        encType="multipart/form-data"
                        options={{
                            forceFormData: true,
                        }}
                        className="masterDataForm"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_method" value="put" />
                                <ComponentFormFields
                                    categories={categories}
                                    item={item}
                                    errors={errors}
                                    autoFocus
                                />

                                <div className="masterDataFormActions">
                                    <Button type="submit" disabled={processing}>
                                        Simpan Perubahan
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

ComponentEdit.layout = {
    activeMenu: 'Stok Komponen',
    pageTitle: 'Edit Komponen',
};
