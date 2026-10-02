import SpkIndex from '@/pages/spk/index';
import type { ComponentProps } from 'react';

type ReparasiIndexProps = ComponentProps<typeof SpkIndex>;

export default function ReparasiIndex(props: ReparasiIndexProps) {
    return <SpkIndex {...props} />;
}

ReparasiIndex.layout = {
    activeMenu: 'Reparasi',
    pageTitle: 'Reparasi',
};
