import {
    SpkDiamondDetailStoneLabelLink,
    type SpkDiamondDetailStoneRow,
} from '@/components/spk/spk-stone-allocated-display-cells';
import { formatDecimal3Id } from '@/components/spk/spk-stone-decimal-format';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SpkItemNoLink } from '@/components/spk/spk-item-no-link';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';
import { SpkDiamondStatusBadge } from '@/components/spk/spk-diamond-status-badge';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import {
    displayListDate,
    SpkTableCreatedByCell,
    SpkTableDescriptionCell,
} from '@/components/spk/spk-list-cells';
import { Checkbox } from '@/components/ui/checkbox';
import { show as spkDiamondShow } from '@/routes/spk-diamond';
import { show as pasangBatuShow } from '@/routes/pasang-batu';

export const SPK_DIAMOND_INDEX_STONE_DETAIL_HEADERS = [
    {
        key: 'shape',
        label: 'Bentuk',
        className: 'spkDiamondIndexStoneColShape spkTableColCenter',
    },
    {
        key: 'label',
        label: 'Nama/Kode',
        className: 'spkDiamondIndexStoneColName',
    },
    {
        key: 'pcs',
        label: 'Pcs',
        className: 'spkDiamondIndexStoneColPcs spkTableColCenter',
    },
    {
        key: 'crtPerPcs',
        label: 'CRT/pcs',
        className: 'spkDiamondIndexStoneColCrtPerPcs spkTableColCenter',
    },
    {
        key: 'totalCrt',
        label: 'Tot. CRT',
        className: 'spkDiamondIndexStoneColTotalCrt spkTableColCenter',
    },
] as const;

function emptyDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const text = String(value).trim();

    return text !== '' ? text : '—';
}

function stoneDataCells(item: SpkDiamondDetailStoneRow): ReactNode {
    const shapeText = emptyDash(item.shape);

    return (
        <>
            <td
                className="spkDiamondIndexStoneColShape spkTableColCenter"
                title={shapeText !== '—' ? shapeText : undefined}
            >
                {shapeText}
            </td>
            <td className="spkDiamondIndexStoneColName">
                <SpkDiamondDetailStoneLabelLink row={item} />
            </td>
            <td className="spkDiamondIndexStoneColPcs spkTableColCenter tabular-nums">
                {emptyDash(item.reservedPcs)}
            </td>
            <td className="spkDiamondIndexStoneColCrtPerPcs spkTableColCenter tabular-nums">
                {item.crtPerPcs !== null
                    ? formatDecimal3Id(item.crtPerPcs)
                    : '—'}
            </td>
            <td className="spkDiamondIndexStoneColTotalCrt spkTableColCenter tabular-nums">
                {formatDecimal3Id(item.reservedCrt)}
            </td>
        </>
    );
}

export type SpkDiamondIndexDocumentRow = {
    id: number;
    docNo: string;
    tanggal: string;
    spkNo: string | null;
    orderReference?: string | null;
    spkType?: string | null;
    orderTypeLabel?: string | null;
    spkImageUrl: string | null;
    typeSkuLabel: string | null;
    itemDescription: string | null;
    skuAssigned: boolean;
    diamondMountingId: number | null;
    createdBy: string | null;
    createdDate: string | null;
    status: string;
    statusLabel?: string | null;
    allocatedStones: SpkDiamondDetailStoneRow[];
};

type SpkDiamondIndexDocumentRowsProps = {
    row: SpkDiamondIndexDocumentRow;
    selected: boolean;
    onToggleSelect: (
        id: number,
        checked: boolean | 'indeterminate',
    ) => void;
};

export function SpkDiamondIndexDocumentRows({
    row,
    selected,
    onToggleSelect,
}: SpkDiamondIndexDocumentRowsProps) {
    const stones = row.allocatedStones;
    const rowSpan = Math.max(stones.length, 1);
    const rowClassName = selected ? 'is-selected' : undefined;

    const leadingCells = (
        <>
            <td
                rowSpan={rowSpan}
                className="spkTableColCheck--finishing"
            >
                <Checkbox
                    checked={selected}
                    onCheckedChange={(checked) =>
                        onToggleSelect(row.id, checked)
                    }
                    aria-label={`Pilih ${emptyDash(row.docNo)}`}
                />
            </td>
            <td
                rowSpan={rowSpan}
                className="spkTableColDoc spkTableColDoc--spk spkTableColDoc--spkDiamond"
            >
                <Link
                    href={spkDiamondShow.url(row.id)}
                    className="spkProduksiLink"
                >
                    {emptyDash(row.docNo)}
                </Link>
            </td>
            <td rowSpan={rowSpan} className="spkTableColCenter">
                {displayListDate(row.tanggal)}
            </td>
            <td
                rowSpan={rowSpan}
                className="spkTableColCenter tabular-nums"
            >
                {row.diamondMountingId !== null &&
                row.diamondMountingId > 0 ? (
                    <Link
                        href={pasangBatuShow.url(row.diamondMountingId)}
                        className="spkProduksiLink"
                    >
                        {row.diamondMountingId.toLocaleString('id-ID')}
                    </Link>
                ) : (
                    '—'
                )}
            </td>
            <td rowSpan={rowSpan}>
                <div className="flex min-w-0 flex-col items-start gap-1">
                    <SpkItemNoLink
                        spkNo={row.spkNo}
                        orderReference={row.orderReference}
                    />
                    <SpkOrderTypeColumn
                        spkType={row.spkType ?? null}
                        orderTypeLabel={row.orderTypeLabel ?? null}
                    />
                </div>
            </td>
            <td rowSpan={rowSpan}>
                <div className="flex items-start gap-3">
                    <SpkItemThumbnail
                        imageUrl={row.spkImageUrl}
                        spkNo={row.spkNo ?? row.docNo}
                    />
                    <SpkTableDescriptionCell row={row} />
                </div>
            </td>
        </>
    );

    const trailingCells = (
        <>
            <td
                rowSpan={rowSpan}
                className="spkTableColCenter spkTableColCreatedMeta"
            >
                <SpkTableCreatedByCell
                    createdBy={row.createdBy}
                    createdDate={row.createdDate}
                />
            </td>
            <td
                rowSpan={rowSpan}
                className="spkTableColStatus spkTableColCenter"
            >
                <SpkDiamondStatusBadge
                    status={row.status}
                    label={row.statusLabel}
                />
            </td>
        </>
    );

    if (stones.length === 0) {
        return (
            <tr key={row.id} className={rowClassName}>
                {leadingCells}
                <td
                    colSpan={SPK_DIAMOND_INDEX_STONE_DETAIL_HEADERS.length}
                    className="spkDiamondIndexStoneColEmpty spkTableColCenter text-muted-foreground"
                >
                    —
                </td>
                {trailingCells}
            </tr>
        );
    }

    return stones.map((stone, index) => (
        <tr
            key={`${row.id}-${index}`}
            className={rowClassName}
        >
            {index === 0 ? leadingCells : null}
            {stoneDataCells(stone)}
            {index === 0 ? trailingCells : null}
        </tr>
    ));
}
