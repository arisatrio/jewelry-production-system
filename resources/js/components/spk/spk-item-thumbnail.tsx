import pictureIcon from '@ui5/webcomponents-icons/dist/picture.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type SpkItemThumbnailProps = {
    imageUrl: string | null;
    spkNo: string | null;
};

export function SpkItemThumbnail({ imageUrl, spkNo }: SpkItemThumbnailProps) {
    const [failedUrl, setFailedUrl] = useState<string | null>(null);
    const [open, setOpen] = useState(false);
    const modalTitle = spkNo ? `Gambar SPK · ${spkNo}` : 'Gambar SPK';

    if (!imageUrl || failedUrl === imageUrl) {
        return (
            <span
                className="flex size-12 shrink-0 items-center justify-center rounded border border-slate-200 bg-slate-50 text-slate-400"
                title="Gambar belum tersedia"
            >
                <Icon name={pictureIcon} mode="Decorative" />
            </span>
        );
    }

    return (
        <>
            <button
                type="button"
                className="block size-12 shrink-0 cursor-zoom-in overflow-hidden rounded border border-slate-200 bg-white"
                aria-label={`Lihat ${modalTitle.toLowerCase()}`}
                title={modalTitle}
                onClick={() => setOpen(true)}
            >
                <img
                    src={imageUrl}
                    alt={modalTitle}
                    loading="lazy"
                    className="size-full object-cover"
                    onError={() => setFailedUrl(imageUrl)}
                />
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="spkAlertModal">
                    <DialogHeader>
                        <DialogTitle>{modalTitle}</DialogTitle>
                    </DialogHeader>
                    <div className="spkAlertModalBody flex items-center justify-center">
                        <img
                            src={imageUrl}
                            alt={modalTitle}
                            className="max-h-[65vh] w-auto max-w-full rounded object-contain"
                        />
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
