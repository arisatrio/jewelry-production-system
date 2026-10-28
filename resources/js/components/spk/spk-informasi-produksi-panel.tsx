import { SpkProsesTerakhirDisplay } from '@/components/spk/spk-proses-terakhir-display';
import { SpkChainListCard } from '@/components/spk/spk-chain-list';
import { SpkComponentListCard } from '@/components/spk/spk-component-list';
import {
    formatDecimal2Id,
    SpkItemDetailCard,
    SpkItemImageColumn,
    SpkStoneListCard,
} from '@/components/spk/spk-stone-list';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import type {
    SpkDetail,
    SpkItemDetail,
    SpkChainItem,
    SpkComponentItem,
    SpkChainReceiptSummary,
    SpkComponentReceiptSummary,
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
    chains?: SpkChainItem[];
    components?: SpkComponentItem[];
    receiptEmployeeOptions?: string[];
    chainReceipt?: SpkChainReceiptSummary | null;
    componentReceipt?: SpkComponentReceiptSummary | null;
};

type SpkApprovalFooterSectionProps = {
    production: SpkDetail;
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

function asideItemTypeDisplay(item: SpkItemDetail): string {
    const typeCode = displayValue(item.typeCode);
    const productItemName = displayValue(item.productItemName);
    const parts = [typeCode, productItemName].filter((part) => part !== '-');

    if (parts.length > 0) {
        return parts.join(' | ');
    }

    return displayValue(item.name);
}

function asideItemSkuDisplay(item: SpkItemDetail): string {
    return displayValue(item.skuCode);
}

function formatLastWeight(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const withoutUnit = String(value).replace(/\s*g\s*$/i, '').trim();
    const formatted = formatDecimal2Id(withoutUnit);

    if (formatted === '-') {
        return '-';
    }

    return `${formatted} g`;
}

function AsideField({
    label,
    children,
}: {
    label: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="spkDetailField">
            <span className="spkDetailFieldLabel">{label}</span>
            <div className="spkDetailValue">{children}</div>
        </div>
    );
}

function informasiProduksiMeta(production: SpkDetail) {
    const spkType = displayValue(production.tipeProduksi);
    const salesHeading = production.pesananHeading?.trim() ?? '';
    const requestStockNo = displayValue(production.requestStockNo);
    const requestStockDrkNo = displayValue(production.requestStockDrkNo);
    const isStockPermintaanToko =
        spkType === 'Stock' && requestStockNo !== '-';
    const isStockPermintaanDireksi =
        spkType === 'Stock' && !isStockPermintaanToko;
    const tipeProduksiLabel =
        spkType === 'Pesanan' &&
        salesHeading !== '' &&
        salesHeading !== 'Pesanan'
            ? salesHeading
            : isStockPermintaanToko
              ? 'Stock (Permintaan Toko)'
              : isStockPermintaanDireksi
                ? 'Stock (Permintaan Direksi)'
                : spkType;
    const requestStockDisplay = isStockPermintaanToko
        ? requestStockNo
        : requestStockDrkNo !== '-'
          ? requestStockDrkNo
          : '-';
    const refSpkNo = displayValue(production.refSpkNo);

    return {
        spkType,
        tipeProduksiLabel,
        requestStockDisplay,
        refSpkNo,
    };
}

function PesananRefDokumenDisplay({ production }: { production: SpkDetail }) {
    const orderNo = displayValue(production.requestOrderNo);
    const customerName = displayValue(production.customer);
    const paymentStatus = production.paymentStatus?.trim() ?? '';

    if (orderNo === '-' && customerName === '-') {
        return <>-</>;
    }

    const statusSuffix =
        paymentStatus !== '' ? `(${paymentStatus})` : null;

    return (
        <p className="spkRefDokumenPesanan">
            {orderNo !== '-' ? (
                <span className="spkRefDokumenPesananPart">{orderNo}</span>
            ) : null}
            {statusSuffix ? (
                <span className="spkRefDokumenPesananPart spkRefDokumenPesananStatus">
                    {statusSuffix}
                </span>
            ) : null}
            {customerName !== '-' ? (
                <span
                    className="spkRefDokumenPesananPart spkRefDokumenPesananCustomer"
                >
                    {customerName}
                </span>
            ) : null}
        </p>
    );
}

function approvalFooterStepComplete(
    column: SpkApprovalFooterColumn,
): boolean {
    const name = displayValue(column.name);
    const date = displayValue(column.date);

    return name !== '-' && date !== '-';
}

function SpkApprovalFooterSection({
    production,
    approvalFooter,
}: SpkApprovalFooterSectionProps) {
    const footerColumns =
        approvalFooter && approvalFooter.length > 0
            ? approvalFooter
            : defaultApprovalFooter(production);

    return (
        <footer
            className="spkApprovalFooter spkApprovalFooter--aside"
            aria-label="Persetujuan"
        >
            <ol className="spkPageTimeline spkApprovalFooterAsideTimeline">
                {footerColumns.map((column, index) => {
                    const isComplete = approvalFooterStepComplete(column);
                    const isLast = index === footerColumns.length - 1;

                    return (
                        <li
                            key={column.title}
                            className={[
                                'spkPageTimelineItem',
                                isComplete ? 'is-ok' : '',
                                isLast ? 'is-last' : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                        >
                            <div
                                className="spkPageTimelineMarker"
                                aria-hidden="true"
                            >
                                <span className="spkPageTimelineDot">
                                    {isComplete ? (
                                        <Check
                                            className="spkPageTimelineIcon"
                                            strokeWidth={3}
                                        />
                                    ) : null}
                                </span>
                            </div>
                            <div className="spkPageTimelineContent spkApprovalFooterCol">
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
                        </li>
                    );
                })}
            </ol>
        </footer>
    );
}

export function SpkInformasiProduksiAsideSection({
    production,
    item,
    approvalFooter,
    showProsesTerakhir = true,
}: {
    production: SpkDetail;
    item: SpkItemDetail;
    approvalFooter?: SpkApprovalFooterColumn[];
    showProsesTerakhir?: boolean;
}) {
    const { spkType, tipeProduksiLabel, requestStockDisplay, refSpkNo } =
        informasiProduksiMeta(production);
    const itemTypeLine = asideItemTypeDisplay(item);
    const itemSku = asideItemSkuDisplay(item);

    return (
        <section
            className="spkDetailCard spkDetailPageAsideCard"
            aria-label="Gambar item dan informasi produksi"
        >
            <div className="spkDetailPageAsideImage">
                <div className="spkDetailPageAsideSpkHeader">
                    <h3 className="spkShowSectionTitle spkShowSectionTitle--spkNo">
                        <span className="spkDetailPageAsideSpkLabel">No. SPK</span>
                        {displayValue(production.produksiNo)}
                    </h3>
                </div>
                <SpkItemImageColumn
                    item={item}
                    enablePreview
                    spkNo={
                        production.produksiNo.trim() !== ''
                            ? production.produksiNo
                            : null
                    }
                />
            </div>
            <div className="spkDetailPageAsideItemMeta">
                <p className="spkDetailPageAsideItemType">{itemTypeLine}</p>
                {itemSku !== '-' ? (
                    <p className="spkDetailPageAsideItemSku">{itemSku}</p>
                ) : null}
            </div>
            <div className="spkInformasiProduksiAsideFields">
                <AsideField label="Tipe Produksi">
                    {tipeProduksiLabel}
                </AsideField>
                {spkType === 'Pesanan' ? (
                    <AsideField label="Nomor Ref Dokumen">
                        <PesananRefDokumenDisplay production={production} />
                    </AsideField>
                ) : null}
                {spkType === 'Stock' ? (
                    <AsideField label="Nomor Ref Dokumen">
                        {requestStockDisplay}
                    </AsideField>
                ) : null}
                {spkType === 'Pesanan' ? (
                    <AsideField label="Tanggal Pesanan Dibuat">
                        {displayValue(production.requestOrderCreatedDate)}
                    </AsideField>
                ) : null}
                {refSpkNo !== '-' ? (
                    <AsideField label="SPK Referensi">{refSpkNo}</AsideField>
                ) : null}
                {spkType === 'Pesanan' ? (
                    <AsideField label="Tanggal SPK Dibuat">
                        {spkCreatedDate(production.createdDate)}
                    </AsideField>
                ) : (
                    <AsideField label="Tanggal Permintaan">
                        {displayValue(production.orderDate)}
                    </AsideField>
                )}
                {spkType === 'Stock' ? (
                    <AsideField label="Tanggal SPK Dibuat">
                        {spkCreatedDate(production.createdDate)}
                    </AsideField>
                ) : null}
                <AsideField label="Tanggal Diterima Produksi">
                    {displayValue(production.receivedByProductionDate)}
                </AsideField>
                <AsideField
                    label={
                        <>
                            Tanggal Target Selesai
                            {spkType === 'Stock' ? null : (
                                <em
                                    className="spkShowTargetSales spkShowTargetSales--label"
                                >
                                    *Target Sales
                                </em>
                            )}
                        </>
                    }
                >
                    {displayValue(production.estimatedDelivery)}
                </AsideField>
                {showProsesTerakhir ? (
                    <AsideField label="Proses Terakhir">
                        <SpkProsesTerakhirDisplay row={production} />
                    </AsideField>
                ) : null}
                <AsideField label="Berat Terakhir">
                    {formatLastWeight(production.lastWeight)}
                </AsideField>
            </div>

            <SpkApprovalFooterSection
                production={production}
                approvalFooter={approvalFooter}
            />
        </section>
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
    chains = [],
    components = [],
    receiptEmployeeOptions = [],
    chainReceipt = null,
    componentReceipt = null,
}: SpkInformasiProduksiPanelProps) {
    const notes = displayValue(production.notes);

    return (
        <div
            role="tabpanel"
            aria-label="Detail Item"
            className="spkInformasiProduksiBody"
        >
            <section className="spkShowSection spkShowSection--detailItemOnly">
                <SpkItemDetailCard
                    item={item}
                    notes={notes}
                    showImage={false}
                    variant="panel"
                />
            </section>

            <section className="spkShowSection">
                <SpkStoneListCard
                    stones={stones}
                    spk={{
                        rowId: Number(production.id),
                        produksiNo: production.produksiNo,
                    }}
                />
            </section>

            <section className="spkShowSection">
                <div
                    className="spkAccessoryPairRow spkAccessoryPairRow--inDetailPanel"
                >
                    <SpkChainListCard
                        chains={chains}
                        spk={{
                            rowId: Number(production.id),
                            produksiNo: production.produksiNo,
                        }}
                        receiptEmployeeOptions={receiptEmployeeOptions}
                        chainReceipt={chainReceipt}
                    />
                    <SpkComponentListCard
                        components={components}
                        spk={{
                            rowId: Number(production.id),
                            produksiNo: production.produksiNo,
                        }}
                        receiptEmployeeOptions={receiptEmployeeOptions}
                        componentReceipt={componentReceipt}
                    />
                </div>
            </section>
        </div>
    );
}
