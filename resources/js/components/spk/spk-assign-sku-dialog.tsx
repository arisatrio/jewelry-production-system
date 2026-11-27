import { router } from '@inertiajs/react';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { Form } from '@ui5/webcomponents-react/Form';
import { FormItem } from '@ui5/webcomponents-react/FormItem';
import { Label } from '@ui5/webcomponents-react/Label';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { Text } from '@ui5/webcomponents-react/Text';
import { useEffect, useMemo, useState } from 'react';
export type SpkAssignSkuDialogRow = {
    rowId: number;
    spkNo: string;
    itemDescription?: string | null;
};
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type CategoryOption = {
    value: string;
    label: string;
};

type SkuOption = {
    value: string;
    label: string;
    skuCode: string;
    categoryPrefixId: string;
};

type AssignSkuOptionsResponse = {
    categories: CategoryOption[];
    skus: SkuOption[];
};

type SpkAssignSkuDialogProps = {
    row: SpkAssignSkuDialogRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onAssigned?: (message: string) => void;
    /** Inertia partial reload prop names after successful assign. */
    reloadOnly?: string[];
};

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

    return fieldError ?? payload.message ?? 'Gagal meng-assign SKU.';
}

export function SpkAssignSkuDialog({
    row,
    open,
    onOpenChange,
    onAssigned,
    reloadOnly = ['productions'],
}: SpkAssignSkuDialogProps) {
    const [loadingOptions, setLoadingOptions] = useState(false);
    const [optionsFailed, setOptionsFailed] = useState(false);
    const [categories, setCategories] = useState<CategoryOption[]>([]);
    const [skus, setSkus] = useState<SkuOption[]>([]);
    const [categoryPrefixId, setCategoryPrefixId] = useState('');
    const [categoryLabel, setCategoryLabel] = useState('');
    const [skuId, setSkuId] = useState('');
    const [skuLabel, setSkuLabel] = useState('');
    const [saving, setSaving] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        setCategoryPrefixId('');
        setCategoryLabel('');
        setSkuId('');
        setSkuLabel('');
        setErrorMessage(null);
        setOptionsFailed(false);
        setLoadingOptions(true);

        void fetch('/spk/assign-sku/options', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('options');
                }

                const payload =
                    (await response.json()) as AssignSkuOptionsResponse;

                setCategories(payload.categories ?? []);
                setSkus(payload.skus ?? []);
            })
            .catch(() => {
                setOptionsFailed(true);
                setCategories([]);
                setSkus([]);
            })
            .finally(() => {
                setLoadingOptions(false);
            });
    }, [open]);

    const skuOptionsForCategory = useMemo(
        () =>
            skus.filter(
                (option) => option.categoryPrefixId === categoryPrefixId,
            ),
        [skus, categoryPrefixId],
    );

    const canSubmit =
        categoryPrefixId !== '' && skuId !== '' && !saving && !loadingOptions;

    const handleSave = async () => {
        if (row === null || !canSubmit) {
            return;
        }

        setSaving(true);
        setErrorMessage(null);

        try {
            const response = await fetch(`/spk/${row.rowId}/assign-sku`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    category_prefix_id: categoryPrefixId,
                    sku_id: skuId,
                }),
            });

            const payload = (await response.json().catch(() => ({}))) as {
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                setErrorMessage(firstErrorMessage(payload));

                return;
            }

            onOpenChange(false);
            onAssigned?.(
                payload.message ??
                    `SKU berhasil di-assign ke SPK ${row.spkNo}.`,
            );
            router.reload({ only: reloadOnly });
        } catch {
            setErrorMessage('Gagal meng-assign SKU. Silakan coba lagi.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Assign SKU</DialogTitle>
                    <DialogDescription>
                        {row
                            ? `Pilih SKU untuk SPK ${row.spkNo}.`
                            : 'Pilih SKU untuk SPK.'}
                    </DialogDescription>
                </DialogHeader>

                {errorMessage !== null ? (
                    <MessageStrip design="Negative" hideCloseButton>
                        {errorMessage}
                    </MessageStrip>
                ) : null}

                {optionsFailed ? (
                    <MessageStrip design="Negative" hideCloseButton>
                        Gagal memuat data SKU. Silakan tutup dan coba lagi.
                    </MessageStrip>
                ) : null}

                <Form
                    headerText="Item & SKU"
                    className="spkFioriForm spkAssignSkuForm"
                >
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
                            disabled={loadingOptions || optionsFailed}
                            placeholder={
                                loadingOptions
                                    ? 'Memuat...'
                                    : 'Pilih tipe item'
                            }
                            filter="Contains"
                            showClearIcon
                            value={categoryLabel}
                            onSelectionChange={(event) => {
                                const item = event.detail.item;

                                if (!item?.value) {
                                    return;
                                }

                                setCategoryPrefixId(String(item.value));
                                setCategoryLabel(item.text ?? '');
                                setSkuId('');
                                setSkuLabel('');
                            }}
                        >
                            {categories.map((option) => (
                                <ComboBoxItem
                                    key={option.value}
                                    text={option.label}
                                    value={option.value}
                                />
                            ))}
                        </ComboBox>
                    </FormItem>

                    <FormItem
                        labelContent={
                            <Label showColon required>
                                SKU
                            </Label>
                        }
                    >
                        <ComboBox
                            accessibleName="SKU"
                            className="spkFioriComboBox"
                            disabled={
                                loadingOptions ||
                                optionsFailed ||
                                categoryPrefixId === ''
                            }
                            placeholder={
                                categoryPrefixId === ''
                                    ? 'Pilih tipe item dahulu'
                                    : 'Cari SKU'
                            }
                            filter="Contains"
                            showClearIcon
                            value={skuLabel}
                            onSelectionChange={(event) => {
                                const item = event.detail.item;

                                if (!item?.value) {
                                    return;
                                }

                                setSkuId(String(item.value));
                                setSkuLabel(item.text ?? '');
                            }}
                        >
                            {skuOptionsForCategory.map((option) => (
                                <ComboBoxItem
                                    key={option.value}
                                    text={option.label}
                                    value={option.value}
                                />
                            ))}
                        </ComboBox>
                    </FormItem>
                </Form>

                {row?.itemDescription?.trim() ? (
                    <Text className="spkAssignSkuDescriptionHint">
                        Deskripsi SPK: {row.itemDescription.trim()}
                    </Text>
                ) : null}

                <DialogFooter>
                    <Button
                        design="Transparent"
                        onClick={() => onOpenChange(false)}
                        disabled={saving}
                    >
                        Batal
                    </Button>
                    <Button
                        design="Emphasized"
                        onClick={() => void handleSave()}
                        disabled={!canSubmit}
                    >
                        {saving ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}