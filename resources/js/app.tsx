import { createInertiaApp } from '@inertiajs/react';
import { ThemeProvider } from '@ui5/webcomponents-react/ThemeProvider';
import '@ui5/webcomponents-react/styles.css';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import FioriLayout from '@/layouts/fiori-layout';
import SettingsLayout from '@/layouts/settings/layout';
import '@/lib/ui5-shell-assets';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name.startsWith('spk/'):
            case name.startsWith('jewelcad/'):
            case name.startsWith('resin/'):
            case name.startsWith('master-data/'):
                return FioriLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <ThemeProvider staticCssInjected>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </ThemeProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
