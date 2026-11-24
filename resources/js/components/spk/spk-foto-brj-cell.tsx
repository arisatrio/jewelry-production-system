import pictureIcon from '@ui5/webcomponents-icons/dist/picture.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useState } from 'react';
import { SpkFotoBrjUploadDialog } from '@/components/spk/spk-foto-brj-upload-dialog';

type SpkFotoBrjCellProps = {
    rowId: number;
    spkNo: string;
    imageUrl: string | null;
    uploadEnabled?: boolean;
    onUploaded?: () => void;
};

export function SpkFotoBrjCell({
    rowId,
    spkNo,
    imageUrl,
    uploadEnabled = true,
    onUploaded,
}: SpkFotoBrjCellProps) {
    const [open, setOpen] = useState(false);
    const [previewUrl, setPreviewUrl] = useState<string | null>(imageUrl);
    const [failedUrl, setFailedUrl] = useState<string | null>(null);
    const modalTitle = `Foto Barang Jadi · ${spkNo}`;

    useEffect(() => {
        setPreviewUrl(imageUrl);
    }, [imageUrl]);

    const displayUrl =
        previewUrl && failedUrl !== previewUrl ? previewUrl : null;

    return (
        <>
            <button
                type="button"
                className={
                    displayUrl
                        ? 'mx-auto block size-12 shrink-0 cursor-pointer overflow-hidden rounded border border-slate-200 bg-white'
                        : 'mx-auto flex size-12 shrink-0 cursor-pointer items-center justify-center rounded border border-dashed border-slate-300 bg-slate-50 text-slate-500 hover:border-slate-400 hover:bg-slate-100'
                }
                aria-label={`Kelola ${modalTitle.toLowerCase()}`}
                title={modalTitle}
                onClick={() => setOpen(true)}
            >
                {displayUrl ? (
                    <img
                        src={displayUrl}
                        alt={modalTitle}
                        loading="lazy"
                        className="size-full object-cover"
                        onError={() => setFailedUrl(displayUrl)}
                    />
                ) : (
                    <Icon name={pictureIcon} mode="Decorative" />
                )}
            </button>

            <SpkFotoBrjUploadDialog
                open={open}
                onOpenChange={setOpen}
                rowId={rowId}
                spkNo={spkNo}
                imageUrl={imageUrl}
                uploadEnabled={uploadEnabled}
                onUploadSuccess={onUploaded}
            />
        </>
    );
}