import type {
    SpkDetail,
    SpkDiamondDocumentRow,
    SpkDiamondSummary,
    SpkStoneItem,
} from '@/components/spk/types';
import { SpkStoneListCard } from '@/components/spk/spk-stone-list';

type SpkBatuPanelProps = {
    production: SpkDetail;
    stones: SpkStoneItem[];
    spkDiamond: SpkDiamondSummary | null;
    spkDiamondDocuments: SpkDiamondDocumentRow[];
};

export function SpkBatuPanel({
    production,
    stones,
    spkDiamond,
    spkDiamondDocuments,
}: SpkBatuPanelProps) {
    return (
        <div
            role="tabpanel"
            aria-label="Alokasi & SPK Batu"
            className="spkInformasiProduksiBody spkBatuPanel"
        >
            <SpkStoneListCard
                stones={stones}
                spk={{
                    rowId: Number(production.id),
                    produksiNo: production.produksiNo,
                }}
                spkDiamond={spkDiamond}
                spkDiamondDocuments={spkDiamondDocuments}
                showSpkBatuDocumentHeader
            />
        </div>
    );
}
