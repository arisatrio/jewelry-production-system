import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

const TOAST_DURATION_MS = 8000;

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            duration={TOAST_DURATION_MS}
            toastOptions={{
                duration: TOAST_DURATION_MS,
                classNames: {
                    toast: 'app-toast',
                    title: 'app-toast__title',
                    description: 'app-toast__description',
                },
            }}
            style={
                {
                    '--normal-bg': 'var(--primary)',
                    '--normal-text': 'var(--primary-foreground)',
                    '--normal-border': 'var(--primary)',
                    '--success-bg': 'var(--primary)',
                    '--success-text': 'var(--primary-foreground)',
                    '--success-border': 'var(--primary)',
                    '--info-bg': 'var(--primary)',
                    '--info-text': 'var(--primary-foreground)',
                    '--info-border': 'var(--primary)',
                    '--warning-bg': 'var(--primary)',
                    '--warning-text': 'var(--primary-foreground)',
                    '--warning-border': 'var(--primary)',
                    '--error-bg': 'var(--primary)',
                    '--error-text': 'var(--primary-foreground)',
                    '--error-border': 'var(--primary)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
