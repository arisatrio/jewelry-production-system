import type { ReactNode } from 'react';
import { Button } from '@ui5/webcomponents-react/Button';
import { Dialog } from '@ui5/webcomponents-react/Dialog';
import { Title } from '@ui5/webcomponents-react/Title';

export type FioriFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    children: ReactNode;
    formId?: string;
    processing?: boolean;
    cancelLabel?: string;
    submitLabel?: string;
    showSubmit?: boolean;
    onCancel?: () => void;
    size?: 'default' | 'wide';
    headerEnd?: ReactNode;
    className?: string;
};

export function FioriFormDialog({
    open,
    onOpenChange,
    title,
    children,
    formId,
    processing = false,
    cancelLabel = 'Batal',
    submitLabel = 'Simpan',
    showSubmit = true,
    onCancel,
    size = 'default',
    headerEnd,
    className,
}: FioriFormDialogProps) {
    const handleCancel = () => {
        if (onCancel) {
            onCancel();

            return;
        }

        onOpenChange(false);
    };

    const handleSubmitClick = () => {
        if (!formId || processing) {
            return;
        }

        const form = document.getElementById(formId) as HTMLFormElement | null;
        form?.requestSubmit();
    };

    const dialogClassName = [
        'fioriFormDialog',
        size === 'wide' ? 'fioriFormDialog--wide' : '',
        className ?? '',
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <Dialog
            open={open}
            accessibleName={title}
            className={dialogClassName}
            onClose={() => onOpenChange(false)}
            header={
                <div className="fioriFormDialogHeader">
                    <Title level="H5" className="fioriFormDialogTitle">
                        {title}
                    </Title>
                    <div className="fioriFormDialogHeaderActions">
                        {headerEnd ?? (
                            <>
                                <Button
                                    design="Transparent"
                                    type="Button"
                                    disabled={processing}
                                    onClick={handleCancel}
                                >
                                    {cancelLabel}
                                </Button>
                                {showSubmit ? (
                                    <Button
                                        design="Emphasized"
                                        type="Button"
                                        disabled={processing}
                                        onClick={handleSubmitClick}
                                    >
                                        {processing
                                            ? 'Menyimpan...'
                                            : submitLabel}
                                    </Button>
                                ) : null}
                            </>
                        )}
                    </div>
                </div>
            }
        >
            {children}
        </Dialog>
    );
}
