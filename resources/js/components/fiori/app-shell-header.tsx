import { useEffect, useMemo, useRef, useState, type FormEvent, type KeyboardEvent } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import employeeIcon from '@ui5/webcomponents-icons/dist/employee.js';
import exitFullScreenIcon from '@ui5/webcomponents-icons/dist/exit-full-screen.js';
import fullScreenIcon from '@ui5/webcomponents-icons/dist/full-screen.js';
import logIcon from '@ui5/webcomponents-icons/dist/log.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import settingsIcon from '@ui5/webcomponents-icons/dist/action-settings.js';
import { Avatar } from '@ui5/webcomponents-react/Avatar';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { ListItemStandard } from '@ui5/webcomponents-react/ListItemStandard';
import { Menu } from '@ui5/webcomponents-react/Menu';
import { MenuItem } from '@ui5/webcomponents-react/MenuItem';
import { MenuSeparator } from '@ui5/webcomponents-react/MenuSeparator';
import { ShellBar } from '@ui5/webcomponents-react/ShellBar';
import { ShellBarItem } from '@ui5/webcomponents-react/ShellBarItem';
import { ShellBarSpacer } from '@ui5/webcomponents-react/ShellBarSpacer';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { index as spkIndex, show as spkShow } from '@/routes/spk';
import { suggestions as searchSpkSuggestions } from '@/routes/spk/select';
import { cn } from '@/lib/utils';
import {
    defaultMidDropdowns,
    defaultModuleNavItems,
    defaultPostSpkDropdowns,
    defaultPrimaryNavItems,
    defaultTrailingDropdowns,
    type ShellNavDropdown,
    type ShellNavItem,
} from '@/components/fiori/nav-config';

const PROFILE_SETTINGS_LINKS_ENABLED = false;

type SpkSearchSuggestion = {
    rowId: number;
    spkNo: string;
    spkType: string | null;
    customer: string | null;
    item: string | null;
    lastProcess: string | null;
};

export type AppShellHeaderProps = {
    activeMenu?: string;
    defaultActiveMenu?: string;
    onActiveMenuChange?: (menu: string) => void;
    brandText?: string;
    logoSrc?: string;
    logoAlt?: string;
    pageTitle?: string;
    searchPlaceholder?: string;
    searchQuery?: string;
    defaultSearchQuery?: string;
    onSearchChange?: (query: string) => void;
    onSearchSubmit?: (query: string) => void;
    showSearch?: boolean;
    showNotifications?: boolean;
    notificationsCount?: string;
    onNotificationsClick?: () => void;
    settingsHref?: string;
    primaryNavItems?: ShellNavItem[];
    moduleNavItems?: ShellNavItem[];
    midDropdowns?: ShellNavDropdown[];
    postSpkDropdowns?: ShellNavDropdown[];
    trailingDropdowns?: ShellNavDropdown[];
    userName?: string;
};

function getGreeting(hour: number): string {
    if (hour < 11) {
        return 'Selamat pagi';
    }

    if (hour < 15) {
        return 'Selamat siang';
    }

    if (hour < 18) {
        return 'Selamat sore';
    }

    return 'Selamat malam';
}

function formatHeaderDate(date: Date): string {
    return date.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function findNavHref(text: string, items: ShellNavItem[]): string | undefined {
    return items.find((item) => item.text === text)?.href;
}

function dropdownButtonId(id: string): string {
    return `${id}-menu-btn`;
}

export default function AppShellHeader({
    activeMenu: controlledActiveMenu,
    defaultActiveMenu = 'Dashboard',
    onActiveMenuChange,
    brandText = 'Production System',
    logoSrc = '/images/logo.jpg',
    logoAlt = 'Wanda',
    pageTitle,
    searchPlaceholder = 'Cari SPK...',
    searchQuery: controlledSearchQuery,
    defaultSearchQuery = '',
    onSearchChange,
    onSearchSubmit,
    showSearch = true,
    showNotifications = true,
    notificationsCount = '3',
    onNotificationsClick,
    settingsHref,
    primaryNavItems = defaultPrimaryNavItems,
    moduleNavItems = defaultModuleNavItems,
    midDropdowns = defaultMidDropdowns,
    postSpkDropdowns = defaultPostSpkDropdowns,
    trailingDropdowns = defaultTrailingDropdowns,
    userName: userNameProp,
}: AppShellHeaderProps) {
    const page = usePage();
    const userName = userNameProp ?? page.props.auth.user?.name ?? 'Guest';
    const resolvedSettingsHref = settingsHref ?? editProfile.url();

    const isActiveMenuControlled = controlledActiveMenu !== undefined;
    const [uncontrolledActiveMenu, setUncontrolledActiveMenu] =
        useState(defaultActiveMenu);
    const activeMenu = isActiveMenuControlled
        ? controlledActiveMenu
        : uncontrolledActiveMenu;

    const isSearchControlled = controlledSearchQuery !== undefined;
    const [uncontrolledSearchQuery, setUncontrolledSearchQuery] =
        useState(defaultSearchQuery);
    const searchQuery = isSearchControlled
        ? controlledSearchQuery
        : uncontrolledSearchQuery;
    const [suggestions, setSuggestions] = useState<SpkSearchSuggestion[]>([]);
    const [suggestionsOpen, setSuggestionsOpen] = useState(false);
    const [suggestionsLoading, setSuggestionsLoading] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const searchWrapRef = useRef<HTMLDivElement | null>(null);
    const suggestionRequestIdRef = useRef(0);

    useEffect(() => {
        if (isSearchControlled) {
            return;
        }

        const [pathOnly, queryString = ''] = page.url.split('?');

        if (pathOnly !== '/spk') {
            return;
        }

        const params = new URLSearchParams(queryString);
        setUncontrolledSearchQuery(params.get('search') ?? '');
    }, [page.url, isSearchControlled]);

    useEffect(() => {
        const query = searchQuery.trim();

        if (query.length < 2) {
            setSuggestions([]);
            setSuggestionsOpen(false);
            setSuggestionsLoading(false);
            setHighlightedIndex(-1);

            return;
        }

        const requestId = ++suggestionRequestIdRef.current;
        const timeout = window.setTimeout(async () => {
            setSuggestionsLoading(true);

            try {
                const response = await fetch(
                    searchSpkSuggestions.url({
                        query: {
                            search: query,
                            limit: 8,
                        },
                    }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    throw new Error('Gagal memuat saran SPK.');
                }

                const payload = (await response.json()) as {
                    data?: SpkSearchSuggestion[];
                };

                if (requestId !== suggestionRequestIdRef.current) {
                    return;
                }

                const nextSuggestions = Array.isArray(payload.data)
                    ? payload.data
                    : [];

                setSuggestions(nextSuggestions);
                setSuggestionsOpen(true);
                setHighlightedIndex(nextSuggestions.length > 0 ? 0 : -1);
            } catch {
                if (requestId !== suggestionRequestIdRef.current) {
                    return;
                }

                setSuggestions([]);
                setSuggestionsOpen(false);
                setHighlightedIndex(-1);
            } finally {
                if (requestId === suggestionRequestIdRef.current) {
                    setSuggestionsLoading(false);
                }
            }
        }, 250);

        return () => window.clearTimeout(timeout);
    }, [searchQuery]);

    useEffect(() => {
        const handlePointerDown = (event: MouseEvent) => {
            if (
                searchWrapRef.current &&
                !searchWrapRef.current.contains(event.target as Node)
            ) {
                setSuggestionsOpen(false);
            }
        };

        document.addEventListener('mousedown', handlePointerDown);

        return () =>
            document.removeEventListener('mousedown', handlePointerDown);
    }, []);

    const allDropdowns = useMemo(
        () => [...midDropdowns, ...postSpkDropdowns, ...trailingDropdowns],
        [midDropdowns, postSpkDropdowns, trailingDropdowns],
    );

    const [openDropdownId, setOpenDropdownId] = useState<string | null>(null);
    const [isProfileMenuOpen, setIsProfileMenuOpen] = useState(false);
    const [profileMenuOpener, setProfileMenuOpener] = useState<
        HTMLElement | undefined
    >();
    const [isFullscreen, setIsFullscreen] = useState(false);
    const pendingProfileActionRef = useRef<string | null>(null);
    const ignoreProfileClickRef = useRef(false);

    const spkNavItem = moduleNavItems.find((item) => item.text === 'SPK');
    const trailingModuleNavItems = moduleNavItems.filter(
        (item) => item.text !== 'SPK',
    );

    useEffect(() => {
        const syncFullscreen = () => {
            setIsFullscreen(Boolean(document.fullscreenElement));
        };

        document.addEventListener('fullscreenchange', syncFullscreen);

        return () =>
            document.removeEventListener('fullscreenchange', syncFullscreen);
    }, []);

    const toggleFullscreen = async () => {
        try {
            if (!document.fullscreenElement) {
                await document.documentElement.requestFullscreen();
            } else {
                await document.exitFullscreen();
            }
        } catch {
            // Browser may block fullscreen without gesture / permission
        }
    };

    const { greeting, formattedDate } = useMemo(() => {
        const now = new Date();

        return {
            greeting: getGreeting(now.getHours()),
            formattedDate: formatHeaderDate(now),
        };
    }, []);

    const selectMenu = (text: string) => {
        if (!isActiveMenuControlled) {
            setUncontrolledActiveMenu(text);
        }

        onActiveMenuChange?.(text);

        const dropdownItems = allDropdowns.flatMap(
            (dropdown) => dropdown.items,
        );

        const href =
            findNavHref(text, primaryNavItems) ??
            findNavHref(text, moduleNavItems) ??
            findNavHref(text, dropdownItems);

        if (href) {
            router.visit(href);
        }
    };

    const updateSearchQuery = (query: string) => {
        if (!isSearchControlled) {
            setUncontrolledSearchQuery(query);
        }

        onSearchChange?.(query);
    };

    const visitSpkIndexSearch = (query: string) => {
        setSuggestionsOpen(false);

        router.get(
            spkIndex.url({
                query: {
                    search: query !== '' ? query : undefined,
                },
            }),
            {},
            {
                preserveState: false,
            },
        );
    };

    const visitSpkSuggestion = (suggestion: SpkSearchSuggestion) => {
        setSuggestionsOpen(false);
        updateSearchQuery(suggestion.spkNo);
        router.visit(spkShow.url(suggestion.spkNo));
    };

    const handleSearchSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (onSearchSubmit) {
            onSearchSubmit(searchQuery);

            return;
        }

        if (
            highlightedIndex >= 0 &&
            suggestions[highlightedIndex] !== undefined
        ) {
            visitSpkSuggestion(suggestions[highlightedIndex]);

            return;
        }

        visitSpkIndexSearch(searchQuery.trim());
    };

    const handleSearchKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (!suggestionsOpen || suggestions.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setHighlightedIndex((current) =>
                current < suggestions.length - 1 ? current + 1 : 0,
            );

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlightedIndex((current) =>
                current <= 0 ? suggestions.length - 1 : current - 1,
            );

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setSuggestionsOpen(false);
        }
    };

    const runProfileMenuAction = (text: string) => {
        if (text === 'Profile') {
            if (!PROFILE_SETTINGS_LINKS_ENABLED) {
                return;
            }

            router.visit(editProfile.url());

            return;
        }

        if (text === 'Logout') {
            router.flushAll();
            router.visit(logout.url(), {
                method: 'post',
                preserveState: false,
            });
        }
    };

    const isDropdownActive = (dropdown: ShellNavDropdown): boolean =>
        dropdown.items.some((item) => item.text === activeMenu);

    const shellTitle = pageTitle ?? activeMenu;

    const allMenuItems = [
        ...primaryNavItems,
        ...midDropdowns.flatMap((dropdown) => dropdown.items),
        ...(spkNavItem ? [spkNavItem] : []),
        ...postSpkDropdowns.flatMap((dropdown) => dropdown.items),
        ...trailingModuleNavItems,
        ...trailingDropdowns.flatMap((dropdown) => dropdown.items),
    ];

    const renderDropdownButton = (dropdown: ShellNavDropdown) => (
        <button
            key={dropdown.id}
            id={dropdownButtonId(dropdown.id)}
            type="button"
            className={cn(
                'appNavItem',
                'appNavItemDropdown',
                isDropdownActive(dropdown) && 'is-active',
            )}
            onClick={() => setOpenDropdownId(dropdown.id)}
        >
            {dropdown.text}
            <span className="appNavChevron" aria-hidden="true">
                ▾
            </span>
        </button>
    );

    const renderNavLink = (item: ShellNavItem) => (
        <button
            key={item.text}
            type="button"
            className={cn(
                'appNavItem',
                activeMenu === item.text && 'is-active',
            )}
            onClick={() => selectMenu(item.text)}
        >
            {item.text}
        </button>
    );

    return (
        <>
            <div className="shellHeaderWrap">
                <ShellBar
                    accessibilityAttributes={{
                        profile: {
                            hasPopup: 'menu',
                            expanded: isProfileMenuOpen ? 'true' : 'false',
                            name: 'Profile',
                        },
                    }}
                    showNotifications={showNotifications}
                    notificationsCount={
                        showNotifications ? notificationsCount : undefined
                    }
                    logo={
                        <div className="shellBrand" slot="logo">
                            <div className="shellBrandMark">
                                <img
                                    className="shellLogo"
                                    src={logoSrc}
                                    alt={logoAlt}
                                />
                                <span className="shellBrandText">
                                    {brandText}
                                </span>
                            </div>
                            <span
                                className="shellBrandDivider"
                                aria-hidden="true"
                            />
                            <span className="shellPageTitle">{shellTitle}</span>
                        </div>
                    }
                    content={
                        <>
                            {showSearch ? (
                                <div
                                    className="shellSearchWrap"
                                    ref={searchWrapRef}
                                >
                                    <form
                                        className="shellSearchForm"
                                        role="search"
                                        onSubmit={handleSearchSubmit}
                                    >
                                        <Icon
                                            className="shellSearchIcon"
                                            name={searchIcon}
                                        />
                                        <input
                                            className="shellSearchInput"
                                            type="search"
                                            placeholder={searchPlaceholder}
                                            value={searchQuery}
                                            autoComplete="off"
                                            aria-autocomplete="list"
                                            aria-expanded={suggestionsOpen}
                                            aria-controls="shell-spk-suggestions"
                                            onChange={(event) =>
                                                updateSearchQuery(
                                                    event.target.value,
                                                )
                                            }
                                            onFocus={() => {
                                                if (
                                                    searchQuery.trim().length >=
                                                        2 &&
                                                    suggestions.length > 0
                                                ) {
                                                    setSuggestionsOpen(true);
                                                }
                                            }}
                                            onKeyDown={handleSearchKeyDown}
                                            aria-label="Cari SPK"
                                        />
                                        {searchQuery ? (
                                            <button
                                                type="button"
                                                className="shellSearchClear"
                                                aria-label="Hapus pencarian"
                                                onClick={() => {
                                                    updateSearchQuery('');
                                                    setSuggestions([]);
                                                    setSuggestionsOpen(false);
                                                }}
                                            >
                                                ×
                                            </button>
                                        ) : null}
                                    </form>

                                    {suggestionsOpen &&
                                    searchQuery.trim().length >= 2 ? (
                                        <div
                                            id="shell-spk-suggestions"
                                            className="shellSearchSuggestions"
                                            role="listbox"
                                            aria-label="Saran SPK"
                                        >
                                            {suggestionsLoading ? (
                                                <div className="shellSearchSuggestionEmpty">
                                                    Mencari SPK...
                                                </div>
                                            ) : suggestions.length === 0 ? (
                                                <div className="shellSearchSuggestionEmpty">
                                                    Tidak ada SPK yang cocok.
                                                </div>
                                            ) : (
                                                suggestions.map(
                                                    (suggestion, index) => (
                                                        <button
                                                            key={
                                                                suggestion.rowId
                                                            }
                                                            type="button"
                                                            role="option"
                                                            aria-selected={
                                                                index ===
                                                                highlightedIndex
                                                            }
                                                            className={cn(
                                                                'shellSearchSuggestionItem',
                                                                index ===
                                                                    highlightedIndex &&
                                                                    'is-active',
                                                            )}
                                                            onMouseEnter={() =>
                                                                setHighlightedIndex(
                                                                    index,
                                                                )
                                                            }
                                                            onClick={() =>
                                                                visitSpkSuggestion(
                                                                    suggestion,
                                                                )
                                                            }
                                                        >
                                                            <span className="shellSearchSuggestionPrimary">
                                                                {
                                                                    suggestion.spkNo
                                                                }
                                                            </span>
                                                            <span className="shellSearchSuggestionSecondary">
                                                                {[
                                                                    suggestion.item,
                                                                    suggestion.customer,
                                                                    suggestion.lastProcess,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(
                                                                        ' · ',
                                                                    ) || '—'}
                                                            </span>
                                                        </button>
                                                    ),
                                                )
                                            )}

                                            <button
                                                type="button"
                                                className="shellSearchSuggestionFooter"
                                                onClick={() =>
                                                    visitSpkIndexSearch(
                                                        searchQuery.trim(),
                                                    )
                                                }
                                            >
                                                Lihat semua hasil pencarian
                                            </button>
                                        </div>
                                    ) : null}
                                </div>
                            ) : null}
                            <ShellBarSpacer />
                            <div className="shellUserMeta">
                                <span className="shellDate">
                                    {formattedDate}
                                </span>
                                <span className="shellGreeting">
                                    {greeting}, {userName}
                                </span>
                            </div>
                        </>
                    }
                    profile={
                        <Avatar
                            className="shellProfileAvatar"
                            icon={employeeIcon}
                            size="XS"
                            shape="Circle"
                            colorScheme="Transparent"
                        />
                    }
                    menuItems={allMenuItems.map((item) => (
                        <ListItemStandard key={item.text} data-menu={item.text}>
                            {item.text}
                        </ListItemStandard>
                    ))}
                    onMenuItemClick={(event) => {
                        const text = event.detail.item.dataset.menu;

                        if (text) {
                            selectMenu(text);
                        }
                    }}
                    onNotificationsClick={(event) => {
                        event.preventDefault();
                        onNotificationsClick?.();
                    }}
                    onProfileClick={(event) => {
                        if (ignoreProfileClickRef.current) {
                            return;
                        }

                        setProfileMenuOpener(event.detail.targetRef);
                        setIsProfileMenuOpen(true);
                    }}
                >
                    <ShellBarItem
                        icon={
                            isFullscreen ? exitFullScreenIcon : fullScreenIcon
                        }
                        text={
                            isFullscreen ? 'Keluar layar penuh' : 'Layar penuh'
                        }
                        onClick={(event) => {
                            event.preventDefault();
                            void toggleFullscreen();
                        }}
                    />
                </ShellBar>
                {PROFILE_SETTINGS_LINKS_ENABLED ? (
                    <Link
                        href={resolvedSettingsHref}
                        className="shellSettingsBtn"
                        aria-label="Pengaturan"
                        title="Pengaturan"
                    >
                        <Icon name={settingsIcon} />
                    </Link>
                ) : (
                    <button
                        type="button"
                        className="shellSettingsBtn"
                        disabled
                        aria-label="Pengaturan"
                        title="Pengaturan"
                    >
                        <Icon name={settingsIcon} />
                    </button>
                )}
            </div>
            <nav className="appNav" aria-label="Menu modul">
                {primaryNavItems.map(renderNavLink)}
                {midDropdowns.map(renderDropdownButton)}
                {spkNavItem ? renderNavLink(spkNavItem) : null}
                {postSpkDropdowns.map(renderDropdownButton)}
                {trailingModuleNavItems.map(renderNavLink)}
                {trailingDropdowns.map(renderDropdownButton)}
            </nav>
            {allDropdowns.map((dropdown) => (
                <Menu
                    key={dropdown.id}
                    open={openDropdownId === dropdown.id}
                    opener={dropdownButtonId(dropdown.id)}
                    onClose={() =>
                        setOpenDropdownId((current) =>
                            current === dropdown.id ? null : current,
                        )
                    }
                    onItemClick={(event) => {
                        selectMenu(event.detail.text);
                        setOpenDropdownId(null);
                    }}
                >
                    {dropdown.items.map((item) => (
                        <MenuItem key={item.text} text={item.text} />
                    ))}
                </Menu>
            ))}
            <Menu
                open={isProfileMenuOpen}
                opener={profileMenuOpener}
                onClose={() => {
                    setIsProfileMenuOpen(false);

                    const action = pendingProfileActionRef.current;
                    pendingProfileActionRef.current = null;

                    if (!action) {
                        return;
                    }

                    window.setTimeout(() => {
                        runProfileMenuAction(action);
                        ignoreProfileClickRef.current = false;
                    }, 0);
                }}
                onItemClick={(event) => {
                    pendingProfileActionRef.current = event.detail.text;
                    ignoreProfileClickRef.current = true;
                    setIsProfileMenuOpen(false);
                }}
            >
                <MenuItem
                    text="Profile"
                    icon={employeeIcon}
                    disabled={!PROFILE_SETTINGS_LINKS_ENABLED}
                />
                <MenuSeparator />
                <MenuItem text="Logout" icon={logIcon} />
            </Menu>
        </>
    );
}
