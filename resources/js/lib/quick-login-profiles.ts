export type QuickLoginProfile = {
    user_id: string;
    name: string;
    lastLogin: string;
};

const STORAGE_KEY = 'quickLoginProfiles';
const MAX_PROFILES = 5;

export function getQuickLoginProfiles(): QuickLoginProfile[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const saved = JSON.parse(
            localStorage.getItem(STORAGE_KEY) || '[]',
        ) as QuickLoginProfile[];

        return Array.isArray(saved) ? saved : [];
    } catch {
        return [];
    }
}

export function saveQuickLoginProfile(userData: {
    user_id: string;
    name: string;
}): QuickLoginProfile[] {
    const saved = getQuickLoginProfiles();
    const profile: QuickLoginProfile = {
        ...userData,
        lastLogin: new Date().toISOString(),
    };
    const updated = [
        profile,
        ...saved.filter((item) => item.user_id !== userData.user_id),
    ].slice(0, MAX_PROFILES);

    localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));

    return updated;
}

export function removeQuickLoginProfile(userId: string): QuickLoginProfile[] {
    const updated = getQuickLoginProfiles().filter(
        (profile) => profile.user_id !== userId,
    );

    localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));

    return updated;
}

export function clearQuickLoginProfiles(): void {
    localStorage.removeItem(STORAGE_KEY);
}
