import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as bahanEmasIndex,
    update,
} from '@/routes/inventory/gold-materials';

type BahanEmasRow = {
    id: number;
    name: string | null;
};

type BahanEmasEditProps = {
    item: BahanEmasRow;
};

export default function BahanEmasEdit({ item }: BahanEmasEditProps) {
    return (
        <>
            <Head title={`Edit Bahan Emas · ${item.name ?? item.id}`} />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Edit Bahan Emas</h1>
                        <p className="masterDataSubtitle">
                            Perbarui nama bahan emas pada master data
                            msmaterialgold.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={bahanEmasIndex.url()}>Kembali</Link>
                    </Button>
                </div>

                <div className="masterDataFormCard">
                    <Form {...update.form(item.id)} className="masterDataForm">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nama</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        maxLength={100}
                                        defaultValue={item.name ?? ''}
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

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

BahanEmasEdit.layout = {
    activeMenu: 'Bahan Emas',
    pageTitle: 'Edit Bahan Emas',
};
