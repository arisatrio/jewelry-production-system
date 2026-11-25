import type { ReactNode } from 'react';

export default function PublicLayout({ children }: { children: ReactNode }) {
    return (
        <div className="spkPublicShareShell">
            <main className="spkPublicShareMain">{children}</main>
        </div>
    );
}