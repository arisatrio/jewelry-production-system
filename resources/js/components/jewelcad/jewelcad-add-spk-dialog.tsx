import { useEffect, useState } from 'react';
import pictureIcon from '@ui5/webcomponents-icons/dist/picture.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { FileUploader } from '@ui5/webcomponents-react/FileUploader';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Label } from '@ui5/webcomponents-react/Label';
import { Text } from '@ui5/webcomponents-react/Text';
import {
    GoldWeightMasterHint,
    GoldWeightRowLabel,
    isGoldWeightChangedFromMaster,
    isStoneListChangedFromMaster,
    SpkFormStoneListCard,
    type SpkFormStoneRow,
} from '@/components/spk/spk-stone-list';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input as UiInput } from '@/components/ui/input';
import { detail as jewelCadSpkDetail } from '@/routes/jewelcad/spk';
import { spks as searchJewelCadSpks } from '@/routes/jewelcad/select';

export type JewelCadStonePayload = {
    shape_id: string;
    position_id: string;
    position_nama: string;
    pcs: string;
    carat_per_pcs: string;
    size: string;
};

export type JewelCadAddedSpk = {
    spk_id: string;
    spk_no: string;
    material: string;
    gold_weight: string;
    jwcad_3d: string;
    file: File | null;
    image_url: string | null;
    file_name: string | null;
    qty: string;
    notes: string;
    estimation_brj: string;
    stones: JewelCadStonePayload[];
};

type SpkSelectorOption = {
    rowId: number;
    spkNo: string;
    customer: string;
    item: string;
    goldColor: string;
    goldWeight: string;
    qty: number;
    notes: string;
};

type SpkDetailPayload = {
    production: {
        id: number;
        spkNo: string;
        spkType: string;
        customer: string;
        itemName: string;
        requestOrderNo: string;
        orderDate: string;
        estimatedDelivery: string;
        qty: number;
        notes: string;
        goldWeight: string;
        goldColor: string;
    };
    item: {
        typeCode: string;
        productItemName: string;
        skuCode: string;
        qty: string;
        diameter: string;
        dimensi: string;
        ringSize: string;
        masterGoldWeight: string | null;
        jwcad3d: string;
        description: string;
        imageUrl: string | null;
        fileName: string | null;
    };
    masterStoneCount?: number;
    stones: SpkFormStoneRow[];
    options: {
        goldColors: string[];
        shapeOptions: Array<{ value: string; label: string; name?: string }>;
        positionOptions: Array<{ value: string; label: string }>;
    };
};

type JewelCadAddSpkDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    excludeSpkIds: number[];
    editDetail?: JewelCadAddedSpk | null;
    onAdded: (detail: JewelCadAddedSpk) => void;
};

type Step = 'select' | 'detail';

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function mapPayloadStonesToForm(
    stones: JewelCadStonePayload[],
    masterStones: SpkFormStoneRow[] = [],
): SpkFormStoneRow[] {
    return stones.map((stone, index) => ({
        id: `draft-stone-${index}-${stone.shape_id || 'x'}`,
        positionId: stone.position_id,
        positionName: stone.position_nama,
        positionNama: stone.position_nama,
        shapeId: stone.shape_id,
        shapeName: '',
        size: stone.size,
        pcs: stone.pcs,
        caratPerPcs: stone.carat_per_pcs,
        master: masterStones[index]?.master ?? null,
    }));
}

export function JewelCadAddSpkDialog({
    open,
    onOpenChange,
    excludeSpkIds,
    editDetail = null,
    onAdded,
}: JewelCadAddSpkDialogProps) {
    const isEditing = editDetail !== null;
    const [step, setStep] = useState<Step>(isEditing ? 'detail' : 'select');
    const [search, setSearch] = useState('');
    const [rows, setRows] = useState<SpkSelectorOption[]>([]);
    const [loadingList, setLoadingList] = useState(false);
    const [loadingDetail, setLoadingDetail] = useState(false);
    const [detail, setDetail] = useState<SpkDetailPayload | null>(null);
    const [goldWeight, setGoldWeight] = useState('');
    const [goldColor, setGoldColor] = useState('');
    const [jwcad3d, setJwcad3d] = useState('');
    const [imageFile, setImageFile] = useState<File | null>(null);
    const [imagePreviewUrl, setImagePreviewUrl] = useState<string | null>(null);
    const [currentFileName, setCurrentFileName] = useState<string | null>(null);
    const [estimationBrj, setEstimationBrj] = useState('');
    const [stones, setStones] = useState<SpkFormStoneRow[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const resetState = () => {
        setStep(isEditing ? 'detail' : 'select');
        setSearch('');
        setRows([]);
        setLoadingList(false);
        setLoadingDetail(false);
        setDetail(null);
        setGoldWeight('');
        setGoldColor('');
        setJwcad3d('');
        setImageFile(null);
        setImagePreviewUrl((previous) => {
            if (previous?.startsWith('blob:')) {
                URL.revokeObjectURL(previous);
            }

            return null;
        });
        setCurrentFileName(null);
        setEstimationBrj('');
        setStones([]);
        setErrors({});
    };

    useEffect(() => {
        if (!open) {
            resetState();
        }
    }, [open]);

    useEffect(() => {
        if (!open || !editDetail) {
            return;
        }

        const rowId = Number(editDetail.spk_id);

        if (!Number.isFinite(rowId) || rowId <= 0) {
            return;
        }

        setStep('detail');
        setLoadingDetail(true);
        setErrors({});

        const controller = new AbortController();

        void fetch(jewelCadSpkDetail.url(rowId), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat detail SPK');
                }

                const payload = (await response.json()) as {
                    data: SpkDetailPayload;
                };
                const data = payload.data;

                setDetail(data);
                setGoldWeight(
                    editDetail.gold_weight || data.production.goldWeight,
                );
                setGoldColor(editDetail.material || data.production.goldColor);
                setJwcad3d(
                    editDetail.jwcad_3d ||
                        (data.item.jwcad3d !== '-' ? data.item.jwcad3d : ''),
                );
                setImageFile(editDetail.file);
                setImagePreviewUrl(
                    editDetail.file
                        ? URL.createObjectURL(editDetail.file)
                        : (editDetail.image_url ?? data.item.imageUrl),
                );
                setCurrentFileName(
                    editDetail.file?.name ??
                        editDetail.file_name ??
                        data.item.fileName,
                );
                setEstimationBrj(editDetail.estimation_brj || '');
                setStones(
                    editDetail.stones.length > 0
                        ? mapPayloadStonesToForm(
                              editDetail.stones,
                              data.stones,
                          )
                        : data.stones,
                );
            })
            .catch((error: unknown) => {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }

                setErrors({
                    form: 'Gagal memuat detail SPK. Coba buka ulang.',
                });
            })
            .finally(() => setLoadingDetail(false));

        return () => controller.abort();
    }, [open, editDetail]);

    useEffect(() => {
        if (!open || step !== 'select' || isEditing) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setLoadingList(true);

            void fetch(
                searchJewelCadSpks.url({
                    query: {
                        search: search || undefined,
                        exclude:
                            excludeSpkIds.length > 0
                                ? excludeSpkIds.join(',')
                                : undefined,
                    },
                }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            )
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error('Gagal memuat daftar SPK');
                    }

                    const payload = (await response.json()) as {
                        data: SpkSelectorOption[];
                    };

                    setRows(payload.data ?? []);
                })
                .catch((error: unknown) => {
                    if (
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    ) {
                        return;
                    }

                    setRows([]);
                })
                .finally(() => setLoadingList(false));
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [open, step, search, excludeSpkIds, isEditing]);

    const loadDetail = (rowId: number) => {
        setLoadingDetail(true);
        setErrors({});

        void fetch(jewelCadSpkDetail.url(rowId), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat detail SPK');
                }

                const payload = (await response.json()) as {
                    data: SpkDetailPayload;
                };
                const data = payload.data;

                setDetail(data);
                setGoldWeight(data.production.goldWeight);
                setGoldColor(data.production.goldColor);
                setJwcad3d(
                    data.item.jwcad3d !== '-' ? data.item.jwcad3d : '',
                );
                setImageFile(null);
                setImagePreviewUrl(data.item.imageUrl);
                setCurrentFileName(data.item.fileName);
                setStones(data.stones);
                setEstimationBrj('');
                setStep('detail');
            })
            .catch(() => {
                setErrors({
                    form: 'Gagal memuat detail SPK. Coba pilih ulang.',
                });
            })
            .finally(() => setLoadingDetail(false));
    };

    const handleAdd = () => {
        if (!detail) {
            return;
        }

        const nextErrors: Record<string, string> = {};

        if (goldWeight.trim() === '') {
            nextErrors.gold_weight = 'Berat emas wajib diisi.';
        }

        if (estimationBrj.trim() === '') {
            nextErrors.estimation_brj = 'Estimasi BRJ wajib diisi.';
        }

        if (Object.keys(nextErrors).length > 0) {
            setErrors(nextErrors);

            return;
        }

        onAdded({
            spk_id: String(detail.production.id),
            spk_no: detail.production.spkNo,
            material: goldColor.trim(),
            gold_weight: goldWeight.trim(),
            jwcad_3d: jwcad3d.trim(),
            file: imageFile,
            image_url: imagePreviewUrl,
            file_name: currentFileName,
            qty: String(detail.production.qty),
            notes: detail.production.notes,
            estimation_brj: estimationBrj.trim(),
            stones: stones.map((stone) => ({
                shape_id: stone.shapeId,
                position_id: stone.positionId,
                position_nama: stone.positionNama || stone.positionName,
                pcs: stone.pcs,
                carat_per_pcs: stone.caratPerPcs,
                size: stone.size,
            })),
        });
        onOpenChange(false);
    };

    const showMasterSkuSyncAlert =
        detail !== null &&
        (isGoldWeightChangedFromMaster(
            goldWeight,
            detail.item.masterGoldWeight,
        ) ||
            isStoneListChangedFromMaster(
                stones,
                detail.masterStoneCount ?? 0,
            ));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="jewelCadAddSpkDialog">
                <DialogHeader>
                    <DialogTitle>
                        {step === 'select'
                            ? 'Pilih SPK'
                            : isEditing
                              ? `Ubah Detail SPK · ${detail?.production.spkNo ?? editDetail?.spk_no ?? ''}`
                              : `Detail SPK · ${detail?.production.spkNo ?? ''}`}
                    </DialogTitle>
                    <DialogDescription>
                        {step === 'select'
                            ? 'Cari dan pilih SPK yang akan ditambahkan ke request JewelCAD.'
                            : 'Ubah berat emas dan daftar batu. Perubahan master SPK disimpan saat request JewelCAD disimpan.'}
                    </DialogDescription>
                </DialogHeader>

                {errors.form ? (
                    <Text className="spkFioriError">{errors.form}</Text>
                ) : null}

                {step === 'select' ? (
                    <div className="jewelCadAddSpkSelect">
                        <UiInput
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari no. SPK, customer, atau item..."
                            autoFocus
                        />

                        <div className="spkCreateDialogTableWrap">
                            <table className="spkCreateDialogTable">
                                <thead>
                                    <tr>
                                        <th>No. SPK</th>
                                        <th>Customer</th>
                                        <th>Item</th>
                                        <th>Bahan Emas</th>
                                        <th>Berat Emas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {loadingList || loadingDetail ? (
                                        <tr>
                                            <td colSpan={5}>Memuat data...</td>
                                        </tr>
                                    ) : rows.length === 0 ? (
                                        <tr>
                                            <td colSpan={5}>
                                                Tidak ada SPK ditemukan.
                                            </td>
                                        </tr>
                                    ) : (
                                        rows.map((row) => (
                                            <tr
                                                key={row.rowId}
                                                tabIndex={0}
                                                onClick={() =>
                                                    loadDetail(row.rowId)
                                                }
                                                onKeyDown={(event) => {
                                                    if (
                                                        event.key === 'Enter' ||
                                                        event.key === ' '
                                                    ) {
                                                        event.preventDefault();
                                                        loadDetail(row.rowId);
                                                    }
                                                }}
                                            >
                                                <td>{row.spkNo}</td>
                                                <td>{row.customer}</td>
                                                <td>{row.item}</td>
                                                <td>
                                                    {row.goldColor || '—'}
                                                </td>
                                                <td>
                                                    {row.goldWeight || '—'}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : loadingDetail && !detail ? (
                    <Text>Memuat detail SPK...</Text>
                ) : detail ? (
                    <div className="jewelCadAddSpkDetail spkFioriDetailBlock">
                        <section className="spkShowSection">
                            <h3 className="spkShowSectionTitle">
                                Informasi Produksi
                            </h3>
                            <table className="spkShowMetaTable">
                                <tbody>
                                    <tr>
                                        <th scope="row">Tipe Produksi</th>
                                        <td>{detail.production.spkType}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Pesanan</th>
                                        <td>
                                            {detail.production.requestOrderNo} (
                                            {detail.production.customer})
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Tanggal Permintaan</th>
                                        <td>{detail.production.orderDate}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            Tanggal Estimasi Selesai
                                        </th>
                                        <td>
                                            {detail.production.estimatedDelivery}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>

                        <section className="spkShowSection">
                            <h3 className="spkShowSectionTitle">Detail Item</h3>
                            <div className="spkItemDetailGrid">
                                <div
                                    className="spkItemImageCol"
                                    aria-label="Gambar item"
                                >
                                    {imagePreviewUrl ? (
                                        <div className="spkItemImagePreview">
                                            <img
                                                src={imagePreviewUrl}
                                                alt={`Gambar ${detail.production.spkNo}`}
                                            />
                                        </div>
                                    ) : (
                                        <div className="spkItemImagePlaceholder">
                                            <Icon
                                                name={pictureIcon}
                                                mode="Decorative"
                                            />
                                            <span>Gambar item</span>
                                        </div>
                                    )}
                                    <div className="spkFioriDetailFile">
                                        <FileUploader
                                            accept=".jpg,.jpeg,.png,.pdf,.webp"
                                            placeholder="Upload gambar SPK"
                                            valueState={fieldState(errors.file)}
                                            onChange={(event) => {
                                                const files = event.target.files;
                                                const nextFile =
                                                    files?.[0] ?? null;

                                                setImagePreviewUrl(
                                                    (previous) => {
                                                        if (
                                                            previous?.startsWith(
                                                                'blob:',
                                                            )
                                                        ) {
                                                            URL.revokeObjectURL(
                                                                previous,
                                                            );
                                                        }

                                                        return nextFile
                                                            ? URL.createObjectURL(
                                                                  nextFile,
                                                              )
                                                            : (detail.item
                                                                  .imageUrl ??
                                                              null);
                                                    },
                                                );
                                                setImageFile(nextFile);
                                                setCurrentFileName(
                                                    nextFile?.name ??
                                                        detail.item.fileName,
                                                );
                                            }}
                                        />
                                        {currentFileName ? (
                                            <Text className="spkFioriHint">
                                                File saat ini: {currentFileName}
                                            </Text>
                                        ) : null}
                                        {errors.file ? (
                                            <Text className="spkFioriError">
                                                {errors.file}
                                            </Text>
                                        ) : null}
                                    </div>
                                </div>

                                <div className="spkItemFieldsCol">
                                    <table className="spkItemMetaTable spkItemMetaTable--sm">
                                        <tbody>
                                            <tr>
                                                <th scope="row">
                                                    Tipe Item | SKU
                                                </th>
                                                <td>
                                                    <div className="spkItemTypeProductStack">
                                                        <span className="spkItemTypeProductLine">
                                                            {[
                                                                detail.item
                                                                    .typeCode !==
                                                                '-'
                                                                    ? detail
                                                                          .item
                                                                          .typeCode
                                                                    : null,
                                                                detail.item
                                                                    .productItemName !==
                                                                '-'
                                                                    ? detail
                                                                          .item
                                                                          .productItemName
                                                                    : null,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' | ') ||
                                                                '—'}
                                                        </span>
                                                        {detail.item.skuCode !==
                                                        '-' ? (
                                                            <span className="spkItemSkuCode">
                                                                {
                                                                    detail.item
                                                                        .skuCode
                                                                }
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Qty</th>
                                                <td>{detail.item.qty}</td>
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
                                                                {
                                                                    detail.item
                                                                        .diameter
                                                                }
                                                            </strong>
                                                        </div>
                                                        <div className="spkItemUkuranField">
                                                            <span className="spkItemUkuranLabel">
                                                                Dimensi PxL
                                                                (mm)
                                                            </span>
                                                            <strong>
                                                                {
                                                                    detail.item
                                                                        .dimensi
                                                                }
                                                            </strong>
                                                        </div>
                                                        <div className="spkItemUkuranField">
                                                            <span className="spkItemUkuranLabel">
                                                                Ring Size
                                                            </span>
                                                            <strong>
                                                                {
                                                                    detail.item
                                                                        .ringSize
                                                                }
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    <GoldWeightRowLabel
                                                        currentWeight={
                                                            goldWeight
                                                        }
                                                        masterWeight={
                                                            detail.item
                                                                .masterGoldWeight
                                                        }
                                                    />
                                                </th>
                                                <td>
                                                    <Input
                                                        accessibleName="Berat Emas"
                                                        className="spkItemMetaInput"
                                                        type="Number"
                                                        value={goldWeight}
                                                        valueState={fieldState(
                                                            errors.gold_weight,
                                                        )}
                                                        onInput={(event) =>
                                                            setGoldWeight(
                                                                event.target
                                                                    .value ??
                                                                    '',
                                                            )
                                                        }
                                                    />
                                                    <GoldWeightMasterHint
                                                        currentWeight={
                                                            goldWeight
                                                        }
                                                        masterWeight={
                                                            detail.item
                                                                .masterGoldWeight
                                                        }
                                                    />
                                                    {errors.gold_weight ? (
                                                        <Text className="spkFioriError">
                                                            {
                                                                errors.gold_weight
                                                            }
                                                        </Text>
                                                    ) : null}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Bahan Emas</th>
                                                <td>
                                                    {goldColor.trim() !== ''
                                                        ? goldColor
                                                        : '—'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    File JewelCAD 3D
                                                </th>
                                                <td>
                                                    <Input
                                                        accessibleName="File JewelCAD 3D"
                                                        className="spkItemMetaInput"
                                                        value={jwcad3d}
                                                        placeholder="Nama file JewelCAD"
                                                        valueState={fieldState(
                                                            errors.jwcad_3d,
                                                        )}
                                                        onInput={(event) =>
                                                            setJwcad3d(
                                                                event.target
                                                                    .value ??
                                                                    '',
                                                            )
                                                        }
                                                    />
                                                    {errors.jwcad_3d ? (
                                                        <Text className="spkFioriError">
                                                            {errors.jwcad_3d}
                                                        </Text>
                                                    ) : null}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Catatan</th>
                                                <td className="spkItemNotesCell">
                                                    {detail.production.notes ||
                                                        '—'}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>

                        {showMasterSkuSyncAlert ? (
                            <Text
                                className="spkMasterSyncAlert"
                                role="alert"
                            >
                                Perubahan pada detail item akan disimpan ke
                                Master SKU
                            </Text>
                        ) : null}

                        <SpkFormStoneListCard
                            stones={stones}
                            shapeOptions={detail.options.shapeOptions}
                            positionOptions={detail.options.positionOptions}
                            onChange={setStones}
                            errors={errors}
                        />

                        <div className="jewelCadAddSpkEstimation">
                            <Label showColon required>
                                Estimasi BRJ
                            </Label>
                            <Input
                                type="Number"
                                value={estimationBrj}
                                valueState={fieldState(errors.estimation_brj)}
                                onInput={(event) =>
                                    setEstimationBrj(event.target.value ?? '')
                                }
                            />
                            {errors.estimation_brj ? (
                                <Text className="spkFioriError">
                                    {errors.estimation_brj}
                                </Text>
                            ) : null}
                        </div>
                    </div>
                ) : null}

                <DialogFooter>
                    {step === 'detail' && !isEditing ? (
                        <Button
                            design="Transparent"
                            type="Button"
                            onClick={() => {
                                setStep('select');
                                setDetail(null);
                                setErrors({});
                            }}
                        >
                            Kembali
                        </Button>
                    ) : null}
                    <Button
                        design="Default"
                        type="Button"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    {step === 'detail' ? (
                        <Button
                            design="Emphasized"
                            type="Button"
                            disabled={loadingDetail || !detail}
                            onClick={handleAdd}
                        >
                            {isEditing ? 'Simpan Perubahan' : 'Tambah'}
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
