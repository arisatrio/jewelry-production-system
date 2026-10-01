import { useEffect, useState } from 'react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Input } from '@ui5/webcomponents-react/Input';
import { Text } from '@ui5/webcomponents-react/Text';

export type PasangBatuStoneOption = {
    value: string;
    label: string;
    stock?: string;
    description?: string;
};

export type PasangBatuDiamondOption = PasangBatuStoneOption & {
    code: string | null;
    diamondType: string | null;
    shape: string | null;
    certificate: string | null;
    crt: string | null;
};

export type SettingStoneLine = {
    key: string;
    stone_id: string;
    pcs: string;
    crt: string;
    notes: string;
};

export type DiamondLine = {
    key: string;
    diamond_id: string;
};

export type MountedStoneLine = {
    key: string;
    diamond_code: string;
    shape_id: string;
    pcs: string;
    crt: string;
    size: string;
};

type PasangBatuStoneEditorProps = {
    stoneOptions: PasangBatuStoneOption[];
    shapeOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    settingStones: SettingStoneLine[];
    returnStones: SettingStoneLine[];
    diamonds: DiamondLine[];
    mountedStones: MountedStoneLine[];
    errors: Record<string, string>;
    onSettingChange: (lines: SettingStoneLine[]) => void;
    onReturnChange: (lines: SettingStoneLine[]) => void;
    onDiamondsChange: (lines: DiamondLine[]) => void;
    onMountedChange: (lines: MountedStoneLine[]) => void;
};

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function newLineKey(prefix: string): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

function resolveOptionLabel(
    options: PasangBatuStoneOption[],
    value: string,
): string {
    if (value.trim() === '') {
        return '';
    }

    return options.find((option) => option.value === value)?.label ?? '';
}

function OptionComboBox({
    accessibleName,
    value,
    options,
    error,
    placeholder,
    onChange,
}: {
    accessibleName: string;
    value: string;
    options: PasangBatuStoneOption[];
    error?: string;
    placeholder: string;
    onChange: (nextValue: string) => void;
}) {
    const selectedLabel = resolveOptionLabel(options, value);
    const [text, setText] = useState(selectedLabel);

    useEffect(() => {
        setText(selectedLabel);
    }, [selectedLabel, value]);

    const clearSelection = () => {
        setText('');
        onChange('');
    };

    const selectOption = (nextValue: string, label: string) => {
        setText(label);
        onChange(nextValue);
    };

    return (
        <ComboBox
            accessibleName={accessibleName}
            className="spkCoranMaterialComboBox"
            placeholder={placeholder}
            filter="Contains"
            showClearIcon
            noTypeahead
            value={text}
            valueState={fieldState(error)}
            onInput={(event) => {
                const nextText = event.target.value ?? '';
                setText(nextText);

                if (!nextText.trim()) {
                    clearSelection();

                    return;
                }

                if (selectedLabel && selectedLabel !== nextText) {
                    onChange('');
                }
            }}
            onSelectionChange={(event) => {
                const item = event.detail.item;

                if (!item?.value) {
                    return;
                }

                selectOption(String(item.value), item.text ?? '');
            }}
            onChange={(event) => {
                const typed = event.target.value?.trim() ?? '';

                if (!typed) {
                    clearSelection();

                    return;
                }

                const matched = options.find(
                    (option) =>
                        option.label.toLowerCase() === typed.toLowerCase(),
                );

                if (matched) {
                    selectOption(matched.value, matched.label);

                    return;
                }

                setText(typed);
                onChange('');
            }}
        >
            {options.map((option) => (
                <ComboBoxItem
                    key={option.value}
                    text={option.label}
                    additionalText={
                        option.stock !== undefined
                            ? `Stok: ${Number(option.stock).toLocaleString('id-ID')} pcs`
                            : option.description || undefined
                    }
                    value={option.value}
                />
            ))}
        </ComboBox>
    );
}

function emptySettingLine(): SettingStoneLine {
    return {
        key: newLineKey('setting'),
        stone_id: '',
        pcs: '',
        crt: '',
        notes: '',
    };
}

function emptyDiamondLine(): DiamondLine {
    return {
        key: newLineKey('diamond'),
        diamond_id: '',
    };
}

function emptyMountedLine(): MountedStoneLine {
    return {
        key: newLineKey('mounted'),
        diamond_code: '',
        shape_id: '',
        pcs: '',
        crt: '',
        size: '',
    };
}

function updateSettingLine(
    lines: SettingStoneLine[],
    key: string,
    patch: Partial<SettingStoneLine>,
): SettingStoneLine[] {
    return lines.map((line) =>
        line.key === key ? { ...line, ...patch } : line,
    );
}

function updateDiamondLine(
    lines: DiamondLine[],
    key: string,
    patch: Partial<DiamondLine>,
): DiamondLine[] {
    return lines.map((line) =>
        line.key === key ? { ...line, ...patch } : line,
    );
}

function updateMountedLine(
    lines: MountedStoneLine[],
    key: string,
    patch: Partial<MountedStoneLine>,
): MountedStoneLine[] {
    return lines.map((line) =>
        line.key === key ? { ...line, ...patch } : line,
    );
}

function SettingStonePanel({
    title,
    errorPrefix,
    lines,
    stoneOptions,
    errors,
    onChange,
}: {
    title: string;
    errorPrefix: string;
    lines: SettingStoneLine[];
    stoneOptions: PasangBatuStoneOption[];
    errors: Record<string, string>;
    onChange: (lines: SettingStoneLine[]) => void;
}) {
    return (
        <div className="spkStoneBatchPanel">
            <div className="spkCoranMaterialEditorPanelHeader">
                <div className="spkCoranDetailLabel">{title}</div>
                <Button
                    design="Emphasized"
                    icon={addIcon}
                    type="Button"
                    accessibleName={`Tambah ${title}`}
                    onClick={() => onChange([...lines, emptySettingLine()])}
                >
                    Tambah
                </Button>
            </div>

            <table className="spkCoranMaterialEditorTable">
                <thead>
                    <tr>
                        <th scope="col">Batu</th>
                        <th scope="col">Pcs</th>
                        <th scope="col">Crt</th>
                        <th scope="col" className="spkTableActionCol">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {lines.length === 0 ? (
                        <tr>
                            <td colSpan={4} className="spkCoranBreakdownEmpty">
                                Tidak ada data
                            </td>
                        </tr>
                    ) : (
                        lines.map((line, index) => {
                            const stoneError =
                                errors[`${errorPrefix}.${index}.stone_id`];
                            const pcsError =
                                errors[`${errorPrefix}.${index}.pcs`];
                            const crtError =
                                errors[`${errorPrefix}.${index}.crt`];

                            return (
                                <tr key={line.key} className="spkCoranLineRow">
                                    <td>
                                        <div className="spkFioriFieldStack">
                                            <OptionComboBox
                                                accessibleName={`${title} batu`}
                                                value={line.stone_id}
                                                options={stoneOptions}
                                                error={stoneError}
                                                placeholder="Cari / pilih batu"
                                                onChange={(stoneId) =>
                                                    onChange(
                                                        updateSettingLine(
                                                            lines,
                                                            line.key,
                                                            {
                                                                stone_id:
                                                                    stoneId,
                                                            },
                                                        ),
                                                    )
                                                }
                                            />
                                            {stoneError ? (
                                                <Text className="spkFioriError">
                                                    {stoneError}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </td>
                                    <td>
                                        <div className="spkFioriFieldStack">
                                            <Input
                                                type="Number"
                                                accessibleName={`${title} pcs`}
                                                value={line.pcs}
                                                valueState={fieldState(
                                                    pcsError,
                                                )}
                                                onInput={(event) =>
                                                    onChange(
                                                        updateSettingLine(
                                                            lines,
                                                            line.key,
                                                            {
                                                                pcs:
                                                                    event.target
                                                                        .value ??
                                                                    '',
                                                            },
                                                        ),
                                                    )
                                                }
                                            />
                                            {pcsError ? (
                                                <Text className="spkFioriError">
                                                    {pcsError}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </td>
                                    <td>
                                        <div className="spkFioriFieldStack">
                                            <Input
                                                type="Number"
                                                accessibleName={`${title} crt`}
                                                value={line.crt}
                                                valueState={fieldState(
                                                    crtError,
                                                )}
                                                onInput={(event) =>
                                                    onChange(
                                                        updateSettingLine(
                                                            lines,
                                                            line.key,
                                                            {
                                                                crt:
                                                                    event.target
                                                                        .value ??
                                                                    '',
                                                            },
                                                        ),
                                                    )
                                                }
                                            />
                                            {crtError ? (
                                                <Text className="spkFioriError">
                                                    {crtError}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </td>
                                    <td className="spkTableActionCol">
                                        <Button
                                            design="Transparent"
                                            icon={deleteIcon}
                                            type="Button"
                                            accessibleName={`Hapus ${title}`}
                                            onClick={() =>
                                                onChange(
                                                    lines.filter(
                                                        (item) =>
                                                            item.key !==
                                                            line.key,
                                                    ),
                                                )
                                            }
                                        />
                                    </td>
                                </tr>
                            );
                        })
                    )}
                </tbody>
            </table>
        </div>
    );
}

function DiamondPanel({
    lines,
    diamondOptions,
    errors,
    onChange,
}: {
    lines: DiamondLine[];
    diamondOptions: PasangBatuDiamondOption[];
    errors: Record<string, string>;
    onChange: (lines: DiamondLine[]) => void;
}) {
    const diamondOptionById = new Map(
        diamondOptions.map((option) => [option.value, option]),
    );

    return (
        <div className="spkStoneBatchPanel">
            <div className="spkCoranMaterialEditorPanelHeader">
                <div className="spkCoranDetailLabel">Diamond</div>
                <Button
                    design="Emphasized"
                    icon={addIcon}
                    type="Button"
                    accessibleName="Tambah Diamond"
                    onClick={() => onChange([...lines, emptyDiamondLine()])}
                >
                    Tambah
                </Button>
            </div>

            <table className="spkCoranMaterialEditorTable">
                <thead>
                    <tr>
                        <th scope="col">Kode</th>
                        <th scope="col">Diamond</th>
                        <th scope="col">Bentuk</th>
                        <th scope="col">Sertifikat</th>
                        <th scope="col">Crt</th>
                        <th scope="col" className="spkTableActionCol">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {lines.length === 0 ? (
                        <tr>
                            <td colSpan={6} className="spkCoranBreakdownEmpty">
                                Tidak ada data
                            </td>
                        </tr>
                    ) : (
                        lines.map((line, index) => {
                            const diamondError =
                                errors[`diamonds.${index}.diamond_id`];
                            const selectedDiamond = diamondOptionById.get(
                                line.diamond_id,
                            );
                            const selectedInOtherLines = new Set(
                                lines
                                    .filter((item) => item.key !== line.key)
                                    .map((item) => item.diamond_id),
                            );
                            const lineOptions = diamondOptions.filter(
                                (option) =>
                                    !selectedInOtherLines.has(option.value),
                            );

                            return (
                                <tr key={line.key} className="spkCoranLineRow">
                                    <td>
                                        <div className="spkFioriFieldStack">
                                            <OptionComboBox
                                                accessibleName="Batu Dossier"
                                                value={line.diamond_id}
                                                options={lineOptions}
                                                error={diamondError}
                                                placeholder="Cari kode / bentuk / crt"
                                                onChange={(diamondId) =>
                                                    onChange(
                                                        updateDiamondLine(
                                                            lines,
                                                            line.key,
                                                            {
                                                                diamond_id:
                                                                    diamondId,
                                                            },
                                                        ),
                                                    )
                                                }
                                            />
                                            {diamondError ? (
                                                <Text className="spkFioriError">
                                                    {diamondError}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </td>
                                    <td>
                                        {selectedDiamond?.diamondType ?? '—'}
                                    </td>
                                    <td>{selectedDiamond?.shape ?? '—'}</td>
                                    <td>
                                        {selectedDiamond?.certificate ?? '—'}
                                    </td>
                                    <td>{selectedDiamond?.crt ?? '—'}</td>
                                    <td className="spkTableActionCol">
                                        <Button
                                            design="Transparent"
                                            icon={deleteIcon}
                                            type="Button"
                                            accessibleName="Hapus diamond"
                                            onClick={() =>
                                                onChange(
                                                    lines.filter(
                                                        (item) =>
                                                            item.key !==
                                                            line.key,
                                                    ),
                                                )
                                            }
                                        />
                                    </td>
                                </tr>
                            );
                        })
                    )}
                </tbody>
            </table>
            {errors.diamonds ? (
                <Text className="spkFioriError">{errors.diamonds}</Text>
            ) : null}
        </div>
    );
}

function MountedStonePanel({
    lines,
    shapeOptions,
    errors,
    onChange,
}: {
    lines: MountedStoneLine[];
    shapeOptions: PasangBatuStoneOption[];
    errors: Record<string, string>;
    onChange: (lines: MountedStoneLine[]) => void;
}) {
    return (
        <div className="spkStoneBatchPanel">
            <div className="spkCoranMaterialEditorPanelHeader">
                <div className="spkCoranDetailLabel">Batu terpasang</div>
                <Button
                    design="Emphasized"
                    icon={addIcon}
                    type="Button"
                    accessibleName="Tambah batu terpasang"
                    onClick={() => onChange([...lines, emptyMountedLine()])}
                >
                    Tambah
                </Button>
            </div>

            <table className="spkCoranMaterialEditorTable">
                <thead>
                    <tr>
                        <th scope="col">Kode Diamond</th>
                        <th scope="col">Shape</th>
                        <th scope="col">Pcs</th>
                        <th scope="col">Crt</th>
                        <th scope="col">Size</th>
                        <th scope="col" className="spkTableActionCol">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {lines.length === 0 ? (
                        <tr>
                            <td colSpan={6} className="spkCoranBreakdownEmpty">
                                Tidak ada data
                            </td>
                        </tr>
                    ) : (
                        lines.map((line, index) => {
                            const shapeError =
                                errors[`mounted_stones.${index}.shape_id`];
                            const pcsError =
                                errors[`mounted_stones.${index}.pcs`];
                            const crtError =
                                errors[`mounted_stones.${index}.crt`];

                            return (
                                <tr key={line.key} className="spkCoranLineRow">
                                    <td>
                                        <Input
                                            accessibleName="Kode diamond terpasang"
                                            value={line.diamond_code}
                                            valueState={fieldState(
                                                errors[
                                                    `mounted_stones.${index}.diamond_code`
                                                ],
                                            )}
                                            onInput={(event) =>
                                                onChange(
                                                    updateMountedLine(
                                                        lines,
                                                        line.key,
                                                        {
                                                            diamond_code:
                                                                event.target
                                                                    .value ??
                                                                '',
                                                        },
                                                    ),
                                                )
                                            }
                                        />
                                    </td>
                                    <td>
                                        <div className="spkFioriFieldStack">
                                            <OptionComboBox
                                                accessibleName="Shape batu terpasang"
                                                value={line.shape_id}
                                                options={shapeOptions}
                                                error={shapeError}
                                                placeholder="Cari / pilih shape"
                                                onChange={(shapeId) =>
                                                    onChange(
                                                        updateMountedLine(
                                                            lines,
                                                            line.key,
                                                            {
                                                                shape_id:
                                                                    shapeId,
                                                            },
                                                        ),
                                                    )
                                                }
                                            />
                                            {shapeError ? (
                                                <Text className="spkFioriError">
                                                    {shapeError}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </td>
                                    <td>
                                        <div className="spkFioriFieldStack">
                                            <Input
                                                type="Number"
                                                accessibleName="Pcs batu terpasang"
                                                value={line.pcs}
                                                valueState={fieldState(
                                                    pcsError,
                                                )}
                                                onInput={(event) =>
                                                    onChange(
                                                        updateMountedLine(
                                                            lines,
                                                            line.key,
                                                            {
                                                                pcs:
                                                                    event.target
                                                                        .value ??
                                                                    '',
                                                            },
                                                        ),
                                                    )
                                                }
                                            />
                                            {pcsError ? (
                                                <Text className="spkFioriError">
                                                    {pcsError}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </td>
                                    <td>
                                        <div className="spkFioriFieldStack">
                                            <Input
                                                type="Number"
                                                accessibleName="Crt batu terpasang"
                                                value={line.crt}
                                                valueState={fieldState(
                                                    crtError,
                                                )}
                                                onInput={(event) =>
                                                    onChange(
                                                        updateMountedLine(
                                                            lines,
                                                            line.key,
                                                            {
                                                                crt:
                                                                    event.target
                                                                        .value ??
                                                                    '',
                                                            },
                                                        ),
                                                    )
                                                }
                                            />
                                            {crtError ? (
                                                <Text className="spkFioriError">
                                                    {crtError}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </td>
                                    <td>
                                        <Input
                                            accessibleName="Size batu terpasang"
                                            value={line.size}
                                            valueState={fieldState(
                                                errors[
                                                    `mounted_stones.${index}.size`
                                                ],
                                            )}
                                            onInput={(event) =>
                                                onChange(
                                                    updateMountedLine(
                                                        lines,
                                                        line.key,
                                                        {
                                                            size:
                                                                event.target
                                                                    .value ??
                                                                '',
                                                        },
                                                    ),
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="spkTableActionCol">
                                        <Button
                                            design="Transparent"
                                            icon={deleteIcon}
                                            type="Button"
                                            accessibleName="Hapus batu terpasang"
                                            onClick={() =>
                                                onChange(
                                                    lines.filter(
                                                        (item) =>
                                                            item.key !==
                                                            line.key,
                                                    ),
                                                )
                                            }
                                        />
                                    </td>
                                </tr>
                            );
                        })
                    )}
                </tbody>
            </table>
        </div>
    );
}

export function PasangBatuStoneEditor({
    stoneOptions,
    shapeOptions,
    diamondOptions,
    settingStones,
    returnStones,
    diamonds,
    mountedStones,
    errors,
    onSettingChange,
    onReturnChange,
    onDiamondsChange,
    onMountedChange,
}: PasangBatuStoneEditorProps) {
    return (
        <div className="spkFioriDetailBlock">
            <div className="spkStoneCardHeader">
                <div className="spkFioriDetailBlockTitle">
                    Detail Batch Pasang Batu
                </div>
            </div>

            <div className="spkStoneBatchGrid">
                <SettingStonePanel
                    title="Setting Batu"
                    errorPrefix="setting_stones"
                    lines={settingStones}
                    stoneOptions={stoneOptions}
                    errors={errors}
                    onChange={onSettingChange}
                />
                <SettingStonePanel
                    title="Retur Batu"
                    errorPrefix="return_stones"
                    lines={returnStones}
                    stoneOptions={stoneOptions}
                    errors={errors}
                    onChange={onReturnChange}
                />
                <DiamondPanel
                    lines={diamonds}
                    diamondOptions={diamondOptions}
                    errors={errors}
                    onChange={onDiamondsChange}
                />
                <MountedStonePanel
                    lines={mountedStones}
                    shapeOptions={shapeOptions}
                    errors={errors}
                    onChange={onMountedChange}
                />
            </div>
        </div>
    );
}
