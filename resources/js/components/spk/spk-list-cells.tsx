import type { SpkRow } from '@/components/spk/types';

type SpkListDescriptionFields = Pick<
    SpkRow,
    'typeSkuLabel' | 'itemDescription' | 'skuAssigned'
>;

type SpkListLastProcessFields = Pick<
    SpkRow,
    'prosesTerakhir' | 'prosesTerakhirDate' | 'status'
>;

type SpkListStatusFields = Pick<SpkRow, 'status'>;

type SpkListTipeProduksiFields = Pick<SpkRow, 'tipeProduksi' | 'customer'>;

export function tipeProduksiBadgeClass(tipe: string): string {
    const lower = tipe.toLowerCase();

    if (lower.includes('pesanan')) {
        return 'spkTableBadge--pesanan';
    }

    if (lower.includes('stock') || lower.includes('stok')) {
        return 'spkTableBadge--stock';
    }

    if (lower.includes('refund') || lower.includes('exchange')) {
        return 'spkTableBadge--refundExchange';
    }

    if (lower.includes('reparasi')) {
        return 'spkTableBadge--reparasi';
    }

    return 'spkTableBadge--default';
}

export function statusBadgeClass(status: string): string {
    const lower = status.toLowerCase();

    if (lower.includes('done') || lower.includes('selesai')) {
        return 'spkTableBadge--done';
    }

    if (lower.includes('in progress')) {
        return 'spkTableBadge--inProgress';
    }

    if (lower.includes('approved')) {
        return 'spkTableBadge--approved';
    }

    if (lower.includes('draft')) {
        return 'spkTableBadge--draft';
    }

    if (lower.includes('pengajuan') || lower.includes('menunggu')) {
        return 'spkTableBadge--pengajuan';
    }

    return 'spkTableBadge--default';
}

export function SpkTableTipeProduksiCell({
    row,
}: {
    row: SpkListTipeProduksiFields;
}) {
    return (
        <>
            <span
                className={`spkTableBadge ${tipeProduksiBadgeClass(row.tipeProduksi)}`}
            >
                {row.tipeProduksi}
            </span>
            <span>{row.customer}</span>
        </>
    );
}

export function SpkTableDescriptionCell({
    row,
}: {
    row: SpkListDescriptionFields;
}) {
    const typeSkuLabel = row.typeSkuLabel?.trim() ?? '';
    const itemDescription = row.itemDescription?.trim() ?? '';
    const showMissingSku = row.skuAssigned === false;
    const showTypeSku = !showMissingSku && typeSkuLabel !== '';
    const showItemDescription = itemDescription !== '';

    if (!showMissingSku && !showTypeSku && !showItemDescription) {
        return <span>-</span>;
    }

    return (
        <div className="spkTableDescription">
            {showMissingSku ? (
                <span className="spkTableBadge spkTableBadge--missingSku">
                    Belum assign SKU
                </span>
            ) : showTypeSku ? (
                <span className="spkTableDescriptionTypeSku">
                    {typeSkuLabel}
                </span>
            ) : null}
            <span className="spkTableDescriptionItem">
                {showItemDescription ? itemDescription : '-'}
            </span>
        </div>
    );
}

export function SpkTableLastProcessCell({
    row,
}: {
    row: SpkListLastProcessFields;
}) {
    const lastProcess = row.prosesTerakhir.trim();
    const lastProcessDate = (row.prosesTerakhirDate ?? '').trim();

    if (lastProcess !== '') {
        return (
            <div className="spkTableLastProcess">
                <span>{lastProcess}</span>
                {lastProcessDate !== '' ? (
                    <span className="spkTableLastProcessDate">
                        pada {lastProcessDate}
                    </span>
                ) : null}
            </div>
        );
    }

    if (row.status === 'Approved') {
        return <span>Belum Diproses</span>;
    }

    return <span>-</span>;
}

export function SpkTableStatusCell({ row }: { row: SpkListStatusFields }) {
    return (
        <div className="spkTableStatus">
            <span className={`spkTableBadge ${statusBadgeClass(row.status)}`}>
                {row.status}
            </span>
        </div>
    );
}
