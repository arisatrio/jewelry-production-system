import { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmDialogOptions = {
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
    destructive?: boolean;
};

export function confirmDialog({
    title,
    description,
    confirmText = 'Ya, Lanjut',
    cancelText = 'Batal',
    destructive = false,
}: ConfirmDialogOptions): Promise<boolean> {
    return new Promise((resolve) => {
        const container = document.createElement('div');
        document.body.appendChild(container);

        const root = createRoot(container);
        let settled = false;

        const finish = (value: boolean) => {
            if (settled) {
                return;
            }

            settled = true;
            root.unmount();
            container.remove();
            resolve(value);
        };

        const ConfirmComponent = () => {
            const [open, setOpen] = useState(true);
            const confirmedRef = useRef(false);

            useEffect(() => {
                if (!open) {
                    const timer = window.setTimeout(() => {
                        finish(confirmedRef.current);
                    }, 300);

                    return () => window.clearTimeout(timer);
                }
            }, [open]);

            return (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                        <DialogFooter className="flex justify-end gap-2 pt-4">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    confirmedRef.current = false;
                                    setOpen(false);
                                }}
                            >
                                {cancelText}
                            </Button>
                            <Button
                                variant={
                                    destructive ? 'destructive' : 'default'
                                }
                                onClick={() => {
                                    confirmedRef.current = true;
                                    setOpen(false);
                                }}
                            >
                                {confirmText}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            );
        };

        root.render(<ConfirmComponent />);
    });
}
