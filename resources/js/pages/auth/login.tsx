import { Form, Head, router } from '@inertiajs/react';
import { Clock, LogIn, User, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { confirmDialog } from '@/lib/confirm-dialog';
import {
    clearQuickLoginProfiles,
    getQuickLoginProfiles,
    removeQuickLoginProfile,
} from '@/lib/quick-login-profiles';
import type { QuickLoginProfile } from '@/lib/quick-login-profiles';
import { quick, store } from '@/routes/login';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

function formatLastLogin(lastLogin: string): string {
    const date = new Date(lastLogin);
    const now = new Date();
    const diffInHours = Math.floor(
        (now.getTime() - date.getTime()) / (1000 * 60 * 60),
    );

    if (diffInHours < 1) {
        return 'Baru saja';
    }

    if (diffInHours < 24) {
        return `${diffInHours} jam lalu`;
    }

    if (diffInHours < 48) {
        return 'Kemarin';
    }

    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

export default function Login({ status }: Props) {
    const [recentUsers, setRecentUsers] = useState<QuickLoginProfile[]>(
        getQuickLoginProfiles,
    );

    const handleRecentUserClick = (profile: QuickLoginProfile): void => {
        router.post(quick.url(), { user_id: profile.user_id });
    };

    const handleRemoveProfile = async (userId: string): Promise<void> => {
        const confirmed = await confirmDialog({
            title: 'Hapus Profil Login',
            description: 'Apakah Anda yakin ingin menghapus profil login ini?',
            confirmText: 'Hapus',
            destructive: true,
        });

        if (confirmed) {
            setRecentUsers(removeQuickLoginProfile(userId));
        }
    };

    return (
        <>
            <Head title="Log in" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
                onBefore={() => {
                    sessionStorage.setItem('showSaveLoginDialog', '1');
                }}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="user_id">User ID</Label>
                            <Input
                                id="user_id"
                                type="text"
                                name="user_id"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="username"
                                placeholder="Masukkan user ID..."
                            />
                            <InputError message={errors.user_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="Masukkan password..."
                            />
                            <InputError message={errors.password} />
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <LogIn className="mr-2 h-4 w-4" />
                            )}
                            Log in
                        </Button>
                    </div>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {recentUsers.length > 0 && (
                <div className="mt-8">
                    <div className="mb-4 flex items-center gap-2">
                        <Clock className="h-4 w-4 text-muted-foreground" />
                        <span className="text-sm font-semibold text-foreground">
                            Login Cepat
                        </span>
                        <Separator className="flex-1" />
                    </div>

                    <div className="space-y-3">
                        {recentUsers.map((profile, index) => (
                            <Card
                                key={profile.user_id}
                                className="group cursor-pointer py-0 transition-all duration-200 hover:border-primary/20 hover:shadow-md"
                            >
                                <CardContent className="p-4">
                                    <div className="flex items-center justify-between">
                                        <button
                                            type="button"
                                            className="flex min-w-0 flex-1 items-center gap-3 text-left"
                                            onClick={() =>
                                                handleRecentUserClick(profile)
                                            }
                                        >
                                            <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 transition-colors group-hover:bg-primary/20">
                                                <User className="h-5 w-5 text-primary" />
                                            </div>

                                            <div className="min-w-0 flex-1">
                                                <div className="mb-1 flex items-center gap-2">
                                                    <h3 className="truncate font-medium text-foreground">
                                                        {profile.name}
                                                    </h3>
                                                    {index === 0 && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="px-2 py-0.5 text-xs"
                                                        >
                                                            Terakhir
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                    <span className="rounded bg-muted px-2 py-0.5 font-mono text-xs">
                                                        {profile.user_id}
                                                    </span>
                                                    <span>•</span>
                                                    <span className="text-xs">
                                                        {formatLastLogin(
                                                            profile.lastLogin,
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                        </button>

                                        <div className="ml-3 flex items-center gap-2">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="h-8 w-8 p-0 opacity-0 transition-opacity group-hover:opacity-100"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    void handleRemoveProfile(
                                                        profile.user_id,
                                                    );
                                                }}
                                                title="Hapus profil"
                                            >
                                                <X className="h-4 w-4 text-muted-foreground hover:text-destructive" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                className="h-8 px-3 text-xs"
                                                onClick={() =>
                                                    handleRecentUserClick(
                                                        profile,
                                                    )
                                                }
                                            >
                                                <LogIn className="mr-1 h-3 w-3" />
                                                Login
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <div className="mt-4 text-center">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={async () => {
                                const confirmed = await confirmDialog({
                                    title: 'Hapus Semua Profil',
                                    description:
                                        'Apakah Anda yakin ingin menghapus semua profil login cepat?',
                                    confirmText: 'Hapus semua',
                                    destructive: true,
                                });

                                if (confirmed) {
                                    clearQuickLoginProfiles();
                                    setRecentUsers([]);
                                }
                            }}
                            className="text-xs text-muted-foreground hover:text-destructive"
                        >
                            Hapus Semua Profil
                        </Button>
                    </div>
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: '',
    description: '',
};
