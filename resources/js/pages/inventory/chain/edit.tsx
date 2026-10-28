import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ChainFormFields } from '@/pages/inventory/chain/chain-form-fields';
import type { ChainFormItem } from '@/pages/inventory/chain/chain-form-fields';
import { index as chainsIndex, update } from '@/routes/inventory/chains';

type ChainEditProps = {
    item: ChainFormItem;
    categories: string[];
};

export default function ChainEdit({ item, categories }: ChainEditProps) {
    return (
        <>
            <Head title={`Edit Chain · ${item.item ?? item.itemCode}`} />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Edit Chain</h1>
                        <p className="masterDataSubtitle">
                            Perbarui master data chain pada tabel mschain.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={chainsIndex.url()}>Kembali</Link>
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
                                <ChainFormFields
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

ChainEdit.layout = {
    activeMenu: 'Stok Rantai',
    pageTitle: 'Edit Chain',
};
