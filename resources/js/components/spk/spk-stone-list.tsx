import pictureIcon from '@ui5/webcomponents-icons/dist/picture.js';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Text } from '@ui5/webcomponents-react/Text';
import { useState } from 'react';
import type { SpkItemDetail, SpkStoneItem } from '@/components/spk/types';

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

export function formatDecimal3Id(
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
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

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

export function formatGoldWeightGramsLabel(
    value: string | number,
): string {
    const parsed = parseGoldWeightGrams(value);

    if (parsed === null) {
        return String(value);
    }

    return parsed.toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
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

    return Math.abs(currentWeight - masterWeight) > 0.0005;
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
    if (!isMasterFieldChanged(current, master) || master === null || master === undefined) {
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
    const hasStructuredType =
        typeProductLine !== '' || skuCode !== '-';

    if (!hasStructuredType) {
        return <>{emptyDash(item.name)}</>;
    }

    return (
        <div className="spkItemTypeProductStack">
            {typeProductLine !== '' ? (
                <span className="spkItemTypeProductLine">{typeProductLine}</span>
            ) : null}
            {skuCode !== '-' ? (
                <span className="spkItemSkuCode">{skuCode}</span>
            ) : null}
        </div>
    );
}

export function SpkItemDetailCard({
    item,
    notes,
}: {
    item: SpkItemDetail;
    notes?: string;
}) {
    return (
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
                            <td>{formatDecimal3Id(item.goldWeight)}</td>
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

export function SpkStoneListCard({ stones }: { stones: SpkStoneItem[] }) {
    const totalButir = stones.reduce(
        (sum, stone) => sum + (Number(stone.pcs) || 0),
        0,
    );
    const totalCarat = stones.reduce((sum, stone) => {
        const value = Number(String(stone.totalCarat).replace(',', '.'));

        return sum + (Number.isFinite(value) ? value : 0);
    }, 0);

    return (
        <div className="spkItemStoneSection">
            <div className="spkStoneCardHeader">
                <h2 className="spkFioriDetailBlockTitle">Daftar Batu</h2>
                <Text className="spkFioriDetailBlockMeta">
                    {stones.length} item
                </Text>
            </div>

            {stones.length === 0 ? (
                <div className="spkStoneEmpty">
                    <Text>Tidak ada batu pada varian ini.</Text>
                </div>
            ) : (
                <div className="spkStoneTableWrap">
                    <table className="spkStoneTable">
                        <thead>
                            <tr>
                                <th>Posisi</th>
                                <th>Bentuk</th>
                                <th>Diameter / Dimensi PxL (mm)</th>
                                <th>Carat per Butir (pcs)</th>
                                <th>Jumlah Butir (pcs)</th>
                                <th>Total Carat</th>
                            </tr>
                        </thead>
                        <tbody>
                            {stones.map((stone) => (
                                <tr key={stone.id}>
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
                                    <td>{formatDecimal3Id(stone.totalCarat)}</td>
                                </tr>
                            ))}
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
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
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
    const [text, setText] = useState(
        () => positionNama || positionName || '',
    );

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

    const updateDraft = (field: keyof typeof emptyDraft, value: string) => {
        setDraft((current) => ({ ...current, [field]: value }));
    };

    const handleAdd = () => {
        const shape = shapeOptions.find(
            (option) => option.value === draft.shapeId,
        );

        if (
            !draft.positionId &&
            !draft.positionNama &&
            !draft.shapeId &&
            !draft.pcs &&
            !draft.caratPerPcs &&
            !draft.size
        ) {
            return;
        }

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
                <h2 className="spkFioriDetailBlockTitle">Daftar Batu</h2>
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
                            <th>Carat per Butir (pcs)</th>
                            <th>Jumlah Butir (pcs)</th>
                            <th>Total Carat</th>
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
                            <td>
                                <button
                                    type="button"
                                    className="spkStoneRowActionBtn"
                                    onClick={handleAdd}
                                >
                                    Tambah
                                </button>
                            </td>
                        </tr>

                        {stones.length === 0 ? (
                            <tr>
                                <td colSpan={7}>
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
                                                {
                                                    errors[
                                                        `stones.${index}.size`
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
                                                {
                                                    errors[
                                                        `stones.${index}.pcs`
                                                    ]
                                                }
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
                        </tr>
                    </tfoot>
                </table>
            </div>
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
                                <td>{formatDecimal3Id(item.goldWeight)}</td>
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
                                <td className="spkItemNotesCell">{notesText}</td>
                            </tr>
                        </tbody>
                    </table>

                    <SpkStoneListCard stones={stones} />
                </div>
            </div>
        </div>
    );
}
