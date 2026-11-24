import { Link } from '@inertiajs/react';
import { PasangBatuMountedStonesSection } from '@/components/pasang-batu/pasang-batu-detail-batu-section';
import type { SpkMountedStonesDetail } from '@/components/spk/types';

export function SpkMountedStonesPanel({
    mountedStonesDetail,
}: {
    mountedStonesDetail: SpkMountedStonesDetail;
}) {
    const { pasangBatu, mounted, mountedTotals } = mountedStonesDetail;

    const emptyMessage = pasangBatu
        ? 'Belum ada batu terpasang pada dokumen Pasang Batu terkait.'
        : 'Belum ada dokumen Pasang Batu untuk SPK ini.';

    return (
        <div
            role="tabpanel"
            aria-label="Batu terpasang"
            className="spkInformasiProduksiBody spkBatuPanel"
        >
            <div className="spkItemStoneSection spkItemStoneSection--allocationSpkTab">
                {pasangBatu ? (
                    <p className="spkMountedStonesPasangBatuRef">
                        Dokumen Pasang Batu:{' '}
                        <Link
                            href={pasangBatu.showUrl}
                            className="spkProduksiLink"
                        >
                            {pasangBatu.docNo}
                        </Link>
                        {' - '}
                        {pasangBatu.statusLabel}
                    </p>
                ) : null}

                <PasangBatuMountedStonesSection
                    mounted={mounted}
                    mountedTotals={mountedTotals}
                    emptyMessage={emptyMessage}
                />
            </div>
        </div>
    );
}