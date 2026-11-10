import { Link } from '@inertiajs/react';
import { Text } from '@ui5/webcomponents-react/Text';
import {
    SpkDiamondDetailCertificateLink,
    SpkDiamondDetailStoneLabelLink,
    type SpkDiamondDetailStoneRow,
} from '@/components/spk/spk-stone-allocated-display-cells';
import { formatDecimal3Id } from '@/components/spk/spk-stone-decimal-format';
import {
    displayCrt,
    displayCell,
} from '@/components/pasang-batu/pasang-batu-stone-tables';

export type PasangBatuSpkRequirementRow = {
    positionName: string;
    shapeName: string;
    size: string;
    caratPerPcs: string;
    pcs: number;
    totalCarat: string;
};

export type PasangBatuSpkDiamondItem = SpkDiamondDetailStoneRow & {
    positionName: string;
};

export type PasangBatuSpkDiamondPanel = {
    id: number;
    docNo: string;
    showUrl: string;
    items: PasangBatuSpkDiamondItem[];
    totals: {
        pcs: number;
        carat: number;
    };
};

export type PasangBatuMountedDetailRow = {
    kode: string;
    shape: string;
    pcs: number | string | null;
    crt: string | null;
    size: string;
};

export type PasangBatuBatuDetail = {
    spkRequirements: PasangBatuSpkRequirementRow[];
    spkRequirementsTotals: {
        pcs: number;
        carat: number;
    };
    spkDiamond: PasangBatuSpkDiamondPanel | null;
    mounted: PasangBatuMountedDetailRow[];
    mountedTotals: {
        pcs: number;
        carat: number;
    };
};

function formatTotalCarat(value: number): string {
    return value.toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

function emptyState(message: string) {
    return (
        <div className="spkStoneEmpty">
            <Text>{message}</Text>
        </div>
    );
}

export function PasangBatuDetailBatuSection({
    batuDetail,
}: {
    batuDetail: PasangBatuBatuDetail;
}) {
    const {
        spkRequirements,
        spkRequirementsTotals,
        spkDiamond,
        mounted,
        mountedTotals,
    } = batuDetail;

    return (
        <div className="spkPasangBatuDetailBatuStack">
            <section className="spkShowSection spkBatuPanelSection">
                <div className="spkBatuPanelSectionHeader">
                    <h3 className="spkFioriDetailBlockTitle">
                        Kebutuhan batu SPK
                    </h3>
                    <Text className="spkFioriDetailBlockMeta">
                        {spkRequirements.length.toLocaleString('id-ID')} baris
                    </Text>
                </div>
                <div className="spkStoneTableWrap">
                    <table className="spkStoneTable spkStoneTable--withStatus">
                        <thead>
                            <tr>
                                <th>Posisi</th>
                                <th>Bentuk</th>
                                <th>Diameter / Dimensi PxL (mm)</th>
                                <th>CRT per Butir</th>
                                <th>Jumlah Butir (pcs)</th>
                                <th>Total CRT</th>
                            </tr>
                        </thead>
                        <tbody>
                            {spkRequirements.length === 0 ? (
                                <tr>
                                    <td colSpan={6}>{emptyState('—')}</td>
                                </tr>
                            ) : (
                                spkRequirements.map((row, index) => (
                                    <tr
                                        key={`spk-req-${index}-${row.positionName}-${row.shapeName}`}
                                    >
                                        <td>{displayCell(row.positionName)}</td>
                                        <td>{displayCell(row.shapeName)}</td>
                                        <td>{displayCell(row.size)}</td>
                                        <td className="tabular-nums">
                                            {formatDecimal3Id(row.caratPerPcs)}
                                        </td>
                                        <td className="tabular-nums">
                                            {displayCell(row.pcs)}
                                        </td>
                                        <td className="tabular-nums">
                                            {formatDecimal3Id(row.totalCarat)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                        {spkRequirements.length > 0 ? (
                            <tfoot>
                                <tr className="spkStoneTableTotal spkStoneTableTotal--allocated">
                                    <td colSpan={4}>Total kebutuhan</td>
                                    <td className="spkStoneTableTotalValue">
                                        {spkRequirementsTotals.pcs.toLocaleString(
                                            'id-ID',
                                        )}
                                    </td>
                                    <td className="spkStoneTableTotalValue">
                                        {formatTotalCarat(
                                            spkRequirementsTotals.carat,
                                        )}
                                    </td>
                                </tr>
                            </tfoot>
                        ) : null}
                    </table>
                </div>
            </section>

            <section className="spkShowSection spkBatuPanelSection">
                <div className="spkBatuPanelSectionHeader">
                    <h3 className="spkFioriDetailBlockTitle">SPK Diamond</h3>
                    <Text className="spkFioriDetailBlockMeta">
                        {spkDiamond === null
                            ? 'Belum terhubung'
                            : spkDiamond.docNo}
                    </Text>
                </div>
                {spkDiamond === null ? (
                    emptyState(
                        'Dokumen SPK Diamond belum terhubung ke Pasang Batu ini.',
                    )
                ) : (
                    <>
                        <p className="spkPasangBatuDetailBatuDocLink">
                            <Link
                                href={spkDiamond.showUrl}
                                className="spkProduksiLink"
                            >
                                {spkDiamond.docNo}
                            </Link>
                        </p>
                        <div className="spkStoneTableWrap">
                            <table className="spkStoneTable spkStoneAllocatedFlatTable">
                                <thead>
                                    <tr>
                                        <th>Tipe</th>
                                        <th>No. sertifikat</th>
                                        <th>Bentuk</th>
                                        <th>Nama/Kode batu</th>
                                        <th>CRT per butir</th>
                                        <th>Jumlah butir (pcs)</th>
                                        <th>Total CRT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {spkDiamond.items.length === 0 ? (
                                        <tr>
                                            <td colSpan={7}>
                                                {emptyState(
                                                    'Tidak ada baris batu pada dokumen SPK Diamond.',
                                                )}
                                            </td>
                                        </tr>
                                    ) : (
                                        spkDiamond.items.map((item, index) => (
                                            <tr
                                                key={`spk-diamond-${item.sourceLabel}-${index}`}
                                            >
                                                <td>{item.sourceLabel}</td>
                                                <td>
                                                    <SpkDiamondDetailCertificateLink
                                                        certificate={
                                                            item.certificate
                                                        }
                                                    />
                                                </td>
                                                <td>{displayCell(item.shape)}</td>
                                                <td>
                                                    <SpkDiamondDetailStoneLabelLink
                                                        row={item}
                                                    />
                                                </td>
                                                <td className="tabular-nums">
                                                    {item.crtPerPcs !== null
                                                        ? formatDecimal3Id(
                                                              item.crtPerPcs,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="tabular-nums">
                                                    {displayCell(
                                                        item.reservedPcs,
                                                    )}
                                                </td>
                                                <td className="tabular-nums">
                                                    {formatDecimal3Id(
                                                        item.reservedCrt,
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                                {spkDiamond.items.length > 0 ? (
                                    <tfoot>
                                        <tr className="spkStoneTableTotal spkStoneTableTotal--allocated">
                                            <td colSpan={5}>
                                                Total batu dialokasikan
                                            </td>
                                            <td className="spkStoneTableTotalValue">
                                                {spkDiamond.totals.pcs.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </td>
                                            <td className="spkStoneTableTotalValue">
                                                {formatTotalCarat(
                                                    spkDiamond.totals.carat,
                                                )}
                                            </td>
                                        </tr>
                                    </tfoot>
                                ) : null}
                            </table>
                        </div>
                    </>
                )}
            </section>

            <section className="spkShowSection spkBatuPanelSection">
                <div className="spkBatuPanelSectionHeader">
                    <h3 className="spkFioriDetailBlockTitle">Batu terpasang</h3>
                    <Text className="spkFioriDetailBlockMeta">
                        {mounted.length.toLocaleString('id-ID')} baris
                    </Text>
                </div>
                <div className="spkStoneTableWrap">
                    <table className="spkStoneTable spkStoneAllocatedFlatTable">
                        <thead>
                            <tr>
                                <th>Kode / Nama batu</th>
                                <th>Bentuk</th>
                                <th>Jumlah butir (pcs)</th>
                                <th>Total CRT</th>
                                <th>Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            {mounted.length === 0 ? (
                                <tr>
                                    <td colSpan={5}>
                                        {emptyState(
                                            'Belum ada batu terpasang pada dokumen ini.',
                                        )}
                                    </td>
                                </tr>
                            ) : (
                                mounted.map((row, index) => (
                                    <tr key={`mounted-${row.kode}-${index}`}>
                                        <td>{displayCell(row.kode)}</td>
                                        <td>{displayCell(row.shape)}</td>
                                        <td className="tabular-nums">
                                            {displayCell(row.pcs)}
                                        </td>
                                        <td className="tabular-nums">
                                            {displayCrt(row.crt)}
                                        </td>
                                        <td className="tabular-nums">
                                            {displayCell(row.size)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                        {mounted.length > 0 ? (
                            <tfoot>
                                <tr className="spkStoneTableTotal spkStoneTableTotal--allocated">
                                    <td colSpan={2}>Total terpasang</td>
                                    <td className="spkStoneTableTotalValue">
                                        {mountedTotals.pcs.toLocaleString(
                                            'id-ID',
                                        )}
                                    </td>
                                    <td className="spkStoneTableTotalValue">
                                        {formatTotalCarat(mountedTotals.carat)}
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        ) : null}
                    </table>
                </div>
            </section>
        </div>
    );
}
