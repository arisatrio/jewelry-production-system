import { Form, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update as updateSpkFotoBrj } from '@/routes/faktur-barang-jadi/spk/foto-brj';

export type SpkFotoBrjConfirmResult = {
    hasFotoBrj: boolean;
    imageUrl: string | null;
    lastWeight: string | null;
};

type SpkFotoBrjUploadDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rowId: number;
    spkNo: string;
    imageUrl: string | null;
    lastWeight?: string | null;
    uploadEnabled?: boolean;
    /** Setelah unggah/ konfirmasi untuk alur tambah SPK ke faktur */
    addMode?: boolean;
    onConfirmed?: (result: SpkFotoBrjConfirmResult) => void;
    onUploadSuccess?: () => void;
};

function normalizeWeightInput(raw: string): string {
    return raw.trim().replace(',', '.');
}

function isValidLastWeight(raw: string, required: boolean): boolean {
    const normalized = normalizeWeightInput(raw);

    if (normalized === '') {
        return !required;
    }

    const value = Number(normalized);

    return Number.isFinite(value) && value >= 0;
}

export function SpkFotoBrjUploadDialog({
    open,
    onOpenChange,
    rowId,
    spkNo,
    imageUrl,
    lastWeight: initialLastWeight = null,
    uploadEnabled = true,
    addMode = false,
    onConfirmed,
    onUploadSuccess,
}: SpkFotoBrjUploadDialogProps) {
    const [previewUrl, setPreviewUrl] = useState<string | null>(imageUrl);
    const [failedUrl, setFailedUrl] = useState<string | null>(null);
    const [fileInputKey, setFileInputKey] = useState(0);
    const [pendingFile, setPendingFile] = useState(false);
    const [lastWeight, setLastWeight] = useState(initialLastWeight ?? '');
    const [savingWeightOnly, setSavingWeightOnly] = useState(false);
    const modalTitle = `Foto Barang Jadi · ${spkNo}`;

    useEffect(() => {
        if (open) {
            setPreviewUrl(imageUrl);
            setFailedUrl(null);
            setPendingFile(false);
            setLastWeight(initialLastWeight ?? '');
            setFileInputKey((key) => key + 1);
        }
    }, [open, imageUrl, initialLastWeight]);

    const displayUrl =
        previewUrl && failedUrl !== previewUrl ? previewUrl : null;
    const hasExistingFoto = imageUrl !== null && imageUrl.trim() !== '';
    const lastWeightValid = isValidLastWeight(lastWeight, addMode);
    const normalizedLastWeight = normalizeWeightInput(lastWeight);

    const resetDialog = (): void => {
        setFileInputKey((key) => key + 1);
        setPreviewUrl(imageUrl);
        setFailedUrl(null);
        setPendingFile(false);
        setLastWeight(initialLastWeight ?? '');
        setSavingWeightOnly(false);
    };

    const notifyConfirmed = (
        url: string | null,
        savedLastWeight: string | null,
    ): void => {
        onConfirmed?.({
            hasFotoBrj: url !== null && url.trim() !== '',
            imageUrl: url,
            lastWeight: savedLastWeight,
        });
    };

    const savedLastWeightValue = (): string | null =>
        normalizedLastWeight !== '' ? normalizedLastWeight : null;

    const submitWeightOnly = (): void => {
        if (!lastWeightValid) {
            return;
        }

        setSavingWeightOnly(true);
        router.post(
            updateSpkFotoBrj.url(rowId),
            {
                last_weight: normalizedLastWeight,
                with_last_weight: true,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    resetDialog();
                    notifyConfirmed(imageUrl, savedLastWeightValue());
                },
                onFinish: () => setSavingWeightOnly(false),
            },
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    resetDialog();
                }

                onOpenChange(nextOpen);
            }}
        >
            <DialogContent
                className="max-h-[90vh] overflow-y-auto sm:max-w-lg"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>{modalTitle}</DialogTitle>
                    <DialogDescription>
                        {addMode
                            ? 'Isi berat akhir dan unggah foto barang jadi sebelum SPK ditambahkan ke faktur.'
                            : 'Pratinjau foto barang jadi dan unggah file baru.'}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex min-h-[12rem] items-center justify-center rounded border border-slate-200 bg-slate-50 p-4">
                    {displayUrl ? (
                        <img
                            src={displayUrl}
                            alt={modalTitle}
                            className="max-h-[45vh] w-auto max-w-full rounded object-contain"
                            onError={() =>
                                displayUrl
                                    ? setFailedUrl(displayUrl)
                                    : undefined
                            }
                        />
                    ) : (
                        <p className="text-center text-sm text-muted-foreground">
                            Belum ada foto barang jadi.
                        </p>
                    )}
                </div>

                {addMode ? (
                    <div className="grid gap-2">
                        <Label htmlFor={`foto-brj-last-weight-${rowId}`}>
                            Berat Akhir Barang Jadi (Emas + Batu)
                        </Label>
                        <Input
                            id={`foto-brj-last-weight-${rowId}`}
                            name="last_weight"
                            type="text"
                            inputMode="decimal"
                            autoComplete="off"
                            value={lastWeight}
                            placeholder="contoh: 4,66"
                            onChange={(event) =>
                                setLastWeight(event.target.value)
                            }
                        />
                        <p className="text-muted-foreground text-xs">
                            Disimpan ke berat akhir SPK.
                        </p>
                    </div>
                ) : null}

                {uploadEnabled ? (
                    <Form
                        {...updateSpkFotoBrj.form(rowId)}
                        encType="multipart/form-data"
                        preserveScroll
                        onSuccess={() => {
                            const confirmedUrl =
                                previewUrl ?? imageUrl ?? null;
                            onOpenChange(false);
                            resetDialog();
                            onUploadSuccess?.();
                            if (addMode) {
                                notifyConfirmed(
                                    confirmedUrl,
                                    savedLastWeightValue(),
                                );
                            }
                        }}
                    >
                        {({ processing, errors }) => (
                            <>
                                {addMode ? (
                                    <>
                                        <input
                                            type="hidden"
                                            name="last_weight"
                                            value={normalizedLastWeight}
                                        />
                                        <input
                                            type="hidden"
                                            name="with_last_weight"
                                            value="1"
                                        />
                                        <InputError
                                            message={errors.last_weight}
                                        />
                                    </>
                                ) : null}
                                <div className="grid gap-2">
                                    <Label htmlFor={`foto-brj-file-${rowId}`}>
                                        Unggah foto
                                    </Label>
                                    <Input
                                        id={`foto-brj-file-${rowId}`}
                                        key={fileInputKey}
                                        type="file"
                                        name="foto"
                                        accept="image/jpeg,image/png,image/webp,image/gif"
                                        disabled={processing}
                                        required={
                                            addMode &&
                                            !hasExistingFoto &&
                                            !pendingFile
                                        }
                                        onChange={(event) => {
                                            const file =
                                                event.target.files?.[0];

                                            if (!file) {
                                                setPreviewUrl(imageUrl);
                                                setPendingFile(false);

                                                return;
                                            }

                                            setPreviewUrl(
                                                URL.createObjectURL(file),
                                            );
                                            setFailedUrl(null);
                                            setPendingFile(true);
                                        }}
                                    />
                                    <InputError message={errors.foto} />
                                    <p className="text-muted-foreground text-xs">
                                        JPG, PNG, WEBP, atau GIF — maks. 10 MB.
                                    </p>
                                </div>
                                <DialogFooter className="gap-2 sm:justify-end">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => onOpenChange(false)}
                                    >
                                        Batal
                                    </Button>
                                    {addMode &&
                                    hasExistingFoto &&
                                    !pendingFile ? (
                                        <Button
                                            type="button"
                                            disabled={
                                                savingWeightOnly ||
                                                !lastWeightValid
                                            }
                                            onClick={submitWeightOnly}
                                        >
                                            {savingWeightOnly
                                                ? 'Menyimpan...'
                                                : 'Tambahkan ke faktur'}
                                        </Button>
                                    ) : null}
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            (addMode && !lastWeightValid) ||
                                            (addMode &&
                                                !hasExistingFoto &&
                                                !pendingFile)
                                        }
                                    >
                                        {processing
                                            ? 'Menyimpan...'
                                            : addMode
                                              ? 'Simpan & tambahkan'
                                              : 'Simpan foto'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                ) : (
                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        {addMode ? (
                            <Button
                                type="button"
                                disabled={savingWeightOnly || !lastWeightValid}
                                onClick={submitWeightOnly}
                            >
                                {savingWeightOnly
                                    ? 'Menyimpan...'
                                    : 'Tambahkan ke faktur'}
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                Tutup
                            </Button>
                        )}
                    </DialogFooter>
                )}
            </DialogContent>
        </Dialog>
    );
}