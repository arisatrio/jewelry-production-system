import SpkIndex from '@/pages/spk/index';
import type { ComponentProps } from 'react';

type FakturBarangJadiIndexProps = ComponentProps<typeof SpkIndex>;

export default function FakturBarangJadiIndex(props: FakturBarangJadiIndexProps) {
    return <SpkIndex {...props} />;
}

FakturBarangJadiIndex.layout = {
    activeMenu: 'Faktur Barang Jadi',
    pageTitle: 'Faktur Barang Jadi',
};
