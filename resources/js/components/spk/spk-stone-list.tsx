import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import outboxIcon from '@ui5/webcomponents-icons/dist/outbox.js';
import pictureIcon from '@ui5/webcomponents-icons/dist/picture.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import { router, Link } from '@inertiajs/react';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Text } from '@ui5/webcomponents-react/Text';
import { useMemo, useState, Fragment, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    SpkStoneStockDialog,
    type SpkStoneStockDialogStone,
} from '@/components/spk/spk-stone-stock-dialog';
import {
    SpkDiamondCreateDialog,
    allSpkStoneLinesFullyAllocated,
    spkStoneLineFullyAllocated,
} from '@/components/spk/spk-diamond-create-dialog';
import { SpkStoneAllocatedItemsTable } from '@/components/spk/spk-stone-allocated-items-table';
import {
    SpkAllocatedCertificateLink,
    SpkAllocatedStoneLabelLink,
} from '@/components/spk/spk-stone-allocated-display-cells';
import { SpkStoneStatusLabelsCell } from '@/components/spk/spk-stone-status-labels-cell';
import { formatDecimal3Id } from '@/components/spk/spk-stone-decimal-format';
import { useSpkFormStoneStock } from '@/hooks/use-spk-form-stone-stock';
import type {
    SpkItemDetail,
    SpkStoneAllocatedItem,
    SpkStoneFulfillmentAction,
    SpkStoneItem,
    SpkStoneStock,
    SpkDiamondSummary,
    SpkDiamondDocumentRow,
} from '@/components/spk/types';
import { fulfillment as advanceStoneFulfillment } from '@/routes/spk/stones';
import { revoke as revokeStoneAllocationItem } from '@/routes/spk/stones/allocations/items';
import { show as spkDiamondShow } from '@/routes/spk-diamond';

type SpkItemStoneCardProps = {
    item: SpkItemDetail;
    stones: SpkStoneItem[];
    notes?: string;
};

function emptyDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const text = String(value).trim();

    return text !== '' ? text : '-';
}

export function formatDecimal2Id(
    value: string | number | null | undefined,
): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const text = String(value).trim();

    if (text === '' || text === '-') {
        return '-';
    }

    const parsed = Number(text.replace(',', '.'));

    if (!Number.isFinite(parsed)) {
        return text;
    }

    return parsed.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export { formatDecimal3Id } from '@/components/spk/spk-stone-decimal-format';

export function parseGoldWeightGrams(
    value: string | number | null | undefined,
): number | null {
    if (value === null || value === undefined) {
        return null;
    }

    const text = String(value).trim().replace(',', '.');

    if (text === '' || text === '-') {
        return null;
    }

    const parsed = Number(text);

    return Number.isFinite(parsed) ? parsed : null;
}

export function formatGoldWeightGramsLabel(value: string | number): string {
    const parsed = parseGoldWeightGrams(value);

    if (parsed === null) {
        return String(value);
    }

    return parsed.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function isGoldWeightChangedFromMaster(
    current: string | number | null | undefined,
    master: string | number | null | undefined,
): boolean {
    const currentWeight = parseGoldWeightGrams(current);
    const masterWeight = parseGoldWeightGrams(master);

    if (currentWeight === null || masterWeight === null || masterWeight <= 0) {
        return false;
    }

    return Math.abs(currentWeight - masterWeight) > 0.005;
}

export function GoldWeightRowLabel({
    currentWeight,
    masterWeight,
}: {
    currentWeight?: string | number | null;
    masterWeight?: string | number | null;
}) {
    const changed = isGoldWeightChangedFromMaster(currentWeight, masterWeight);

    return (
        <span className="spkGoldWeightLabel">
            Berat Emas (g)
            {changed && masterWeight !== null && masterWeight !== undefined ? (
                <span className="spkMasterDiffHint">
                    {`*Berat emas diubah dari Master SKU (${formatGoldWeightGramsLabel(masterWeight)} g)`}
                </span>
            ) : null}
        </span>
    );
}

export function GoldWeightMasterHint({
    currentWeight,
    masterWeight,
}: {
    currentWeight?: string | number | null;
    masterWeight?: string | number | null;
}) {
    if (
        !isGoldWeightChangedFromMaster(currentWeight, masterWeight) ||
        masterWeight === null ||
        masterWeight === undefined
    ) {
        return null;
    }

    return (
        <span className="spkMasterDiffHint">
            {`*Berat emas diubah dari Master SKU (${formatGoldWeightGramsLabel(masterWeight)} g)`}
        </span>
    );
}

function normalizeMasterText(
    value: string | number | null | undefined,
): string | null {
    if (value === null || value === undefined) {
        return null;
    }

    const text = String(value).trim();

    return text !== '' && text !== '-' ? text : null;
}

export function isMasterFieldChanged(
    current: string | number | null | undefined,
    master: string | number | null | undefined,
): boolean {
    const masterText = normalizeMasterText(master);

    if (masterText === null) {
        return false;
    }

    const masterNumber = parseGoldWeightGrams(masterText);
    const currentNumber = parseGoldWeightGrams(current);

    if (masterNumber !== null) {
        if (masterNumber <= 0) {
            return false;
        }

        if (currentNumber !== null) {
            return Math.abs(currentNumber - masterNumber) > 0.0005;
        }
    }

    const currentText = normalizeMasterText(current) ?? '';

    return currentText.toLowerCase() !== masterText.toLowerCase();
}

function formatMasterDiffValue(master: string | number): string {
    const parsed = parseGoldWeightGrams(master);

    if (parsed === null) {
        return String(master).trim();
    }

    if (Number.isInteger(parsed)) {
        return parsed.toLocaleString('id-ID');
    }

    return parsed.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 3,
    });
}

export function MasterDiffHint({
    current,
    master,
    displayMaster,
}: {
    current?: string | number | null;
    master?: string | number | null;
    displayMaster?: string | number | null;
}) {
    if (
        !isMasterFieldChanged(current, master) ||
        master === null ||
        master === undefined
    ) {
        return null;
    }

    const shown = displayMaster ?? master;

    return (
        <span className="spkMasterDiffHint">
            {`*Diubah dari Master SKU (${formatMasterDiffValue(shown)})`}
        </span>
    );
}

function SpkTypeProductItemValue({ item }: { item: SpkItemDetail }) {
    const typeCode = emptyDash(item.typeCode);
    const productItemName = emptyDash(item.productItemName);
    const skuCode = emptyDash(item.skuCode);
    const typeProductLine = [typeCode, productItemName]
        .filter((part) => part !== '-')
        .join(' | ');
    const hasStructuredType = typeProductLine !== '' || skuCode !== '-';

    if (!hasStructuredType) {
        return <>{emptyDash(item.name)}</>;
    }

    return (
        <div className="spkItemTypeProductStack">
            {typeProductLine !== '' ? (
                <span className="spkItemTypeProductLine">
                    {typeProductLine}
                </span>
            ) : null}
            {skuCode !== '-' ? (
                <span className="spkItemSkuCode">{skuCode}</span>
            ) : null}
        </div>
    );
}

export function SpkItemImageColumn({
    item,
    enablePreview = false,
    spkNo = null,
}: {
    item: SpkItemDetail;
    enablePreview?: boolean;
    spkNo?: string | null;
}) {
    const [previewOpen, setPreviewOpen] = useState(false);
    const [failedUrl, setFailedUrl] = useState<string | null>(null);
    const imageUrl = item.imageUrl;
    const hasImage = Boolean(imageUrl) && failedUrl !== imageUrl;
    const modalTitle = spkNo ? `Gambar SPK · ${spkNo}` : 'Gambar SPK';
    const imageAlt =
        item.name !== '-' ? `Gambar ${item.name}` : 'Gambar item';

    return (
        <div className="spkItemImageCol" aria-label="Gambar item">
            {hasImage ? (
                enablePreview ? (
                    <>
                        <button
                            type="button"
                            className="spkItemImagePreview spkItemImagePreview--clickable"
                            aria-label={`Lihat ${modalTitle.toLowerCase()}`}
                            title={modalTitle}
                            onClick={() => setPreviewOpen(true)}
                        >
                            <img
                                src={imageUrl ?? ''}
                                alt={imageAlt}
                                onError={() => {
                                    if (imageUrl) {
                                        setFailedUrl(imageUrl);
                                    }
                                }}
                            />
                        </button>
                        <Dialog
                            open={previewOpen}
                            onOpenChange={setPreviewOpen}
                        >
                            <DialogContent className="spkAlertModal">
                                <DialogHeader>
                                    <DialogTitle>{modalTitle}</DialogTitle>
                                </DialogHeader>
                                <div className="spkAlertModalBody flex items-center justify-center">
                                    <img
                                        src={imageUrl ?? ''}
                                        alt={imageAlt}
                                        className="max-h-[65vh] w-auto max-w-full rounded object-contain"
                                    />
                                </div>
                            </DialogContent>
                        </Dialog>
                    </>
                ) : (
                    <div className="spkItemImagePreview">
                        <img src={imageUrl ?? ''} alt={imageAlt} />
                    </div>
                )
            ) : (
                <div className="spkItemImagePlaceholder">
                    <Icon name={pictureIcon} mode="Decorative" />
                    <span>Gambar item</span>
                </div>
            )}
        </div>
    );
}

function ItemDetailField({
    label,
    children,
    fullWidth = false,
}: {
    label: ReactNode;
    children: ReactNode;
    fullWidth?: boolean;
}) {
    return (
        <div
            className={[
                'spkDetailField',
                fullWidth ? 'spkDetailField--full' : '',
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <span className="spkDetailFieldLabel">{label}</span>
            <div className="spkDetailValue">{children}</div>
        </div>
    );
}

function SpkItemDetailUkuranAside({
    item,
}: {
    item: SpkItemDetail;
}) {
    return (
        <div className="spkItemUkuranFields spkItemUkuranFields--aside">
            <div className="spkItemUkuranField spkItemUkuranField--aside">
                <span className="spkItemUkuranLabel">Panjang (mm)</span>
                <strong>{emptyDash(item.diameter)}</strong>
            </div>
            <div className="spkItemUkuranField spkItemUkuranField--aside">
                <span className="spkItemUkuranLabel">Dimensi PxL (mm)</span>
                <strong>{emptyDash(item.dimensi)}</strong>
            </div>
            <div className="spkItemUkuranField spkItemUkuranField--aside">
                <span className="spkItemUkuranLabel">Ring Size</span>
                <strong>{emptyDash(item.ringSize)}</strong>
            </div>
        </div>
    );
}

function SpkItemDetailUkuranPanelMetrics({
    item,
}: {
    item: SpkItemDetail;
}) {
    return (
        <div className="spkItemUkuranFields spkItemUkuranFields--panel">
            <div className="spkItemUkuranMetric">
                <span className="spkItemUkuranLabel">Panjang (mm)</span>
                <strong>{emptyDash(item.diameter)}</strong>
            </div>
            <div className="spkItemUkuranMetric">
                <span className="spkItemUkuranLabel">Dimensi PxL (mm)</span>
                <strong>{emptyDash(item.dimensi)}</strong>
            </div>
            <div className="spkItemUkuranMetric">
                <span className="spkItemUkuranLabel">Ring Size</span>
                <strong>{emptyDash(item.ringSize)}</strong>
            </div>
        </div>
    );
}

function SpkItemDetailAsideFields({
    item,
    notes,
}: {
    item: SpkItemDetail;
    notes?: string;
}) {
    return (
        <div
            className="spkInformasiProduksiAsideFields spkItemDetailAsideFields"
        >
            <ItemDetailField label="Tipe Item | SKU">
                <SpkTypeProductItemValue item={item} />
            </ItemDetailField>
            <ItemDetailField label="Status Order">
                {emptyDash(item.statusOrderLabel)}
            </ItemDetailField>
            <ItemDetailField label="Qty">{emptyDash(item.qty)}</ItemDetailField>
            <ItemDetailField label="Ukuran">
                <SpkItemDetailUkuranAside item={item} />
            </ItemDetailField>
            <ItemDetailField
                label={
                    <GoldWeightRowLabel
                        currentWeight={item.goldWeight}
                        masterWeight={item.masterGoldWeight}
                    />
                }
            >
                {formatDecimal2Id(item.goldWeight)}
            </ItemDetailField>
            <ItemDetailField label="Warna Emas">
                {emptyDash(item.goldColor)}
            </ItemDetailField>
            <ItemDetailField label="File JewelCAD 3D">
                {emptyDash(item.jwcad3d)}
            </ItemDetailField>
            <ItemDetailField label="Catatan">
                <span className="spkItemNotesCell">{emptyDash(notes)}</span>
            </ItemDetailField>
        </div>
    );
}

function SpkItemDetailPanelFields({
    item,
    notes,
}: {
    item: SpkItemDetail;
    notes?: string;
}) {
    return (
        <div className="spkItemDetailPanelFields">
            <div className="spkItemDetailPanelPrimary">
                <ItemDetailField label="Tipe Item | SKU">
                    <SpkTypeProductItemValue item={item} />
                </ItemDetailField>
                <ItemDetailField label="Status Order">
                    {emptyDash(item.statusOrderLabel)}
                </ItemDetailField>
                <ItemDetailField label="Qty">
                    {emptyDash(item.qty)}
                </ItemDetailField>
                <ItemDetailField
                    label={
                        <GoldWeightRowLabel
                            currentWeight={item.goldWeight}
                            masterWeight={item.masterGoldWeight}
                        />
                    }
                >
                    {formatDecimal2Id(item.goldWeight)}
                </ItemDetailField>
                <ItemDetailField label="Warna Emas">
                    {emptyDash(item.goldColor)}
                </ItemDetailField>
                <ItemDetailField label="File JewelCAD 3D">
                    {emptyDash(item.jwcad3d)}
                </ItemDetailField>
                <ItemDetailField label="Catatan">
                    <span className="spkItemNotesCell">
                        {emptyDash(notes)}
                    </span>
                </ItemDetailField>
            </div>
            <div className="spkItemDetailPanelUkuran">
                <ItemDetailField label="Ukuran">
                    <SpkItemDetailUkuranPanelMetrics item={item} />
                </ItemDetailField>
            </div>
        </div>
    );
}

export function SpkItemDetailCard({
    item,
    notes,
    showImage = true,
    variant = 'table',
}: {
    item: SpkItemDetail;
    notes?: string;
    showImage?: boolean;
    variant?: 'table' | 'aside' | 'panel';
}) {
    if (variant === 'aside') {
        return (
            <div
                className={
                    showImage ? 'spkItemDetailGrid' : 'spkItemDetailFieldsOnly'
                }
            >
                {showImage ? <SpkItemImageColumn item={item} /> : null}
                <SpkItemDetailAsideFields item={item} notes={notes} />
            </div>
        );
    }

    if (variant === 'panel') {
        return (
            <div
                className={
                    showImage ? 'spkItemDetailGrid' : 'spkItemDetailFieldsOnly'
                }
            >
                {showImage ? <SpkItemImageColumn item={item} /> : null}
                <SpkItemDetailPanelFields item={item} notes={notes} />
            </div>
        );
    }

    return (
        <div
            className={
                showImage ? 'spkItemDetailGrid' : 'spkItemDetailFieldsOnly'
            }
        >
            {showImage ? <SpkItemImageColumn item={item} /> : null}

            <div className="spkItemFieldsCol">
                <table className="spkItemMetaTable spkItemMetaTable--sm">
                    <tbody>
                        <tr>
                            <th scope="row">Tipe Item | SKU</th>
                            <td>
                                <SpkTypeProductItemValue item={item} />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Status Order</th>
                            <td>{emptyDash(item.statusOrderLabel)}</td>
                        </tr>
                        <tr>
                            <th scope="row">Qty</th>
                            <td>{emptyDash(item.qty)}</td>
                        </tr>
                        <tr>
                            <th scope="row">Ukuran</th>
                            <td>
                                <div className="spkItemUkuranFields">
                                    <div className="spkItemUkuranField">
                                        <span className="spkItemUkuranLabel">
                                            Panjang (mm)
                                        </span>
                                        <strong>
                                            {emptyDash(item.diameter)}
                                        </strong>
                                    </div>
                                    <div className="spkItemUkuranField">
                                        <span className="spkItemUkuranLabel">
                                            Dimensi PxL (mm)
                                        </span>
                                        <strong>
                                            {emptyDash(item.dimensi)}
                                        </strong>
                                    </div>
                                    <div className="spkItemUkuranField">
                                        <span className="spkItemUkuranLabel">
                                            Ring Size
                                        </span>
                                        <strong>
                                            {emptyDash(item.ringSize)}
                                        </strong>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <GoldWeightRowLabel
                                    currentWeight={item.goldWeight}
                                    masterWeight={item.masterGoldWeight}
                                />
                            </th>
                            <td>{formatDecimal2Id(item.goldWeight)}</td>
                        </tr>
                        <tr>
                            <th scope="row">Warna Emas</th>
                            <td>{emptyDash(item.goldColor)}</td>
                        </tr>
                        <tr>
                            <th scope="row">File JewelCAD 3D</th>
                            <td>{emptyDash(item.jwcad3d)}</td>
                        </tr>
                        <tr>
                            <th scope="row">Catatan</th>
                            <td className="spkItemNotesCell">
                                {emptyDash(notes)}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function SpkStoneStockCell({
    stock,
    loading = false,
    failed = false,
    onShowList,
}: {
    stock?: SpkStoneStock | null;
    loading?: boolean;
    failed?: boolean;
    onShowList: () => void;
}) {
    if (loading) {
        return <span className="spkStoneStockMeta">Memuat...</span>;
    }

    if (failed) {
        return <span className="spkStoneStockMeta">Gagal cek stok</span>;
    }

    if (!stock) {
        return <>-</>;
    }

    const isAvailable = stock.status === 'available';
    const sourceLabel = stock.source === 'dossier' ? 'Dossier' : 'Mikro';

    return (
        <div className="spkStoneStockCell">
            <div className="spkStoneStock" title={stock.note ?? undefined}>
                <span
                    className={
                        isAvailable
                            ? 'spkStoneStockBadge is-available'
                            : 'spkStoneStockBadge is-unavailable'
                    }
                >
                    {isAvailable ? 'Tersedia' : 'Tidak Tersedia'}
                </span>
                <span className="spkStoneStockMeta">
                    {`${sourceLabel} · stok ${stock.availablePcs.toLocaleString('id-ID')} pcs`}
                </span>
                {stock.note ? (
                    <span className="spkStoneStockMeta">{stock.note}</span>
                ) : null}
            </div>
            <button
                type="button"
                className="spkStoneStockListBtn"
                aria-label={`Lihat daftar stok batu ${sourceLabel}`}
                title={`Lihat daftar stok batu ${sourceLabel}`}
                onClick={onShowList}
            >
                <Icon name={listIcon} mode="Decorative" />
            </button>
        </div>
    );
}

function SpkStoneAllocationStatusCell({ stone }: { stone: SpkStoneItem }) {
    if (stone.stock === undefined) {
        return (
            <SpkStoneStatusLabelsCell stock={null} allocation={null} />
        );
    }

    return (
        <SpkStoneStatusLabelsCell
            stock={stone.stock}
            allocation={
                stone.allocation ?? {
                    status: 'not_allocated',
                    label: 'Belum Dialokasikan',
                    allocatedPcs: 0,
                }
            }
        />
    );
}

function SpkStoneAllocationActionCell({
    stone,
    onShowStock,
}: {
    stone: SpkStoneItem;
    onShowStock: () => void;
}) {
    const canAllocate = stone.stock?.status === 'available';
    const allocateTitle = canAllocate
        ? 'Alokasikan batu ke SPK ini'
        : 'Lihat daftar stok batu';
    const allocation = stone.allocation;
    const orderAction = allocation?.nextAction;
    const requiredPcs = Number(stone.pcs ?? 0);
    const allocatedPcs = allocation?.allocatedPcs ?? 0;
    const isFullyAllocated =
        requiredPcs > 0 && allocatedPcs >= requiredPcs;
    const [processingOrder, setProcessingOrder] = useState(false);

    const handleOrder = (): void => {
        if (orderAction !== 'mark_ordering') {
            return;
        }

        setProcessingOrder(true);
        router.post(
            advanceStoneFulfillment.url(Number(stone.id)),
            { action: orderAction satisfies SpkStoneFulfillmentAction },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessingOrder(false);
                },
            },
        );
    };

    return (
        <div className="spkChainTableActionCell">
            {orderAction === 'mark_ordering' ? (
                <button
                    type="button"
                    className="spkChainFulfillmentActionBtn spkChainFulfillmentActionBtn--order"
                    disabled={processingOrder}
                    title="Tandai batu sedang dipesan"
                    onClick={handleOrder}
                >
                    <Icon name={outboxIcon} mode="Decorative" />
                    <span className="spkChainFulfillmentActionBtnLabel">
                        Order Batu
                    </span>
                </button>
            ) : null}
            {!isFullyAllocated ? (
                <button
                    type="button"
                    className="spkChainFulfillmentActionBtn"
                    title={allocateTitle}
                    onClick={onShowStock}
                >
                    <Icon name={outboxIcon} mode="Decorative" />
                    <span className="spkChainFulfillmentActionBtnLabel">
                        Alokasi Batu
                    </span>
                </button>
            ) : null}
        </div>
    );
}

function formatStoneAllocatedAudit(
    items: SpkStoneAllocatedItem[],
): string | null {
    const latest = items.find(
        (item) =>
            (item.allocatedDate !== null &&
                item.allocatedDate.trim() !== '') ||
            (item.allocatedBy !== null && item.allocatedBy.trim() !== ''),
    );

    if (latest === undefined) {
        return null;
    }

    const parts: string[] = [];

    if (
        latest.allocatedDate !== null &&
        latest.allocatedDate.trim() !== ''
    ) {
        parts.push(`Tanggal: ${latest.allocatedDate}`);
    }

    if (latest.allocatedBy !== null && latest.allocatedBy.trim() !== '') {
        parts.push(`Oleh: ${latest.allocatedBy}`);
    }

    return parts.length > 0 ? parts.join(' · ') : null;
}

function stoneCanManageAllocation(stone: SpkStoneItem): boolean {
    return (stone.allocatedItems?.length ?? 0) > 0;
}

function sumStoneAllocatedTotals(stones: SpkStoneItem[]): {
    pcs: number;
    carat: number;
} {
    let pcs = 0;
    let carat = 0;

    for (const stone of stones) {
        for (const item of stone.allocatedItems ?? []) {
            const itemPcs = Number(String(item.reservedPcs).replace(',', '.'));

            if (Number.isFinite(itemPcs)) {
                pcs += itemPcs;
            }

            if (
                item.reservedCrt !== null &&
                String(item.reservedCrt).trim() !== ''
            ) {
                const itemCrt = Number(
                    String(item.reservedCrt).replace(',', '.'),
                );

                if (Number.isFinite(itemCrt)) {
                    carat += itemCrt;
                }
            }
        }
    }

    return { pcs, carat };
}

function stoneAllocatedTotalsShortfall(
    allocated: { pcs: number; carat: number },
    requiredPcs: number,
    requiredCarat: number,
): boolean {
    if (allocated.pcs < requiredPcs) {
        return true;
    }

    if (requiredCarat <= 0) {
        return false;
    }

    return allocated.carat + 0.0005 < requiredCarat;
}

function formatStoneTableTotalCarat(value: number): string {
    return value.toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

function SpkStoneRequirementsTable({
    stones,
    totalButir,
    totalCarat,
    onShowStock,
    title = 'Kebutuhan Batu',
    headingLevel = 'h3',
    showStatusAndActions = true,
    embedAllocations = false,
    revokingAllocationId = null,
    onRevokeAllocation,
    allocatedTotals,
    allocatedTotalsShort = false,
}: {
    stones: SpkStoneItem[];
    totalButir: number;
    totalCarat: number;
    onShowStock?: (stone: SpkStoneItem) => void;
    title?: string;
    headingLevel?: 'h2' | 'h3';
    showStatusAndActions?: boolean;
    embedAllocations?: boolean;
    revokingAllocationId?: number | null;
    onRevokeAllocation?: (
        stone: SpkStoneItem,
        item: SpkStoneAllocatedItem,
    ) => void;
    allocatedTotals?: { pcs: number; carat: number };
    allocatedTotalsShort?: boolean;
}) {
    const HeadingTag = headingLevel;
    const columnCount = showStatusAndActions ? 8 : 6;
    const tableClassName = showStatusAndActions
        ? 'spkStoneTable spkStoneTable--withStatus'
        : 'spkStoneTable';

    return (
        <>
            <div className="spkBatuPanelSectionHeader">
                <HeadingTag className="spkFioriDetailBlockTitle">
                    {title}
                </HeadingTag>
                <Text className="spkFioriDetailBlockMeta">
                    {stones.length} baris
                </Text>
            </div>
            {stones.length === 0 ? (
                <div className="spkStoneEmpty">
                    <Text>Tidak ada batu pada varian ini.</Text>
                </div>
            ) : (
                <div className="spkStoneTableWrap">
                    <table className={tableClassName}>
                        <thead>
                            <tr>
                                <th>Posisi</th>
                                <th>Bentuk</th>
                                <th>Diameter / Dimensi PxL (mm)</th>
                                <th>CRT per Butir (pcs)</th>
                                <th>Jumlah Butir (pcs)</th>
                                <th>Total CRT</th>
                                {showStatusAndActions ? (
                                    <>
                                        <th className="spkStoneStatusCol">
                                            Status
                                        </th>
                                        <th className="spkStoneTableActionCol">
                                            Aksi
                                        </th>
                                    </>
                                ) : null}
                            </tr>
                        </thead>
                        <tbody>
                            {stones.map((stone) => {
                                const allocatedItems =
                                    stone.allocatedItems ?? [];
                                const allocatedAudit = embedAllocations
                                    ? formatStoneAllocatedAudit(allocatedItems)
                                    : null;

                                return (
                                    <Fragment key={stone.id}>
                                        <tr>
                                            <td>
                                                {emptyDash(stone.positionName)}
                                                <MasterDiffHint
                                                    current={stone.positionName}
                                                    master={
                                                        stone.master
                                                            ?.positionName
                                                    }
                                                />
                                            </td>
                                            <td>
                                                {emptyDash(
                                                    stone.shapeName ||
                                                        stone.shape,
                                                )}
                                                <MasterDiffHint
                                                    current={
                                                        stone.shapeName ||
                                                        stone.shape
                                                    }
                                                    master={
                                                        stone.master?.shapeName
                                                    }
                                                />
                                            </td>
                                            <td>
                                                {emptyDash(stone.size)}
                                                <MasterDiffHint
                                                    current={stone.size}
                                                    master={stone.master?.size}
                                                />
                                            </td>
                                            <td>
                                                {formatDecimal3Id(
                                                    stone.caratPerPcs ??
                                                        stone.carat,
                                                )}
                                                <MasterDiffHint
                                                    current={
                                                        stone.caratPerPcs ??
                                                        stone.carat
                                                    }
                                                    master={
                                                        stone.master
                                                            ?.caratPerPcs
                                                    }
                                                />
                                            </td>
                                            <td>
                                                {emptyDash(stone.pcs)}
                                                <MasterDiffHint
                                                    current={stone.pcs}
                                                    master={stone.master?.pcs}
                                                />
                                            </td>
                                            <td>
                                                {formatDecimal3Id(
                                                    stone.totalCarat,
                                                )}
                                            </td>
                                            {showStatusAndActions ? (
                                                <>
                                                    <td className="spkStoneStatusCol">
                                                        <SpkStoneAllocationStatusCell
                                                            stone={stone}
                                                        />
                                                    </td>
                                                    <td className="spkStoneTableActionCol">
                                                        <SpkStoneAllocationActionCell
                                                            stone={stone}
                                                            onShowStock={() =>
                                                                onShowStock?.(
                                                                    stone,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                </>
                                            ) : null}
                                        </tr>
                                        {embedAllocations &&
                                        allocatedItems.length > 0 ? (
                                            <tr className="spkChainIssuedRow">
                                                <td colSpan={columnCount}>
                                                    <div className="spkChainIssuedRowInner">
                                                        <div className="spkChainIssuedMeta">
                                                            <strong>
                                                                Batu yang
                                                                dialokasikan:
                                                            </strong>
                                                            {allocatedAudit !==
                                                            null ? (
                                                                <span className="spkChainIssuedMetaAudit">
                                                                    {
                                                                        allocatedAudit
                                                                    }
                                                                </span>
                                                            ) : null}
                                                            <span className="spkChainIssuedMetaDetail">
                                                                <SpkStoneAllocatedItemsTable
                                                                    items={
                                                                        allocatedItems
                                                                    }
                                                                    revokingAllocationId={
                                                                        revokingAllocationId
                                                                    }
                                                                    onRevokeItem={
                                                                        stoneCanManageAllocation(
                                                                            stone,
                                                                        ) &&
                                                                        onRevokeAllocation
                                                                            ? (
                                                                                  item,
                                                                              ) => {
                                                                                  onRevokeAllocation(
                                                                                      stone,
                                                                                      item,
                                                                                  );
                                                                              }
                                                                            : undefined
                                                                    }
                                                                />
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        ) : null}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                        <tfoot>
                            <tr className="spkStoneTableTotal">
                                <td colSpan={4}>Total kebutuhan batu</td>
                                <td className="spkStoneTableTotalValue">
                                    {totalButir}
                                </td>
                                <td className="spkStoneTableTotalValue">
                                    {formatStoneTableTotalCarat(totalCarat)}
                                </td>
                                {showStatusAndActions ? (
                                    <>
                                        <td />
                                        <td />
                                    </>
                                ) : null}
                            </tr>
                            {embedAllocations && allocatedTotals !== undefined ? (
                                <tr
                                    className={
                                        allocatedTotalsShort
                                            ? 'spkStoneTableTotal spkStoneTableTotal--allocated spkStoneTableTotal--allocatedShort'
                                            : 'spkStoneTableTotal spkStoneTableTotal--allocated'
                                    }
                                >
                                    <td colSpan={4}>
                                        Total batu dialokasikan
                                    </td>
                                    <td className="spkStoneTableTotalValue">
                                        {allocatedTotals.pcs}
                                    </td>
                                    <td className="spkStoneTableTotalValue">
                                        {formatStoneTableTotalCarat(
                                            allocatedTotals.carat,
                                        )}
                                    </td>
                                    {showStatusAndActions ? (
                                        <>
                                            <td />
                                            <td />
                                        </>
                                    ) : null}
                                </tr>
                            ) : null}
                        </tfoot>
                    </table>
                </div>
            )}
        </>
    );
}

function resolveSpkDiamondDocumentNo(
    spkDiamondDocuments: SpkDiamondDocumentRow[],
    spkDiamond: SpkDiamondSummary | null,
): string {
    const fromList = spkDiamondDocuments[0]?.docNo?.trim() ?? '';

    if (fromList !== '') {
        return fromList;
    }

    return spkDiamond?.docNo?.trim() ?? '';
}

export function SpkStoneListCard({
    stones,
    spk,
    spkDiamond = null,
    spkDiamondDocuments = [],
    layout = 'combined',
    showSpkBatuDocumentHeader = false,
}: {
    stones: SpkStoneItem[];
    spk?: { rowId: number; produksiNo: string };
    spkDiamond?: SpkDiamondSummary | null;
    spkDiamondDocuments?: SpkDiamondDocumentRow[];
    layout?: 'combined' | 'requirementsOnly';
    showSpkBatuDocumentHeader?: boolean;
}) {
    const [stockListStone, setStockListStone] = useState<SpkStoneItem | null>(
        null,
    );
    const [revokingAllocationId, setRevokingAllocationId] = useState<
        number | null
    >(null);
    const [createDiamondDialogOpen, setCreateDiamondDialogOpen] =
        useState(false);
    const hasSpkDiamondDocument =
        spkDiamondDocuments.length > 0 ||
        (spkDiamond !== null && spkDiamond.docNo.trim() !== '');
    const canCreateDiamondSpk = useMemo(
        () =>
            allSpkStoneLinesFullyAllocated(stones) && !hasSpkDiamondDocument,
        [stones, hasSpkDiamondDocument],
    );
    const createDiamondDisabledTitle = hasSpkDiamondDocument
        ? `SPK Pasang Batu sudah ada: ${spkDiamond?.docNo ?? ''}`
        : canCreateDiamondSpk
          ? 'Buat dokumen SPK Pasang Batu'
          : 'Alokasikan semua baris batu pada SPK terlebih dahulu';

    const openCreateDiamondDialog = (): void => {
        if (hasSpkDiamondDocument) {
            toast.info(createDiamondDisabledTitle);

            return;
        }

        if (!canCreateDiamondSpk) {
            toast.warning(createDiamondDisabledTitle);

            return;
        }

        setCreateDiamondDialogOpen(true);
    };

    const openPrintDiamondDocument = (): void => {
        if (!hasSpkDiamondDocument) {
            toast.warning('Buat SPK Pasang Batu terlebih dahulu.');

            return;
        }

        toast.info(
            `Dokumen ${spkDiamond?.docNo ?? ''} tersimpan. Cetak SPK Pasang Batu akan menyusul.`,
        );
    };
    const totalButir = stones.reduce(
        (sum, stone) => sum + (Number(stone.pcs) || 0),
        0,
    );
    const totalCarat = stones.reduce((sum, stone) => {
        const value = Number(String(stone.totalCarat).replace(',', '.'));

        return sum + (Number.isFinite(value) ? value : 0);
    }, 0);
    const allocatedTotals = useMemo(
        () => sumStoneAllocatedTotals(stones),
        [stones],
    );
    const allocatedTotalsShort = stoneAllocatedTotalsShortfall(
        allocatedTotals,
        totalButir,
        totalCarat,
    );

    const diamondHeaderActions =
        spk !== undefined ? (
            <div className="spkStoneDiamondHeaderActions">
                <button
                    type="button"
                    className="spkChainPrintReceiptBtn"
                    aria-label="Buat SPK Pasang Batu"
                    title={createDiamondDisabledTitle}
                    disabled={hasSpkDiamondDocument}
                    onClick={openCreateDiamondDialog}
                >
                    <Icon name={addIcon} mode="Decorative" />
                    <span className="spkChainPrintReceiptBtnLabel">
                        Buat SPK Pasang Batu
                    </span>
                </button>
                <button
                    type="button"
                    className="spkChainPrintReceiptBtn"
                    aria-label="Print SPK Pasang Batu"
                    title={
                        hasSpkDiamondDocument
                            ? `Print SPK Pasang Batu ${spkDiamondDocuments[0]?.docNo ?? spkDiamond?.docNo ?? ''}`
                            : 'Print SPK Pasang Batu'
                    }
                    disabled={!hasSpkDiamondDocument}
                    onClick={openPrintDiamondDocument}
                >
                    <Icon name={printIcon} mode="Decorative" />
                    <span className="spkChainPrintReceiptBtnLabel">
                        Print SPK Pasang Batu
                    </span>
                </button>
            </div>
        ) : null;

    if (layout === 'requirementsOnly') {
        return (
            <div className="spkItemStoneSection spkItemStoneSection--requirementsOnly">
                <SpkStoneRequirementsTable
                    stones={stones}
                    totalButir={totalButir}
                    totalCarat={totalCarat}
                    headingLevel="h2"
                    showStatusAndActions={false}
                />
            </div>
        );
    }

    if (showSpkBatuDocumentHeader) {
        const spkDiamondDocNo = resolveSpkDiamondDocumentNo(
            spkDiamondDocuments,
            spkDiamond,
        );
        const spkDiamondShowId = spkDiamondDocuments[0]?.id ?? null;
        const stonesWithAllocations = stones.filter(
            (stone) => (stone.allocatedItems?.length ?? 0) > 0,
        );

        const revokeAllocationItem = (
            stone: SpkStoneItem,
            item: SpkStoneAllocatedItem,
        ): void => {
            if (!window.confirm('Hapus alokasi batu ini dari SPK?')) {
                return;
            }

            setRevokingAllocationId(item.id);
            router.post(
                revokeStoneAllocationItem.url([
                    Number(stone.id),
                    item.id,
                ]),
                {},
                {
                    preserveScroll: true,
                    onFinish: () => {
                        setRevokingAllocationId(null);
                    },
                },
            );
        };

        return (
            <div className="spkItemStoneSection spkItemStoneSection--allocationSpkTab">
                <div className="spkSpkBatuDocHeader spkBatuPanelToolbar">
                    <div className="spkSpkBatuDocHeaderMain">
                        <Text className="spkSpkBatuDocHeaderLabel">
                            No SPK Pasang Batu
                        </Text>
                        {spkDiamondDocNo !== '' ? (
                            spkDiamondShowId !== null && spkDiamondShowId > 0 ? (
                                <Link
                                    href={spkDiamondShow.url(spkDiamondShowId)}
                                    className="spkSpkBatuDocHeaderValue spkProduksiLink tabular-nums"
                                >
                                    {spkDiamondDocNo}
                                </Link>
                            ) : (
                                <strong className="spkSpkBatuDocHeaderValue tabular-nums">
                                    {spkDiamondDocNo}
                                </strong>
                            )
                        ) : (
                            <Text className="spkSpkBatuDocHeaderEmpty">
                                (SPK Pasang Batu belum dibuat)
                            </Text>
                        )}
                    </div>
                    {diamondHeaderActions}
                </div>

                <section className="spkShowSection spkBatuPanelSection spkBatuPanelSection--requirements">
                    <SpkStoneRequirementsTable
                        stones={stones}
                        totalButir={totalButir}
                        totalCarat={totalCarat}
                        onShowStock={setStockListStone}
                    />
                </section>

                <section className="spkShowSection spkBatuPanelSection">
                    <div className="spkBatuPanelSectionHeader">
                        <h3 className="spkFioriDetailBlockTitle">
                            Alokasi batu
                        </h3>
                        <Text className="spkFioriDetailBlockMeta">
                            {allocatedTotals.pcs} pcs ·{' '}
                            {formatStoneTableTotalCarat(allocatedTotals.carat)}{' '}
                            CRT
                        </Text>
                    </div>

                    {stonesWithAllocations.length === 0 ? (
                        <div className="spkStoneEmpty">
                            <Text>Belum ada batu yang dialokasikan.</Text>
                        </div>
                    ) : (
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
                                        <th className="spkStoneTableActionCol">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stonesWithAllocations.flatMap((stone) => {
                                        const items =
                                            stone.allocatedItems ?? [];

                                        return items.map((item) => (
                                            <tr key={item.id}>
                                                <td>
                                                    {item.source === 'dossier'
                                                        ? 'Dossier'
                                                        : item.source ===
                                                            'micro'
                                                          ? 'Mikro'
                                                          : item.source}
                                                </td>
                                                <td>
                                                    <SpkAllocatedCertificateLink
                                                        certificate={
                                                            item.certificate
                                                        }
                                                    />
                                                </td>
                                                <td>{emptyDash(item.shape)}</td>
                                                <td>
                                                    <SpkAllocatedStoneLabelLink
                                                        item={item}
                                                    />
                                                </td>
                                                <td className="tabular-nums">
                                                    {item.reservedCrt !==
                                                        null &&
                                                    item.reservedPcs
                                                        ? formatDecimal3Id(
                                                              Number(
                                                                  String(
                                                                      item.reservedCrt,
                                                                  ).replace(
                                                                      ',',
                                                                      '.',
                                                                  ),
                                                              ) /
                                                                  Number(
                                                                      String(
                                                                          item.reservedPcs,
                                                                      ).replace(
                                                                          ',',
                                                                          '.',
                                                                      ),
                                                                  ),
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
                                                <td className="spkStoneTableActionCol">
                                                    {stoneCanManageAllocation(
                                                        stone,
                                                    ) ? (
                                                        <button
                                                            type="button"
                                                            className="spkChainIssuedRowActionBtn spkChainIssuedRowActionBtn--danger"
                                                            aria-label="Hapus alokasi batu"
                                                            title="Hapus alokasi batu"
                                                            disabled={
                                                                revokingAllocationId ===
                                                                item.id
                                                            }
                                                            onClick={() => {
                                                                revokeAllocationItem(
                                                                    stone,
                                                                    item,
                                                                );
                                                            }}
                                                        >
                                                            <Icon
                                                                name={deleteIcon}
                                                                mode="Decorative"
                                                            />
                                                        </button>
                                                    ) : (
                                                        '-'
                                                    )}
                                                </td>
                                            </tr>
                                        ));
                                    })}
                                </tbody>
                                <tfoot>
                                    <tr
                                        className={
                                            allocatedTotalsShort
                                                ? 'spkStoneTableTotal spkStoneTableTotal--allocated spkStoneTableTotal--allocatedShort'
                                                : 'spkStoneTableTotal spkStoneTableTotal--allocated'
                                        }
                                    >
                                        <td colSpan={5}>
                                            Total batu dialokasikan
                                        </td>
                                        <td className="spkStoneTableTotalValue">
                                            {allocatedTotals.pcs}
                                        </td>
                                        <td className="spkStoneTableTotalValue">
                                            {formatStoneTableTotalCarat(
                                                allocatedTotals.carat,
                                            )}
                                        </td>
                                        <td />
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </section>

                {spk !== undefined ? (
                    <SpkStoneStockDialog
                        stone={stockListStone}
                        spk={spk}
                        onOpenChange={(open) => {
                            if (!open) {
                                setStockListStone(null);
                            }
                        }}
                    />
                ) : null}

                {spk !== undefined ? (
                    <SpkDiamondCreateDialog
                        open={createDiamondDialogOpen}
                        onOpenChange={setCreateDiamondDialogOpen}
                        spk={spk}
                        stones={stones}
                    />
                ) : null}
            </div>
        );
    }

    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader spkChainCardHeader">
                <div className="spkChainCardHeaderStart">
                    <h2 className="spkFioriDetailBlockTitle">Batu</h2>
                    <Text className="spkFioriDetailBlockMeta">
                        {stones.length} item
                    </Text>
                </div>
                {diamondHeaderActions}
            </div>

            {stones.length === 0 ? (
                <div className="spkStoneEmpty">
                    <Text>Tidak ada batu pada varian ini.</Text>
                </div>
            ) : (
                <div className="spkStoneTableWrap">
                    <table className="spkStoneTable spkStoneTable--withStatus">
                        <thead>
                            <tr>
                                <th>Posisi</th>
                                <th>Bentuk</th>
                                <th>Diameter / Dimensi PxL (mm)</th>
                                <th>CRT per Butir (pcs)</th>
                                <th>Jumlah Butir (pcs)</th>
                                <th>Total CRT</th>
                                <th className="spkStoneStatusCol">Status</th>
                                <th className="spkStoneTableActionCol">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {stones.map((stone) => {
                                const allocatedItems =
                                    stone.allocatedItems ?? [];
                                const allocatedAudit =
                                    formatStoneAllocatedAudit(allocatedItems);

                                return (
                                    <Fragment key={stone.id}>
                                        <tr>
                                    <td>
                                        {emptyDash(stone.positionName)}
                                        <MasterDiffHint
                                            current={stone.positionName}
                                            master={stone.master?.positionName}
                                        />
                                    </td>
                                    <td>
                                        {emptyDash(
                                            stone.shapeName || stone.shape,
                                        )}
                                        <MasterDiffHint
                                            current={
                                                stone.shapeName || stone.shape
                                            }
                                            master={stone.master?.shapeName}
                                        />
                                    </td>
                                    <td>
                                        {emptyDash(stone.size)}
                                        <MasterDiffHint
                                            current={stone.size}
                                            master={stone.master?.size}
                                        />
                                    </td>
                                    <td>
                                        {formatDecimal3Id(
                                            stone.caratPerPcs ?? stone.carat,
                                        )}
                                        <MasterDiffHint
                                            current={
                                                stone.caratPerPcs ?? stone.carat
                                            }
                                            master={stone.master?.caratPerPcs}
                                        />
                                    </td>
                                    <td>
                                        {emptyDash(stone.pcs)}
                                        <MasterDiffHint
                                            current={stone.pcs}
                                            master={stone.master?.pcs}
                                        />
                                    </td>
                                    <td>
                                        {formatDecimal3Id(stone.totalCarat)}
                                    </td>
                                    <td className="spkStoneStatusCol">
                                        <SpkStoneAllocationStatusCell
                                            stone={stone}
                                        />
                                    </td>
                                    <td className="spkStoneTableActionCol">
                                        <SpkStoneAllocationActionCell
                                            stone={stone}
                                            onShowStock={() =>
                                                setStockListStone(stone)
                                            }
                                        />
                                    </td>
                                </tr>
                                        {allocatedItems.length > 0 ? (
                                            <tr className="spkChainIssuedRow">
                                                <td colSpan={8}>
                                                    <div className="spkChainIssuedRowInner">
                                                        <div className="spkChainIssuedMeta">
                                                            <strong>
                                                                Batu yang
                                                                dialokasikan:
                                                            </strong>
                                                            {allocatedAudit !==
                                                            null ? (
                                                                <span className="spkChainIssuedMetaAudit">
                                                                    {
                                                                        allocatedAudit
                                                                    }
                                                                </span>
                                                            ) : null}
                                                            <span className="spkChainIssuedMetaDetail">
                                                                <SpkStoneAllocatedItemsTable
                                                                    items={
                                                                        allocatedItems
                                                                    }
                                                                    revokingAllocationId={
                                                                        revokingAllocationId
                                                                    }
                                                                    onRevokeItem={
                                                                        stoneCanManageAllocation(
                                                                            stone,
                                                                        )
                                                                            ? (
                                                                                  item,
                                                                              ) => {
                                                                                  if (
                                                                                      !window.confirm(
                                                                                          'Hapus alokasi batu ini dari SPK?',
                                                                                      )
                                                                                  ) {
                                                                                      return;
                                                                                  }

                                                                                  setRevokingAllocationId(
                                                                                      item.id,
                                                                                  );
                                                                                  router.post(
                                                                                      revokeStoneAllocationItem.url(
                                                                                          [
                                                                                              Number(
                                                                                                  stone.id,
                                                                                              ),
                                                                                              item.id,
                                                                                          ],
                                                                                      ),
                                                                                      {},
                                                                                      {
                                                                                          preserveScroll: true,
                                                                                          onFinish: () => {
                                                                                              setRevokingAllocationId(
                                                                                                  null,
                                                                                              );
                                                                                          },
                                                                                      },
                                                                                  );
                                                                              }
                                                                            : undefined
                                                                    }
                                                                />
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        ) : null}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                        <tfoot>
                            <tr className="spkStoneTableTotal">
                                <td colSpan={4}>Total kebutuhan batu</td>
                                <td className="spkStoneTableTotalValue">
                                    {totalButir}
                                </td>
                                <td className="spkStoneTableTotalValue">
                                    {formatStoneTableTotalCarat(totalCarat)}
                                </td>
                                <td />
                                <td />
                            </tr>
                            <tr
                                className={
                                    allocatedTotalsShort
                                        ? 'spkStoneTableTotal spkStoneTableTotal--allocated spkStoneTableTotal--allocatedShort'
                                        : 'spkStoneTableTotal spkStoneTableTotal--allocated'
                                }
                            >
                                <td colSpan={4}>Total batu dialokasikan</td>
                                <td className="spkStoneTableTotalValue">
                                    {allocatedTotals.pcs}
                                </td>
                                <td className="spkStoneTableTotalValue">
                                    {formatStoneTableTotalCarat(
                                        allocatedTotals.carat,
                                    )}
                                </td>
                                <td />
                                <td />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}

            <SpkStoneStockDialog
                stone={stockListStone}
                spk={spk}
                onOpenChange={(open) => {
                    if (!open) {
                        setStockListStone(null);
                    }
                }}
            />

            {spk !== undefined ? (
                <SpkDiamondCreateDialog
                    open={createDiamondDialogOpen}
                    onOpenChange={setCreateDiamondDialogOpen}
                    spk={spk}
                    stones={stones}
                />
            ) : null}
        </div>
    );
}

export type SpkFormStoneMaster = {
    positionNama: string;
    positionName: string;
    shapeId: string;
    shapeName: string;
    size: string;
    pcs: string;
    caratPerPcs: string;
};

export type SpkFormStoneRow = {
    id: string;
    positionId: string;
    positionName: string;
    positionNama: string;
    shapeId: string;
    shapeName: string;
    size: string;
    pcs: string;
    caratPerPcs: string;
    master?: SpkFormStoneMaster | null;
};

export function isStoneListChangedFromMaster(
    stones: SpkFormStoneRow[],
    masterCount: number,
): boolean {
    if (stones.length !== masterCount) {
        return stones.length > 0 || masterCount > 0;
    }

    return stones.some((stone) => {
        const master = stone.master;

        if (!master) {
            return true;
        }

        return (
            isMasterFieldChanged(
                stone.positionNama || stone.positionName,
                master.positionNama || master.positionName,
            ) ||
            isMasterFieldChanged(stone.shapeId, master.shapeId) ||
            isMasterFieldChanged(stone.size, master.size) ||
            isMasterFieldChanged(stone.caratPerPcs, master.caratPerPcs) ||
            isMasterFieldChanged(stone.pcs, master.pcs)
        );
    });
}

type ShapeOption = {
    value: string;
    label: string;
    name?: string;
};

type SpkFormStoneListCardProps = {
    stones: SpkFormStoneRow[];
    shapeOptions: ShapeOption[];
    positionOptions: ShapeOption[];
    onChange: (stones: SpkFormStoneRow[]) => void;
    errors?: Record<string, string | undefined>;
};

const emptyDraft = {
    positionId: '',
    positionName: '',
    positionNama: '',
    shapeId: '',
    size: '',
    pcs: '',
    caratPerPcs: '',
};

function formatTotalCarat(pcs: string, caratPerPcs: string): string {
    if (pcs === '' || caratPerPcs === '') {
        return '—';
    }

    const pcsValue = Number(pcs);
    const caratValue = Number(String(caratPerPcs).replace(',', '.'));

    if (!Number.isFinite(pcsValue) || !Number.isFinite(caratValue)) {
        return '—';
    }

    return (pcsValue * caratValue).toFixed(3);
}

function nextStoneId(): string {
    return `stone-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function PositionComboBox({
    positionId,
    positionNama,
    positionName,
    positionOptions,
    ariaLabel,
    onChange,
}: {
    positionId: string;
    positionNama: string;
    positionName: string;
    positionOptions: ShapeOption[];
    ariaLabel: string;
    onChange: (next: {
        positionId: string;
        positionNama: string;
        positionName: string;
    }) => void;
}) {
    const [text, setText] = useState(() => positionNama || positionName || '');

    const selectExisting = (nextId: string, nextLabel?: string) => {
        const matched = positionOptions.find(
            (option) => option.value === nextId,
        );
        const label = nextLabel ?? matched?.label ?? '';

        setText(label);
        onChange({
            positionId: nextId,
            positionNama: '',
            positionName: label,
        });
    };

    const selectNew = (typedName: string) => {
        const nama = typedName.trim();
        setText(nama);
        onChange({
            positionId: '',
            positionNama: nama,
            positionName: nama,
        });
    };

    const clear = () => {
        setText('');
        onChange({
            positionId: '',
            positionNama: '',
            positionName: '',
        });
    };

    return (
        <ComboBox
            accessibleName={ariaLabel}
            className="spkStoneRowComboBox"
            placeholder="Cari / ketik posisi baru"
            filter="Contains"
            showClearIcon
            noTypeahead
            value={text}
            onInput={(event) => {
                const nextText = event.target.value ?? '';
                setText(nextText);

                if (!nextText.trim()) {
                    clear();

                    return;
                }

                const selected = positionOptions.find(
                    (option) => option.value === positionId,
                );

                if (selected && selected.label !== nextText) {
                    onChange({
                        positionId: '',
                        positionNama: '',
                        positionName: '',
                    });
                }
            }}
            onSelectionChange={(event) => {
                const item = event.detail.item;

                if (!item?.value) {
                    return;
                }

                selectExisting(String(item.value), item.text ?? '');
            }}
            onChange={(event) => {
                const typed = event.target.value?.trim() ?? '';

                if (!typed) {
                    clear();

                    return;
                }

                const matched = positionOptions.find(
                    (option) =>
                        option.label.toLowerCase() === typed.toLowerCase(),
                );

                if (matched) {
                    selectExisting(matched.value, matched.label);

                    return;
                }

                selectNew(typed);
            }}
        >
            {positionOptions.map((option) => (
                <ComboBoxItem
                    key={option.value}
                    text={option.label}
                    value={option.value}
                />
            ))}
        </ComboBox>
    );
}

/** Daftar batu di form buat/edit SPK — bisa ditambah dan dihapus. */
export function SpkFormStoneListCard({
    stones,
    shapeOptions,
    positionOptions,
    onChange,
    errors = {},
}: SpkFormStoneListCardProps) {
    const [draft, setDraft] = useState(emptyDraft);
    const [draftKey, setDraftKey] = useState(0);
    const [stockListStone, setStockListStone] =
        useState<SpkStoneStockDialogStone | null>(null);
    const stockInputs = useMemo(
        () =>
            stones.map((stone) => ({
                id: stone.id,
                shapeId: stone.shapeId,
                pcs: stone.pcs,
                caratPerPcs: stone.caratPerPcs,
                size: stone.size,
            })),
        [stones],
    );
    const stockStateById = useSpkFormStoneStock(stockInputs);

    const totalButir = stones.reduce(
        (sum, stone) => sum + (Number(stone.pcs) || 0),
        0,
    );
    const totalCarat = stones.reduce((sum, stone) => {
        const value =
            Number(stone.pcs || 0) *
            Number(String(stone.caratPerPcs).replace(',', '.') || 0);

        return sum + (Number.isFinite(value) ? value : 0);
    }, 0);

    const draftTotal = formatTotalCarat(draft.pcs, draft.caratPerPcs);

    const draftHasValue =
        draft.positionId.trim() !== '' ||
        draft.positionNama.trim() !== '' ||
        draft.positionName.trim() !== '' ||
        draft.shapeId.trim() !== '' ||
        draft.pcs.trim() !== '' ||
        draft.caratPerPcs.trim() !== '' ||
        draft.size.trim() !== '';

    const updateDraft = (field: keyof typeof emptyDraft, value: string) => {
        setDraft((current) => ({ ...current, [field]: value }));
    };

    const handleAdd = () => {
        if (!draftHasValue) {
            return;
        }

        const shape = shapeOptions.find(
            (option) => option.value === draft.shapeId,
        );

        onChange([
            ...stones,
            {
                id: nextStoneId(),
                positionId: draft.positionId,
                positionName: draft.positionName,
                positionNama: draft.positionNama,
                shapeId: draft.shapeId,
                shapeName: shape?.name || shape?.label || '',
                size: draft.size,
                pcs: draft.pcs,
                caratPerPcs: draft.caratPerPcs,
            },
        ]);
        setDraft(emptyDraft);
        setDraftKey((current) => current + 1);
    };

    const handleRemove = (id: string) => {
        onChange(stones.filter((stone) => stone.id !== id));
    };

    const updateStone = (
        id: string,
        field: 'shapeId' | 'size' | 'pcs' | 'caratPerPcs',
        value: string,
    ) => {
        onChange(
            stones.map((stone) => {
                if (stone.id !== id) {
                    return stone;
                }

                if (field === 'shapeId') {
                    const shape = shapeOptions.find(
                        (option) => option.value === value,
                    );

                    return {
                        ...stone,
                        shapeId: value,
                        shapeName: shape?.name || shape?.label || '',
                    };
                }

                return { ...stone, [field]: value };
            }),
        );
    };

    const updateStonePosition = (
        id: string,
        next: {
            positionId: string;
            positionNama: string;
            positionName: string;
        },
    ) => {
        onChange(
            stones.map((stone) =>
                stone.id === id
                    ? {
                          ...stone,
                          ...next,
                      }
                    : stone,
            ),
        );
    };

    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader">
                <h2 className="spkFioriDetailBlockTitle">Batu</h2>
                <Text className="spkFioriDetailBlockMeta">
                    {stones.length} item
                </Text>
            </div>

            <div className="spkStoneTableWrap">
                <table className="spkStoneTable spkStoneTable--editable">
                    <thead>
                        <tr>
                            <th>Posisi</th>
                            <th>Bentuk</th>
                            <th>Diameter / Dimensi PxL (mm)</th>
                            <th>CRT per Butir (pcs)</th>
                            <th>Jumlah Butir (pcs)</th>
                            <th>Total CRT</th>
                            <th>Stok</th>
                            <th className="spkStoneTableActionCol">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="spkStoneTableDraftRow">
                            <td>
                                <PositionComboBox
                                    key={`draft-position-${draftKey}`}
                                    positionId={draft.positionId}
                                    positionNama={draft.positionNama}
                                    positionName={draft.positionName}
                                    positionOptions={positionOptions}
                                    ariaLabel="Posisi batu baru"
                                    onChange={(next) =>
                                        setDraft((current) => ({
                                            ...current,
                                            ...next,
                                        }))
                                    }
                                />
                            </td>
                            <td>
                                <select
                                    className="spkStoneRowSelect"
                                    value={draft.shapeId}
                                    aria-label="Bentuk batu baru"
                                    onChange={(event) =>
                                        updateDraft(
                                            'shapeId',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Pilih bentuk</option>
                                    {shapeOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </td>
                            <td>
                                <input
                                    className="spkStoneRowInput"
                                    type="text"
                                    inputMode="decimal"
                                    value={draft.size}
                                    placeholder="mm / PxL"
                                    aria-label="Ukuran batu baru"
                                    onChange={(event) =>
                                        updateDraft('size', event.target.value)
                                    }
                                />
                            </td>
                            <td>
                                <input
                                    className="spkStoneRowInput"
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    value={draft.caratPerPcs}
                                    placeholder="Crt/pcs"
                                    aria-label="Carat per butir baru"
                                    onChange={(event) =>
                                        updateDraft(
                                            'caratPerPcs',
                                            event.target.value,
                                        )
                                    }
                                />
                            </td>
                            <td>
                                <input
                                    className="spkStoneRowInput"
                                    type="number"
                                    min="0"
                                    step="1"
                                    value={draft.pcs}
                                    placeholder="pcs"
                                    aria-label="Jumlah butir baru"
                                    onChange={(event) =>
                                        updateDraft('pcs', event.target.value)
                                    }
                                />
                            </td>
                            <td>{draftTotal}</td>
                            <td>-</td>
                            <td>
                                <button
                                    type="button"
                                    className="spkStoneRowActionBtn"
                                    disabled={!draftHasValue}
                                    onClick={handleAdd}
                                >
                                    Tambah
                                </button>
                            </td>
                        </tr>

                        {stones.length === 0 ? (
                            <tr>
                                <td colSpan={8}>
                                    <div className="spkStoneEmpty">
                                        <Text>
                                            Belum ada batu. Isi baris di atas
                                            lalu klik Tambah.
                                        </Text>
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            stones.map((stone, index) => (
                                <tr key={stone.id}>
                                    <td>
                                        <PositionComboBox
                                            key={stone.id}
                                            positionId={stone.positionId}
                                            positionNama={stone.positionNama}
                                            positionName={stone.positionName}
                                            positionOptions={positionOptions}
                                            ariaLabel={`Posisi batu ${index + 1}`}
                                            onChange={(next) =>
                                                updateStonePosition(
                                                    stone.id,
                                                    next,
                                                )
                                            }
                                        />
                                        <MasterDiffHint
                                            current={
                                                stone.positionNama ||
                                                stone.positionName
                                            }
                                            master={
                                                stone.master?.positionNama ||
                                                stone.master?.positionName
                                            }
                                        />
                                        {errors[
                                            `stones.${index}.position_id`
                                        ] ||
                                        errors[
                                            `stones.${index}.position_nama`
                                        ] ? (
                                            <Text className="spkFioriError">
                                                {errors[
                                                    `stones.${index}.position_id`
                                                ] ??
                                                    errors[
                                                        `stones.${index}.position_nama`
                                                    ]}
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <select
                                            className="spkStoneRowSelect"
                                            value={stone.shapeId}
                                            aria-label={`Bentuk batu ${index + 1}`}
                                            onChange={(event) =>
                                                updateStone(
                                                    stone.id,
                                                    'shapeId',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Pilih bentuk
                                            </option>
                                            {shapeOptions.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                        <MasterDiffHint
                                            current={stone.shapeId}
                                            master={stone.master?.shapeId}
                                            displayMaster={
                                                stone.master?.shapeName ||
                                                stone.master?.shapeId
                                            }
                                        />
                                        {errors[`stones.${index}.shape_id`] ? (
                                            <Text className="spkFioriError">
                                                {
                                                    errors[
                                                        `stones.${index}.shape_id`
                                                    ]
                                                }
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <input
                                            className="spkStoneRowInput"
                                            type="text"
                                            inputMode="decimal"
                                            value={stone.size}
                                            placeholder="mm / PxL"
                                            aria-label={`Ukuran batu ${index + 1}`}
                                            onChange={(event) =>
                                                updateStone(
                                                    stone.id,
                                                    'size',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <MasterDiffHint
                                            current={stone.size}
                                            master={stone.master?.size}
                                        />
                                        {errors[`stones.${index}.size`] ? (
                                            <Text className="spkFioriError">
                                                {errors[`stones.${index}.size`]}
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <input
                                            className="spkStoneRowInput"
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            value={stone.caratPerPcs}
                                            aria-label={`Carat per butir ${index + 1}`}
                                            onChange={(event) =>
                                                updateStone(
                                                    stone.id,
                                                    'caratPerPcs',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <MasterDiffHint
                                            current={stone.caratPerPcs}
                                            master={stone.master?.caratPerPcs}
                                        />
                                        {errors[
                                            `stones.${index}.carat_per_pcs`
                                        ] ? (
                                            <Text className="spkFioriError">
                                                {
                                                    errors[
                                                        `stones.${index}.carat_per_pcs`
                                                    ]
                                                }
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        <input
                                            className="spkStoneRowInput"
                                            type="number"
                                            min="0"
                                            step="1"
                                            value={stone.pcs}
                                            aria-label={`Jumlah butir ${index + 1}`}
                                            onChange={(event) =>
                                                updateStone(
                                                    stone.id,
                                                    'pcs',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <MasterDiffHint
                                            current={stone.pcs}
                                            master={stone.master?.pcs}
                                        />
                                        {errors[`stones.${index}.pcs`] ? (
                                            <Text className="spkFioriError">
                                                {errors[`stones.${index}.pcs`]}
                                            </Text>
                                        ) : null}
                                    </td>
                                    <td>
                                        {formatTotalCarat(
                                            stone.pcs,
                                            stone.caratPerPcs,
                                        )}
                                    </td>
                                    <td>
                                        <SpkStoneStockCell
                                            stock={
                                                stockStateById[stone.id]?.stock
                                            }
                                            loading={
                                                stockStateById[stone.id]
                                                    ?.loading ?? false
                                            }
                                            failed={
                                                stockStateById[stone.id]
                                                    ?.failed ?? false
                                            }
                                            onShowList={() =>
                                                setStockListStone({
                                                    id: stone.id,
                                                    isPreview: true,
                                                    shapeId: stone.shapeId,
                                                    shapeName: stone.shapeName,
                                                    shape: stone.shapeName,
                                                    shapeCode: '',
                                                    size: stone.size,
                                                    pcs: Number(stone.pcs) || 0,
                                                    caratPerPcs:
                                                        stone.caratPerPcs,
                                                    carat: stone.caratPerPcs,
                                                    totalCarat: formatTotalCarat(
                                                        stone.pcs,
                                                        stone.caratPerPcs,
                                                    ),
                                                    stock:
                                                        stockStateById[stone.id]
                                                            ?.stock ?? null,
                                                })
                                            }
                                        />
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            className="spkStoneRowActionBtn spkStoneRowActionBtn--danger"
                                            onClick={() =>
                                                handleRemove(stone.id)
                                            }
                                        >
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                    <tfoot>
                        <tr className="spkStoneTableTotal">
                            <td colSpan={4}>Total</td>
                            <td className="spkStoneTableTotalValue">
                                {totalButir}
                            </td>
                            <td className="spkStoneTableTotalValue">
                                {totalCarat.toLocaleString('id-ID', {
                                    minimumFractionDigits: 3,
                                    maximumFractionDigits: 3,
                                })}
                            </td>
                            <td />
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>
            <SpkStoneStockDialog
                stone={stockListStone}
                onOpenChange={(open) => {
                    if (!open) {
                        setStockListStone(null);
                    }
                }}
            />
            {errors.stones ? (
                <Text className="spkFioriError">{errors.stones}</Text>
            ) : null}
        </div>
    );
}

export function SpkItemStoneCard({
    item,
    stones,
    notes,
}: SpkItemStoneCardProps) {
    const notesText = emptyDash(notes);

    return (
        <div className="spkItemStoneBody">
            <div className="spkItemDetailGrid">
                <div className="spkItemImageCol" aria-label="Gambar item">
                    {item.imageUrl ? (
                        <div className="spkItemImagePreview">
                            <img
                                src={item.imageUrl}
                                alt={
                                    item.name !== '-'
                                        ? `Gambar ${item.name}`
                                        : 'Gambar item'
                                }
                            />
                        </div>
                    ) : (
                        <div className="spkItemImagePlaceholder">
                            <Icon name={pictureIcon} mode="Decorative" />
                            <span>Gambar item</span>
                        </div>
                    )}
                </div>

                <div className="spkItemFieldsCol">
                    <table className="spkItemMetaTable spkItemMetaTable--sm">
                        <tbody>
                            <tr>
                                <th scope="row">Tipe Item | SKU</th>
                                <td>
                                    <SpkTypeProductItemValue item={item} />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Status Order</th>
                                <td>{emptyDash(item.statusOrderLabel)}</td>
                            </tr>
                            <tr>
                                <th scope="row">Qty</th>
                                <td>{emptyDash(item.qty)}</td>
                            </tr>
                            <tr>
                                <th scope="row">Ukuran</th>
                                <td>
                                    <div className="spkItemUkuranFields">
                                        <div className="spkItemUkuranField">
                                            <span className="spkItemUkuranLabel">
                                                Panjang (mm)
                                            </span>
                                            <strong>
                                                {emptyDash(item.diameter)}
                                            </strong>
                                        </div>
                                        <div className="spkItemUkuranField">
                                            <span className="spkItemUkuranLabel">
                                                Dimensi PxL (mm)
                                            </span>
                                            <strong>
                                                {emptyDash(item.dimensi)}
                                            </strong>
                                        </div>
                                        <div className="spkItemUkuranField">
                                            <span className="spkItemUkuranLabel">
                                                Ring Size
                                            </span>
                                            <strong>
                                                {emptyDash(item.ringSize)}
                                            </strong>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <GoldWeightRowLabel
                                        currentWeight={item.goldWeight}
                                        masterWeight={item.masterGoldWeight}
                                    />
                                </th>
                                <td>{formatDecimal2Id(item.goldWeight)}</td>
                            </tr>
                            <tr>
                                <th scope="row">Warna Emas</th>
                                <td>{emptyDash(item.goldColor)}</td>
                            </tr>
                            <tr>
                                <th scope="row">File JewelCAD 3D</th>
                                <td>{emptyDash(item.jwcad3d)}</td>
                            </tr>
                            <tr>
                                <th scope="row">Catatan</th>
                                <td className="spkItemNotesCell">
                                    {notesText}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <SpkStoneListCard stones={stones} />
                </div>
            </div>
        </div>
    );
}
