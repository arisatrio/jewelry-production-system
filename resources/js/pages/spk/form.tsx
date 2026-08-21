import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import paperPlaneIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import pictureIcon from '@ui5/webcomponents-icons/dist/picture.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import saveIcon from '@ui5/webcomponents-icons/dist/save.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { DatePicker } from '@ui5/webcomponents-react/DatePicker';
import { FileUploader } from '@ui5/webcomponents-react/FileUploader';
import { Form } from '@ui5/webcomponents-react/Form';
import { FormGroup } from '@ui5/webcomponents-react/FormGroup';
import { FormItem } from '@ui5/webcomponents-react/FormItem';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Label } from '@ui5/webcomponents-react/Label';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { Text } from '@ui5/webcomponents-react/Text';
import { TextArea } from '@ui5/webcomponents-react/TextArea';
import ProductionController from '@/actions/App/Http/Controllers/ProductionController';
import { SpkCreateSelectorDialog } from '@/components/spk/spk-create-selector-dialog';
import {
    GoldWeightMasterHint,
    GoldWeightRowLabel,
    isGoldWeightChangedFromMaster,
    isStoneListChangedFromMaster,
    SpkFormStoneListCard,
    type SpkFormStoneRow,
} from '@/components/spk/spk-stone-list';
import {
    index as spkIndex,
    show as spkShow,
    store,
    submit as spkSubmitRoute,
    update,
} from '@/routes/spk';
import {
    referenceSpks as searchReferenceSpks,
    requestOrders as searchRequestOrders,
} from '@/routes/spk/select';

type OptionItem = {
    value: string;
    label: string;
    prefix?: string;
    name?: string;
};

type StoneRow = {
    id: string;
    positionId?: string;
    positionName?: string;
    shape: string;
    shapeId?: string;
    pcs: number;
    carat: number;
    totalCarat: number;
    size: string | number;
};

type SpkType = 'Pesanan' | 'Exchange' | 'Refund' | 'Reparasi' | 'Stock';

type RequestOrderOption = {
    rowId: number;
    docNo: string;
    customer: string;
    item: string;
    refSku: string | null;
};

type ReferenceSpkOption = {
    rowId: number;
    spkNo: string;
    customer: string;
    item: string;
    lastWeight: string | null;
    frameNo: string | null;
    requestOrderNo: string | null;
};

type ProductionForm = {
    id: number | null;
    isNew: boolean;
    spkNo: string | null;
    spkType: string;
    requestOrderNo: string | null;
    customerName: string | null;
    itemName: string | null;
    refSpkId: number | null;
    refSpkNo: string | null;
    orderDate: string;
    priority: string;
    description: string;
    workEstimated: number | null;
    estimatedDeliveryTime: string;
    itemTypeId: string;
    categoryPrefixId?: string;
    skuId?: string;
    frameId: string;
    frameNo: string;
    qty: string;
    satuan: string;
    statusOrder: string;
    diameterLengthRingsize: string;
    diameter?: string;
    dimensi?: string;
    ringSize?: string;
    goldWeight: string;
    goldColor: string;
    goldContent: string;
    jwcad3d: string;
    notes: string;
    fileName: string | null;
    status: string;
    hasRequestOrder: boolean;
};

type SkuStoneOption = {
    shapeId: string;
    shapeName: string;
    positionId: string;
    positionNama: string;
    pcs: string;
    caratPerPcs: string;
    size: string;
};

type SkuOption = {
    value: string;
    label: string;
    skuCode: string;
    itemOriginal: string;
    categoryPrefixId: string;
    description: string;
    goldColor: string;
    goldWeight: string | null;
    jwcad3d: string;
    imageUrl: string | null;
    stones: SkuStoneOption[];
};

type ApprovalFooterColumn = {
    title: string;
    name: string;
    date: string;
};

type ApprovalAbilities = {
    canEdit: boolean;
    canSubmit: boolean;
    canApprove: boolean;
    canReject: boolean;
    status: string;
    statusLabel: string;
    role: string;
    history: Array<{
        status: string;
        statusLabel: string;
        approve: string;
        notes: string | null;
        createdBy: string | null;
        createdAt: string | null;
    }>;
};

type SpkFormProps = {
    production: ProductionForm;
    stones: StoneRow[];
    formDocumentNo: string;
    productionImageBaseUrl: string;
    approvalFooter: ApprovalFooterColumn[];
    approval: ApprovalAbilities;
    options: {
        spkTypes: SpkType[];
        units: string[];
        priorities: OptionItem[];
        statusOrders: OptionItem[];
        goldColors: string[];
        itemTypes?: OptionItem[];
        categories?: OptionItem[];
        skus?: SkuOption[];
        shapeOptions?: OptionItem[];
        positionOptions?: OptionItem[];
    };
};

const REFERENCE_TYPES: SpkType[] = ['Exchange', 'Refund', 'Reparasi'];

function combineUkuranLabel(
    diameter: string,
    dimensi: string,
    ringSize: string,
): string {
    const parts = [diameter, dimensi, ringSize].map((value) => value.trim());

    if (parts.every((value) => value === '')) {
        return '';
    }

    // Tetap 3 slot agar posisi diameter / dimensi / ring size tidak bergeser.
    return parts.join(' / ');
}


function formatDisplayDate(isoDate: string): string {
    if (!isoDate) {
        return '';
    }

    const [year, month, day] = isoDate.split('-');

    if (!year || !month || !day) {
        return isoDate;
    }

    return `${day}/${month}/${year}`;
}

function countWorkingDaysBetween(startDate: string, endDate: string): number | null {
    if (!startDate || !endDate) {
        return null;
    }

    const start = new Date(`${startDate}T00:00:00`);
    const end = new Date(`${endDate}T00:00:00`);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return null;
    }

    if (end <= start) {
        return 0;
    }

    let count = 0;
    const current = new Date(start);

    while (current < end) {
        current.setDate(current.getDate() + 1);

        const day = current.getDay();

        if (day !== 0 && day !== 6) {
            count++;
        }
    }

    return count;
}

function formatDecimal3(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '';
    }

    const text = String(value).trim();

    if (text === '' || text === '-') {
        return text;
    }

    const numeric = Number(text.replace(',', '.'));

    if (!Number.isFinite(numeric)) {
        return text;
    }

    return numeric.toFixed(3);
}

function formatSize(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '';
    }

    const text = String(value).trim();

    if (text === '' || text === '-') {
        return text;
    }

    const numeric = Number(text.replace(',', '.'));

    if (!Number.isFinite(numeric)) {
        return text;
    }

    return numeric.toFixed(3).replace(/\.?0+$/, '') || '0';
}

function fieldState(message?: string): 'None' | 'Negative' {
    return message ? 'Negative' : 'None';
}

function readXsrfToken(): string {
    const row = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    if (!row) {
        return '';
    }

    return decodeURIComponent(row.slice('XSRF-TOKEN='.length));
}

function resolvePrintImageUrl(url: string | null | undefined): string {
    if (!url || url.trim() === '') {
        return '';
    }

    const imageUrl = url.trim();

    if (
        imageUrl.startsWith('http://') ||
        imageUrl.startsWith('https://') ||
        imageUrl.startsWith('data:') ||
        imageUrl.startsWith('blob:')
    ) {
        return imageUrl;
    }

    if (imageUrl.startsWith('//')) {
        return `${window.location.protocol}${imageUrl}`;
    }

    if (imageUrl.startsWith('/')) {
        return `${window.location.origin}${imageUrl}`;
    }

    return `${window.location.origin}/${imageUrl.replace(/^\/+/, '')}`;
}

function splitUkuranLabel(value: string): {
    diameter: string;
    dimensi: string;
    ringSize: string;
} {
    const raw = value.trim();

    if (raw === '') {
        return {
            diameter: '',
            dimensi: '',
            ringSize: '',
        };
    }

    // Pecah 3 slot: diameter / dimensi / ring size (toleran spasi di sekitar '/').
    const parts = raw.split(/\s*\/\s*/).map((part) => part.trim());

    while (parts.length < 3) {
        parts.push('');
    }

    return {
        diameter: parts[0] ?? '',
        dimensi: parts[1] ?? '',
        ringSize: parts.slice(2).join(' / ').trim(),
    };
}

function stoneMasterFromSku(stone: SkuStoneOption): SpkFormStoneRow['master'] {
    return {
        positionNama: stone.positionNama ?? '',
        positionName: stone.positionNama ?? '',
        shapeId: stone.shapeId ?? '',
        shapeName: stone.shapeName ?? '',
        size: stone.size ?? '',
        pcs: stone.pcs ?? '',
        caratPerPcs: stone.caratPerPcs ?? '',
    };
}

function mapProductionStonesToForm(
    stones: StoneRow[],
    skuStones: SkuStoneOption[] = [],
): SpkFormStoneRow[] {
    return stones.map((stone, index) => ({
        id: stone.id,
        positionId: stone.positionId ?? '',
        positionName: stone.positionName ?? '',
        positionNama: '',
        shapeId: stone.shapeId ?? '',
        shapeName: stone.shape,
        size:
            stone.size === '-' || stone.size === null || stone.size === undefined
                ? ''
                : String(stone.size),
        pcs: stone.pcs ? String(stone.pcs) : '',
        caratPerPcs: stone.carat ? String(stone.carat) : '',
        master: skuStones[index] ? stoneMasterFromSku(skuStones[index]) : null,
    }));
}

function mapSkuStonesToForm(stones: SkuStoneOption[]): SpkFormStoneRow[] {
    return stones.map((stone, index) => ({
        id: `sku-stone-${index}`,
        positionId: stone.positionId ?? '',
        positionName: stone.positionNama ?? '',
        positionNama: stone.positionNama ?? '',
        shapeId: stone.shapeId ?? '',
        shapeName: stone.shapeName ?? '',
        size: stone.size ?? '',
        pcs: stone.pcs ?? '',
        caratPerPcs: stone.caratPerPcs ?? '',
        master: stoneMasterFromSku(stone),
    }));
}

export default function SpkFormPage({
    production,
    stones: productionStones,
    options,
    formDocumentNo,
    approvalFooter,
    approval,
}: SpkFormProps) {
    const isNew = production.isNew;
    const canEdit = approval?.canEdit !== false;
    const canSubmit = approval?.canSubmit === true;
    const titleSpkNo = production.spkNo ?? 'SPK Baru';
    const displaySpkNo = production.spkNo || (isNew ? 'auto-generated' : '-');
    const skuOptions = options.skus ?? [];
    const categoryOptions = options.categories ?? options.itemTypes ?? [];
    const initialCategoryId =
        production.categoryPrefixId ?? production.itemTypeId ?? '';
    const initialSkuId = production.skuId ?? '';
    const initialSku =
        skuOptions.find((sku) => sku.value === initialSkuId) ?? null;
    const initialCategory =
        categoryOptions.find(
            (category) => category.value === initialCategoryId,
        ) ?? null;
    const initialUkuran =
        production.diameter !== undefined ||
        production.dimensi !== undefined ||
        production.ringSize !== undefined
            ? {
                  diameter: production.diameter ?? '',
                  dimensi: production.dimensi ?? '',
                  ringSize: production.ringSize ?? '',
              }
            : splitUkuranLabel(production.diameterLengthRingsize || '');

    const { data, setData, post, processing, errors, transform } = useForm({
        spk_type: production.spkType || 'Stock',
        request_order_no: production.requestOrderNo ?? '',
        ref_spk_id:
            production.refSpkId !== null ? String(production.refSpkId) : '',
        order_date: production.orderDate,
        estimated_delivery_time: production.estimatedDeliveryTime,
        priority: production.priority || 'NO',
        description: production.description,
        category_prefix_id: initialCategoryId,
        sku_id: initialSkuId,
        qty: production.qty !== '' ? production.qty : '1',
        satuan: production.satuan || 'Pcs',
        diameter_length_ringsize: production.diameterLengthRingsize,
        diameter: initialUkuran.diameter,
        dimensi: initialUkuran.dimensi,
        ring_size: initialUkuran.ringSize,
        gold_weight: production.goldWeight,
        gold_color: production.goldColor,
        gold_content: production.goldContent,
        jwcad_3d: production.jwcad3d ?? '',
        notes: production.notes,
        file: null as File | null,
        stones: [] as Array<{
            shape_id: string;
            position_id: string;
            position_nama: string;
            pcs: string;
            carat_per_pcs: string;
            size: string;
        }>,
    });

    const [requestOrderLabel, setRequestOrderLabel] = useState(() =>
        production.requestOrderNo
            ? {
                  docNo: production.requestOrderNo,
                  customer: production.customerName ?? '',
                  item: production.itemName ?? '',
              }
            : null,
    );
    const [referenceLabel, setReferenceLabel] = useState(() =>
        production.refSpkNo
            ? {
                  spkNo: production.refSpkNo,
                  customer: production.customerName ?? '',
                  item: production.itemName ?? '',
              }
            : null,
    );
    const [requestOrderOpen, setRequestOrderOpen] = useState(false);
    const [referenceOpen, setReferenceOpen] = useState(false);
    const [requestOrders, setRequestOrders] = useState<RequestOrderOption[]>(
        [],
    );
    const [referenceSpks, setReferenceSpks] = useState<ReferenceSpkOption[]>(
        [],
    );
    const [requestOrderSearch, setRequestOrderSearch] = useState('');
    const [referenceSearch, setReferenceSearch] = useState('');
    const [loadingRequestOrders, setLoadingRequestOrders] = useState(false);
    const [loadingReferences, setLoadingReferences] = useState(false);
    const [selectedSkuId, setSelectedSkuId] = useState(() => initialSkuId);
    const [productItemText, setProductItemText] = useState(
        () => initialSku?.label ?? '',
    );
    const [formStones, setFormStones] = useState<SpkFormStoneRow[]>(() =>
        mapProductionStonesToForm(productionStones, initialSku?.stones ?? []),
    );
    const [ukuran, setUkuran] = useState(() => initialUkuran);
    const shapeOptions = options.shapeOptions ?? [];
    const positionOptions = options.positionOptions ?? [];
    const [itemTypeText, setItemTypeText] = useState(
        () => initialCategory?.label ?? '',
    );

    const spkType = data.spk_type as SpkType;
    const needsRequestOrder = isNew && spkType === 'Pesanan';
    const needsReference =
        isNew && REFERENCE_TYPES.includes(spkType as SpkType);
    const showRequestOrderInfo =
        (!isNew && production.hasRequestOrder) ||
        (isNew && Boolean(data.request_order_no));
    const showReferenceInfo =
        (!isNew && Boolean(production.refSpkNo)) ||
        (isNew && Boolean(data.ref_spk_id));

    const skuOptionsForCategory = useMemo(() => {
        if (!data.category_prefix_id) {
            return [];
        }

        return skuOptions.filter(
            (sku) => sku.categoryPrefixId === data.category_prefix_id,
        );
    }, [data.category_prefix_id, skuOptions]);

    const selectedSku = useMemo(() => {
        if (!selectedSkuId) {
            return null;
        }

        return skuOptions.find((sku) => sku.value === selectedSkuId) ?? null;
    }, [skuOptions, selectedSkuId]);

    const itemImageUrl = selectedSku?.imageUrl ?? null;
    const showMasterSkuSyncAlert =
        isGoldWeightChangedFromMaster(
            data.gold_weight,
            selectedSku?.goldWeight,
        ) ||
        isStoneListChangedFromMaster(
            formStones,
            selectedSku?.stones?.length ?? 0,
        );

    useEffect(() => {
        if (!isNew || !requestOrderOpen) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setLoadingRequestOrders(true);

            void fetch(
                searchRequestOrders.url({
                    query: {
                        search: requestOrderSearch || undefined,
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
                        throw new Error('Gagal memuat request order');
                    }

                    const payload = (await response.json()) as {
                        data: RequestOrderOption[];
                    };

                    setRequestOrders(payload.data ?? []);
                })
                .catch((error: unknown) => {
                    if (
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    ) {
                        return;
                    }

                    setRequestOrders([]);
                })
                .finally(() => setLoadingRequestOrders(false));
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [isNew, requestOrderOpen, requestOrderSearch]);

    useEffect(() => {
        if (!isNew || !referenceOpen) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setLoadingReferences(true);

            void fetch(
                searchReferenceSpks.url({
                    query: {
                        search: referenceSearch || undefined,
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
                        throw new Error('Gagal memuat SPK referensi');
                    }

                    const payload = (await response.json()) as {
                        data: ReferenceSpkOption[];
                    };

                    setReferenceSpks(payload.data ?? []);
                })
                .catch((error: unknown) => {
                    if (
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    ) {
                        return;
                    }

                    setReferenceSpks([]);
                })
                .finally(() => setLoadingReferences(false));
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [isNew, referenceOpen, referenceSearch]);

    const estimatedDeliveryLabel = data.estimated_delivery_time
        ? formatDisplayDate(data.estimated_delivery_time)
        : '';
    const calculatedWorkEstimated = countWorkingDaysBetween(
        data.order_date,
        data.estimated_delivery_time,
    );
    const resolvedWorkEstimated =
        calculatedWorkEstimated ??
        (production.workEstimated !== null && production.workEstimated > 0
            ? production.workEstimated
            : null);
    const workEstimatedText =
        resolvedWorkEstimated !== null
            ? `${resolvedWorkEstimated} hari kerja`
            : '';

    const handleSpkTypeChange = (nextType: string) => {
        setData({
            ...data,
            spk_type: nextType,
            request_order_no: '',
            ref_spk_id: '',
        });
        setRequestOrderLabel(null);
        setReferenceLabel(null);
    };

    const handleItemTypeChange = (
        nextCategoryId: string,
        nextLabel?: string,
    ) => {
        const skuStillValid = skuOptions.some(
            (sku) =>
                sku.value === selectedSkuId &&
                sku.categoryPrefixId === nextCategoryId,
        );

        if (!skuStillValid) {
            setSelectedSkuId('');
            setProductItemText('');
            setFormStones([]);
        }

        setData({
            ...data,
            category_prefix_id: nextCategoryId,
            sku_id: skuStillValid ? selectedSkuId : '',
            gold_weight: skuStillValid ? data.gold_weight : '0',
            gold_color: skuStillValid ? data.gold_color : '',
            description: skuStillValid ? data.description : '',
        });

        if (nextLabel !== undefined) {
            setItemTypeText(nextLabel);

            return;
        }

        if (!nextCategoryId) {
            setItemTypeText('');

            return;
        }

        const matched = categoryOptions.find(
            (option) => option.value === nextCategoryId,
        );
        setItemTypeText(matched?.label ?? '');
    };

    const applyUkuran = (
        nextUkuran: {
            diameter: string;
            dimensi: string;
            ringSize: string;
        },
        extras: Record<string, string> = {},
    ) => {
        setUkuran(nextUkuran);
        setData({
            ...data,
            ...extras,
            diameter: nextUkuran.diameter,
            dimensi: nextUkuran.dimensi,
            ring_size: nextUkuran.ringSize,
            diameter_length_ringsize: combineUkuranLabel(
                nextUkuran.diameter,
                nextUkuran.dimensi,
                nextUkuran.ringSize,
            ),
        });
    };

    const handleUkuranChange = (
        field: 'diameter' | 'dimensi' | 'ringSize',
        value: string,
    ) => {
        applyUkuran({
            ...ukuran,
            [field]: value,
        });
    };

    const handleSkuChange = (nextSkuId: string, nextLabel?: string) => {
        setSelectedSkuId(nextSkuId);

        const sku = skuOptions.find((item) => item.value === nextSkuId);

        if (!sku) {
            setProductItemText(nextLabel ?? '');
            setFormStones([]);
            setData({
                ...data,
                sku_id: '',
                description: '',
                gold_weight: '0',
                gold_color: '',
                jwcad_3d: '',
            });

            return;
        }

        const category = categoryOptions.find(
            (option) => option.value === sku.categoryPrefixId,
        );

        if (category) {
            setItemTypeText(category.label);
        }

        setProductItemText(sku.label);
        setFormStones(mapSkuStonesToForm(sku.stones ?? []));
        setData({
            ...data,
            category_prefix_id: sku.categoryPrefixId || data.category_prefix_id,
            sku_id: nextSkuId,
            description:
                sku.description.trim() !== ''
                    ? sku.description
                    : sku.label,
            gold_color: sku.goldColor || data.gold_color,
            gold_weight: sku.goldWeight || '0',
            jwcad_3d: sku.jwcad3d || '',
        });
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!canEdit && !isNew) {
            return;
        }

        transform((formData) => ({
            ...formData,
            stones: formStones.map((stone) => ({
                shape_id: stone.shapeId,
                position_id: stone.positionId,
                position_nama: stone.positionNama,
                pcs: stone.pcs,
                carat_per_pcs: stone.caratPerPcs,
                size: stone.size,
            })),
        }));

        if (isNew) {
            post(store.url(), {
                forceFormData: true,
                preserveScroll: true,
            });

            return;
        }

        post(update.url(production.id!), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const submitToManager = (): void => {
        if (!production.id || !canSubmit) {
            return;
        }

        router.post(spkSubmitRoute.url(production.id), {}, {
            preserveScroll: true,
        });
    };

    const openPrintPreview = async () => {
        const selectedCategory =
            categoryOptions.find(
                (category) => category.value === data.category_prefix_id,
            ) ?? null;

        const typeCodeFromLabel = (
            itemTypeText || production.itemName || ''
        ).match(/\(([^)]+)\)\s*$/);

        const typeCode =
            selectedCategory?.prefix?.trim() ||
            typeCodeFromLabel?.[1]?.trim() ||
            '';

        const productItemName =
            selectedSku?.itemOriginal?.trim() ||
            '';

        const skuCode = selectedSku?.skuCode?.trim() || '';

        const typeVariant = [
            typeCode,
            productItemName,
        ]
            .filter((value): value is string =>
                Boolean(value && value.trim() !== ''),
            )
            .join(' | ');

        const qtyLabel = `${data.qty || '-'} ${data.satuan || 'Pcs'}`;

        const payload = {
            info: {
                spkNo: displaySpkNo,
                spkType: data.spk_type || '-',
                requestOrderNo:
                    data.request_order_no || requestOrderLabel?.docNo || '',
                refSpkNo: referenceLabel?.spkNo || production.refSpkNo || '',
                customerName:
                    requestOrderLabel?.customer ||
                    referenceLabel?.customer ||
                    production.customerName ||
                    '',
                orderDate: formatDisplayDate(data.order_date),
                workEstimated: estimatedDeliveryLabel,
                estimatedDelivery: estimatedDeliveryLabel,
                priority: data.priority || '',
                itemType: itemTypeText || production.itemName || '',
                itemVariance: selectedSku?.label || productItemText || '',
                qty: qtyLabel,
            },
            item: {
                typeVariant: typeVariant || '-',
                typeCode,
                productItemName,
                skuCode,
                skuId: data.sku_id || '',
                productionId: production.id ? String(production.id) : '',
                qty: qtyLabel,
                diameter: formatSize(ukuran.diameter),
                dimensi: formatSize(ukuran.dimensi),
                ringSize: ukuran.ringSize,
                goldWeight: formatDecimal3(data.gold_weight),
                goldColor: data.gold_color,
                jwcad3d: data.jwcad_3d,
                description: data.description,
                imageUrl: resolvePrintImageUrl(selectedSku?.imageUrl ?? ''),
            },
            stones: formStones.map((stone) => {
                const shapeOption = shapeOptions.find(
                    (option) => option.value === stone.shapeId,
                );

                return {
                    positionName:
                        stone.positionNama || stone.positionName || '',
                    shapeName:
                        shapeOption?.name ||
                        stone.shapeName ||
                        '',
                    size: formatSize(stone.size),
                    caratPerPcs: formatDecimal3(stone.caratPerPcs),
                    pcs: stone.pcs,
                    totalCarat: (() => {
                        const pcs = Number(stone.pcs || 0);
                        const carat = Number(
                            String(stone.caratPerPcs).replace(',', '.') || 0,
                        );

                        if (!Number.isFinite(pcs) || !Number.isFinite(carat)) {
                            return '';
                        }

                        return (pcs * carat).toFixed(3);
                    })(),
                };
            }),
            notes: data.notes,
            approval: approvalFooter,
            detailUrl:
                !production.isNew && production.spkNo
                    ? `${window.location.origin}${spkShow.url(production.spkNo)}`
                    : '',
        };

        try {
            const response = await fetch(
                ProductionController.printPreview.url(),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': readXsrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ document: payload }),
                },
            );

            if (!response.ok) {
                throw new Error('Gagal membuka preview print');
            }

            const html = await response.text();
            const previewWindow = window.open('', '_blank');

            if (!previewWindow) {
                return;
            }

            previewWindow.document.open();
            previewWindow.document.write(html);
            previewWindow.document.close();
        } catch {
            window.alert('Gagal membuka preview print. Coba lagi.');
        }
    };

    return (
        <>
            <Head
                title={
                    isNew ? 'Tambah SPK' : `Edit SPK ${titleSpkNo}`
                }
            />

            <div className="spkDetailShell">
                <div className="spkDetailStack">
                    <form
                        id="spk-edit-form"
                        onSubmit={submit}
                        className="spkFioriForm"
                    >
                        <section className="spkFioriFormCard">
                            <div className="spkFioriFormCardHeader">
                                <div className="spkFioriFormCardHeaderMain">
                                    <h2 className="spkFioriFormCardTitle">
                                        {isNew
                                            ? 'Form Pembuatan SPK'
                                            : 'Form Edit SPK'}
                                    </h2>
                                    <p className="spkFioriFormCardSubtitle">
                                        No. Form Dokumen: {formDocumentNo}
                                    </p>
                                </div>
                                <div className="spkFioriFormCardActions">
                                    <Button
                                        design="Default"
                                        icon={printIcon}
                                        onClick={() => {
                                            void openPrintPreview();
                                        }}
                                    >
                                        Preview Print
                                    </Button>
                                    <Button
                                        design="Default"
                                        className="spkFioriFormCardBtnGrey"
                                        icon={declineIcon}
                                        onClick={() => {
                                            if (
                                                !isNew &&
                                                production.spkNo
                                            ) {
                                                router.visit(
                                                    spkShow.url(
                                                        production.spkNo,
                                                    ),
                                                );
                                                return;
                                            }

                                            router.visit(spkIndex.url());
                                        }}
                                    >
                                        Batal
                                    </Button>
                                    <Button
                                        design="Attention"
                                        className="spkFioriFormCardBtnYellow"
                                        icon={saveIcon}
                                        type="Submit"
                                        disabled={processing || (!isNew && !canEdit)}
                                    >
                                        {processing
                                            ? 'Menyimpan...'
                                            : 'Simpan Draft'}
                                    </Button>
                                    {!isNew && canSubmit ? (
                                        <Button
                                            design="Emphasized"
                                            icon={paperPlaneIcon}
                                            disabled={processing}
                                            onClick={submitToManager}
                                        >
                                            Kirim ke Manager
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                            <Form
                                accessibleMode="Edit"
                                layout="S1 M2 L2 XL2"
                                labelSpan="S12 M4 L4 XL4"
                                itemSpacing="Normal"
                            >
                                <FormGroup
                                    headerText="Informasi Produksi"
                                    columnSpan={2}
                                >
                                    <FormItem
                                        labelContent={
                                            <Label showColon>No. SPK</Label>
                                        }
                                    >
                                        <Input
                                            value={displaySpkNo}
                                            readonly
                                            accessibleName="No. SPK"
                                        />
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required={isNew}>
                                                Tipe Produksi
                                            </Label>
                                        }
                                    >
                                        {isNew ? (
                                            <Select
                                                accessibleName="Tipe Produksi"
                                                valueState={fieldState(
                                                    errors.spk_type,
                                                )}
                                                onChange={(event) =>
                                                    handleSpkTypeChange(
                                                        event.detail
                                                            .selectedOption
                                                            ?.value ?? 'Stock',
                                                    )
                                                }
                                            >
                                                {options.spkTypes.map(
                                                    (type) => (
                                                        <Option
                                                            key={type}
                                                            value={type}
                                                            selected={
                                                                data.spk_type ===
                                                                type
                                                            }
                                                        >
                                                            {type}
                                                        </Option>
                                                    ),
                                                )}
                                            </Select>
                                        ) : (
                                            <Input
                                                value={production.spkType}
                                                readonly
                                            />
                                        )}
                                        {errors.spk_type ? (
                                            <Text className="spkFioriError">
                                                {errors.spk_type}
                                            </Text>
                                        ) : null}
                                    </FormItem>

                                    {needsRequestOrder ? (
                                        <FormItem
                                            labelContent={
                                                <Label showColon required>
                                                    Pesanan
                                                </Label>
                                            }
                                        >
                                            <div className="spkCreateSelectorRow">
                                                <div className="spkCreateSelectorValue">
                                                    {requestOrderLabel ? (
                                                        <>
                                                            <strong>
                                                                {
                                                                    requestOrderLabel.docNo
                                                                }
                                                            </strong>
                                                            <span>
                                                                {
                                                                    requestOrderLabel.customer
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    requestOrderLabel.item
                                                                }
                                                            </span>
                                                        </>
                                                    ) : (
                                                        <span>
                                                            Belum ada pesanan
                                                            dipilih
                                                        </span>
                                                    )}
                                                </div>
                                                <Button
                                                    design="Default"
                                                    onClick={() =>
                                                        setRequestOrderOpen(
                                                            true,
                                                        )
                                                    }
                                                >
                                                    Pilih
                                                </Button>
                                            </div>
                                            {errors.request_order_no ? (
                                                <Text className="spkFioriError">
                                                    {errors.request_order_no}
                                                </Text>
                                            ) : null}
                                        </FormItem>
                                    ) : null}

                                    {needsReference ? (
                                        <FormItem
                                            labelContent={
                                                <Label showColon required>
                                                    SPK Referensi
                                                </Label>
                                            }
                                        >
                                            <div className="spkCreateSelectorRow">
                                                <div className="spkCreateSelectorValue">
                                                    {referenceLabel ? (
                                                        <>
                                                            <strong>
                                                                {
                                                                    referenceLabel.spkNo
                                                                }
                                                            </strong>
                                                            <span>
                                                                {
                                                                    referenceLabel.customer
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    referenceLabel.item
                                                                }
                                                            </span>
                                                        </>
                                                    ) : (
                                                        <span>
                                                            Belum ada SPK
                                                            referensi dipilih
                                                        </span>
                                                    )}
                                                </div>
                                                <Button
                                                    design="Default"
                                                    onClick={() =>
                                                        setReferenceOpen(true)
                                                    }
                                                >
                                                    Pilih
                                                </Button>
                                            </div>
                                            {errors.ref_spk_id ? (
                                                <Text className="spkFioriError">
                                                    {errors.ref_spk_id}
                                                </Text>
                                            ) : null}
                                        </FormItem>
                                    ) : null}

                                    {showRequestOrderInfo &&
                                    !needsRequestOrder ? (
                                        <>
                                            <FormItem
                                                labelContent={
                                                    <Label showColon>
                                                        Pesanan
                                                    </Label>
                                                }
                                            >
                                                <Input
                                                    value={
                                                        production.requestOrderNo ??
                                                        data.request_order_no
                                                    }
                                                    readonly
                                                />
                                            </FormItem>
                                            <FormItem
                                                labelContent={
                                                    <Label showColon>
                                                        Customer
                                                    </Label>
                                                }
                                            >
                                                <Input
                                                    value={
                                                        production.customerName ??
                                                        requestOrderLabel?.customer ??
                                                        ''
                                                    }
                                                    readonly
                                                />
                                            </FormItem>
                                            <FormItem
                                                labelContent={
                                                    <Label showColon>
                                                        Item
                                                    </Label>
                                                }
                                            >
                                                <Input
                                                    value={
                                                        production.itemName ??
                                                        requestOrderLabel?.item ??
                                                        ''
                                                    }
                                                    readonly
                                                />
                                            </FormItem>
                                        </>
                                    ) : null}

                                    {showRequestOrderInfo &&
                                    needsRequestOrder &&
                                    requestOrderLabel ? (
                                        <>
                                            <FormItem
                                                labelContent={
                                                    <Label showColon>
                                                        Customer
                                                    </Label>
                                                }
                                            >
                                                <Input
                                                    value={
                                                        requestOrderLabel.customer
                                                    }
                                                    readonly
                                                />
                                            </FormItem>
                                            <FormItem
                                                labelContent={
                                                    <Label showColon>
                                                        Item
                                                    </Label>
                                                }
                                            >
                                                <Input
                                                    value={
                                                        requestOrderLabel.item
                                                    }
                                                    readonly
                                                />
                                            </FormItem>
                                        </>
                                    ) : null}

                                    {showReferenceInfo && !needsReference ? (
                                        <FormItem
                                            labelContent={
                                                <Label showColon>
                                                    SPK Referensi
                                                </Label>
                                            }
                                        >
                                            <Input
                                                value={
                                                    production.refSpkNo ??
                                                    referenceLabel?.spkNo ??
                                                    ''
                                                }
                                                readonly
                                            />
                                        </FormItem>
                                    ) : null}

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Tanggal Permintaan
                                            </Label>
                                        }
                                    >
                                        <DatePicker
                                            value={data.order_date}
                                            valueFormat="yyyy-MM-dd"
                                            displayFormat="dd/MM/yyyy"
                                            required
                                            valueState={fieldState(
                                                errors.order_date,
                                            )}
                                            onChange={(event) =>
                                                setData(
                                                    'order_date',
                                                    event.target.value ?? '',
                                                )
                                            }
                                        />
                                        {errors.order_date ? (
                                            <Text className="spkFioriError">
                                                {errors.order_date}
                                            </Text>
                                        ) : null}
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Tanggal Estimasi Selesai
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriDateWithHint">
                                            <DatePicker
                                                value={data.estimated_delivery_time}
                                                valueFormat="yyyy-MM-dd"
                                                displayFormat="dd/MM/yyyy"
                                                required
                                                valueState={fieldState(
                                                    errors.estimated_delivery_time,
                                                )}
                                                onChange={(event) =>
                                                    setData(
                                                        'estimated_delivery_time',
                                                        event.target.value ?? '',
                                                    )
                                                }
                                            />
                                            {workEstimatedText ? (
                                                <Text className="spkFioriInlineHint">
                                                    {workEstimatedText}
                                                </Text>
                                            ) : null}
                                        </div>
                                        {errors.estimated_delivery_time ? (
                                            <Text className="spkFioriError">
                                                {
                                                    errors.estimated_delivery_time
                                                }
                                            </Text>
                                        ) : null}
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Prioritas
                                            </Label>
                                        }
                                    >
                                        <Select
                                            accessibleName="Prioritas"
                                            valueState={fieldState(
                                                errors.priority,
                                            )}
                                            onChange={(event) =>
                                                setData(
                                                    'priority',
                                                    event.detail.selectedOption
                                                        ?.value ?? '',
                                                )
                                            }
                                        >
                                            <Option
                                                value=""
                                                selected={data.priority === ''}
                                            >
                                                Pilih prioritas
                                            </Option>
                                            {options.priorities.map(
                                                (option) => (
                                                    <Option
                                                        key={option.value}
                                                        value={option.value}
                                                        selected={
                                                            data.priority ===
                                                            option.value
                                                        }
                                                    >
                                                        {option.label}
                                                    </Option>
                                                ),
                                            )}
                                        </Select>
                                        {errors.priority ? (
                                            <Text className="spkFioriError">
                                                {errors.priority}
                                            </Text>
                                        ) : null}
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Tipe Item
                                            </Label>
                                        }
                                    >
                                        <ComboBox
                                            accessibleName="Tipe Item"
                                            className="spkFioriComboBox"
                                            placeholder="Cari / pilih tipe item"
                                            filter="Contains"
                                            showClearIcon
                                            noTypeahead
                                            value={itemTypeText}
                                            valueState={fieldState(
                                                errors.category_prefix_id,
                                            )}
                                            onInput={(event) => {
                                                const nextText =
                                                    event.target.value ?? '';
                                                setItemTypeText(nextText);

                                                if (!nextText.trim()) {
                                                    handleItemTypeChange('');

                                                    return;
                                                }

                                                const selected =
                                                    categoryOptions.find(
                                                        (option) =>
                                                            option.value ===
                                                            data.category_prefix_id,
                                                    );

                                                if (
                                                    selected &&
                                                    selected.label !== nextText
                                                ) {
                                                    setData(
                                                        'category_prefix_id',
                                                        '',
                                                    );
                                                }
                                            }}
                                            onSelectionChange={(event) => {
                                                const item = event.detail.item;
                                                if (!item?.value) {
                                                    return;
                                                }

                                                handleItemTypeChange(
                                                    String(item.value),
                                                    item.text ?? '',
                                                );
                                            }}
                                            onChange={(event) => {
                                                const typed =
                                                    event.target.value?.trim() ??
                                                    '';

                                                if (!typed) {
                                                    handleItemTypeChange('');

                                                    return;
                                                }

                                                const matched =
                                                    categoryOptions.find(
                                                        (option) =>
                                                            option.label.toLowerCase() ===
                                                            typed.toLowerCase(),
                                                    );

                                                if (matched) {
                                                    handleItemTypeChange(
                                                        matched.value,
                                                        matched.label,
                                                    );
                                                }
                                            }}
                                        >
                                            {categoryOptions.map((option) => (
                                                <ComboBoxItem
                                                    key={option.value}
                                                    text={option.label}
                                                    value={option.value}
                                                />
                                            ))}
                                        </ComboBox>
                                        {errors.category_prefix_id ? (
                                            <Text className="spkFioriError">
                                                {errors.category_prefix_id}
                                            </Text>
                                        ) : null}
                                    </FormItem>

                                    <FormItem
                                        columnSpan={2}
                                        labelContent={
                                            <Label showColon required>
                                                SKU
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriFieldStack">
                                            <ComboBox
                                                accessibleName="SKU"
                                                className="spkFioriComboBox"
                                                disabled={
                                                    data.category_prefix_id ===
                                                    ''
                                                }
                                                placeholder={
                                                    data.category_prefix_id ===
                                                    ''
                                                        ? 'Pilih tipe item dahulu'
                                                        : 'Cari SKU (SKU)'
                                                }
                                                filter="Contains"
                                                showClearIcon
                                                noTypeahead
                                                value={productItemText}
                                                valueState={fieldState(
                                                    errors.sku_id,
                                                )}
                                                onInput={(event) => {
                                                    const nextText =
                                                        event.target.value ?? '';
                                                    setProductItemText(nextText);

                                                    if (!nextText.trim()) {
                                                        handleSkuChange('');

                                                        return;
                                                    }

                                                    const selected =
                                                        skuOptionsForCategory.find(
                                                            (option) =>
                                                                option.value ===
                                                                selectedSkuId,
                                                        );

                                                    if (
                                                        selected &&
                                                        selected.label !== nextText
                                                    ) {
                                                        setSelectedSkuId('');
                                                        setData('sku_id', '');
                                                    }
                                                }}
                                                onSelectionChange={(event) => {
                                                    const item =
                                                        event.detail.item;
                                                    if (!item?.value) {
                                                        return;
                                                    }

                                                    handleSkuChange(
                                                        String(item.value),
                                                        item.text ?? '',
                                                    );
                                                }}
                                                onChange={(event) => {
                                                    const typed =
                                                        event.target.value?.trim() ??
                                                        '';

                                                    if (!typed) {
                                                        handleSkuChange('');

                                                        return;
                                                    }

                                                    const matched =
                                                        skuOptionsForCategory.find(
                                                            (option) =>
                                                                option.label.toLowerCase() ===
                                                                    typed.toLowerCase() ||
                                                                option.skuCode.toLowerCase() ===
                                                                    typed.toLowerCase(),
                                                        );

                                                    if (matched) {
                                                        handleSkuChange(
                                                            matched.value,
                                                            matched.label,
                                                        );
                                                    }
                                                }}
                                            >
                                                {skuOptionsForCategory.map(
                                                    (option) => (
                                                        <ComboBoxItem
                                                            key={option.value}
                                                            text={option.label}
                                                            value={option.value}
                                                        />
                                                    ),
                                                )}
                                            </ComboBox>
                                            {errors.sku_id ? (
                                                <Text className="spkFioriError">
                                                    {errors.sku_id}
                                                </Text>
                                            ) : null}
                                        </div>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Qty
                                            </Label>
                                        }
                                    >
                                        <div className="spkFioriQtyWithUnit">
                                            <Input
                                                type="Number"
                                                value={data.qty}
                                                required
                                                valueState={fieldState(
                                                    errors.qty,
                                                )}
                                                onInput={(event) =>
                                                    setData(
                                                        'qty',
                                                        event.target.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            <Select
                                                accessibleName="Satuan"
                                                className="spkFioriUnitSelect"
                                                valueState={fieldState(
                                                    errors.satuan,
                                                )}
                                                onChange={(event) =>
                                                    setData(
                                                        'satuan',
                                                        event.detail
                                                            .selectedOption
                                                            ?.value ?? 'Pcs',
                                                    )
                                                }
                                            >
                                                {options.units.map((unit) => (
                                                    <Option
                                                        key={unit}
                                                        value={unit}
                                                        selected={
                                                            data.satuan === unit
                                                        }
                                                    >
                                                        {unit}
                                                    </Option>
                                                ))}
                                            </Select>
                                        </div>
                                        {errors.qty ? (
                                            <Text className="spkFioriError">
                                                {errors.qty}
                                            </Text>
                                        ) : null}
                                        {errors.satuan ? (
                                            <Text className="spkFioriError">
                                                {errors.satuan}
                                            </Text>
                                        ) : null}
                                    </FormItem>
                                </FormGroup>
                            </Form>

                            <div className="spkFioriDetailBlock">
                                <div className="spkFioriDetailBlockTitle">
                                    Detail Item
                                </div>
                                <div className="spkItemDetailGrid">
                                    <div
                                        className="spkItemImageCol"
                                        aria-label="Gambar item"
                                    >
                                        {itemImageUrl ? (
                                            <div className="spkItemImagePreview">
                                                <img
                                                    src={itemImageUrl}
                                                    alt={
                                                        selectedSku?.label
                                                            ? `Gambar ${selectedSku.label}`
                                                            : 'Gambar item'
                                                    }
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
                                                placeholder="Upload gambar"
                                                valueState={fieldState(
                                                    errors.file,
                                                )}
                                                onChange={(event) => {
                                                    const files =
                                                        event.target.files;
                                                    setData(
                                                        'file',
                                                        files?.[0] ?? null,
                                                    );
                                                }}
                                            />
                                            {production.fileName ? (
                                                <Text className="spkFioriHint">
                                                    File saat ini:{' '}
                                                    {production.fileName}
                                                </Text>
                                            ) : null}
                                            {selectedSku?.imageUrl ? (
                                                <Text className="spkFioriHint">
                                                    Menampilkan gambar dari
                                                    design_image SKU.
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
                                                        {(() => {
                                                            const selectedCategory =
                                                                categoryOptions.find(
                                                                    (category) =>
                                                                        category.value ===
                                                                        data.category_prefix_id,
                                                                ) ?? null;
                                                            const typeCodeFromLabel =
                                                                (
                                                                    itemTypeText ||
                                                                    production.itemName ||
                                                                    ''
                                                                ).match(
                                                                    /\(([^)]+)\)\s*$/,
                                                                );
                                                            const typeCode =
                                                                selectedCategory?.prefix?.trim() ||
                                                                typeCodeFromLabel?.[1]?.trim() ||
                                                                '';
                                                            const productItemName =
                                                                selectedSku?.itemOriginal?.trim() ||
                                                                '';
                                                            const skuCode =
                                                                selectedSku?.skuCode?.trim() ||
                                                                '';
                                                            const typeProductLine =
                                                                [
                                                                    typeCode,
                                                                    productItemName,
                                                                ]
                                                                    .filter(
                                                                        (
                                                                            value,
                                                                        ): value is string =>
                                                                            Boolean(
                                                                                value &&
                                                                                    value.trim() !==
                                                                                        '',
                                                                            ),
                                                                    )
                                                                    .join(
                                                                        ' | ',
                                                                    );

                                                            if (
                                                                typeProductLine ||
                                                                skuCode
                                                            ) {
                                                                return (
                                                                    <div className="spkItemTypeProductStack">
                                                                        {typeProductLine ? (
                                                                            <span className="spkItemTypeProductLine">
                                                                                {
                                                                                    typeProductLine
                                                                                }
                                                                            </span>
                                                                        ) : null}
                                                                        {skuCode ? (
                                                                            <span className="spkItemSkuCode">
                                                                                {
                                                                                    skuCode
                                                                                }
                                                                            </span>
                                                                        ) : null}
                                                                    </div>
                                                                );
                                                            }

                                                            return (
                                                                [
                                                                    itemTypeText ||
                                                                        production.itemName,
                                                                    selectedSku?.label ||
                                                                        productItemText,
                                                                ]
                                                                    .filter(
                                                                        (
                                                                            value,
                                                                        ): value is string =>
                                                                            Boolean(
                                                                                value &&
                                                                                    value.trim() !==
                                                                                        '',
                                                                            ),
                                                                    )
                                                                    .join(
                                                                        ' | ',
                                                                    ) || '—'
                                                            );
                                                        })()}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        Deskripsi Item
                                                    </th>
                                                    <td>
                                                        {data.description.trim() !==
                                                        ''
                                                            ? data.description
                                                            : '—'}
                                                        {errors.description ? (
                                                            <Text className="spkFioriError">
                                                                {
                                                                    errors.description
                                                                }
                                                            </Text>
                                                        ) : null}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        <GoldWeightRowLabel
                                                            currentWeight={
                                                                data.gold_weight
                                                            }
                                                            masterWeight={
                                                                selectedSku?.goldWeight
                                                            }
                                                        />
                                                    </th>
                                                    <td>
                                                        <Input
                                                            accessibleName="Berat Emas"
                                                            className="spkItemMetaInput"
                                                            type="Number"
                                                            value={
                                                                data.gold_weight
                                                            }
                                                            placeholder="Masukkan berat emas"
                                                            required
                                                            valueState={fieldState(
                                                                errors.gold_weight,
                                                            )}
                                                            onInput={(event) =>
                                                                setData(
                                                                    'gold_weight',
                                                                    event
                                                                        .target
                                                                        .value ??
                                                                        '',
                                                                )
                                                            }
                                                        />
                                                        <GoldWeightMasterHint
                                                            currentWeight={
                                                                data.gold_weight
                                                            }
                                                            masterWeight={
                                                                selectedSku?.goldWeight
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
                                                    <th scope="row">
                                                        Warna Emas
                                                    </th>
                                                    <td>
                                                        {data.gold_color.trim() !==
                                                        ''
                                                            ? data.gold_color
                                                            : '—'}
                                                        {errors.gold_color ? (
                                                            <Text className="spkFioriError">
                                                                {
                                                                    errors.gold_color
                                                                }
                                                            </Text>
                                                        ) : null}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        File JewelCAD 3D
                                                    </th>
                                                    <td>
                                                        {data.jwcad_3d.trim() !==
                                                        ''
                                                            ? data.jwcad_3d
                                                            : '—'}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Ukuran</th>
                                                    <td>
                                                        <div className="spkItemUkuranFields">
                                                            <div className="spkItemUkuranField">
                                                                <Label>
                                                                    Panjang
                                                                    (mm)
                                                                </Label>
                                                                <Input
                                                                    value={
                                                                        ukuran.diameter
                                                                    }
                                                                    placeholder="Masukkan panjang"
                                                                    onInput={(
                                                                        event,
                                                                    ) =>
                                                                        handleUkuranChange(
                                                                            'diameter',
                                                                            event
                                                                                .target
                                                                                .value ??
                                                                                '',
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="spkItemUkuranField">
                                                                <Label>
                                                                    Dimensi PxL
                                                                    (mm)
                                                                </Label>
                                                                <Input
                                                                    value={
                                                                        ukuran.dimensi
                                                                    }
                                                                    placeholder="Masukkan dimensi"
                                                                    onInput={(
                                                                        event,
                                                                    ) =>
                                                                        handleUkuranChange(
                                                                            'dimensi',
                                                                            event
                                                                                .target
                                                                                .value ??
                                                                                '',
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="spkItemUkuranField">
                                                                <Label>
                                                                    Ring Size
                                                                </Label>
                                                                <Input
                                                                    value={
                                                                        ukuran.ringSize
                                                                    }
                                                                    placeholder="Masukkan ring size"
                                                                    onInput={(
                                                                        event,
                                                                    ) =>
                                                                        handleUkuranChange(
                                                                            'ringSize',
                                                                            event
                                                                                .target
                                                                                .value ??
                                                                                '',
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                        {errors.diameter_length_ringsize ? (
                                                            <Text className="spkFioriError">
                                                                {
                                                                    errors.diameter_length_ringsize
                                                                }
                                                            </Text>
                                                        ) : null}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Catatan</th>
                                                    <td>
                                                        <TextArea
                                                            className="spkFioriNotesTextArea"
                                                            value={data.notes}
                                                            rows={4}
                                                            placeholder="Masukkan catatan"
                                                            onInput={(event) =>
                                                                setData(
                                                                    'notes',
                                                                    event
                                                                        .target
                                                                        .value ??
                                                                        '',
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {showMasterSkuSyncAlert ? (
                                    <Text
                                        className="spkMasterSyncAlert"
                                        role="alert"
                                    >
                                        Perubahan pada detail item akan
                                        disimpan ke Master SKU
                                    </Text>
                                ) : null}

                                <SpkFormStoneListCard
                                    stones={formStones}
                                    shapeOptions={shapeOptions}
                                    positionOptions={positionOptions}
                                    onChange={setFormStones}
                                    errors={errors}
                                />
                            </div>

                            <footer
                                className="spkApprovalFooter"
                                aria-label="Persetujuan"
                            >
                                {approvalFooter.map((column) => (
                                    <div
                                        key={column.title}
                                        className="spkApprovalFooterCol"
                                    >
                                        <div className="spkApprovalFooterTitle">
                                            {column.title}
                                        </div>
                                        <div className="spkApprovalFooterMeta">
                                            <div className="spkApprovalFooterMetaRow">
                                                <span className="spkApprovalFooterMetaLabel">
                                                    Nama
                                                </span>
                                                <span className="spkApprovalFooterMetaValue">
                                                    {column.name.trim() !== ''
                                                        ? column.name
                                                        : '-'}
                                                </span>
                                            </div>
                                            <div className="spkApprovalFooterMetaRow">
                                                <span className="spkApprovalFooterMetaLabel">
                                                    Tanggal
                                                </span>
                                                <span className="spkApprovalFooterMetaValue">
                                                    {column.date.trim() !== ''
                                                        ? column.date
                                                        : '-'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </footer>
                        </section>
                    </form>
                </div>
            </div>

            <SpkCreateSelectorDialog
                open={requestOrderOpen}
                onOpenChange={setRequestOrderOpen}
                title="Pilih Pesanan"
                description="Cari dan pilih request order untuk SPK Pesanan."
                search={requestOrderSearch}
                onSearchChange={setRequestOrderSearch}
                loading={loadingRequestOrders}
                emptyText="Tidak ada request order ditemukan."
                columns={[
                    { key: 'docNo', label: 'No. Pesanan' },
                    { key: 'customer', label: 'Customer' },
                    { key: 'item', label: 'Item' },
                ]}
                rows={requestOrders.map((row) => ({
                    id: String(row.rowId),
                    docNo: row.docNo,
                    customer: row.customer,
                    item: row.item,
                }))}
                onSelect={(id) => {
                    const selected = requestOrders.find(
                        (row) => String(row.rowId) === id,
                    );

                    if (selected) {
                        setData('request_order_no', selected.docNo);
                        setRequestOrderLabel({
                            docNo: selected.docNo,
                            customer: selected.customer,
                            item: selected.item,
                        });
                    }

                    setRequestOrderOpen(false);
                }}
            />

            <SpkCreateSelectorDialog
                open={referenceOpen}
                onOpenChange={setReferenceOpen}
                title="Pilih SPK Referensi"
                description="Pilih SPK berstatus SPKDONE sebagai referensi."
                search={referenceSearch}
                onSearchChange={setReferenceSearch}
                loading={loadingReferences}
                emptyText="Tidak ada SPK referensi ditemukan."
                columns={[
                    { key: 'spkNo', label: 'No. SPK' },
                    { key: 'customer', label: 'Customer' },
                    { key: 'item', label: 'Item' },
                    { key: 'lastWeight', label: 'Berat' },
                    { key: 'frameNo', label: 'Rangka' },
                ]}
                rows={referenceSpks.map((row) => ({
                    id: String(row.rowId),
                    spkNo: row.spkNo,
                    customer: row.customer,
                    item: row.item,
                    lastWeight: row.lastWeight ?? '—',
                    frameNo: row.frameNo ?? '—',
                }))}
                onSelect={(id) => {
                    const selected = referenceSpks.find(
                        (row) => String(row.rowId) === id,
                    );

                    if (selected) {
                        setData('ref_spk_id', String(selected.rowId));
                        setReferenceLabel({
                            spkNo: selected.spkNo,
                            customer: selected.customer,
                            item: selected.item,
                        });
                    }

                    setReferenceOpen(false);
                }}
            />
        </>
    );
}

SpkFormPage.layout = {
    activeMenu: 'SPK',
    pageTitle: 'Form SPK',
};
