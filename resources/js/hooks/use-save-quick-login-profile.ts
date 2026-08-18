import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { confirmDialog } from '@/lib/confirm-dialog';
import { saveQuickLoginProfile } from '@/lib/quick-login-profiles';

export function useSaveQuickLoginProfile(): void {
    const user = usePage().props.auth.user;

    useEffect(() => {
        if (sessionStorage.getItem('showSaveLoginDialog') !== '1') {
            return;
        }

        sessionStorage.removeItem('showSaveLoginDialog');

        if (!user?.user_id) {
            return;
        }

        void confirmDialog({
            title: 'Simpan info login?',
            description:
                'Simpan info login ini untuk kemudahan login berikutnya di perangkat ini?',
            confirmText: 'Simpan',
        }).then((confirmed) => {
            if (confirmed) {
                saveQuickLoginProfile({
                    user_id: user.user_id,
                    name: user.name,
                });
            }
        });
    }, [user]);
}
