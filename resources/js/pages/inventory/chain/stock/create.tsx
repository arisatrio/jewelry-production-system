import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ChainStockFormFields } from '@/pages/inventory/chain/chain-stock-form-fields';
import { index as chainsIndex, show } from '@/routes/inventory/chains';
import { store } from '@/routes/inventory/chains/stock';

type ChainItem = {
    id: number;
    itemName: string | null;
    itemCode: string;
    length: string;
};

type ChainStockCreateProps = {
    item: ChainItem;
    colorOptions: string[];
};

export default function ChainStockCreate({
    item,
    colorOptions,
}: ChainStockCreateProps) {
    const title = item.itemName ?? item.itemCode;

    return (
        <>
            <Head title={`Tambah Stok · ${title}`} />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Tambah Stok Chain</h1>
                        <p className="masterDataSubtitle">
                            {title} · panjang master {item.length} cm
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
                                <ChainStockFormFields
                                    idPrefix="chain-stock-page"
                                    colorOptions={colorOptions}
                                    errors={errors}
                                    autoFocus
                                />

                                <div className="masterDataFormActions">
                                    <Button type="submit" disabled={processing}>
                                        Simpan
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link href={chainsIndex.url()}>
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

ChainStockCreate.layout = {
    activeMenu: 'Stok Rantai',
    pageTitle: 'Tambah Stok Chain',
};
