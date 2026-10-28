import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ComponentFormFields } from '@/pages/inventory/component/component-form-fields';
import { index as chainsIndex, store } from '@/routes/inventory/components';

type ChainCreateProps = {
    categories: string[];
};

export default function ChainCreate({ categories }: ChainCreateProps) {
    return (
        <>
            <Head title="Tambah Chain" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Tambah Chain</h1>
                        <p className="masterDataSubtitle">
                            Tambahkan master data chain baru ke tabel mschain.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={chainsIndex.url()}>Kembali</Link>
                    </Button>
                </div>

                <div className="masterDataFormCard">
                    <Form
                        action={store.url()}
                        method="post"
                        className="masterDataForm"
                    >
                        {({ processing, errors }) => (
                            <>
                                <ComponentFormFields
                                    categories={categories}
                                    errors={errors}
                                    autoFocus
                                />

                                <div className="masterDataFormActions">
                                    <Button type="submit" disabled={processing}>
                                        Simpan
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

ChainCreate.layout = {
    activeMenu: 'Stok Komponen',
    pageTitle: 'Tambah Chain',
};
