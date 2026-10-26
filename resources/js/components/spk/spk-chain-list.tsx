import inboxIcon from '@ui5/webcomponents-icons/dist/inbox.js';
import outboxIcon from '@ui5/webcomponents-icons/dist/outbox.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Text } from '@ui5/webcomponents-react/Text';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { SpkChainAllocateDialog } from '@/components/spk/spk-chain-allocate-dialog';
import { SpkChainReceiptPrintDialog } from '@/components/spk/spk-chain-receipt-print-dialog';
import type {
    SpkChainFulfillmentAction,
    SpkChainItem,
    SpkChainStock,
    SpkMsChainOption,
} from '@/components/spk/types';
import { fulfillment as advanceChainFulfillment } from '@/routes/spk/chains';

export type SpkFormChainRow = {
    id: string;
    chainId: string;
    category: string;
    item: string;
    color: string;
    size: string;
};

type SpkFormChainListCardProps = {
    chains: SpkFormChainRow[];
    chainOptions: SpkMsChainOption[];
    onChange: (chains: SpkFormChainRow[]) => void;
    errors?: Record<string, string | undefined>;
};

type ChainDraft = {
    chainId: string;
    category: string;
    item: string;
    color: string;
    size: string;
};

const CHAIN_CATEGORY_OPTIONS = ['Bracelet Chain', 'Pendant Chain'] as const;

const CHAIN_COLOR_OPTIONS = [
    'White Gold',
    'Rose Gold',
    'Yellow Gold',
    'Two Tones',
] as const;

const emptyDraft = (): ChainDraft => ({
    chainId: '',
    category: '',
    item: '',
    color: '',
    size: '',
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

function normalizeChainSize(value: string): string {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '';
    }

    const normalized = trimmed.replace(',', '.');

    if (/^\d+(\.\d+)?$/.test(normalized)) {
        return normalized.replace(/\.?0+$/, '').replace(/\.$/, '');
    }

    const match = trimmed.match(/(\d+(?:[.,]\d+)?)/);

    if (match === null) {
        return '';
    }

    return match[1].replace(',', '.').replace(/\.?0+$/, '').replace(/\.$/, '');
}

function chainOptionsForCategory(
    chainOptions: SpkMsChainOption[],
    category: string,
): SpkMsChainOption[] {
    const trimmedCategory = category.trim();

    if (trimmedCategory === '') {
        return chainOptions;
    }

    return chainOptions.filter(
        (option) => option.category === trimmedCategory,
    );
}

function resolveChainId(
    chain: Pick<SpkFormChainRow, 'chainId' | 'category' | 'item' | 'size'>,
    chainOptions: SpkMsChainOption[],
): string {
    if (chain.chainId.trim() !== '') {
        return chain.chainId;
    }

    const category = chain.category.trim();
    const item = chain.item.trim();
    const size = normalizeChainSize(chain.size);

    const match = chainOptions.find(
        (option) =>
            option.category === category &&
            option.item === item &&
            normalizeChainSize(option.size) === size,
    );

    return match?.value ?? '';
}

function applyChainMasterSelection(
    chainId: string,
    chainOptions: SpkMsChainOption[],
): Pick<ChainDraft, 'chainId' | 'category' | 'item' | 'size'> {
    const option = chainOptions.find((entry) => entry.value === chainId);

    if (option === undefined) {
        return {
            chainId: '',
            category: '',
            item: '',
            size: '',
        };
    }

    return {
        chainId: option.value,
        category: option.category,
        item: option.label,
        size: option.size,
    };
}

type ChainFieldSelectProps = {
    value: string;
    ariaLabel: string;
    placeholder: string;
    options: readonly string[];
    onChange: (value: string) => void;
};

function ChainFieldSelect({
    value,
    ariaLabel,
    placeholder,
    options,
    onChange,
}: ChainFieldSelectProps) {
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

type ChainMasterSelectProps = {
    value: string;
    ariaLabel: string;
    placeholder: string;
    options: SpkMsChainOption[];
    legacyItem: string;
    onChange: (chainId: string) => void;
};

function ChainMasterSelect({
    value,
    ariaLabel,
    placeholder,
    options,
    legacyItem,
    onChange,
}: ChainMasterSelectProps) {
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
    return `chain-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

function chainSizeInputValue(value: string): string {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '';
    }

    const normalized = trimmed.replace(',', '.');

    if (/^\d+(\.\d+)?$/.test(normalized)) {
        return normalized;
    }

    const match = trimmed.match(/(\d+(?:[.,]\d+)?)/);

    if (match === null) {
        return '';
    }

    return match[1].replace(',', '.');
}

function formatChainSizeDisplay(value: string): string {
    const text = value.trim();

    if (text === '') {
        return '-';
    }

    const numeric = text.replace(',', '.');

    if (/^\d+(\.\d+)?$/.test(numeric)) {
        return `${numeric} cm`;
    }

    return text;
}

type ChainSizeInputProps = {
    value: string;
    ariaLabel: string;
    onChange: (value: string) => void;
};

function ChainSizeInput({ value, ariaLabel, onChange }: ChainSizeInputProps) {
    return (
        <input
            className="spkStoneRowInput"
            type="number"
            min="0"
            step="0.01"
            inputMode="decimal"
            value={chainSizeInputValue(value)}
            placeholder="Ukuran (cm)"
            aria-label={ariaLabel}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

export function mapProductionChainsToForm(
    chains: Array<{
        id: string;
        chainId?: string;
        category: string;
        item: string;
        color: string;
        size: string;
    }>,
): SpkFormChainRow[] {
    return chains.map((chain) => ({
        id: chain.id,
        chainId: chain.chainId ?? '',
        category: chain.category ?? '',
        item: chain.item ?? '',
        color: chain.color ?? '',
        size: chain.size ?? '',
    }));
}

export function SpkFormChainListCard({
    chains,
    chainOptions,
    onChange,
    errors = {},
}: SpkFormChainListCardProps) {
    const [draft, setDraft] = useState(emptyDraft);
    const [draftKey, setDraftKey] = useState(0);

    const draftMasterOptions = chainOptionsForCategory(
        chainOptions,
        draft.category,
    );

    const updateDraft = (field: keyof ChainDraft, value: string) => {
        setDraft((current) => ({ ...current, [field]: value }));
    };

    const draftHasValue =
        draft.category.trim() !== '' ||
        draft.item.trim() !== '' ||
        draft.color.trim() !== '' ||
        draft.size.trim() !== '';

    const handleAdd = () => {
        if (!draftHasValue) {
            return;
        }

        onChange([
            ...chains,
            {
                id: newRowId(),
                chainId: draft.chainId.trim(),
                category: draft.category.trim(),
                item: draft.item.trim(),
                color: draft.color.trim(),
                size: draft.size.trim(),
            },
        ]);
        setDraft(emptyDraft());
        setDraftKey((current) => current + 1);
    };

    const handleRemove = (id: string) => {
        onChange(chains.filter((chain) => chain.id !== id));
    };

    const updateChain = (id: string, patch: Partial<ChainDraft>) => {
        onChange(
            chains.map((chain) =>
                chain.id === id ? { ...chain, ...patch } : chain,
            ),
        );
    };

    const handleDraftCategoryChange = (category: string) => {
        setDraft((current) => {
            const nextOptions = chainOptionsForCategory(chainOptions, category);
            const stillValid = nextOptions.some(
                (option) => option.value === current.chainId,
            );

            if (stillValid) {
                return { ...current, category };
            }

            return {
                ...current,
                category,
                chainId: '',
                item: '',
                size: '',
            };
        });
    };

    const handleDraftChainMasterChange = (chainId: string) => {
        if (chainId === '__legacy__') {
            return;
        }

        setDraft((current) => ({
            ...current,
            ...applyChainMasterSelection(chainId, chainOptions),
        }));
    };

    const handleRowCategoryChange = (id: string, category: string) => {
        const chain = chains.find((entry) => entry.id === id);

        if (chain === undefined) {
            return;
        }

        const nextOptions = chainOptionsForCategory(chainOptions, category);
        const stillValid = nextOptions.some(
            (option) => option.value === chain.chainId,
        );

        updateChain(id, {
            category,
            chainId: stillValid ? chain.chainId : '',
            item: stillValid ? chain.item : '',
            size: stillValid ? chain.size : '',
        });
    };

    const handleRowChainMasterChange = (id: string, chainId: string) => {
        if (chainId === '__legacy__') {
            return;
        }

        updateChain(id, applyChainMasterSelection(chainId, chainOptions));
    };

    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader">
                <h2 className="spkFioriDetailBlockTitle">Rantai</h2>
                <Text className="spkFioriDetailBlockMeta">
                    {chains.length} baris
                </Text>
            </div>

            <div className="spkStoneTableWrap">
                <table className="spkStoneTable spkStoneTable--editable spkChainTable">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Jenis Item Chain</th>
                            <th>Warna</th>
                            <th>Ukuran (cm)</th>
                            <th className="spkStoneTableActionCol">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="spkStoneTableDraftRow">
                            <td>
                                <ChainFieldSelect
                                    key={`draft-category-${draftKey}`}
                                    value={draft.category}
                                    ariaLabel="Kategori rantai baru"
                                    placeholder="Pilih kategori"
                                    options={CHAIN_CATEGORY_OPTIONS}
                                    onChange={handleDraftCategoryChange}
                                />
                            </td>
                            <td>
                                <ChainMasterSelect
                                    key={`draft-item-${draftKey}`}
                                    value={draft.chainId}
                                    ariaLabel="Jenis item chain baru"
                                    placeholder="Pilih jenis item chain"
                                    options={draftMasterOptions}
                                    legacyItem={draft.item}
                                    onChange={handleDraftChainMasterChange}
                                />
                            </td>
                            <td>
                                <ChainFieldSelect
                                    key={`draft-color-${draftKey}`}
                                    value={draft.color}
                                    ariaLabel="Warna rantai baru"
                                    placeholder="Pilih warna"
                                    options={CHAIN_COLOR_OPTIONS}
                                    onChange={(next) =>
                                        updateDraft('color', next)
                                    }
                                />
                            </td>
                            <td>
                                <ChainSizeInput
                                    value={draft.size}
                                    ariaLabel="Ukuran (cm) rantai baru"
                                    onChange={(next) =>
                                        updateDraft('size', next)
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

                        {chains.length === 0 ? (
                            <tr>
                                <td colSpan={5}>
                                    <div className="spkStoneEmpty">
                                        <Text>
                                            Belum ada rantai. Isi baris di atas
                                            lalu klik Tambah.
                                        </Text>
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            chains.map((chain, index) => {
                                const rowMasterOptions = chainOptionsForCategory(
                                    chainOptions,
                                    chain.category,
                                );
                                const rowChainId = resolveChainId(
                                    chain,
                                    chainOptions,
                                );

                                return (
                                    <tr key={chain.id}>
                                        <td>
                                            <ChainFieldSelect
                                                value={chain.category}
                                                ariaLabel={`Kategori rantai ${index + 1}`}
                                                placeholder="Pilih kategori"
                                                options={CHAIN_CATEGORY_OPTIONS}
                                                onChange={(next) =>
                                                    handleRowCategoryChange(
                                                        chain.id,
                                                        next,
                                                    )
                                                }
                                            />
                                            {errors[`chains.${index}.category`] ? (
                                                <Text className="spkFioriError">
                                                    {
                                                        errors[
                                                            `chains.${index}.category`
                                                        ]
                                                    }
                                                </Text>
                                            ) : null}
                                        </td>
                                        <td>
                                            <ChainMasterSelect
                                                value={rowChainId}
                                                ariaLabel={`Jenis item chain ${index + 1}`}
                                                placeholder="Pilih jenis item chain"
                                                options={rowMasterOptions}
                                                legacyItem={chain.item}
                                                onChange={(next) =>
                                                    handleRowChainMasterChange(
                                                        chain.id,
                                                        next,
                                                    )
                                                }
                                            />
                                            {errors[`chains.${index}.item`] ? (
                                                <Text className="spkFioriError">
                                                    {
                                                        errors[
                                                            `chains.${index}.item`
                                                        ]
                                                    }
                                                </Text>
                                            ) : null}
                                        </td>
                                        <td>
                                            <ChainFieldSelect
                                                value={chain.color}
                                                ariaLabel={`Warna rantai ${index + 1}`}
                                                placeholder="Pilih warna"
                                                options={CHAIN_COLOR_OPTIONS}
                                                onChange={(next) =>
                                                    updateChain(chain.id, {
                                                        color: next,
                                                    })
                                                }
                                            />
                                            {errors[`chains.${index}.color`] ? (
                                                <Text className="spkFioriError">
                                                    {
                                                        errors[
                                                            `chains.${index}.color`
                                                        ]
                                                    }
                                                </Text>
                                            ) : null}
                                        </td>
                                        <td>
                                            <ChainSizeInput
                                                value={chain.size}
                                                ariaLabel={`Ukuran (cm) rantai ${index + 1}`}
                                                onChange={(next) =>
                                                    updateChain(chain.id, {
                                                        size: next,
                                                        chainId: '',
                                                    })
                                                }
                                            />
                                            {errors[`chains.${index}.size`] ? (
                                                <Text className="spkFioriError">
                                                    {
                                                        errors[
                                                            `chains.${index}.size`
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
                                                    handleRemove(chain.id)
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
            {errors.chains ? (
                <Text className="spkFioriError">{errors.chains}</Text>
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

function chainStockStatusLabel(
    stock?: SpkChainStock | null,
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

function chainAllocatedStatusLabel(
    allocation?: SpkChainItem['allocation'],
): { text: string; allocated: boolean } | null {
    if (allocation === undefined || allocation === null) {
        return null;
    }

    if (allocation.status === 'unlinked') {
        return { text: 'Belum Dialokasikan', allocated: false };
    }

    const allocated =
        allocation.status !== 'not_allocated' && allocation.allocatedUnits > 0;

    return {
        text: allocated ? 'Sudah Dialokasikan' : 'Belum Dialokasikan',
        allocated,
    };
}

function chainReceiptPrintEnabled(chains: SpkChainItem[]): boolean {
    if (chains.length === 0) {
        return false;
    }

    return chains.every((chain) => {
        const status = chain.allocation?.status;

        return status === 'allocated' || status === 'sent_to_workshop';
    });
}

function chainReceiptPrintDisabledTitle(chains: SpkChainItem[]): string {
    if (chains.length === 0) {
        return 'Tidak ada rantai pada SPK ini';
    }

    const hasReceived = chains.some((chain) => {
        const status = chain.allocation?.status;

        return (
            status === 'received_by_workshop' || status === 'installed'
        );
    });

    if (hasReceived) {
        return 'Rantai sudah diterima di workshop';
    }

    const hasNotAllocated = chains.some((chain) => {
        const status = chain.allocation?.status;

        return (
            status === 'not_allocated' ||
            status === 'unlinked' ||
            status === undefined
        );
    });

    if (hasNotAllocated) {
        return 'Alokasikan rantai terlebih dahulu';
    }

    return 'Tanda terima tidak tersedia';
}

type SpkChainListCardProps = {
    chains: SpkChainItem[];
    spk?: {
        rowId: number;
        produksiNo: string;
    };
    receiptEmployeeOptions?: string[];
};

const STATUS_COLUMN_ACTIONS: SpkChainFulfillmentAction[] = [
    'allocate',
    'receive_workshop',
];

function SpkChainFulfillmentActionButton({
    chain,
    disabled,
    onAllocate,
    onlyActions,
}: {
    chain: SpkChainItem;
    disabled?: boolean;
    onAllocate: () => void;
    onlyActions?: SpkChainFulfillmentAction[];
}) {
    const allocation = chain.allocation;
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

    const allocateTitle =
        disabled === true
            ? 'Stok tidak tersedia atau master belum terhubung'
            : 'Alokasikan unit chain ke SPK ini';

    const actionTitle =
        nextAction === 'allocate' ? allocateTitle : label;

    const handleClick = (): void => {
        if (nextAction === 'allocate') {
            onAllocate();

            return;
        }

        setProcessing(true);
        router.post(
            advanceChainFulfillment.url(Number(chain.id)),
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
                    Alokasi Rantai
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
                <Icon name={inboxIcon} mode="Decorative" />
                <span className="spkChainFulfillmentActionBtnLabel">
                    {processing ? 'Memproses…' : label}
                </span>
            </button>
        );
    }

    return null;
}

function SpkChainFulfillmentStatusCell({
    chain,
    onAllocate,
}: {
    chain: SpkChainItem;
    onAllocate: () => void;
}) {
    const canAllocate = chain.allocation?.nextAction === 'allocate';
    const stockLabel = chainStockStatusLabel(chain.stock);
    const allocatedLabel = chainAllocatedStatusLabel(chain.allocation);

    return (
        <div className="spkChainStatusCell">
            <div className="spkChainStatusCellActions">
                <SpkChainFulfillmentActionButton
                    chain={chain}
                    disabled={!canAllocate}
                    onlyActions={STATUS_COLUMN_ACTIONS}
                    onAllocate={onAllocate}
                />
            </div>
            <div className="spkChainStatusLabels">
                {stockLabel !== null ? (
                    <span
                        className={
                            stockLabel.available
                                ? 'spkStoneStockBadge is-available'
                                : 'spkStoneStockBadge is-unavailable'
                        }
                        title={chain.stock?.note ?? undefined}
                    >
                        {stockLabel.text}
                    </span>
                ) : (
                    <span className="spkStoneStockMeta">—</span>
                )}
                {allocatedLabel !== null ? (
                    <span
                        className={
                            allocatedLabel.allocated
                                ? 'spkStoneStockBadge is-available'
                                : 'spkStoneStockBadge is-unavailable'
                        }
                    >
                        {allocatedLabel.text}
                    </span>
                ) : (
                    <span className="spkStoneStockMeta">—</span>
                )}
            </div>
        </div>
    );
}

export function SpkChainListCard({
    chains,
    spk,
    receiptEmployeeOptions = [],
}: SpkChainListCardProps) {
    const showWorkflow = spk !== undefined;
    const [allocateChain, setAllocateChain] = useState<SpkChainItem | null>(
        null,
    );
    const [receiptDialogOpen, setReceiptDialogOpen] = useState(false);
    const [preparingReceipt, setPreparingReceipt] = useState(false);

    const canPrintReceipt = showWorkflow && chainReceiptPrintEnabled(chains);
    const printReceiptTitle = canPrintReceipt
        ? 'Buat dan cetak tanda terima serah terima rantai ke workshop'
        : chainReceiptPrintDisabledTitle(chains);

    const openReceiptDialog = (): void => {
        if (!canPrintReceipt) {
            toast.warning(chainReceiptPrintDisabledTitle(chains));

            return;
        }

        const chainsToSend = chains.filter(
            (chain) => chain.allocation?.nextAction === 'send_to_workshop',
        );

        if (chainsToSend.length === 0) {
            setReceiptDialogOpen(true);

            return;
        }

        setPreparingReceipt(true);

        const sendNext = (index: number): void => {
            const chain = chainsToSend[index];

            if (chain === undefined) {
                setPreparingReceipt(false);
                setReceiptDialogOpen(true);

                return;
            }

            router.post(
                advanceChainFulfillment.url(Number(chain.id)),
                { action: 'send_to_workshop' },
                {
                    preserveScroll: true,
                    onError: () => {
                        setPreparingReceipt(false);
                    },
                    onFinish: () => {
                        sendNext(index + 1);
                    },
                },
            );
        };

        sendNext(0);
    };

    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader spkChainCardHeader">
                <div className="spkChainCardHeaderStart">
                    <h2 className="spkFioriDetailBlockTitle">Rantai</h2>
                    <Text className="spkFioriDetailBlockMeta">
                        {chains.length} baris
                    </Text>
                </div>
                {showWorkflow && chains.length > 0 ? (
                    <button
                        type="button"
                        className="spkChainPrintReceiptBtn"
                        disabled={preparingReceipt}
                        aria-label="Print Tanda Terima"
                        title={
                            preparingReceipt
                                ? 'Menyiapkan…'
                                : printReceiptTitle
                        }
                        onClick={openReceiptDialog}
                    >
                        {preparingReceipt ? (
                            <span className="spkChainPrintReceiptBtnLabel">
                                Menyiapkan…
                            </span>
                        ) : (
                            <>
                                <Icon name={printIcon} mode="Decorative" />
                                <span className="spkChainPrintReceiptBtnLabel">
                                    Tanda Terima Rantai
                                </span>
                            </>
                        )}
                    </button>
                ) : null}
            </div>

            {chains.length === 0 ? (
                <div className="spkStoneEmpty">
                    <Text>Tidak ada rantai pada SPK ini.</Text>
                </div>
            ) : (
                <>
                    <div className="spkStoneTableWrap">
                        <table className="spkStoneTable spkChainTable">
                            <thead>
                                <tr>
                                    <th>Kategori</th>
                                    <th>Jenis Item Chain</th>
                                    <th>Warna</th>
                                    <th>Ukuran (cm)</th>
                                    {showWorkflow ? (
                                        <th className="spkChainStatusCol">
                                            Status
                                        </th>
                                    ) : null}
                                </tr>
                            </thead>
                            <tbody>
                                {chains.map((chain) => (
                                    <tr key={chain.id}>
                                        <td>{emptyDash(chain.category)}</td>
                                        <td>{emptyDash(chain.item)}</td>
                                        <td>{emptyDash(chain.color)}</td>
                                        <td>
                                            {formatChainSizeDisplay(
                                                chain.size,
                                            )}
                                        </td>
                                        {showWorkflow ? (
                                            <td className="spkChainStatusCol">
                                                <SpkChainFulfillmentStatusCell
                                                    chain={chain}
                                                    onAllocate={() =>
                                                        setAllocateChain(chain)
                                                    }
                                                />
                                            </td>
                                        ) : null}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            {showWorkflow && spk !== undefined ? (
                <>
                    <SpkChainAllocateDialog
                        chain={allocateChain}
                        spk={spk}
                        open={allocateChain !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setAllocateChain(null);
                            }
                        }}
                    />
                    <SpkChainReceiptPrintDialog
                        open={receiptDialogOpen}
                        onOpenChange={setReceiptDialogOpen}
                        spkRowId={spk.rowId}
                        chainLineIds={chains.map((chain) =>
                            Number(chain.id),
                        )}
                        employeeOptions={receiptEmployeeOptions}
                    />
                </>
            ) : null}
        </div>
    );
}
