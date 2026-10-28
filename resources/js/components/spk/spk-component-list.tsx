import acceptIcon from '@ui5/webcomponents-icons/dist/accept.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import forwardIcon from '@ui5/webcomponents-icons/dist/forward.js';
import outboxIcon from '@ui5/webcomponents-icons/dist/outbox.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Text } from '@ui5/webcomponents-react/Text';
import { router } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';
import { SpkComponentAllocateDialog } from '@/components/spk/spk-component-allocate-dialog';
import {
    SpkComponentReceiptPrintDialog,
    openComponentReceiptPrintPreview,
} from '@/components/spk/spk-component-receipt-print-dialog';
import type {
    SpkComponentFulfillmentAction,
    SpkComponentFulfillmentStatus,
    SpkComponentIssuedUnit,
    SpkComponentItem,
    SpkComponentReceiptSummary,
    SpkComponentStock,
    SpkMsComponentOption,
} from '@/components/spk/types';
import {
    fulfillment as advanceComponentFulfillment,
    revokeAllocation as revokeComponentAllocation,
} from '@/routes/spk/components';

export type SpkFormComponentRow = {
    id: string;
    componentId: string;
    category: string;
    item: string;
    color: string;
    size: string;
};

type SpkFormComponentListCardProps = {
    components: SpkFormComponentRow[];
    componentOptions: SpkMsComponentOption[];
    onChange: (components: SpkFormComponentRow[]) => void;
    errors?: Record<string, string | undefined>;
};

type ComponentDraft = {
    componentId: string;
    category: string;
    item: string;
    color: string;
};

const COMPONENT_CATEGORY_OPTIONS = ['Komponen', 'Stopper'] as const;

const COMPONENT_COLOR_OPTIONS = [
    'White Gold',
    'Rose Gold',
    'Yellow Gold',
    'Two Tones',
] as const;

const emptyDraft = (): ComponentDraft => ({
    componentId: '',
    category: '',
    item: '',
    color: '',
});

function selectOptionsWithLegacy(
    allowed: readonly string[],
    currentValue: string,
): string[] {
    const value = currentValue.trim();

    if (value === '' || allowed.includes(value)) {
        return [...allowed];
    }

    return [value, ...allowed];
}

function componentOptionsForCategory(
    componentOptions: SpkMsComponentOption[],
    category: string,
): SpkMsComponentOption[] {
    const trimmedCategory = category.trim();

    if (trimmedCategory === '') {
        return componentOptions;
    }

    return componentOptions.filter(
        (option) => option.category === trimmedCategory,
    );
}

function resolveComponentId(
    component: Pick<SpkFormComponentRow, 'componentId' | 'category' | 'item'>,
    componentOptions: SpkMsComponentOption[],
): string {
    if (component.componentId.trim() !== '') {
        return component.componentId;
    }

    const category = component.category.trim();
    const item = component.item.trim();

    if (category === '' || item === '') {
        return '';
    }

    const match = componentOptions.find(
        (option) =>
            option.category === category &&
            (option.label === item || option.item === item),
    );

    return match?.value ?? '';
}

function applyComponentMasterSelection(
    componentId: string,
    componentOptions: SpkMsComponentOption[],
): Pick<ComponentDraft, 'componentId' | 'category' | 'item'> {
    const option = componentOptions.find((entry) => entry.value === componentId);

    if (option === undefined) {
        return {
            componentId: '',
            category: '',
            item: '',
        };
    }

    return {
        componentId: option.value,
        category: option.category,
        item: option.label,
    };
}

type ComponentFieldSelectProps = {
    value: string;
    ariaLabel: string;
    placeholder: string;
    options: readonly string[];
    onChange: (value: string) => void;
};

function ComponentFieldSelect({
    value,
    ariaLabel,
    placeholder,
    options,
    onChange,
}: ComponentFieldSelectProps) {
    return (
        <select
            className="spkStoneRowSelect"
            value={value}
            aria-label={ariaLabel}
            onChange={(event) => onChange(event.target.value)}
        >
            <option value="">{placeholder}</option>
            {selectOptionsWithLegacy(options, value).map((option) => (
                <option key={option} value={option}>
                    {option}
                </option>
            ))}
        </select>
    );
}

type ComponentMasterSelectProps = {
    value: string;
    ariaLabel: string;
    placeholder: string;
    options: SpkMsComponentOption[];
    legacyItem: string;
    onChange: (componentId: string) => void;
};

function ComponentMasterSelect({
    value,
    ariaLabel,
    placeholder,
    options,
    legacyItem,
    onChange,
}: ComponentMasterSelectProps) {
    const legacyLabel = legacyItem.trim();
    const hasLegacy =
        legacyLabel !== '' &&
        value === '' &&
        !options.some((option) => option.item === legacyLabel);

    return (
        <select
            className="spkStoneRowSelect"
            value={value}
            aria-label={ariaLabel}
            onChange={(event) => onChange(event.target.value)}
        >
            <option value="">{placeholder}</option>
            {hasLegacy ? (
                <option value="__legacy__">{legacyLabel} (legacy)</option>
            ) : null}
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

function newRowId(): string {
    return `component-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

export function mapProductionComponentsToForm(
    components: Array<{
        id: string;
        componentId?: string;
        category: string;
        item: string;
        color: string;
        size: string;
    }>,
): SpkFormComponentRow[] {
    return components.map((component) => ({
        id: component.id,
        componentId: component.componentId ?? '',
        category: component.category ?? '',
        item: component.item ?? '',
        color: component.color ?? '',
        size: component.size ?? '',
    }));
}

export function SpkFormComponentListCard({
    components,
    componentOptions,
    onChange,
    errors = {},
}: SpkFormComponentListCardProps) {
    const [draft, setDraft] = useState(emptyDraft);
    const [draftKey, setDraftKey] = useState(0);

    const draftMasterOptions = componentOptionsForCategory(
        componentOptions,
        draft.category,
    );

    const updateDraft = (field: keyof ComponentDraft, value: string) => {
        setDraft((current) => ({ ...current, [field]: value }));
    };

    const draftHasValue =
        draft.category.trim() !== '' ||
        draft.item.trim() !== '' ||
        draft.color.trim() !== '';

    const handleAdd = () => {
        if (!draftHasValue) {
            return;
        }

        onChange([
            ...components,
            {
                id: newRowId(),
                componentId: draft.componentId.trim(),
                category: draft.category.trim(),
                item: draft.item.trim(),
                color: draft.color.trim(),
                size: '',
            },
        ]);
        setDraft(emptyDraft());
        setDraftKey((current) => current + 1);
    };

    const handleRemove = (id: string) => {
        onChange(components.filter((component) => component.id !== id));
    };

    const updateComponent = (id: string, patch: Partial<ComponentDraft>) => {
        onChange(
            components.map((component) =>
                component.id === id ? { ...component, ...patch } : component,
            ),
        );
    };

    const handleDraftCategoryChange = (category: string) => {
        setDraft((current) => {
            const nextOptions = componentOptionsForCategory(
                componentOptions,
                category,
            );
            const stillValid = nextOptions.some(
                (option) => option.value === current.componentId,
            );

            if (stillValid) {
                return { ...current, category };
            }

            return {
                ...current,
                category,
                componentId: '',
                item: '',
            };
        });
    };

    const handleDraftComponentMasterChange = (componentId: string) => {
        if (componentId === '__legacy__') {
            return;
        }

        setDraft((current) => ({
            ...current,
            ...applyComponentMasterSelection(componentId, componentOptions),
        }));
    };

    const handleRowCategoryChange = (id: string, category: string) => {
        const component = components.find((entry) => entry.id === id);

        if (component === undefined) {
            return;
        }

        const nextOptions = componentOptionsForCategory(
            componentOptions,
            category,
        );
        const stillValid = nextOptions.some(
            (option) => option.value === component.componentId,
        );

        updateComponent(id, {
            category,
            componentId: stillValid ? component.componentId : '',
            item: stillValid ? component.item : '',
        });
    };

    const handleRowComponentMasterChange = (
        id: string,
        componentId: string,
    ) => {
        if (componentId === '__legacy__') {
            return;
        }

        updateComponent(
            id,
            applyComponentMasterSelection(componentId, componentOptions),
        );
    };

    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader">
                <h2 className="spkFioriDetailBlockTitle">Komponen</h2>
                <Text className="spkFioriDetailBlockMeta">
                    {components.length} baris
                </Text>
            </div>

            <div className="spkStoneTableWrap">
                <table className="spkStoneTable spkStoneTable--editable spkChainTable spkComponentTable">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Jenis Item Komponen</th>
                            <th>Warna</th>
                            <th className="spkStoneTableActionCol">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="spkStoneTableDraftRow">
                            <td>
                                <ComponentFieldSelect
                                    key={`draft-category-${draftKey}`}
                                    value={draft.category}
                                    ariaLabel="Kategori komponen baru"
                                    placeholder="Pilih kategori"
                                    options={COMPONENT_CATEGORY_OPTIONS}
                                    onChange={handleDraftCategoryChange}
                                />
                            </td>
                            <td>
                                <ComponentMasterSelect
                                    key={`draft-item-${draftKey}`}
                                    value={draft.componentId}
                                    ariaLabel="Jenis item komponen baru"
                                    placeholder="Pilih jenis item komponen"
                                    options={draftMasterOptions}
                                    legacyItem={draft.item}
                                    onChange={handleDraftComponentMasterChange}
                                />
                            </td>
                            <td>
                                <ComponentFieldSelect
                                    key={`draft-color-${draftKey}`}
                                    value={draft.color}
                                    ariaLabel="Warna komponen baru"
                                    placeholder="Pilih warna"
                                    options={COMPONENT_COLOR_OPTIONS}
                                    onChange={(next) =>
                                        updateDraft('color', next)
                                    }
                                />
                            </td>
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

                        {components.length === 0 ? (
                            <tr>
                                <td colSpan={4}>
                                    <div className="spkStoneEmpty">
                                        <Text>
                                            Belum ada komponen. Isi baris di atas
                                            lalu klik Tambah.
                                        </Text>
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            components.map((component, index) => {
                                const rowMasterOptions =
                                    componentOptionsForCategory(
                                        componentOptions,
                                        component.category,
                                    );
                                const rowComponentId = resolveComponentId(
                                    component,
                                    componentOptions,
                                );

                                return (
                                    <tr key={component.id}>
                                        <td>
                                            <ComponentFieldSelect
                                                value={component.category}
                                                ariaLabel={`Kategori komponen ${index + 1}`}
                                                placeholder="Pilih kategori"
                                                options={
                                                    COMPONENT_CATEGORY_OPTIONS
                                                }
                                                onChange={(next) =>
                                                    handleRowCategoryChange(
                                                        component.id,
                                                        next,
                                                    )
                                                }
                                            />
                                            {errors[
                                                `components.${index}.category`
                                            ] ? (
                                                <Text className="spkFioriError">
                                                    {
                                                        errors[
                                                            `components.${index}.category`
                                                        ]
                                                    }
                                                </Text>
                                            ) : null}
                                        </td>
                                        <td>
                                            <ComponentMasterSelect
                                                value={rowComponentId}
                                                ariaLabel={`Jenis item komponen ${index + 1}`}
                                                placeholder="Pilih jenis item komponen"
                                                options={rowMasterOptions}
                                                legacyItem={component.item}
                                                onChange={(next) =>
                                                    handleRowComponentMasterChange(
                                                        component.id,
                                                        next,
                                                    )
                                                }
                                            />
                                            {errors[`components.${index}.item`] ? (
                                                <Text className="spkFioriError">
                                                    {
                                                        errors[
                                                            `components.${index}.item`
                                                        ]
                                                    }
                                                </Text>
                                            ) : null}
                                        </td>
                                        <td>
                                            <ComponentFieldSelect
                                                value={component.color}
                                                ariaLabel={`Warna komponen ${index + 1}`}
                                                placeholder="Pilih warna"
                                                options={COMPONENT_COLOR_OPTIONS}
                                                onChange={(next) =>
                                                    updateComponent(
                                                        component.id,
                                                        { color: next },
                                                    )
                                                }
                                            />
                                            {errors[
                                                `components.${index}.color`
                                            ] ? (
                                                <Text className="spkFioriError">
                                                    {
                                                        errors[
                                                            `components.${index}.color`
                                                        ]
                                                    }
                                                </Text>
                                            ) : null}
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                className="spkStoneRowActionBtn spkStoneRowActionBtn--danger"
                                                onClick={() =>
                                                    handleRemove(component.id)
                                                }
                                            >
                                                Hapus
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
            {errors.components ? (
                <Text className="spkFioriError">{errors.components}</Text>
            ) : null}
        </div>
    );
}

function emptyDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const text = String(value).trim();

    return text !== '' ? text : '-';
}

function formatIssuedUnitAudit(unit: SpkComponentIssuedUnit): string | null {
    const parts: string[] = [];

    if (unit.allocatedDate !== null && unit.allocatedDate.trim() !== '') {
        parts.push(`Tanggal: ${unit.allocatedDate}`);
    }

    if (unit.allocatedBy !== null && unit.allocatedBy.trim() !== '') {
        parts.push(`Oleh: ${unit.allocatedBy}`);
    }

    return parts.length > 0 ? parts.join(' · ') : null;
}

function workshopReceivedAuditLines(
    workshopReceived?: SpkComponentItem['workshopReceived'],
): { received: string | null; by: string | null } {
    if (workshopReceived === undefined || workshopReceived === null) {
        return { received: null, by: null };
    }

    const received =
        workshopReceived.at !== null && workshopReceived.at.trim() !== ''
            ? `Diterima: ${workshopReceived.at}`
            : null;

    const by =
        workshopReceived.by !== null && workshopReceived.by.trim() !== ''
            ? `Oleh: ${workshopReceived.by}`
            : null;

    return { received, by };
}

function SpkComponentIssuedUnitTable({
    unit,
}: {
    unit: SpkComponentIssuedUnit;
}) {
    const unitLabel =
        unit.unitNo !== null && unit.unitNo.trim() !== ''
            ? unit.unitNo
            : String(unit.id);
    const weightLabel =
        unit.weight !== null && unit.weight.trim() !== ''
            ? `${unit.weight} g`
            : '—';

    return (
        <table className="spkChainIssuedUnitTable">
            <thead>
                <tr>
                    <th className="spkChainIssuedColUnit">Unit</th>
                    <th className="spkChainIssuedColName">Nama</th>
                    <th className="spkChainIssuedColColor">Warna</th>
                    <th className="spkChainIssuedColWeight">Berat</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td className="spkChainIssuedColUnit tabular-nums">
                        {unitLabel}
                    </td>
                    <td className="spkChainIssuedColName">
                        {emptyDash(unit.name)}
                    </td>
                    <td className="spkChainIssuedColColor">
                        {emptyDash(unit.color)}
                    </td>
                    <td className="spkChainIssuedColWeight tabular-nums">
                        {weightLabel}
                    </td>
                </tr>
            </tbody>
        </table>
    );
}

function componentStockStatusLabel(
    stock?: SpkComponentStock | null,
): { text: string; available: boolean } | null {
    if (stock === undefined || stock === null) {
        return null;
    }

    const available = stock.status === 'available';

    return {
        text: available ? 'Stok Ada' : 'Stok Tidak Ada',
        available,
    };
}

function componentFulfillmentStatusBadgeClass(
    status: SpkComponentFulfillmentStatus,
): string {
    const base = 'spkStoneStockBadge';

    switch (status) {
        case 'allocated':
            return `${base} is-available`;
        case 'sent_to_workshop':
            return `${base} spkChainFulfillmentBadge--progress`;
        case 'received_by_workshop':
            return `${base} spkChainFulfillmentBadge--done`;
        case 'installed':
            return `${base} is-available`;
        case 'not_allocated':
        case 'unlinked':
        default:
            return `${base} is-unavailable`;
    }
}

function componentFulfillmentStatusLabel(
    allocation?: SpkComponentItem['allocation'],
): { text: string; allocated: boolean; badgeClass: string } | null {
    if (allocation === undefined || allocation === null) {
        return null;
    }

    const allocated =
        allocation.status !== 'not_allocated' &&
        allocation.status !== 'unlinked' &&
        allocation.allocatedUnits > 0;

    const text =
        allocation.label.trim() !== ''
            ? allocation.label
            : allocated
              ? 'Sudah Dialokasikan'
              : 'Belum Dialokasikan';

    return {
        text,
        allocated,
        badgeClass: componentFulfillmentStatusBadgeClass(allocation.status),
    };
}

function componentCanManageAllocation(component: SpkComponentItem): boolean {
    return (
        component.issuedUnit !== undefined &&
        component.issuedUnit !== null &&
        component.allocation?.status === 'allocated'
    );
}

const COMPONENT_RECEIPT_PRINTABLE_STATUSES: SpkComponentFulfillmentStatus[] = [
    'allocated',
    'sent_to_workshop',
    'received_by_workshop',
    'installed',
];

function componentReceiptPrintEnabled(
    components: SpkComponentItem[],
): boolean {
    if (components.length === 0) {
        return false;
    }

    return components.every((component) => {
        const status = component.allocation?.status;

        return (
            status !== undefined &&
            COMPONENT_RECEIPT_PRINTABLE_STATUSES.includes(status)
        );
    });
}

function componentNeedsReceiptBeforeWorkshop(
    components: SpkComponentItem[],
): boolean {
    return components.some(
        (component) => component.allocation?.status === 'allocated',
    );
}

function componentReceiptPrintDisabledTitle(
    components: SpkComponentItem[],
): string {
    if (components.length === 0) {
        return 'Tidak ada komponen pada SPK ini';
    }

    const hasNotAllocated = components.some((component) => {
        const status = component.allocation?.status;

        return (
            status === 'not_allocated' ||
            status === 'unlinked' ||
            status === undefined
        );
    });

    if (hasNotAllocated) {
        return 'Alokasikan komponen terlebih dahulu';
    }

    return 'Tanda terima tidak tersedia';
}

function workshopReceiverNameForComponentReceipt(
    components: SpkComponentItem[],
): string {
    for (const component of components) {
        const status = component.allocation?.status;

        if (
            status !== 'received_by_workshop' &&
            status !== 'installed'
        ) {
            continue;
        }

        const by = component.workshopReceived?.by?.trim() ?? '';

        if (by !== '') {
            return by;
        }
    }

    return '';
}

type SpkComponentListCardProps = {
    components: SpkComponentItem[];
    spk?: {
        rowId: number;
        produksiNo: string;
    };
    receiptEmployeeOptions?: string[];
    componentReceipt?: SpkComponentReceiptSummary | null;
};

const STATUS_COLUMN_ACTIONS: SpkComponentFulfillmentAction[] = [
    'allocate',
    'send_to_workshop',
    'receive_workshop',
];

function SpkComponentFulfillmentActionButton({
    component,
    disabled,
    onAllocate,
    onSendToProduction,
    sendToProductionDisabled = false,
    sendToProductionDisabledTitle = 'Tanda terima tidak tersedia',
    onlyActions,
}: {
    component: SpkComponentItem;
    disabled?: boolean;
    onAllocate: () => void;
    onSendToProduction: () => void;
    sendToProductionDisabled?: boolean;
    sendToProductionDisabledTitle?: string;
    onlyActions?: SpkComponentFulfillmentAction[];
}) {
    const allocation = component.allocation;
    const nextAction = allocation?.nextAction;
    const label = allocation?.nextActionLabel;

    const [processing, setProcessing] = useState(false);

    if (
        allocation === undefined ||
        allocation === null ||
        nextAction === null ||
        nextAction === undefined ||
        label === null ||
        label === ''
    ) {
        return null;
    }

    if (
        onlyActions !== undefined &&
        !onlyActions.includes(nextAction)
    ) {
        return null;
    }

    const allocateTitle = 'Alokasikan unit komponen ke SPK ini';

    const actionTitle =
        nextAction === 'allocate' ? allocateTitle : label;

    const handleClick = (): void => {
        if (nextAction === 'allocate') {
            onAllocate();

            return;
        }

        if (nextAction === 'send_to_workshop') {
            if (sendToProductionDisabled) {
                toast.warning(sendToProductionDisabledTitle);

                return;
            }

            onSendToProduction();

            return;
        }

        setProcessing(true);
        router.post(
            advanceComponentFulfillment.url(Number(component.id)),
            { action: nextAction },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                },
            },
        );
    };

    if (nextAction === 'allocate') {
        return (
            <button
                type="button"
                className="spkChainFulfillmentActionBtn"
                disabled={disabled === true || processing}
                title={actionTitle}
                onClick={handleClick}
            >
                <Icon name={outboxIcon} mode="Decorative" />
                <span className="spkChainFulfillmentActionBtnLabel">
                    Alokasi Komponen
                </span>
            </button>
        );
    }

    if (nextAction === 'send_to_workshop') {
        const sendTitle = sendToProductionDisabled
            ? sendToProductionDisabledTitle
            : 'Buat dan cetak tanda terima serah terima ke produksi';

        return (
            <button
                type="button"
                className="spkChainFulfillmentActionBtn spkChainFulfillmentActionBtn--send"
                disabled={sendToProductionDisabled || processing}
                title={sendTitle}
                onClick={handleClick}
            >
                <Icon name={forwardIcon} mode="Decorative" />
                <span className="spkChainFulfillmentActionBtnLabel">
                    Kirim ke Produksi
                </span>
            </button>
        );
    }

    if (nextAction === 'receive_workshop') {
        return (
            <button
                type="button"
                className="spkChainFulfillmentReceiveBtn"
                disabled={processing}
                title={actionTitle}
                onClick={handleClick}
            >
                <Icon name={acceptIcon} mode="Decorative" />
                <span className="spkChainFulfillmentActionBtnLabel">
                    {processing ? 'Memproses…' : label}
                </span>
            </button>
        );
    }

    return null;
}

function SpkComponentFulfillmentActionCell({
    component,
    onAllocate,
    onSendToProduction,
    sendToProductionDisabled,
    sendToProductionDisabledTitle,
}: {
    component: SpkComponentItem;
    onAllocate: () => void;
    onSendToProduction: () => void;
    sendToProductionDisabled: boolean;
    sendToProductionDisabledTitle: string;
}) {
    return (
        <div className="spkChainTableActionCell">
            <SpkComponentFulfillmentActionButton
                component={component}
                onlyActions={STATUS_COLUMN_ACTIONS}
                onAllocate={onAllocate}
                onSendToProduction={onSendToProduction}
                sendToProductionDisabled={sendToProductionDisabled}
                sendToProductionDisabledTitle={sendToProductionDisabledTitle}
            />
        </div>
    );
}

function SpkComponentFulfillmentStatusCell({
    component,
}: {
    component: SpkComponentItem;
}) {
    const stockLabel = componentStockStatusLabel(component.stock);
    const fulfillmentLabel = componentFulfillmentStatusLabel(
        component.allocation,
    );
    const workshopReceivedAudit = workshopReceivedAuditLines(
        component.workshopReceived,
    );
    const showWorkshopReceivedAudit =
        workshopReceivedAudit.received !== null ||
        workshopReceivedAudit.by !== null;
    const showStockLabel =
        stockLabel !== null &&
        !(
            fulfillmentLabel?.allocated === true && !stockLabel.available
        );

    return (
        <div className="spkChainStatusCell spkChainStatusCell--labelsOnly">
            <div className="spkChainStatusLabels">
                {showStockLabel ? (
                    <div className="spkChainStatusLabelRow">
                        <span
                            className={
                                stockLabel.available
                                    ? 'spkStoneStockBadge is-available'
                                    : 'spkStoneStockBadge is-unavailable'
                            }
                            title={component.stock?.note ?? undefined}
                        >
                            {stockLabel.text}
                        </span>
                    </div>
                ) : stockLabel === null ? (
                    <div className="spkChainStatusLabelRow">
                        <span className="spkStoneStockMeta">—</span>
                    </div>
                ) : null}
                <div className="spkChainStatusLabelRow">
                    {fulfillmentLabel !== null ? (
                        <span className={fulfillmentLabel.badgeClass}>
                            {fulfillmentLabel.text}
                        </span>
                    ) : (
                        <span className="spkStoneStockMeta">—</span>
                    )}
                </div>
                {showWorkshopReceivedAudit ? (
                    <>
                        {workshopReceivedAudit.received !== null ? (
                            <div className="spkChainStatusLabelRow">
                                <span className="spkChainWorkshopReceivedAuditLine">
                                    {workshopReceivedAudit.received}
                                </span>
                            </div>
                        ) : null}
                        {workshopReceivedAudit.by !== null ? (
                            <div className="spkChainStatusLabelRow">
                                <span className="spkChainWorkshopReceivedAuditLine">
                                    {workshopReceivedAudit.by}
                                </span>
                            </div>
                        ) : null}
                    </>
                ) : null}
            </div>
        </div>
    );
}

export function SpkComponentListCard({
    components,
    spk,
    receiptEmployeeOptions = [],
    componentReceipt = null,
}: SpkComponentListCardProps) {
    const showWorkflow = spk !== undefined;
    const [allocateComponent, setAllocateComponent] =
        useState<SpkComponentItem | null>(null);
    const [revokingComponentId, setRevokingComponentId] = useState<
        string | null
    >(null);
    const [receiptDialogOpen, setReceiptDialogOpen] = useState(false);

    const canPrintReceipt =
        showWorkflow && componentReceiptPrintEnabled(components);
    const hasExistingReceipt =
        componentReceipt !== null &&
        componentReceipt !== undefined &&
        componentReceipt.printUrl.trim() !== '';
    const printReceiptTitle = !canPrintReceipt
        ? componentReceiptPrintDisabledTitle(components)
        : hasExistingReceipt
          ? `Buka preview tanda terima ${componentReceipt.docNo}`
          : components.some(
                  (component) =>
                      component.allocation?.status === 'sent_to_workshop' ||
                      component.allocation?.status ===
                          'received_by_workshop' ||
                      component.allocation?.status === 'installed',
              )
            ? 'Cetak ulang tanda terima serah terima komponen ke workshop'
            : 'Buat dan cetak tanda terima serah terima komponen ke workshop';

    const openReceiptPreview = (printUrl: string): void => {
        openComponentReceiptPrintPreview(printUrl);
    };

    const openReceiptDialog = (): void => {
        if (!canPrintReceipt) {
            toast.warning(componentReceiptPrintDisabledTitle(components));

            return;
        }

        if (hasExistingReceipt && componentReceipt !== null) {
            if (componentNeedsReceiptBeforeWorkshop(components)) {
                setReceiptDialogOpen(true);

                return;
            }

            openReceiptPreview(componentReceipt.printUrl);

            return;
        }

        setReceiptDialogOpen(true);
    };

    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader spkChainCardHeader">
                <div className="spkChainCardHeaderStart">
                    <h2 className="spkFioriDetailBlockTitle">Komponen</h2>
                    <Text className="spkFioriDetailBlockMeta">
                        {components.length} baris
                    </Text>
                </div>
                {showWorkflow && components.length > 0 ? (
                    <button
                        type="button"
                        className="spkChainPrintReceiptBtn"
                        aria-label="Print Tanda Terima"
                        title={printReceiptTitle}
                        onClick={openReceiptDialog}
                    >
                        <Icon name={printIcon} mode="Decorative" />
                        <span className="spkChainPrintReceiptBtnLabel">
                            Tanda Terima Komponen
                        </span>
                    </button>
                ) : null}
            </div>

            {showWorkflow &&
            components.length > 0 &&
            componentNeedsReceiptBeforeWorkshop(components) ? (
                <p className="spkChainReceiptReminder" role="status">
                    Buat tanda terima untuk kirim ke workshop.
                </p>
            ) : null}

            {components.length === 0 ? (
                <div className="spkStoneEmpty">
                    <Text>Tidak ada komponen pada SPK ini.</Text>
                </div>
            ) : (
                <div className="spkStoneTableWrap">
                    <table className="spkStoneTable spkChainTable spkComponentTable">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Jenis Item Komponen</th>
                                <th>Warna</th>
                                {showWorkflow ? (
                                    <>
                                        <th className="spkChainStatusCol">
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
                            {components.map((component) => (
                                <Fragment key={component.id}>
                                    <tr>
                                        <td>{emptyDash(component.category)}</td>
                                        <td>{emptyDash(component.item)}</td>
                                        <td>{emptyDash(component.color)}</td>
                                        {showWorkflow ? (
                                            <>
                                                <td className="spkChainStatusCol">
                                                    <SpkComponentFulfillmentStatusCell
                                                        component={component}
                                                    />
                                                </td>
                                                <td className="spkStoneTableActionCol">
                                                    <SpkComponentFulfillmentActionCell
                                                        component={component}
                                                        onAllocate={() =>
                                                            setAllocateComponent(
                                                                component,
                                                            )
                                                        }
                                                        onSendToProduction={
                                                            openReceiptDialog
                                                        }
                                                        sendToProductionDisabled={
                                                            !canPrintReceipt
                                                        }
                                                        sendToProductionDisabledTitle={componentReceiptPrintDisabledTitle(
                                                            components,
                                                        )}
                                                    />
                                                </td>
                                            </>
                                        ) : null}
                                    </tr>
                                    {component.issuedUnit !== undefined &&
                                    component.issuedUnit !== null ? (
                                        <tr className="spkChainIssuedRow">
                                            <td
                                                colSpan={
                                                    showWorkflow ? 5 : 3
                                                }
                                            >
                                                {(() => {
                                                    const issuedAudit =
                                                        formatIssuedUnitAudit(
                                                            component.issuedUnit,
                                                        );

                                                    return (
                                                        <div className="spkChainIssuedRowInner">
                                                            <div className="spkChainIssuedMeta">
                                                                <strong>
                                                                    Komponen yang
                                                                    dialokasikan:
                                                                </strong>
                                                                {issuedAudit !==
                                                                null ? (
                                                                    <span className="spkChainIssuedMetaAudit">
                                                                        {
                                                                            issuedAudit
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                                <span className="spkChainIssuedMetaDetail">
                                                                    <SpkComponentIssuedUnitTable
                                                                        unit={
                                                                            component.issuedUnit
                                                                        }
                                                                    />
                                                                </span>
                                                            </div>
                                                            {showWorkflow &&
                                                            componentCanManageAllocation(
                                                                component,
                                                            ) ? (
                                                                <div className="spkChainIssuedRowActions">
                                                                    <button
                                                                        type="button"
                                                                        className="spkChainIssuedRowActionBtn spkChainIssuedRowActionBtn--danger"
                                                                        aria-label="Hapus alokasi komponen"
                                                                        title="Hapus alokasi komponen"
                                                                        disabled={
                                                                            revokingComponentId ===
                                                                            component.id
                                                                        }
                                                                        onClick={() => {
                                                                            setRevokingComponentId(
                                                                                component.id,
                                                                            );
                                                                            router.post(
                                                                                revokeComponentAllocation.url(
                                                                                    Number(
                                                                                        component.id,
                                                                                    ),
                                                                                ),
                                                                                {},
                                                                                {
                                                                                    preserveScroll: true,
                                                                                    onFinish: () => {
                                                                                        setRevokingComponentId(
                                                                                            null,
                                                                                        );
                                                                                    },
                                                                                },
                                                                            );
                                                                        }}
                                                                    >
                                                                        <Icon
                                                                            name={
                                                                                deleteIcon
                                                                            }
                                                                            mode="Decorative"
                                                                        />
                                                                    </button>
                                                                </div>
                                                            ) : null}
                                                        </div>
                                                    );
                                                })()}
                                            </td>
                                        </tr>
                                    ) : null}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {showWorkflow && spk !== undefined ? (
                <>
                    <SpkComponentAllocateDialog
                        component={allocateComponent}
                        spk={spk}
                        open={allocateComponent !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setAllocateComponent(null);
                            }
                        }}
                    />
                    <SpkComponentReceiptPrintDialog
                        open={receiptDialogOpen}
                        onOpenChange={setReceiptDialogOpen}
                        onPrinted={() =>
                            router.reload({
                                only: ['components', 'componentReceipt'],
                                preserveScroll: true,
                            })
                        }
                        spkRowId={spk.rowId}
                        componentLineIds={components.map((component) =>
                            Number(component.id),
                        )}
                        employeeOptions={receiptEmployeeOptions}
                        defaultDiterimaOleh={workshopReceiverNameForComponentReceipt(
                            components,
                        )}
                    />
                </>
            ) : null}
        </div>
    );
}
