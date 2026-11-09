import { router } from '@inertiajs/react';
import navBackIcon from '@ui5/webcomponents-icons/dist/nav-back.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Text } from '@ui5/webcomponents-react/Text';
import { toast } from 'sonner';
import { SpkItemNoLink } from '@/components/spk/spk-item-no-link';
import { SpkDiamondStatusBadge } from '@/components/spk/spk-diamond-status-badge';
import { displayListDate } from '@/components/spk/spk-list-cells';
import {
    SpkDiamondDetailCertificateLink,
    SpkDiamondDetailStoneLabelLink,
    type SpkDiamondDetailStoneRow,
} from '@/components/spk/spk-stone-allocated-display-cells';
import { formatDecimal3Id } from '@/components/spk/spk-stone-decimal-format';
import { index as spkDiamondIndex } from '@/routes/spk-diamond';

export type SpkDiamondDetailItem = SpkDiamondDetailStoneRow & {
    positionName: string;
};

export type SpkDiamondDetailDocument = {
    id: number;
    docNo: string;
    tanggal: string;
    spkRowId: number;
    spkNo: string | null;
    itemName: string | null;
    lineCount: number;
    createdBy: string | null;
    createdDate: string | null;
    status: string;
    statusLabel: string;
    items: SpkDiamondDetailItem[];
    totals: {
        pcs: number;
        carat: number;
    };
};

function emptyDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const text = String(value).trim();

    return text !== '' ? text : '—';
}

function formatTotalCarat(value: number): string {
    return value.toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

type SpkDiamondDetailProps = {
    document: SpkDiamondDetailDocument;
};

export function SpkDiamondDetail({ document }: SpkDiamondDetailProps) {
    const openPrint = (): void => {
        toast.info(
            `Cetak SPK Pasang Batu ${document.docNo} akan menyusul.`,
        );
    };

    return (
        <div className="spkTableShell">
            <div className="spkTableCard">
                <div className="spkTableToolbar spkTableHeaderBar--finishing">
                    <div className="spkTableToolbarLeft">
                        <button
                            type="button"
                            className="spkCreateBtn spkCreateBtn--outline"
                            aria-label="Kembali ke daftar SPK Pasang Batu"
                            onClick={() =>
                                router.visit(spkDiamondIndex.url())
                            }
                        >
                            <Icon name={navBackIcon} mode="Decorative" />
                            <span>Kembali</span>
                        </button>
                        <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                            <h2 className="spkTableHeaderTitle--finishing">
                                Detail SPK Pasang Batu
                            </h2>
                            <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                {document.docNo}
                            </p>
                        </div>
                    </div>
                    <div className="spkTableToolbarRight">
                        <button
                            type="button"
                            className="spkChainPrintReceiptBtn"
                            aria-label="Print SPK Pasang Batu"
                            title="Print SPK Pasang Batu"
                            onClick={openPrint}
                        >
                            <Icon name={printIcon} mode="Decorative" />
                            <span className="spkChainPrintReceiptBtnLabel">
                                Print SPK Pasang Batu
                            </span>
                        </button>
                    </div>
                </div>

                <section className="spkShowSection spkBatuPanelSection">
                    <div className="spkFioriDetailBlock spkSpkBatuDocHeader">
                        <div className="spkSpkBatuDocHeaderMain">
                            <Text className="spkSpkBatuDocHeaderLabel">
                                No. SPK Pasang Batu
                            </Text>
                            <strong className="spkSpkBatuDocHeaderValue tabular-nums">
                                {document.docNo}
                            </strong>
                        </div>
                    </div>
                    <div className="spkItemMetaTableWrap">
                        <table className="spkItemMetaTable">
                            <tbody>
                                <tr>
                                    <th>Tanggal</th>
                                    <td>{displayListDate(document.tanggal)}</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <SpkDiamondStatusBadge
                                            status={document.status}
                                            label={document.statusLabel}
                                        />
                                    </td>
                                </tr>
                                <tr>
                                    <th>No. SPK</th>
                                    <td>
                                        <SpkItemNoLink spkNo={document.spkNo} />
                                    </td>
                                </tr>
                                <tr>
                                    <th>Item</th>
                                    <td>{emptyDash(document.itemName)}</td>
                                </tr>
                                <tr>
                                    <th>Jumlah baris batu</th>
                                    <td className="tabular-nums">
                                        {document.lineCount.toLocaleString(
                                            'id-ID',
                                        )}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Dibuat oleh</th>
                                    <td>{emptyDash(document.createdBy)}</td>
                                </tr>
                                <tr>
                                    <th>Dibuat tanggal</th>
                                    <td>
                                        {document.createdDate
                                            ? displayListDate(
                                                  document.createdDate.slice(
                                                      0,
                                                      10,
                                                  ),
                                              )
                                            : '—'}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="spkShowSection spkBatuPanelSection">
                    <div className="spkBatuPanelSectionHeader">
                        <h3 className="spkFioriDetailBlockTitle">
                            Batu dialokasikan
                        </h3>
                        <Text className="spkFioriDetailBlockMeta">
                            {document.items.length} baris
                        </Text>
                    </div>
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
                                {document.items.length === 0 ? (
                                    <tr>
                                        <td colSpan={7}>
                                            <div className="spkStoneEmpty">
                                                <Text>
                                                    Tidak ada baris batu pada
                                                    dokumen ini.
                                                </Text>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    document.items.map((item, index) => (
                                        <tr
                                            key={`${item.sourceLabel}-${item.label}-${index}`}
                                        >
                                            <td>{item.sourceLabel}</td>
                                            <td>
                                                <SpkDiamondDetailCertificateLink
                                                    certificate={
                                                        item.certificate
                                                    }
                                                />
                                            </td>
                                            <td>{emptyDash(item.shape)}</td>
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
                                                    : '-'}
                                            </td>
                                            <td className="tabular-nums">
                                                {emptyDash(item.reservedPcs)}
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
                            {document.items.length > 0 ? (
                                <tfoot>
                                    <tr className="spkStoneTableTotal spkStoneTableTotal--allocated">
                                        <td colSpan={5}>
                                            Total batu dialokasikan
                                        </td>
                                        <td className="spkStoneTableTotalValue">
                                            {document.totals.pcs.toLocaleString(
                                                'id-ID',
                                            )}
                                        </td>
                                        <td className="spkStoneTableTotalValue">
                                            {formatTotalCarat(
                                                document.totals.carat,
                                            )}
                                        </td>
                                    </tr>
                                </tfoot>
                            ) : null}
                        </table>
                    </div>
                </section>
            </div>
        </div>
    );
}
