import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import ProductionController from '@/actions/App/Http/Controllers/ProductionController';

type SpkRowPrintButtonProps = {
    rowId: number;
    spkNo: string;
};

export function SpkRowPrintButton({ rowId, spkNo }: SpkRowPrintButtonProps) {
    const handleClick = (event: React.MouseEvent<HTMLButtonElement>): void => {
        event.preventDefault();
        event.stopPropagation();

        const previewUrl = ProductionController.print.url(rowId);
        const previewWindow = window.open(previewUrl, '_blank');

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
            aria-label={`Print ${spkNo}`}
            title="Preview Print"
            onClick={handleClick}
        >
            <Icon name={printIcon} mode="Decorative" />
        </button>
    );
}
