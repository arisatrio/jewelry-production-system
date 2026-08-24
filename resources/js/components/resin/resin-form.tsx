import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import saveIcon from '@ui5/webcomponents-icons/dist/save.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { ComboBoxItem } from '@ui5/webcomponents-react/ComboBoxItem';
import { DatePicker } from '@ui5/webcomponents-react/DatePicker';
import { Form } from '@ui5/webcomponents-react/Form';
import { FormGroup } from '@ui5/webcomponents-react/FormGroup';
import { FormItem } from '@ui5/webcomponents-react/FormItem';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Label } from '@ui5/webcomponents-react/Label';
import { Text } from '@ui5/webcomponents-react/Text';
import { spks as searchSpks } from '@/routes/resin/select';

type ShapeOption = {
    id: number;
    name: string;
};

type SpkOption = {
    rowId: number;
    spkNo: string;
    customer: string;
    item: string;
};

type StoneFormRow = {
    shape_id: string;
    pcs: string;
    carat: string;
    size: string;
};

type ResinFormValues = {
    doc_no: string;
    trans_date: string;
    spk_id: string;
    spk_no: string;
    item_name: string;
    customer_name: string;
    file: File | null;
    file_upload: string | null;
    file_url: string | null;
    stones: StoneFormRow[];
};

type ResinFormProps = {
    title: string;
    submitLabel: string;
    cancelHref: string;
    submitUrl: string;
    method: 'post' | 'put';
    isNew?: boolean;
    shapeOptions: ShapeOption[];
    initialValues: ResinFormValues;
};

function fieldState(error?: string): 'None' | 'Negative' {
    return error ? 'Negative' : 'None';
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <Text className="text-sm text-red-600" wrapping-type="Normal">
            {message}
        </Text>
    );
}

function emptyStone(): StoneFormRow {
    return {
        shape_id: '',
        pcs: '',
        carat: '',
        size: '',
    };
}

export function ResinForm({
    title,
    submitLabel,
    cancelHref,
    submitUrl,
    method,
    isNew = false,
    shapeOptions,
    initialValues,
}: ResinFormProps) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<ResinFormValues>(initialValues);

    const [spkSearch, setSpkSearch] = useState('');
    const [spkOptions, setSpkOptions] = useState<SpkOption[]>([]);
    const [spkLoading, setSpkLoading] = useState(false);

    const displayDocNo = isNew ? 'auto-generated' : data.doc_no;

    useEffect(() => {
        const timeout = window.setTimeout(async () => {
            const query = spkSearch.trim();

            if (query.length < 2) {
                setSpkOptions([]);

                return;
            }

            setSpkLoading(true);

            try {
                const response = await fetch(
                    searchSpks.url({
                        query: {
                            search: query,
                            limit: 25,
                        },
                    }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    setSpkOptions([]);

                    return;
                }

                const payload = (await response.json()) as {
                    status: boolean;
                    data: SpkOption[];
                };

                setSpkOptions(payload.data ?? []);
            } catch {
                setSpkOptions([]);
            } finally {
                setSpkLoading(false);
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [spkSearch]);

    const selectedSpkLabel = useMemo(() => {
        if (!data.spk_id) {
            return '';
        }

        const parts = [data.spk_no, data.item_name, data.customer_name].filter(
            (part) => part && part !== '—',
        );

        return parts.join(' · ');
    }, [data.spk_id, data.spk_no, data.item_name, data.customer_name]);

    const selectSpk = (option: SpkOption) => {
        setData((current) => ({
            ...current,
            spk_id: String(option.rowId),
            spk_no: option.spkNo,
            item_name: option.item,
            customer_name: option.customer,
        }));
        setSpkSearch('');
        setSpkOptions([]);
    };

    const clearSpk = () => {
        setData((current) => ({
            ...current,
            spk_id: '',
            spk_no: '',
            item_name: '',
            customer_name: '',
        }));
    };

    const updateStone = (
        index: number,
        key: keyof StoneFormRow,
        value: string,
    ) => {
        setData(
            'stones',
            data.stones.map((stone, stoneIndex) =>
                stoneIndex === index ? { ...stone, [key]: value } : stone,
            ),
        );
    };

    const addStone = () => {
        setData('stones', [...data.stones, emptyStone()]);
    };

    const removeStone = (index: number) => {
        setData(
            'stones',
            data.stones.filter((_, stoneIndex) => stoneIndex !== index),
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        transform((formData) => ({
            doc_no: formData.doc_no,
            trans_date: formData.trans_date,
            spk_id: formData.spk_id,
            file: formData.file,
            stones: formData.stones,
        }));

        const options = {
            forceFormData: true,
            preserveScroll: true,
        };

        if (method === 'put') {
            put(submitUrl, options);
        } else {
            post(submitUrl, options);
        }
    };

    return (
        <div className="spkTableShell">
            <div className="spkTableCard">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">
                            {title}
                        </h1>
                        <Text wrapping-type="Normal">
                            Dokumen: {displayDocNo}
                        </Text>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            design="Transparent"
                            icon={declineIcon}
                            onClick={() => router.visit(cancelHref)}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button
                            design="Emphasized"
                            icon={saveIcon}
                            onClick={submit}
                            disabled={processing}
                        >
                            {processing ? 'Menyimpan...' : submitLabel}
                        </Button>
                    </div>
                </div>

                <form onSubmit={submit}>
                    <Form
                        layout="S1 M2 L2 XL2"
                        labelSpan="S12 M12 L12 XL12"
                        className="gap-4"
                    >
                        <FormGroup headerText="Informasi Dokumen">
                            <FormItem labelContent={<Label required>Tanggal</Label>}>
                                <div className="flex w-full flex-col gap-1">
                                    <DatePicker
                                        accessibleName="Tanggal resin"
                                        formatPattern="yyyy-MM-dd"
                                        value={data.trans_date}
                                        valueState={fieldState(errors.trans_date)}
                                        onChange={(event) =>
                                            setData(
                                                'trans_date',
                                                event.target.value ?? '',
                                            )
                                        }
                                    />
                                    <FieldError message={errors.trans_date} />
                                </div>
                            </FormItem>

                            <FormItem labelContent={<Label required>SPK</Label>}>
                                <div className="flex w-full flex-col gap-2">
                                    {data.spk_id ? (
                                        <div className="flex flex-wrap items-center gap-2 rounded-md border border-border px-3 py-2">
                                            <div className="min-w-0 flex-1">
                                                <div className="font-medium">
                                                    {data.spk_no || '—'}
                                                </div>
                                                <Text wrapping-type="Normal">
                                                    {selectedSpkLabel}
                                                </Text>
                                            </div>
                                            <Button
                                                design="Transparent"
                                                onClick={clearSpk}
                                                disabled={processing}
                                            >
                                                Ganti
                                            </Button>
                                        </div>
                                    ) : (
                                        <>
                                            <Input
                                                accessibleName="Cari SPK"
                                                placeholder="Ketik minimal 2 karakter nomor SPK / item..."
                                                value={spkSearch}
                                                icon={
                                                    <Icon name={searchIcon} />
                                                }
                                                valueState={fieldState(
                                                    errors.spk_id,
                                                )}
                                                onInput={(event) =>
                                                    setSpkSearch(
                                                        event.target.value ??
                                                            '',
                                                    )
                                                }
                                            />
                                            {spkLoading ? (
                                                <Text>Mencari SPK...</Text>
                                            ) : null}
                                            {spkOptions.length > 0 ? (
                                                <div className="max-h-56 overflow-auto rounded-md border border-border">
                                                    {spkOptions.map(
                                                        (option) => (
                                                            <button
                                                                key={
                                                                    option.rowId
                                                                }
                                                                type="button"
                                                                className="flex w-full flex-col items-start gap-0.5 border-b border-border px-3 py-2 text-left last:border-b-0 hover:bg-muted"
                                                                onClick={() =>
                                                                    selectSpk(
                                                                        option,
                                                                    )
                                                                }
                                                            >
                                                                <span className="font-medium">
                                                                    {
                                                                        option.spkNo
                                                                    }
                                                                </span>
                                                                <span className="text-sm text-muted-foreground">
                                                                    {
                                                                        option.item
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {
                                                                        option.customer
                                                                    }
                                                                </span>
                                                            </button>
                                                        ),
                                                    )}
                                                </div>
                                            ) : null}
                                        </>
                                    )}
                                    <FieldError message={errors.spk_id} />
                                </div>
                            </FormItem>

                            <FormItem labelContent={<Label>File Upload</Label>}>
                                <div className="flex w-full flex-col gap-2">
                                    <input
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf,.webp,image/*,application/pdf"
                                        onChange={(event) =>
                                            setData(
                                                'file',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    {data.file ? (
                                        <Text wrapping-type="Normal">
                                            File baru: {data.file.name}
                                        </Text>
                                    ) : data.file_upload ? (
                                        <Text wrapping-type="Normal">
                                            File saat ini:{' '}
                                            {data.file_url ? (
                                                <a
                                                    href={data.file_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-primary underline"
                                                >
                                                    {data.file_upload}
                                                </a>
                                            ) : (
                                                data.file_upload
                                            )}
                                        </Text>
                                    ) : null}
                                    <FieldError message={errors.file} />
                                </div>
                            </FormItem>
                        </FormGroup>
                    </Form>

                    <div className="mt-6">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">
                                Detail Batu (Resin Stone)
                            </h2>
                            <Button
                                design="Default"
                                icon={addIcon}
                                onClick={addStone}
                                disabled={processing}
                            >
                                Tambah Batu
                            </Button>
                        </div>

                        {data.stones.length === 0 ? (
                            <Text wrapping-type="Normal">
                                Belum ada baris batu. Klik &quot;Tambah
                                Batu&quot; jika diperlukan.
                            </Text>
                        ) : (
                            <div className="spkTableScroll">
                                <table className="spkTable">
                                    <thead>
                                        <tr>
                                            <th>Shape</th>
                                            <th>Pcs</th>
                                            <th>Carat</th>
                                            <th>Size</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.stones.map((stone, index) => (
                                            <tr key={`stone-${index}`}>
                                                <td>
                                                    <ComboBox
                                                        accessibleName={`Shape batu ${index + 1}`}
                                                        placeholder="Pilih shape"
                                                        value={
                                                            shapeOptions.find(
                                                                (option) =>
                                                                    String(
                                                                        option.id,
                                                                    ) ===
                                                                    stone.shape_id,
                                                            )?.name ?? ''
                                                        }
                                                        valueState={fieldState(
                                                            errors[
                                                                `stones.${index}.shape_id`
                                                            ],
                                                        )}
                                                        filter="Contains"
                                                        onSelectionChange={(
                                                            event,
                                                        ) => {
                                                            const item =
                                                                event.detail
                                                                    .item;

                                                            if (!item?.value) {
                                                                return;
                                                            }

                                                            updateStone(
                                                                index,
                                                                'shape_id',
                                                                String(
                                                                    item.value,
                                                                ),
                                                            );
                                                        }}
                                                    >
                                                        {shapeOptions.map(
                                                            (option) => (
                                                                <ComboBoxItem
                                                                    key={
                                                                        option.id
                                                                    }
                                                                    text={
                                                                        option.name
                                                                    }
                                                                    value={String(
                                                                        option.id,
                                                                    )}
                                                                />
                                                            ),
                                                        )}
                                                    </ComboBox>
                                                    <FieldError
                                                        message={
                                                            errors[
                                                                `stones.${index}.shape_id`
                                                            ]
                                                        }
                                                    />
                                                </td>
                                                <td>
                                                    <Input
                                                        accessibleName={`Pcs batu ${index + 1}`}
                                                        type="Number"
                                                        value={stone.pcs}
                                                        valueState={fieldState(
                                                            errors[
                                                                `stones.${index}.pcs`
                                                            ],
                                                        )}
                                                        onInput={(event) =>
                                                            updateStone(
                                                                index,
                                                                'pcs',
                                                                event.target
                                                                    .value ??
                                                                    '',
                                                            )
                                                        }
                                                    />
                                                    <FieldError
                                                        message={
                                                            errors[
                                                                `stones.${index}.pcs`
                                                            ]
                                                        }
                                                    />
                                                </td>
                                                <td>
                                                    <Input
                                                        accessibleName={`Carat batu ${index + 1}`}
                                                        type="Number"
                                                        value={stone.carat}
                                                        valueState={fieldState(
                                                            errors[
                                                                `stones.${index}.carat`
                                                            ],
                                                        )}
                                                        onInput={(event) =>
                                                            updateStone(
                                                                index,
                                                                'carat',
                                                                event.target
                                                                    .value ??
                                                                    '',
                                                            )
                                                        }
                                                    />
                                                    <FieldError
                                                        message={
                                                            errors[
                                                                `stones.${index}.carat`
                                                            ]
                                                        }
                                                    />
                                                </td>
                                                <td>
                                                    <Input
                                                        accessibleName={`Size batu ${index + 1}`}
                                                        value={stone.size}
                                                        valueState={fieldState(
                                                            errors[
                                                                `stones.${index}.size`
                                                            ],
                                                        )}
                                                        onInput={(event) =>
                                                            updateStone(
                                                                index,
                                                                'size',
                                                                event.target
                                                                    .value ??
                                                                    '',
                                                            )
                                                        }
                                                    />
                                                    <FieldError
                                                        message={
                                                            errors[
                                                                `stones.${index}.size`
                                                            ]
                                                        }
                                                    />
                                                </td>
                                                <td>
                                                    <Button
                                                        design="Transparent"
                                                        icon={declineIcon}
                                                        onClick={() =>
                                                            removeStone(index)
                                                        }
                                                        disabled={processing}
                                                    >
                                                        Hapus
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}
