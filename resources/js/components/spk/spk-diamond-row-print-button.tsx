import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { print as spkDiamondPrint } from '@/routes/spk-diamond';

type SpkDiamondRowPrintButtonProps = {
    documentId: number;
    docNo: string;
};

export function SpkDiamondRowPrintButton({
    documentId,
    docNo,
}: SpkDiamondRowPrintButtonProps) {
    const handleClick = (event: React.MouseEvent<HTMLButtonElement>): void => {
        event.preventDefault();
        event.stopPropagation();

        const previewUrl = spkDiamondPrint.url(documentId);
        const previewWindow = window.open(
            previewUrl,
            '_blank',
            'noopener,noreferrer',
        );

        if (!previewWindow) {
            window.alert(
                'Gagal membuka preview print. Izinkan pop-up untuk situs ini, lalu coba lagi.',
            );
        }
    };

    return (
        <button
            type="button"
            className="spkRowActionBtn spkRowActionBtn--print"
            aria-label={`Print ${docNo}`}
            title="Cetak SPK Pasang Batu"
            onClick={handleClick}
        >
            <Icon name={printIcon} mode="Decorative" />
        </button>
    );
}