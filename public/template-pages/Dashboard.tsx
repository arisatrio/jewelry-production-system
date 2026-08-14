'use client';

import { useEffect, useMemo, useState } from 'react';
import employeeIcon from '@ui5/webcomponents-icons/dist/employee.js';
import exitFullScreenIcon from '@ui5/webcomponents-icons/dist/exit-full-screen.js';
import fullScreenIcon from '@ui5/webcomponents-icons/dist/full-screen.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import settingsIcon from '@ui5/webcomponents-icons/dist/action-settings.js';
import { Avatar } from '@ui5/webcomponents-react/Avatar';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { ListItemStandard } from '@ui5/webcomponents-react/ListItemStandard';
import { Menu } from '@ui5/webcomponents-react/Menu';
import { MenuItem } from '@ui5/webcomponents-react/MenuItem';
import { ShellBar } from '@ui5/webcomponents-react/ShellBar';
import { ShellBarItem } from '@ui5/webcomponents-react/ShellBarItem';
import { ShellBarSpacer } from '@ui5/webcomponents-react/ShellBarSpacer';

const primaryNavItems = [{ text: 'Dashboard' }] as const;

const moduleNavItems = [
  { text: 'SPK' },
  { text: 'JewelCAD' },
  { text: 'Resin' },
  { text: 'Coran' },
  { text: 'Finishing' },
  { text: 'Poles Rangka' },
  { text: 'Pasang Batu' },
  { text: 'Poles Chrome' },
  { text: 'Pengerjaan Lanjutan' },
  { text: 'Modifikasi Barang Jadi' },
] as const;

const laporanSubmenus = [
  'Laporan Produksi',
  'Laporan SPK',
  'Laporan Susut',
  'Laporan KPI',
] as const;

const analyticsSubmenus = [
  'Analytics Produktivitas',
  'Analytics Cost & Susut',
  'Analytics Bottleneck',
  'Analytics Lead Time',
] as const;

const USER_NAME = 'Admin';

function getGreeting(hour: number): string {
  if (hour < 11) return 'Selamat pagi';
  if (hour < 15) return 'Selamat siang';
  if (hour < 18) return 'Selamat sore';
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

export default function Dashboard() {
  const [activeMenu, setActiveMenu] = useState('Dashboard');
  const [laporanMenuOpen, setLaporanMenuOpen] = useState(false);
  const [analyticsMenuOpen, setAnalyticsMenuOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [isFullscreen, setIsFullscreen] = useState(false);

  useEffect(() => {
    const syncFullscreen = () => {
      setIsFullscreen(Boolean(document.fullscreenElement));
    };
    document.addEventListener('fullscreenchange', syncFullscreen);
    return () => document.removeEventListener('fullscreenchange', syncFullscreen);
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

  const isLaporanActive = laporanSubmenus.includes(
    activeMenu as (typeof laporanSubmenus)[number],
  );
  const isAnalyticsActive = analyticsSubmenus.includes(
    activeMenu as (typeof analyticsSubmenus)[number],
  );

  return (
    <>
      <div className="shellHeaderWrap">
        <ShellBar
          showNotifications
          notificationsCount="3"
          logo={
            <div className="shellBrand" slot="logo">
              <div className="shellBrandMark">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img className="shellLogo" src="/images/logo.jpg" alt="Wanda" />
                <span className="shellBrandText">Production System</span>
              </div>
              <span className="shellBrandDivider" aria-hidden="true" />
              <span className="shellPageTitle">{activeMenu}</span>
            </div>
          }
          content={
            <>
              <form
                className="shellSearchForm"
                role="search"
                onSubmit={(event) => {
                  event.preventDefault();
                }}
              >
                <Icon className="shellSearchIcon" name={searchIcon} />
                <input
                  className="shellSearchInput"
                  type="search"
                  placeholder="Cari SPK, produk, modul..."
                  value={searchQuery}
                  onChange={(event) => setSearchQuery(event.target.value)}
                  aria-label="Pencarian"
                />
                {searchQuery ? (
                  <button
                    type="button"
                    className="shellSearchClear"
                    aria-label="Hapus pencarian"
                    onClick={() => setSearchQuery('')}
                  >
                    ×
                  </button>
                ) : null}
              </form>
              <ShellBarSpacer />
              <div className="shellUserMeta">
                <span className="shellDate">{formattedDate}</span>
                <span className="shellGreeting">
                  {greeting}, {USER_NAME}
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
          menuItems={[
            ...primaryNavItems.map((item) => (
              <ListItemStandard key={item.text} data-menu={item.text}>
                {item.text}
              </ListItemStandard>
            )),
            ...laporanSubmenus.map((item) => (
              <ListItemStandard key={item} data-menu={item}>
                {item}
              </ListItemStandard>
            )),
            ...analyticsSubmenus.map((item) => (
              <ListItemStandard key={item} data-menu={item}>
                {item}
              </ListItemStandard>
            )),
            ...moduleNavItems.map((item) => (
              <ListItemStandard key={item.text} data-menu={item.text}>
                {item.text}
              </ListItemStandard>
            )),
          ]}
          onMenuItemClick={(event) => {
            const text = event.detail.item.dataset.menu;
            if (text) {
              setActiveMenu(text);
            }
          }}
          onNotificationsClick={(event) => {
            event.preventDefault();
          }}
        >
          <ShellBarItem
            icon={isFullscreen ? exitFullScreenIcon : fullScreenIcon}
            text={isFullscreen ? 'Keluar layar penuh' : 'Layar penuh'}
            onClick={(event) => {
              event.preventDefault();
              void toggleFullscreen();
            }}
          />
        </ShellBar>
        <button
          type="button"
          className="shellSettingsBtn"
          aria-label="Pengaturan"
          title="Pengaturan"
        >
          <Icon name={settingsIcon} />
        </button>
      </div>
      <nav className="appNav" aria-label="Menu modul">
        {primaryNavItems.map((item) => (
          <button
            key={item.text}
            type="button"
            className={`appNavItem${activeMenu === item.text ? ' is-active' : ''}`}
            onClick={() => setActiveMenu(item.text)}
          >
            {item.text}
          </button>
        ))}
        <button
          id="laporan-menu-btn"
          type="button"
          className={`appNavItem appNavItemDropdown${isLaporanActive ? ' is-active' : ''}`}
          onClick={() => {
            setAnalyticsMenuOpen(false);
            setLaporanMenuOpen(true);
          }}
        >
          Laporan
          <span className="appNavChevron" aria-hidden="true">
            ▾
          </span>
        </button>
        <button
          id="analytics-menu-btn"
          type="button"
          className={`appNavItem appNavItemDropdown${isAnalyticsActive ? ' is-active' : ''}`}
          onClick={() => {
            setLaporanMenuOpen(false);
            setAnalyticsMenuOpen(true);
          }}
        >
          Analytics
          <span className="appNavChevron" aria-hidden="true">
            ▾
          </span>
        </button>
        {moduleNavItems.map((item) => (
          <button
            key={item.text}
            type="button"
            className={`appNavItem${activeMenu === item.text ? ' is-active' : ''}`}
            onClick={() => setActiveMenu(item.text)}
          >
            {item.text}
          </button>
        ))}
      </nav>
      <Menu
        open={laporanMenuOpen}
        opener="laporan-menu-btn"
        onClose={() => setLaporanMenuOpen(false)}
        onItemClick={(event) => {
          setActiveMenu(event.detail.text);
          setLaporanMenuOpen(false);
        }}
      >
        {laporanSubmenus.map((item) => (
          <MenuItem key={item} text={item} />
        ))}
      </Menu>
      <Menu
        open={analyticsMenuOpen}
        opener="analytics-menu-btn"
        onClose={() => setAnalyticsMenuOpen(false)}
        onItemClick={(event) => {
          setActiveMenu(event.detail.text);
          setAnalyticsMenuOpen(false);
        }}
      >
        {analyticsSubmenus.map((item) => (
          <MenuItem key={item} text={item} />
        ))}
      </Menu>
      <div className="dashboardContent" />
    </>
  );
}
