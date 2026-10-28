import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ComponentStockFormFields } from '@/pages/inventory/component/component-stock-form-fields';
import { index as componentsIndex, show } from '@/routes/inventory/components';
import { store } from '@/routes/inventory/components/stock';

type ComponentItem = {
    id: number;
    itemName: string | null;
    itemCode: string;
};

type ComponentStockCreateProps = {
    item: ComponentItem;
    colorOptions: string[];
};

export default function ComponentStockCreate({
    item,
    colorOptions,
}: ComponentStockCreateProps) {
    const title = item.itemName ?? item.itemCode;

    return (
        <>
            <Head title={`Tambah Stok · ${title}`} />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Tambah Stok Komponen</h1>
                        <p className="masterDataSubtitle">
                            {title} · isi ukuran fisik unit yang masuk gudang
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={show.url(item.id)}>Kembali</Link>
                    </Button>
                </div>

                <div className="masterDataFormCard">
                    <Form
                        action={store.url(item.id)}
                        method="post"
                        className="masterDataForm"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="return_to"
                                    value="show"
                                />
                                <ComponentStockFormFields
                                    idPrefix="component-stock-page"
                                    colorOptions={colorOptions}
                                    errors={errors}
                                    autoFocus
                                />

                                <div className="masterDataFormActions">
                                    <Button type="submit" disabled={processing}>
                                        Simpan
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link href={componentsIndex.url()}>
                                            Batal
                                        </Link>
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

ComponentStockCreate.layout = {
    activeMenu: 'Stok Komponen',
    pageTitle: 'Tambah Stok Komponen',
};
