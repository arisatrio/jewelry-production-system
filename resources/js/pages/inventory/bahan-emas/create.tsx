import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    store,
    index as bahanEmasIndex,
} from '@/routes/inventory/gold-materials';

export default function BahanEmasCreate() {
    return (
        <>
            <Head title="Tambah Bahan Emas" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Tambah Bahan Emas</h1>
                        <p className="masterDataSubtitle">
                            Tambahkan bahan emas baru ke master data
                            msmaterialgold.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={bahanEmasIndex.url()}>Kembali</Link>
                    </Button>
                </div>

                <div className="masterDataFormCard">
                    <Form {...store.form()} className="masterDataForm">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nama</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        maxLength={100}
                                        placeholder="Contoh: Patri Loket"
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

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

BahanEmasCreate.layout = {
    activeMenu: 'Bahan Emas',
    pageTitle: 'Tambah Bahan Emas',
};
