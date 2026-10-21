import { Form, Head, Link } from '@inertiajs/react';
import {
    TrKomponenFormFields,
    type TrKomponenFormValues,
} from '@/components/inventory/tr-komponen-form-fields';
import { Button } from '@/components/ui/button';
import {
    index as transaksiKomponenIndex,
    store,
} from '@/routes/inventory/component-transactions';

const emptyValues: TrKomponenFormValues = {
    entry_date: null,
    out_date: null,
    jenis_komponen: null,
    name: null,
    item_code: null,
    supplier: null,
    length: null,
    color: null,
    weight: null,
    spk_id: null,
    is_used: null,
    is_reserved: null,
};

export default function TransaksiKomponenCreate() {
    return (
        <>
            <Head title="Tambah Transaksi Komponen" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">
                            Tambah Transaksi Komponen
                        </h1>
                        <p className="masterDataSubtitle">
                            Catat komponen inventory pada tabel trkomponen.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={transaksiKomponenIndex.url()}>Kembali</Link>
                    </Button>
                </div>

                <div className="masterDataFormCard is-wide">
                    <Form {...store.form()} className="masterDataForm">
                        {({ processing, errors }) => (
                            <>
                                <TrKomponenFormFields
                                    values={emptyValues}
                                    errors={errors}
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

TransaksiKomponenCreate.layout = {
    activeMenu: 'Transaksi Komponen',
    pageTitle: 'Tambah Transaksi Komponen',
};
