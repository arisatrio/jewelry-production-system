import { Head } from '@inertiajs/react';
import {
    SpkDiamondDetail,
    type SpkDiamondDetailDocument,
} from '@/components/spk/spk-diamond-detail';

type SpkDiamondShowProps = {
    document: SpkDiamondDetailDocument;
};

export default function SpkDiamondShow({ document }: SpkDiamondShowProps) {
    return (
        <>
            <Head title={`Detail SPK Pasang Batu · ${document.docNo}`} />
            <SpkDiamondDetail document={document} />
        </>
    );
}

SpkDiamondShow.layout = {
    activeMenu: 'SPK Pasang Batu',
    pageTitle: 'Detail SPK Pasang Batu',
};
