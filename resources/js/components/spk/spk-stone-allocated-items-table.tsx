import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { formatDecimal3Id } from '@/components/spk/spk-stone-decimal-format';
import type { SpkStoneAllocatedItem } from '@/components/spk/types';

function emptyDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const text = String(value).trim();

    return text !== '' ? text : '-';
}

function allocatedCrtPerPcs(item: SpkStoneAllocatedItem): string {
    const pcs = Number(String(item.reservedPcs).replace(',', '.'));

    if (!Number.isFinite(pcs) || pcs <= 0) {
        return '-';
    }

    if (item.reservedCrt === null || item.reservedCrt.trim() === '') {
        return '-';
    }

    const total = Number(String(item.reservedCrt).replace(',', '.'));

    if (!Number.isFinite(total)) {
        return '-';
    }

    return formatDecimal3Id(total / pcs);
}

export function SpkStoneAllocatedItemsTable({
    items,
    revokingAllocationId = null,
    onRevokeItem,
}: {
    items: SpkStoneAllocatedItem[];
    revokingAllocationId?: number | null;
    onRevokeItem?: (item: SpkStoneAllocatedItem) => void;
}) {
    const showActions = onRevokeItem !== undefined;

    return (
        <table className="spkChainIssuedUnitTable spkStoneAllocatedItemsTable">
            <thead>
                <tr>
                    {showActions ? (
                        <th className="spkChainAllocateColAction">Aksi</th>
                    ) : null}
                    <th className="spkChainAllocateColName">Nama Batu</th>
                    <th className="spkStoneAllocateColShape">Bentuk</th>
                    <th className="spkChainIssuedColLength">CRT per Butir</th>
                    <th className="spkChainIssuedColLength">
                        Jumlah Butir (pcs)
                    </th>
                    <th className="spkChainIssuedColWeight">Total CRT</th>
                </tr>
            </thead>
            <tbody>
                {items.map((item) => (
                    <tr key={item.id}>
                        {showActions ? (
                            <td className="spkChainAllocateColAction">
                                <button
                                    type="button"
                                    className="spkChainIssuedRowActionBtn spkChainIssuedRowActionBtn--danger"
                                    aria-label="Hapus alokasi batu"
                                    title="Hapus alokasi batu"
                                    disabled={revokingAllocationId === item.id}
                                    onClick={() => {
                                        onRevokeItem(item);
                                    }}
                                >
                                    <Icon name={deleteIcon} mode="Decorative" />
                                </button>
                            </td>
                        ) : null}
                        <td className="spkChainAllocateColName">
                            {emptyDash(item.label)}
                        </td>
                        <td className="spkStoneAllocateColShape">
                            {emptyDash(item.shape)}
                        </td>
                        <td className="spkChainIssuedColLength tabular-nums">
                            {allocatedCrtPerPcs(item)}
                        </td>
                        <td className="spkChainIssuedColLength tabular-nums">
                            {emptyDash(item.reservedPcs)}
                        </td>
                        <td className="spkChainIssuedColWeight tabular-nums">
                            {formatDecimal3Id(item.reservedCrt)}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
