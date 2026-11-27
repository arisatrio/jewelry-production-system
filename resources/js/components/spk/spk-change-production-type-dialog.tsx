import { router } from '@inertiajs/react';
import { Button } from '@ui5/webcomponents-react/Button';
import { Form } from '@ui5/webcomponents-react/Form';
import { FormItem } from '@ui5/webcomponents-react/FormItem';
import { Input } from '@ui5/webcomponents-react/Input';
import { Label } from '@ui5/webcomponents-react/Label';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { Text } from '@ui5/webcomponents-react/Text';
import { useEffect, useState } from 'react';
import { SpkCreateSelectorDialog } from '@/components/spk/spk-create-selector-dialog';
import {
    spkRequestOrderSelectorItemCell,
    type SpkRequestOrderSelectorItemFields,
} from '@/lib/spk-request-order-selector-item';
import type { SpkDetail, SpkItemDetail } from '@/components/spk/types';
import {
    referenceSpks as searchReferenceSpks,
    requestOrders as searchRequestOrders,
} from '@/routes/spk/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const SPK_TYPES = [
    'Pesanan',
    'Exchange',
    'Refund',
    'Reparasi',
    'Stock',
] as const;

type SpkTypeOption = (typeof SPK_TYPES)[number];

type RequestOrderOption = SpkRequestOrderSelectorItemFields & {
    rowId: number;
    docNo: string;
    customer: string;
    item: string;
    sales: string;
    displayLabel?: string;
    skuId?: number | null;
    skuCode?: string | null;
};

type ReferenceSpkOption = {
    rowId: number;
    spkNo: string;
    customer: string;
    item: string;
};

function spkTypeDisplayLabel(type: string): string {
    if (type === 'Stock') {
        return 'Stock (Permintaan Direksi)';
    }

    return type;
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

function firstErrorMessage(payload: {
    message?: string;
    errors?: Record<string, string[]>;
}): string {
    const fieldError = Object.values(payload.errors ?? {})
        .flat()
        .find((message) => message.trim() !== '');

    return fieldError ?? payload.message ?? 'Gagal memperbarui tipe produksi.';
}

function normalizeDocNo(value: string | null | undefined): string {
    const trimmed = value?.trim() ?? '';

    return trimmed === '-' ? '' : trimmed;
}

function positiveSkuId(value: number | null | undefined): number | null {
    if (value === null || value === undefined || value <= 0) {
        return null;
    }

    return value;
}

function spkSkuMismatch(
    production: SpkDetail | null,
    orderSkuId: number | null,
): boolean {
    const spkSkuId = positiveSkuId(production?.skuId);

    if (spkSkuId === null || orderSkuId === null) {
        return false;
    }

    return spkSkuId !== orderSkuId;
}

function resolveProductionRowId(production: SpkDetail | null): number | undefined {
    if (production === null) {
        return undefined;
    }

    if (
        production.rowId !== undefined &&
        production.rowId !== null &&
        production.rowId > 0
    ) {
        return production.rowId;
    }

    const parsed = Number(production.id);

    if (Number.isFinite(parsed) && parsed > 0) {
        return parsed;
    }

    return undefined;
}

type SpkChangeProductionTypeDialogProps = {
    production: SpkDetail | null;
    item?: SpkItemDetail | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onUpdated?: (message: string) => void;
    /** Kunci tipe tujuan (mis. dari tombol "Ubah ke Pesanan"). */
    targetSpkType?: SpkTypeOption;
};

export function SpkChangeProductionTypeDialog({
    production,
    item = null,
    open,
    onOpenChange,
    onUpdated,
    targetSpkType,
}: SpkChangeProductionTypeDialogProps) {
    const [spkType, setSpkType] = useState<SpkTypeOption>('Stock');
    const [requestOrderNo, setRequestOrderNo] = useState('');
    const [requestOrderLabel, setRequestOrderLabel] = useState<{
        displayLabel: string;
        item: string;
    } | null>(null);
    const [requestStockNo, setRequestStockNo] = useState('');
    const [refSpkId, setRefSpkId] = useState('');
    const [referenceLabel, setReferenceLabel] = useState<{
        spkNo: string;
        customer: string;
        item: string;
    } | null>(null);
    const [requestOrderOpen, setRequestOrderOpen] = useState(false);
    const [referenceOpen, setReferenceOpen] = useState(false);
    const [requestOrderSearch, setRequestOrderSearch] = useState('');
    const [referenceSearch, setReferenceSearch] = useState('');
    const [requestOrders, setRequestOrders] = useState<RequestOrderOption[]>(
        [],
    );
    const [referenceSpks, setReferenceSpks] = useState<ReferenceSpkOption[]>(
        [],
    );
    const [loadingRequestOrders, setLoadingRequestOrders] = useState(false);
    const [loadingReferences, setLoadingReferences] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [skuMismatchConfirmOpen, setSkuMismatchConfirmOpen] = useState(false);
    const [selectedOrderSkuId, setSelectedOrderSkuId] = useState<number | null>(
        null,
    );
    const [selectedOrderSkuCode, setSelectedOrderSkuCode] = useState('');

    useEffect(() => {
        if (!open || production === null) {
            return;
        }

        const initialType = (
            targetSpkType ??
            production.tipeProduksi?.trim() ??
            'Stock'
        ) as SpkTypeOption;

        setSpkType(
            SPK_TYPES.includes(initialType) ? initialType : 'Stock',
        );
        setRequestOrderNo(normalizeDocNo(production.requestOrderNo));
        setRequestOrderLabel(
            normalizeDocNo(production.requestOrderNo) !== ''
                ? {
                      displayLabel: normalizeDocNo(production.requestOrderNo),
                      item: production.item?.trim() || production.customer,
                  }
                : null,
        );
        setRequestStockNo(normalizeDocNo(production.requestStockNo));
        setRefSpkId(
            production.refSpkId !== null && production.refSpkId !== undefined
                ? String(production.refSpkId)
                : '',
        );
        setReferenceLabel(
            normalizeDocNo(production.refSpkNo) !== ''
                ? {
                      spkNo: normalizeDocNo(production.refSpkNo),
                      customer: production.customer,
                      item: production.item,
                  }
                : null,
        );
        setErrorMessage(null);
        setSkuMismatchConfirmOpen(false);
        setSelectedOrderSkuId(null);
        setSelectedOrderSkuCode('');
    }, [open, production, targetSpkType]);

    useEffect(() => {
        if (!open || !requestOrderOpen) {
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
    }, [open, requestOrderOpen, requestOrderSearch]);

    useEffect(() => {
        if (!open || !referenceOpen) {
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
    }, [open, referenceOpen, referenceSearch]);

    const needsRequestOrder = spkType === 'Pesanan';
    const needsReference = ['Exchange', 'Refund', 'Reparasi'].includes(spkType);
    const needsRequestStock = spkType === 'Stock';

    const productionRowId = resolveProductionRowId(production);

    const canSubmit =
        !saving &&
        productionRowId !== undefined &&
        (!needsRequestOrder || requestOrderNo !== '') &&
        (!needsReference || refSpkId !== '');

    const handleTypeChange = (nextType: string) => {
        const typed = nextType as SpkTypeOption;
        setSpkType(typed);
        setErrorMessage(null);
    };

    const submitProductionType = async () => {
        if (productionRowId === undefined || !canSubmit) {
            return;
        }

        setSaving(true);
        setErrorMessage(null);
        setSkuMismatchConfirmOpen(false);

        try {
            const body: Record<string, string | number> = {
                spk_type: spkType,
            };

            if (needsRequestOrder) {
                body.request_order_no = requestOrderNo;
            }

            if (needsRequestStock && requestStockNo.trim() !== '') {
                body.request_stock_no = requestStockNo.trim().toUpperCase();
            }

            if (needsReference) {
                body.ref_spk_id = Number(refSpkId);
            }

            const response = await fetch(
                `/spk/${productionRowId}/production-type`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': readXsrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                },
            );

            const payload = (await response.json().catch(() => ({}))) as {
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                setErrorMessage(firstErrorMessage(payload));

                return;
            }

            onOpenChange(false);
            onUpdated?.(
                payload.message ??
                    `Tipe produksi SPK ${production.produksiNo} berhasil diperbarui.`,
            );
            router.reload({ only: ['production', 'item', 'approvalFooter'] });
        } catch {
            setErrorMessage(
                'Gagal memperbarui tipe produksi. Silakan coba lagi.',
            );
        } finally {
            setSaving(false);
        }
    };

    const handleSave = () => {
        if (productionRowId === undefined || !canSubmit) {
            return;
        }

        const orderSkuId =
            selectedOrderSkuId ??
            positiveSkuId(
                requestOrders.find((row) => row.docNo === requestOrderNo)
                    ?.skuId,
            );

        if (
            targetSpkType === 'Pesanan' &&
            spkSkuMismatch(production, orderSkuId)
        ) {
            setSkuMismatchConfirmOpen(true);

            return;
        }

        void submitProductionType();
    };

    const spkSkuDisplay = (() => {
        const skuCode = item?.skuCode?.trim() ?? '';
        const typeSku = production?.typeSkuLabel?.trim() ?? '';

        if (skuCode !== '' && skuCode !== '-') {
            return typeSku !== '' ? `${typeSku}` : skuCode;
        }

        return typeSku !== '' ? typeSku : '—';
    })();

    const orderSkuDisplay =
        selectedOrderSkuCode.trim() !== ''
            ? selectedOrderSkuCode
            : requestOrders.find((row) => row.docNo === requestOrderNo)
                  ?.typeSkuLabel?.trim() ?? '—';

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="spkAlertModal sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {targetSpkType === 'Pesanan'
                                ? 'Ubah ke Pesanan'
                                : 'Ganti tipe produksi'}
                        </DialogTitle>
                        <DialogDescription>
                            {production
                                ? targetSpkType === 'Pesanan'
                                    ? `Pilih nomor pesanan untuk mengubah SPK ${production.produksiNo} menjadi tipe Pesanan.`
                                    : `Perbarui tipe produksi dan nomor pesanan untuk SPK ${production.produksiNo}.`
                                : 'Perbarui tipe produksi SPK.'}
                        </DialogDescription>
                    </DialogHeader>

                    {errorMessage !== null ? (
                        <MessageStrip design="Negative" hideCloseButton>
                            {errorMessage}
                        </MessageStrip>
                    ) : null}

                    <Form
                        headerText={
                            targetSpkType === 'Pesanan'
                                ? 'Nomor pesanan'
                                : 'Tipe & pesanan'
                        }
                        className="spkFioriForm"
                    >
                        {targetSpkType === undefined ? (
                            <FormItem
                                labelContent={
                                    <Label showColon required>
                                        Tipe produksi
                                    </Label>
                                }
                            >
                                <Select
                                    onChange={(event) =>
                                        handleTypeChange(
                                            event.detail.selectedOption
                                                ?.value ?? 'Stock',
                                        )
                                    }
                                >
                                    {SPK_TYPES.map((type) => (
                                        <Option
                                            key={type}
                                            value={type}
                                            selected={spkType === type}
                                        >
                                            {spkTypeDisplayLabel(type)}
                                        </Option>
                                    ))}
                                </Select>
                            </FormItem>
                        ) : null}

                        {needsRequestOrder ? (
                            <FormItem
                                labelContent={
                                    <Label showColon required>
                                        Nomor pesanan
                                    </Label>
                                }
                            >
                                <div className="spkCreateSelectorRow">
                                    <div className="spkCreateSelectorValue">
                                        {requestOrderLabel ? (
                                            <>
                                                <strong>
                                                    {
                                                        requestOrderLabel.displayLabel
                                                    }
                                                </strong>
                                                <span>
                                                    {requestOrderLabel.item}
                                                </span>
                                            </>
                                        ) : (
                                            <span>Belum ada pesanan dipilih</span>
                                        )}
                                    </div>
                                    <Button
                                        design="Default"
                                        onClick={() =>
                                            setRequestOrderOpen(true)
                                        }
                                    >
                                        Pilih
                                    </Button>
                                </div>
                            </FormItem>
                        ) : null}

                        {needsRequestStock ? (
                            <FormItem
                                labelContent={
                                    <Label showColon>
                                        Permintaan stok (RS-)
                                    </Label>
                                }
                            >
                                <Input
                                    value={requestStockNo}
                                    onInput={(event) =>
                                        setRequestStockNo(
                                            event.target.value ?? '',
                                        )
                                    }
                                    placeholder="Opsional — kosongkan untuk permintaan direksi"
                                />
                                <Text className="spkFioriInlineHint">
                                    Isi nomor RS untuk permintaan toko. Kosongkan
                                    untuk permintaan direksi (DRK).
                                </Text>
                            </FormItem>
                        ) : null}

                        {needsReference ? (
                            <FormItem
                                labelContent={
                                    <Label showColon required>
                                        SPK referensi
                                    </Label>
                                }
                            >
                                <div className="spkCreateSelectorRow">
                                    <div className="spkCreateSelectorValue">
                                        {referenceLabel ? (
                                            <>
                                                <strong>
                                                    {referenceLabel.spkNo}
                                                </strong>
                                                <span>
                                                    {referenceLabel.customer} ·{' '}
                                                    {referenceLabel.item}
                                                </span>
                                            </>
                                        ) : (
                                            <span>
                                                Belum ada SPK referensi dipilih
                                            </span>
                                        )}
                                    </div>
                                    <Button
                                        design="Default"
                                        onClick={() => setReferenceOpen(true)}
                                    >
                                        Pilih
                                    </Button>
                                </div>
                            </FormItem>
                        ) : null}
                    </Form>

                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            design="Transparent"
                            disabled={saving}
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            design="Emphasized"
                            disabled={!canSubmit || saving}
                            onClick={handleSave}
                        >
                            {saving ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={skuMismatchConfirmOpen}
                onOpenChange={setSkuMismatchConfirmOpen}
            >
                <DialogContent className="spkAlertModal sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>SKU tidak sama</DialogTitle>
                        <DialogDescription>
                            SKU pada SPK berbeda dengan SKU pesanan yang
                            dipilih. Lanjutkan akan mengganti SKU SPK mengikuti
                            pesanan.
                        </DialogDescription>
                    </DialogHeader>

                    <MessageStrip design="Critical" hideCloseButton>
                        <div className="flex flex-col gap-1">
                            <span>
                                <strong>SKU SPK:</strong> {spkSkuDisplay}
                            </span>
                            <span>
                                <strong>SKU pesanan:</strong> {orderSkuDisplay}
                            </span>
                        </div>
                    </MessageStrip>

                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            design="Transparent"
                            disabled={saving}
                            onClick={() => setSkuMismatchConfirmOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            design="Emphasized"
                            disabled={saving}
                            onClick={() => void submitProductionType()}
                        >
                            {saving ? 'Menyimpan...' : 'Lanjutkan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <SpkCreateSelectorDialog
                open={requestOrderOpen}
                onOpenChange={setRequestOrderOpen}
                title="Pilih nomor pesanan"
                description="Cari dan pilih request order untuk SPK tipe Pesanan."
                search={requestOrderSearch}
                onSearchChange={setRequestOrderSearch}
                loading={loadingRequestOrders}
                emptyText="Tidak ada pesanan ditemukan."
                columns={[
                    { key: 'docNo', label: 'No. pesanan' },
                    { key: 'customer', label: 'Customer' },
                    {
                        key: 'item',
                        label: 'Item',
                        cellClassName: 'spkCreateDialogTableItemCell',
                    },
                ]}
                rows={requestOrders.map((row) => ({
                    id: row.docNo,
                    docNo: row.displayLabel ?? row.docNo,
                    customer: row.customer,
                    item: spkRequestOrderSelectorItemCell(row),
                }))}
                onSelect={(docNo) => {
                    const selected = requestOrders.find(
                        (row) => row.docNo === docNo,
                    );

                    setRequestOrderNo(docNo);
                    setRequestOrderLabel(
                        selected
                            ? {
                                  displayLabel:
                                      selected.displayLabel ?? selected.docNo,
                                  item: selected.item,
                              }
                            : { displayLabel: docNo, item: '' },
                    );
                    setSelectedOrderSkuId(
                        positiveSkuId(selected?.skuId ?? null),
                    );
                    setSelectedOrderSkuCode(
                        selected?.skuCode?.trim() ??
                            selected?.typeSkuLabel?.trim() ??
                            '',
                    );
                    setRequestOrderOpen(false);
                }}
            />

            <SpkCreateSelectorDialog
                open={referenceOpen}
                onOpenChange={setReferenceOpen}
                title="Pilih SPK referensi"
                description="SPK referensi harus berstatus selesai (SPKDONE)."
                search={referenceSearch}
                onSearchChange={setReferenceSearch}
                loading={loadingReferences}
                emptyText="Tidak ada SPK referensi ditemukan."
                columns={[
                    { key: 'spkNo', label: 'No. SPK' },
                    { key: 'customer', label: 'Customer' },
                    { key: 'item', label: 'Item' },
                ]}
                rows={referenceSpks.map((row) => ({
                    id: String(row.rowId),
                    spkNo: row.spkNo,
                    customer: row.customer,
                    item: row.item,
                }))}
                onSelect={(id) => {
                    const selected = referenceSpks.find(
                        (row) => String(row.rowId) === id,
                    );

                    if (selected) {
                        setRefSpkId(String(selected.rowId));
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