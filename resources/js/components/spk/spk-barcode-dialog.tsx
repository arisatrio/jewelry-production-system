import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SpkBarcode } from '@/components/spk/spk-barcode';

type SpkBarcodeDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    value: string;
    label: string;
};

export function SpkBarcodeDialog({
    open,
    onOpenChange,
    value,
    label,
}: SpkBarcodeDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkBarcodeDialog">
                <DialogHeader>
                    <DialogTitle>QR Code SPK</DialogTitle>
                    <DialogDescription>
                        Scan QR code untuk membuka detail {label}.
                    </DialogDescription>
                </DialogHeader>

                <div className="spkBarcodeDialogBody">
                    {value !== '' ? (
                        <>
                            <SpkBarcode value={value} label={label} size={220} />
                            <p className="spkBarcodeDialogLabel">{label}</p>
                        </>
                    ) : (
                        <p className="spkBarcodeDialogEmpty">
                            QR code tidak tersedia.
                        </p>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
