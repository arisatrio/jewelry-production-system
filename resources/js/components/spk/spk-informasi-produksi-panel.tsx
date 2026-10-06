import {
    SpkItemDetailCard,
    SpkStoneListCard,
} from '@/components/spk/spk-stone-list';
import type { ReactNode } from 'react';
import type {
    SpkDetail,
    SpkItemDetail,
    SpkStoneItem,
} from '@/components/spk/types';

export type SpkApprovalFooterColumn = {
    title: string;
    name: string;
    date: string;
};

type SpkInformasiProduksiPanelProps = {
    production: SpkDetail;
    item: SpkItemDetail;
    stones: SpkStoneItem[];
    approvalFooter?: SpkApprovalFooterColumn[];
};

function displayValue(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const text = String(value).trim();

    return text !== '' ? text : '-';
}

function spkCreatedDate(value: string | number | null | undefined): string {
    const text = displayValue(value);

    return text.split(' ')[0] ?? text;
}

function MetaRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <tr>
            <th scope="row">{label}</th>
            <td>{children}</td>
        </tr>
    );
}

function defaultApprovalFooter(
    production: SpkDetail,
): SpkApprovalFooterColumn[] {
    return [
        {
            title: 'Dibuat Oleh',
            name: displayValue(production.createdBy),
            date: displayValue(production.createdDate),
        },
        {
            title: 'Disetujui Oleh',
            name: '-',
            date: '-',
        },
        {
            title: 'Manager Produksi',
            name: '-',
            date: '-',
        },
    ];
}

export function SpkInformasiProduksiPanel({
    production,
    item,
    stones,
    approvalFooter,
}: SpkInformasiProduksiPanelProps) {
    const spkType = displayValue(production.tipeProduksi);
    const salesHeading = production.pesananHeading?.trim() ?? '';
    const tipeProduksiLabel =
        spkType === 'Pesanan' &&
        salesHeading !== '' &&
        salesHeading !== 'Pesanan'
            ? salesHeading
            : spkType;
    const requestStockNo = displayValue(production.requestStockNo);

    const pesananLabel = displayValue(
        production.requestOrderLabel ??
            (() => {
                const requestOrderNo = displayValue(production.requestOrderNo);
                const customerName = displayValue(production.customer);

                return requestOrderNo === '-' && customerName === '-'
                    ? '-'
                    : `${requestOrderNo} (${customerName})`;
            })(),
    );

    const refSpkNo = displayValue(production.refSpkNo);
    const notes = displayValue(production.notes);
    const footerColumns =
        approvalFooter && approvalFooter.length > 0
            ? approvalFooter
            : defaultApprovalFooter(production);

    return (
        <div
            role="tabpanel"
            aria-label="Informasi Produksi"
            className="spkInformasiProduksiBody"
        >
            <section className="spkShowSection">
                <h3 className="spkShowSectionTitle">Informasi Produksi</h3>

                <table className="spkShowMetaTable">
                    <tbody>
                        <MetaRow label="Tipe Produksi">
                            {tipeProduksiLabel}
                        </MetaRow>
                        {spkType === 'Pesanan' ? (
                            <MetaRow label="Nomor Pesanan">{pesananLabel}</MetaRow>
                        ) : null}
                        {spkType === 'Stock' ? (
                            <MetaRow label="Nomor Request Stok">
                                {requestStockNo}
                            </MetaRow>
                        ) : null}
                        {spkType === 'Pesanan' ? (
                            <MetaRow label="Tanggal Pesanan Dibuat">
                                {displayValue(production.requestOrderCreatedDate)}
                            </MetaRow>
                        ) : null}
                        {refSpkNo !== '-' ? (
                            <MetaRow label="SPK Referensi">{refSpkNo}</MetaRow>
                        ) : null}
                        {spkType === 'Pesanan' ? (
                            <MetaRow label="Tanggal SPK Dibuat">
                                {spkCreatedDate(production.createdDate)}
                            </MetaRow>
                        ) : (
                            <MetaRow label="Tanggal Permintaan">
                                {displayValue(production.orderDate)}
                            </MetaRow>
                        )}
                        {spkType === 'Stock' ? (
                            <MetaRow label="Tanggal SPK Dibuat">
                                {spkCreatedDate(production.createdDate)}
                            </MetaRow>
                        ) : null}
                        <MetaRow label="Tanggal Diterima Produksi">
                            {displayValue(production.receivedByProductionDate)}
                        </MetaRow>
                        <MetaRow label="Tanggal Target Selesai">
                            {displayValue(production.estimatedDelivery)}
                            {spkType === 'Stock' ? null : (
                                <em className="spkShowTargetSales">
                                    *Target Sales
                                </em>
                            )}
                        </MetaRow>
                    </tbody>
                </table>
            </section>

            <section className="spkShowSection">
                <h3 className="spkShowSectionTitle">Detail Item</h3>
                <SpkItemDetailCard item={item} notes={notes} />
            </section>

            <section className="spkShowSection">
                <SpkStoneListCard stones={stones} />
            </section>

            <div className="spkShowBottom">
                <footer
                    className="spkApprovalFooter"
                    aria-label="Persetujuan"
                >
                    {footerColumns.map((column) => (
                        <div
                            key={column.title}
                            className="spkApprovalFooterCol"
                        >
                            <div className="spkApprovalFooterTitle">
                                {column.title}
                            </div>
                            <div className="spkApprovalFooterMeta">
                                <div className="spkApprovalFooterMetaRow">
                                    <span className="spkApprovalFooterMetaLabel">
                                        Nama
                                    </span>
                                    <span className="spkApprovalFooterMetaValue">
                                        {displayValue(column.name)}
                                    </span>
                                </div>
                                <div className="spkApprovalFooterMetaRow">
                                    <span className="spkApprovalFooterMetaLabel">
                                        Tanggal
                                    </span>
                                    <span className="spkApprovalFooterMetaValue">
                                        {displayValue(column.date)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ))}
                </footer>
            </div>
        </div>
    );
}
