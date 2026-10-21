import { Form, Head, Link } from '@inertiajs/react';
import {
    TrKomponenFormFields,
    type TrKomponenFormValues,
} from '@/components/inventory/tr-komponen-form-fields';
import { Button } from '@/components/ui/button';
import {
    index as transaksiKomponenIndex,
    update,
} from '@/routes/inventory/component-transactions';

type TransaksiKomponenEditProps = {
    item: TrKomponenFormValues & { id: number };
};

export default function TransaksiKomponenEdit({
    item,
}: TransaksiKomponenEditProps) {
    const { id, ...values } = item;

    return (
        <>
            <Head title={`Edit Transaksi Komponen · ${id}`} />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">
                            Edit Transaksi Komponen
                        </h1>
                        <p className="masterDataSubtitle">
                            Perbarui data komponen pada tabel trkomponen.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={transaksiKomponenIndex.url()}>Kembali</Link>
                    </Button>
                </div>

                <div className="masterDataFormCard is-wide">
                    <Form {...update.form(id)} className="masterDataForm">
                        {({ processing, errors }) => (
                            <>
                                <TrKomponenFormFields
                                    values={values}
                                    errors={errors}
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

TransaksiKomponenEdit.layout = {
    activeMenu: 'Transaksi Komponen',
    pageTitle: 'Edit Transaksi Komponen',
};
