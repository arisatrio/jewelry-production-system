import { home } from '@/routes';
import { index as jewelCadIndex } from '@/routes/jewelcad';
import { index as tipeItemIndex } from '@/routes/master-data/tipe-item';
import { index as varianItemIndex } from '@/routes/master-data/varian-item';
import { index as spkIndex } from '@/routes/spk';

export type ShellNavItem = {
    text: string;
    href?: string;
};

export type ShellNavDropdown = {
    id: string;
    text: string;
    items: ShellNavItem[];
};

export const defaultPrimaryNavItems: ShellNavItem[] = [
    { text: 'Dashboard', href: home.url() },
];

export const defaultModuleNavItems: ShellNavItem[] = [
    { text: 'SPK', href: spkIndex.url() },
    { text: 'Modifikasi Barang Jadi' },
];

export const defaultLaporanSubmenus: ShellNavItem[] = [
    { text: 'Laporan Produksi' },
    { text: 'Laporan SPK' },
    { text: 'Laporan Susut' },
    { text: 'Laporan KPI' },
];

export const defaultAnalyticsSubmenus: ShellNavItem[] = [
    { text: 'Analytics Produktivitas' },
    { text: 'Analytics Cost & Susut' },
    { text: 'Analytics Bottleneck' },
    { text: 'Analytics Lead Time' },
];

export const defaultProduksiSubmenus: ShellNavItem[] = [
    { text: 'JewelCAD', href: jewelCadIndex.url() },
    { text: 'Resin' },
    { text: 'Coran' },
    { text: 'Finishing' },
    { text: 'Poles Rangka' },
    { text: 'Pasang Batu' },
    { text: 'Poles Chrome' },
];

export const defaultPengerjaanLanjutanSubmenus: ShellNavItem[] = [
    { text: 'Reparasi' },
    { text: 'Penambahan Chain' },
];

export const defaultInventorySubmenus: ShellNavItem[] = [
    { text: 'Batu' },
    { text: 'Bahan Emas' },
];

export const defaultMasterDataSubmenus: ShellNavItem[] = [
    { text: 'Tipe Item', href: tipeItemIndex.url() },
    { text: 'Master Item Product', href: varianItemIndex.url() },
];

/** Dropdown menus rendered after primary items, before module links. */
export const defaultMidDropdowns: ShellNavDropdown[] = [
    { id: 'laporan', text: 'Laporan', items: defaultLaporanSubmenus },
    { id: 'analytics', text: 'Analytics', items: defaultAnalyticsSubmenus },
];

/** Dropdown menus rendered after SPK, before trailing module links. */
export const defaultPostSpkDropdowns: ShellNavDropdown[] = [
    { id: 'produksi', text: 'Produksi', items: defaultProduksiSubmenus },
    {
        id: 'pengerjaan-lanjutan',
        text: 'Pengerjaan Lanjutan',
        items: defaultPengerjaanLanjutanSubmenus,
    },
];

/** Dropdown menus rendered after trailing module links. */
export const defaultTrailingDropdowns: ShellNavDropdown[] = [
    { id: 'inventory', text: 'Inventory', items: defaultInventorySubmenus },
    { id: 'master-data', text: 'Master Data', items: defaultMasterDataSubmenus },
];

/** @deprecated Prefer processes from SPK show props / config/spk_processes.php */
export const spkProcessTabs = [
    ...defaultProduksiSubmenus.map((item) => item.text),
    'Pengerjaan Lanjutan',
    ...defaultModuleNavItems
        .filter((item) => item.text !== 'SPK')
        .map((item) => item.text),
];
