import { home } from '@/routes';
import {
    craftsmanPerformance,
    materialYield,
    shopFloor,
    skuOutput,
    workOrder,
} from '@/routes/analytics';
import { index as coranIndex } from '@/routes/coran';
import { index as finishingIndex } from '@/routes/finishing';
import { index as polesRangkaIndex } from '@/routes/poles-rangka';
import { index as pasangBatuIndex } from '@/routes/pasang-batu';
import { index as polesChromeIndex } from '@/routes/poles-chrome';
import { index as goldMaterialTransactionsIndex } from '@/routes/inventory/gold-material-transactions';
import { index as stoneTransactionsIndex } from '@/routes/inventory/stone-transactions';
import { index as jewelCadIndex } from '@/routes/jewelcad';
import { index as masterSkuIndex } from '@/routes/master-data/master-sku';
import { index as tipeItemIndex } from '@/routes/master-data/tipe-item';
import { index as varianItemIndex } from '@/routes/master-data/varian-item';
import { edit as spkProcessSlaEdit } from '@/routes/master-data/spk-process-sla';
import { index as resinIndex } from '@/routes/resin';
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
];

export const defaultAnalyticsSubmenus: ShellNavItem[] = [
    { text: 'Work Order', href: workOrder.url() },
    { text: 'Dashboard Kanban', href: home.url() },
    { text: 'Shop Floor', href: shopFloor.url() },
    { text: 'Material & Yield', href: materialYield.url() },
    { text: 'Performance Pengrajin', href: craftsmanPerformance.url() },
    { text: 'Output SKU', href: skuOutput.url() },
];

export const defaultProduksiSubmenus: ShellNavItem[] = [
    { text: 'JewelCAD', href: jewelCadIndex.url() },
    { text: 'Resin', href: resinIndex.url() },
    { text: 'Coran', href: coranIndex.url() },
    { text: 'Finishing', href: finishingIndex.url() },
    { text: 'Poles Rangka', href: polesRangkaIndex.url() },
    { text: 'Pasang Batu', href: pasangBatuIndex.url() },
    { text: 'Poles Chrome', href: polesChromeIndex.url() },
];

export const defaultPengerjaanLanjutanSubmenus: ShellNavItem[] = [
    { text: 'Reparasi' },
    { text: 'Penambahan Chain' },
    { text: 'Modifikasi Barang Jadi' },
];

export const defaultInventorySubmenus: ShellNavItem[] = [
    { text: 'Batu' },
    { text: 'Bahan Emas' },
    {
        text: 'Transaksi Bahan Emas',
        href: goldMaterialTransactionsIndex.url(),
    },
    {
        text: 'Transaksi Batu',
        href: stoneTransactionsIndex.url(),
    },
];

export const defaultMasterDataSubmenus: ShellNavItem[] = [
    { text: 'Tipe Item', href: tipeItemIndex.url() },
    { text: 'Master Item Product', href: varianItemIndex.url() },
    { text: 'Master SKU', href: masterSkuIndex.url() },
    { text: 'SLA Proses SPK', href: spkProcessSlaEdit.url() },
];

/** Dropdown menus rendered after primary items, before module links. */
export const defaultMidDropdowns: ShellNavDropdown[] = [
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
    {
        id: 'master-data',
        text: 'Master Data',
        items: defaultMasterDataSubmenus,
    },
];

/** @deprecated Prefer processes from SPK show props / config/spk_processes.php */
export const spkProcessTabs = [
    ...defaultProduksiSubmenus.map((item) => item.text),
    'Pengerjaan Lanjutan',
    'Modifikasi Barang Jadi',
];
