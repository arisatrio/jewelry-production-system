import { router } from '@inertiajs/react';
import checklistIcon from '@ui5/webcomponents-icons/dist/checklist.js';
import paperPlaneIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import {
    approve as spkApprove,
    managerApprove as spkManagerApprove,
    submit as spkSubmit,
} from '@/routes/spk';

export const SPK_DRAFT_STATUS = 'Draft';

export const SPK_PENDING_MANAGER_STATUS = 'Menunggu Approval';

export function shouldShowSpkRowSendButton(
    status: string,
    canSubmit: boolean,
    canApprove: boolean,
): boolean {
    return (
        status.trim() === SPK_DRAFT_STATUS && (canSubmit || canApprove)
    );
}

export function shouldShowSpkRowManagerApproveButton(
    status: string,
    canManagerApprove: boolean,
): boolean {
    return (
        status.trim() === SPK_PENDING_MANAGER_STATUS && canManagerApprove
    );
}

type SpkRowSendButtonProps = {
    rowId: number;
    spkNo: string;
    action: 'submit' | 'approve';
    disabled?: boolean;
    onProcessingChange: (processing: boolean) => void;
};

type SpkRowManagerApproveButtonProps = {
    rowId: number;
    spkNo: string;
    disabled?: boolean;
    onProcessingChange: (processing: boolean) => void;
};

export function SpkRowSendButton({
    rowId,
    spkNo,
    action,
    disabled = false,
    onProcessingChange,
}: SpkRowSendButtonProps) {
    const isApprove = action === 'approve';
    const label = isApprove ? 'Kirim ke Produksi' : 'Kirim ke Manager';
    const confirmMessage = isApprove
        ? `Kirim SPK ${spkNo} ke Produksi?`
        : `Kirim SPK ${spkNo} ke Manager?`;
    const url = isApprove
        ? spkApprove.url(rowId)
        : spkSubmit.url(rowId);

    const handleClick = (event: React.MouseEvent<HTMLButtonElement>): void => {
        event.preventDefault();
        event.stopPropagation();

        if (disabled || !window.confirm(confirmMessage)) {
            return;
        }

        onProcessingChange(true);
        router.post(
            url,
            { notes: null },
            {
                preserveScroll: true,
                onFinish: () => onProcessingChange(false),
            },
        );
    };

    return (
        <button
            type="button"
            className="spkRowActionBtn spkRowActionBtn--send"
            aria-label={`${label} ${spkNo}`}
            title={label}
            disabled={disabled}
            onClick={handleClick}
        >
            <Icon name={paperPlaneIcon} mode="Decorative" />
        </button>
    );
}

export function SpkRowManagerApproveButton({
    rowId,
    spkNo,
    disabled = false,
    onProcessingChange,
}: SpkRowManagerApproveButtonProps) {
    const handleClick = (event: React.MouseEvent<HTMLButtonElement>): void => {
        event.preventDefault();
        event.stopPropagation();

        if (
            disabled ||
            !window.confirm(`Approve SPK ${spkNo}?`)
        ) {
            return;
        }

        onProcessingChange(true);
        router.post(
            spkManagerApprove.url(rowId),
            { notes: null },
            {
                preserveScroll: true,
                onFinish: () => onProcessingChange(false),
            },
        );
    };

    return (
        <button
            type="button"
            className="spkRowActionBtn spkRowActionBtn--approve"
            aria-label={`Approve ${spkNo}`}
            title="Approve Manager"
            disabled={disabled}
            onClick={handleClick}
        >
            <Icon name={checklistIcon} mode="Decorative" />
        </button>
    );
}
