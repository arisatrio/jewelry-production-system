import type { ReactNode } from 'react';
import AppShellHeader from '@/components/fiori/app-shell-header';
import type { AppShellHeaderProps } from '@/components/fiori/app-shell-header';
import { useSaveQuickLoginProfile } from '@/hooks/use-save-quick-login-profile';

export type FioriLayoutProps = AppShellHeaderProps & {
    children: ReactNode;
};

export default function FioriLayout({
    children,
    ...headerProps
}: FioriLayoutProps) {
    useSaveQuickLoginProfile();

    return (
        <div className="appShell">
            <AppShellHeader {...headerProps} />
            <div className="dashboardContent">{children}</div>
        </div>
    );
}
