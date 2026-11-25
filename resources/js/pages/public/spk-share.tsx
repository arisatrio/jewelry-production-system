import { Head } from '@inertiajs/react';
import { SpkInformasiProduksiAsideSection } from '@/components/spk/spk-informasi-produksi-panel';
import type { SpkApprovalFooterColumn } from '@/components/spk/spk-informasi-produksi-panel';
import type { SpkDetail, SpkItemDetail } from '@/components/spk/types';

type SpkPublicShareActor = {
    key: string;
    name: string;
};

type SpkPublicShareProps = {
    production: SpkDetail;
    item: SpkItemDetail;
    approvalFooter: SpkApprovalFooterColumn[];
    sharedBy: SpkPublicShareActor | null;
};

function lastUpdatedLabel(modifiedDate: string | undefined): string | null {
    const trimmed = modifiedDate?.trim() ?? '';

    if (trimmed === '' || trimmed === '-') {
        return null;
    }

    return trimmed;
}

export default function SpkPublicShare({
    production,
    item,
    approvalFooter,
    sharedBy,
}: SpkPublicShareProps) {
    const lastUpdated = lastUpdatedLabel(production.modifiedDate);

    return (
        <>
            <Head title={`SPK ${production.produksiNo}`} />
            {sharedBy || lastUpdated ? (
                <div
                    className="spkPublicShareMeta"
                    role="note"
                    aria-label="Informasi tautan"
                >
                    {sharedBy ? (
                        <p className="spkPublicShareByline">
                            Dibagikan oleh{' '}
                            <strong>{sharedBy.name}</strong>
                            {sharedBy.key !== '-' ? (
                                <>
                                    {' '}
                                    <span className="spkPublicShareBylineKey">
                                        ({sharedBy.key})
                                    </span>
                                </>
                            ) : null}
                        </p>
                    ) : null}
                    {lastUpdated ? (
                        <p className="spkPublicShareUpdated">
                            Terakhir diupdate{' '}
                            <time dateTime={lastUpdated}>{lastUpdated}</time>
                        </p>
                    ) : null}
                </div>
            ) : null}
            <div className="spkPublicShareAside">
                <SpkInformasiProduksiAsideSection
                    production={production}
                    item={item}
                    approvalFooter={approvalFooter}
                />
            </div>
        </>
    );
}

SpkPublicShare.layout = {
    pageTitle: 'Informasi Produksi SPK',
};