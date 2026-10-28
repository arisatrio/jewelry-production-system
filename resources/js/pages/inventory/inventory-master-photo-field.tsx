import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEffect, useState } from 'react';

type InventoryMasterPhotoFieldProps = {
    idPrefix?: string;
    currentPhotoUrl?: string | null;
    errors?: Partial<Record<string, string>>;
};

export function InventoryMasterPhotoField({
    idPrefix = '',
    currentPhotoUrl = null,
    errors = {},
}: InventoryMasterPhotoFieldProps) {
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    useEffect(() => {
        return () => {
            if (previewUrl !== null && previewUrl.startsWith('blob:')) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const displayUrl = previewUrl ?? currentPhotoUrl?.trim() ?? '';

    return (
        <div className="grid gap-2">
            <Label htmlFor={`${idPrefix}photo`}>Foto</Label>
            {displayUrl !== '' ? (
                <div className="spkItemImagePreview inventoryMasterPhotoPreview">
                    <img
                        src={displayUrl}
                        alt="Preview foto master"
                    />
                </div>
            ) : (
                <div className="spkItemImagePlaceholder inventoryMasterPhotoPlaceholder">
                    <span>Belum ada foto</span>
                </div>
            )}
            <Input
                id={`${idPrefix}photo`}
                name="photo"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={(event) => {
                    const file = event.target.files?.[0];

                    if (previewUrl !== null && previewUrl.startsWith('blob:')) {
                        URL.revokeObjectURL(previewUrl);
                    }

                    if (file === undefined) {
                        setPreviewUrl(null);

                        return;
                    }

                    setPreviewUrl(URL.createObjectURL(file));
                }}
            />
            <p className="text-muted-foreground text-xs">
                JPEG, PNG, atau WebP · maks. 5 MB. Disimpan ke Google Cloud
                Storage.
            </p>
            <InputError message={errors.photo} />
        </div>
    );
}

type InventoryMasterPhotoPanelProps = {
    photoUrl: string | null;
    title: string;
};

export function InventoryMasterPhotoPanel({
    photoUrl,
    title,
}: InventoryMasterPhotoPanelProps) {
    const url = photoUrl?.trim() ?? '';

    if (url === '') {
        return (
            <div className="inventoryMasterPhotoPanel">
                <span className="spkDetailFieldLabel">Foto</span>
                <div className="spkItemImagePlaceholder inventoryMasterPhotoPlaceholder">
                    <span>Belum ada foto</span>
                </div>
            </div>
        );
    }

    return (
        <div className="inventoryMasterPhotoPanel">
            <span className="spkDetailFieldLabel">Foto</span>
            <a
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                className="spkItemImagePreview inventoryMasterPhotoPreview inventoryMasterPhotoPreview--link"
                title={`Buka foto ${title} di tab baru`}
            >
                <img src={url} alt={`Foto ${title}`} />
            </a>
        </div>
    );
}
