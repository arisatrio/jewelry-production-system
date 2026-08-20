import {
    SpkItemDetailCard,
    SpkStoneListCard,
} from '@/components/spk/spk-stone-list';
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

function MetaRow({ label, children }: { label: string; children: string }) {
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

    const requestOrderNo = displayValue(production.requestOrderNo);
    const customerName = displayValue(production.customer);
    const pesananLabel =
        requestOrderNo === '-' && customerName === '-'
            ? '-'
            : `${requestOrderNo} (${customerName})`;

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
                        <MetaRow label="Tipe Produksi">{spkType}</MetaRow>
                        <MetaRow label="Pesanan">{pesananLabel}</MetaRow>
                        {refSpkNo !== '-' ? (
                            <MetaRow label="SPK Referensi">{refSpkNo}</MetaRow>
                        ) : null}
                        <MetaRow label="Tanggal Permintaan">
                            {displayValue(production.orderDate)}
                        </MetaRow>
                        <MetaRow label="Tanggal Estimasi Selesai">
                            {displayValue(production.estimatedDelivery)}
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
