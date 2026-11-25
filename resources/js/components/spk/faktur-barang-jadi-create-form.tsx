import { router, useHttp } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import saveIcon from '@ui5/webcomponents-icons/dist/save.js';
import { Button } from '@ui5/webcomponents-react/Button';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    emptyFakturReceiptForm,
    FakturBarangJadiReceiptFormFields,
    isKnownFakturEmployee,
    type FakturBarangJadiReceiptFormValues,
} from '@/components/spk/faktur-barang-jadi-receipt-form-fields';
import {
    FakturBarangJadiSelectSpkDialog,
    type FakturBarangJadiSelectedSpk,
} from '@/components/spk/faktur-barang-jadi-select-spk-dialog';
import { SpkFotoBrjCell } from '@/components/spk/spk-foto-brj-cell';
import { SpkFotoBrjUploadDialog } from '@/components/spk/spk-foto-brj-upload-dialog';
import { SpkTableItemCell } from '@/components/spk/spk-table-item-cell';
import {
    firstFormErrorMessage,
    formErrorMessages,
    httpExceptionPayloadMessage,
} from '@/lib/http-form-errors';
import { index as fakturBarangJadiIndex } from '@/routes/faktur-barang-jadi';
import { store as storeFakturBarangJadiReceipt } from '@/routes/faktur-barang-jadi/print/receipt';

const SAVE_FAKTUR_FALLBACK_ERROR =
    'Gagal menyimpan faktur barang jadi. Silakan coba lagi.';

export type FakturBarangJadiCreateFormProps = {
    receiptEmployeeOptions: string[];
    fakturBarangJadiTableAvailable?: boolean | null;
    fakturBarangJadiFotoBrjAvailable?: boolean | null;
};

type ReceiptStoreResponse = {
    id: number;
    docNo: string;
    printUrl: string;
    workflowStatus?: 'created' | null;
};

export function FakturBarangJadiCreateForm({
    receiptEmployeeOptions,
    fakturBarangJadiTableAvailable,
    fakturBarangJadiFotoBrjAvailable,
}: FakturBarangJadiCreateFormProps) {
    const [selectedSpks, setSelectedSpks] = useState<
        FakturBarangJadiSelectedSpk[]
    >([]);
    const [spkDialogOpen, setSpkDialogOpen] = useState(false);
    const [fotoStepSpk, setFotoStepSpk] =
        useState<FakturBarangJadiSelectedSpk | null>(null);
    const receiptForm = useHttp<
        FakturBarangJadiReceiptFormValues,
        ReceiptStoreResponse
    >(emptyFakturReceiptForm());
    const values = receiptForm.data;

    const selectedIds = useMemo(
        () => selectedSpks.map((row) => row.rowId),
        [selectedSpks],
    );

    const allHaveFotoBrj =
        fakturBarangJadiFotoBrjAvailable === false ||
        selectedSpks.every((row) => row.hasFotoBrj);

    const allHaveLastWeight = selectedSpks.every(
        (row) =>
            row.lastWeight !== null &&
            row.lastWeight.trim() !== '' &&
            Number.isFinite(Number(row.lastWeight.replace(',', '.'))),
    );

    const errorMessages = formErrorMessages(receiptForm.errors);

    const setField = (
        field: keyof FakturBarangJadiReceiptFormValues,
        value: string,
    ): void => {
        receiptForm.setData((current) => ({ ...current, [field]: value }));
    };

    const canSubmit =
        fakturBarangJadiTableAvailable !== false &&
        !receiptForm.processing &&
        selectedIds.length > 0 &&
        allHaveFotoBrj &&
        allHaveLastWeight &&
        values.tanggal !== '' &&
        values.dari !== '' &&
        values.untuk !== '' &&
        isKnownFakturEmployee(
            values.diserahkan_oleh,
            receiptEmployeeOptions,
        ) &&
        isKnownFakturEmployee(values.diketahui_oleh, receiptEmployeeOptions);

    const addSpkToDraft = (
        row: FakturBarangJadiSelectedSpk,
        imageUrl: string | null,
        lastWeight: string | null,
    ): void => {
        const entry: FakturBarangJadiSelectedSpk = {
            ...row,
            hasFotoBrj: true,
            fotoBrjImageUrl: imageUrl ?? row.fotoBrjImageUrl,
            lastWeight: lastWeight ?? row.lastWeight,
        };

        setSelectedSpks((current) => {
            if (current.some((item) => item.rowId === entry.rowId)) {
                return current;
            }

            return [...current, entry];
        });

        toast.success(`${entry.spkNo} ditambahkan ke draft faktur.`);
    };

    const handleSpkPicked = (row: FakturBarangJadiSelectedSpk): void => {
        setFotoStepSpk(row);
    };

    const removeSpk = (rowId: number): void => {
        setSelectedSpks((current) =>
            current.filter((row) => row.rowId !== rowId),
        );
    };

    const refreshSpkFoto = (rowId: number, imageUrl: string | null): void => {
        setSelectedSpks((current) =>
            current.map((row) =>
                row.rowId === rowId
                    ? {
                          ...row,
                          hasFotoBrj: true,
                          fotoBrjImageUrl: imageUrl ?? row.fotoBrjImageUrl,
                      }
                    : row,
            ),
        );
    };

    const submitReceipt = (): void => {
        if (!canSubmit) {
            return;
        }

        receiptForm.clearErrors();
        receiptForm.transform((data) => ({
            ids: selectedIds,
            ...Object.fromEntries(
                Object.entries(data)
                    .map(([key, value]) => [key, String(value).trim()])
                    .filter(([, value]) => value !== ''),
            ),
        }));

        void receiptForm
            .post(storeFakturBarangJadiReceipt.url(), {
                onSuccess: (response) => {
                    toast.success(
                        `Faktur ${response.docNo} disimpan. Untuk mengirim, gunakan daftar Faktur Barang Jadi.`,
                    );
                    setSelectedSpks([]);
                    router.visit(fakturBarangJadiIndex.url());
                },
                onError: (errors) => {
                    toast.error(
                        firstFormErrorMessage(
                            errors,
                            SAVE_FAKTUR_FALLBACK_ERROR,
                        ),
                    );
                },
                onHttpException: (response) => {
                    toast.error(
                        httpExceptionPayloadMessage(
                            response,
                            SAVE_FAKTUR_FALLBACK_ERROR,
                        ),
                    );
                },
                onNetworkError: () => {
                    toast.error(
                        'Koneksi terputus saat menyimpan faktur. Silakan coba lagi.',
                    );
                },
            })
            .catch(() => undefined);
    };

    const fotoUploadEnabled = fakturBarangJadiFotoBrjAvailable !== false;

    return (
        <>
            <div className="spkDetailShell">
                <div className="spkDetailStack">
                    <form
                        id="faktur-barang-jadi-create-form"
                        className="spkFioriForm"
                        onSubmit={(event: FormEvent<HTMLFormElement>) => {
                            event.preventDefault();
                            submitReceipt();
                        }}
                    >
                        <section className="spkFioriFormCard">
                        <div className="spkFioriFormCardHeader">
                            <div className="spkFioriFormCardHeaderMain">
                                <h2 className="spkFioriFormCardTitle">
                                    Buat Faktur Barang Jadi
                                </h2>
                                <p className="spkFioriFormCardSubtitle">
                                    Nomor faktur dibuat otomatis setelah
                                    disimpan. Tambahkan SPK satu per satu dengan
                                    berat akhir serta foto barang jadi.
                                </p>
                            </div>
                            <div className="spkFioriFormCardActions">
                                <Button
                                    design="Default"
                                    type="Button"
                                    className="spkFioriFormCardBtnGrey"
                                    icon={declineIcon}
                                    disabled={receiptForm.processing}
                                    onClick={() =>
                                        router.visit(
                                            fakturBarangJadiIndex.url(),
                                        )
                                    }
                                >
                                    Batal
                                </Button>
                                <Button
                                    design="Attention"
                                    className="spkFioriFormCardBtnYellow"
                                    icon={saveIcon}
                                    type="Submit"
                                    disabled={!canSubmit}
                                >
                                    {receiptForm.processing
                                        ? 'Menyimpan...'
                                        : `Simpan (${selectedIds.length} SPK)`}
                                </Button>
                            </div>
                        </div>

                        {errorMessages.length > 0 ? (
                            <ul className="mx-4 mb-4 list-disc rounded-md border border-red-200 bg-red-50 py-2 pr-3 pl-7 text-xs text-red-700">
                                {errorMessages.map((message) => (
                                    <li key={message}>{message}</li>
                                ))}
                            </ul>
                        ) : null}

                        <div className="spkFioriFormCardBody px-4 pb-4">
                            <FakturBarangJadiReceiptFormFields
                                idPrefix="faktur-bj-create"
                                values={values}
                                employeeOptions={receiptEmployeeOptions}
                                onFieldChange={setField}
                            />
                        </div>
                    </section>

                    <section className="spkFioriFormCard">
                        <div className="spkFioriFormCardHeader">
                            <div className="spkFioriFormCardHeaderMain">
                                <h2 className="spkFioriFormCardTitle">
                                    Draft SPK Faktur
                                </h2>
                                <p className="spkFioriFormCardSubtitle">
                                    Klik Pilih SPK untuk menambah satu SPK ke
                                    daftar ini.
                                </p>
                            </div>
                            <div className="spkFioriFormCardActions">
                                <Button
                                    design="Emphasized"
                                    type="Button"
                                    icon={addIcon}
                                    disabled={
                                        receiptForm.processing ||
                                        fotoStepSpk !== null
                                    }
                                    onClick={() => setSpkDialogOpen(true)}
                                >
                                    Pilih SPK
                                </Button>
                            </div>
                        </div>
                        <div className="spkFioriFormCardBody px-4 pb-4">
                            {selectedSpks.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada SPK di draft. Gunakan tombol Pilih
                                    SPK.
                                </p>
                            ) : (
                                <div className="spkTableScroll">
                                    <table className="spkTable spkTable--compact w-full">
                                        <thead>
                                            <tr>
                                                <th>No. SPK</th>
                                                <th>Item</th>
                                                <th className="spkTableColCenter">
                                                    Status
                                                </th>
                                                <th className="spkTableColCenter">
                                                    Foto BRJ
                                                </th>
                                                <th className="spkTableColRight">
                                                    Berat Akhir Barang Jadi (Emas + Batu)
                                                </th>
                                                <th className="spkTableColCenter">
                                                    Lokasi
                                                </th>
                                                <th className="spkTableColCenter w-12" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {selectedSpks.map((row) => (
                                                <tr key={row.rowId}>
                                                    <td className="font-medium whitespace-nowrap">
                                                        {row.spkNo}
                                                    </td>
                                                    <td>
                                                        <SpkTableItemCell
                                                            spkNo={row.spkNo}
                                                            spkImageUrl={
                                                                row.spkImageUrl
                                                            }
                                                            typeSkuLabel={
                                                                row.typeSkuLabel
                                                            }
                                                            itemDescription={
                                                                row.itemDescription
                                                            }
                                                            skuAssigned={
                                                                row.skuAssigned
                                                            }
                                                        />
                                                    </td>
                                                    <td className="spkTableColCenter">
                                                        <span className="spkTableBadge spkTableBadge--done">
                                                            {row.status}
                                                        </span>
                                                    </td>
                                                    <td className="spkTableColCenter">
                                                        <SpkFotoBrjCell
                                                            rowId={row.rowId}
                                                            spkNo={row.spkNo}
                                                            imageUrl={
                                                                row.fotoBrjImageUrl
                                                            }
                                                            uploadEnabled={
                                                                fotoUploadEnabled
                                                            }
                                                            onUploaded={() =>
                                                                refreshSpkFoto(
                                                                    row.rowId,
                                                                    row.fotoBrjImageUrl,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td className="spkTableColRight whitespace-nowrap tabular-nums">
                                                        {row.lastWeight ?? '—'}
                                                    </td>
                                                    <td className="spkTableColCenter">
                                                        {row.location ?? '—'}
                                                    </td>
                                                    <td className="spkTableColCenter">
                                                        <Button
                                                            design="Transparent"
                                                            type="Button"
                                                            icon={deleteIcon}
                                                            accessibleName={`Hapus ${row.spkNo}`}
                                                            onClick={() =>
                                                                removeSpk(
                                                                    row.rowId,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                        </section>
                    </form>
                </div>
            </div>

            <FakturBarangJadiSelectSpkDialog
                open={spkDialogOpen}
                onOpenChange={setSpkDialogOpen}
                excludeSpkIds={selectedIds}
                onSelected={handleSpkPicked}
            />

            {fotoStepSpk !== null ? (
                <SpkFotoBrjUploadDialog
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setFotoStepSpk(null);
                        }
                    }}
                    rowId={fotoStepSpk.rowId}
                    spkNo={fotoStepSpk.spkNo}
                    imageUrl={fotoStepSpk.fotoBrjImageUrl}
                    lastWeight={fotoStepSpk.lastWeight}
                    uploadEnabled={fotoUploadEnabled}
                    addMode
                    onConfirmed={(result) => {
                        if (fotoStepSpk === null) {
                            return;
                        }

                        addSpkToDraft(
                            fotoStepSpk,
                            result.imageUrl ?? fotoStepSpk.fotoBrjImageUrl,
                            result.lastWeight,
                        );
                        setFotoStepSpk(null);
                    }}
                />
            ) : null}
        </>
    );
}