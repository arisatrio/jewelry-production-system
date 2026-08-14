import type { ReactNode } from 'react';
import AppShellHeader, {
    type AppShellHeaderProps,
} from '@/components/fiori/app-shell-header';

export type FioriLayoutProps = AppShellHeaderProps & {
    children: ReactNode;
};

export default function FioriLayout({
    children,
    ...headerProps
}: FioriLayoutProps) {
    return (
        <div className="appShell">
            <AppShellHeader {...headerProps} />
            <div className="dashboardContent">{children}</div>
        </div>
    );
}
