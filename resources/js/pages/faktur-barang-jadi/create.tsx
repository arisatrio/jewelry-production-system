import { Head } from '@inertiajs/react';
import {
    FakturBarangJadiCreateForm,
    type FakturBarangJadiCreateFormProps,
} from '@/components/spk/faktur-barang-jadi-create-form';

export default function FakturBarangJadiCreate(
    props: FakturBarangJadiCreateFormProps,
) {
    return (
        <>
            <Head title="Buat Faktur Barang Jadi" />
            <FakturBarangJadiCreateForm {...props} />
        </>
    );
}

FakturBarangJadiCreate.layout = {
    activeMenu: 'Faktur Barang Jadi',
    pageTitle: 'Buat Faktur Barang Jadi',
};