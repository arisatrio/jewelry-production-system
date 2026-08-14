import { Form, Head, Link } from '@inertiajs/react';
import { store, index as tipeItemIndex } from '@/routes/master-data/tipe-item';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function TipeItemCreate() {
    return (
        <>
            <Head title="Tambah Tipe Item" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Tambah Tipe Item</h1>
                        <p className="masterDataSubtitle">
                            Tambahkan tipe item baru ke master data msitem.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={tipeItemIndex.url()}>Kembali</Link>
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
                                        placeholder="Contoh: Bangle"
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="masterDataFormActions">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
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

TipeItemCreate.layout = {
    activeMenu: 'Tipe Item',
    pageTitle: 'Tambah Tipe Item',
};
