import { QRCodeSVG } from 'qrcode.react';

type SpkBarcodeProps = {
    value: string;
    label: string;
    size?: number;
};

export function SpkBarcode({ value, label, size = 148 }: SpkBarcodeProps) {
    if (value === '') {
        return null;
    }

    return (
        <a
            href={value}
            className="spkBarcode"
            style={{ width: size, height: size }}
            title={`Scan untuk membuka ${label}`}
            aria-label={`QR code menuju detail SPK ${label}`}
        >
            <QRCodeSVG
                value={value}
                size={size}
                level="M"
                marginSize={1}
                bgColor="#ffffff"
                fgColor="#111827"
                className="spkBarcodeSvg"
            />
        </a>
    );
}
