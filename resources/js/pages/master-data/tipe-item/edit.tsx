import { Form, Head, Link } from '@inertiajs/react';
import {
    index as tipeItemIndex,
    update,
} from '@/routes/master-data/tipe-item';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type TipeItemRow = {
    id: number;
    name: string | null;
};

type TipeItemEditProps = {
    item: TipeItemRow;
};

export default function TipeItemEdit({ item }: TipeItemEditProps) {
    return (
        <>
            <Head title={`Edit Tipe Item · ${item.name ?? item.id}`} />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Edit Tipe Item</h1>
                        <p className="masterDataSubtitle">
                            Perbarui nama tipe item pada master data msitem.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={tipeItemIndex.url()}>Kembali</Link>
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
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
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

TipeItemEdit.layout = {
    activeMenu: 'Tipe Item',
    pageTitle: 'Edit Tipe Item',
};
