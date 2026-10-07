import { home } from '@/routes';
import {
    craftsmanPerformance,
    materialYield,
    shopFloor,
    skuOutput,
    workOrder,
} from '@/routes/analytics';
import { index as coranIndex } from '@/routes/coran';
import { index as finishingIndex, report as finishingReport } from '@/routes/finishing';
import { index as diamondDossiersIndex } from '@/routes/inventory/diamond-dossiers';
import { index as goldMaterialTransactionsIndex } from '@/routes/inventory/gold-material-transactions';
import { index as goldMaterialsIndex } from '@/routes/inventory/gold-materials';
import { index as microStonesIndex } from '@/routes/inventory/micro-stones';
import { index as stoneTransactionsIndex } from '@/routes/inventory/stone-transactions';
import { index as jewelCadIndex } from '@/routes/jewelcad';
import { edit as diamondCrtMatrixEdit } from '@/routes/master-data/diamond-crt-matrix';
import { edit as finishingShrinkAllowanceEdit } from '@/routes/master-data/finishing-shrink-allowance';
import { index as masterSkuIndex } from '@/routes/master-data/master-sku';
import { edit as spkProcessSlaEdit } from '@/routes/master-data/spk-process-sla';
import { index as tipeItemIndex } from '@/routes/master-data/tipe-item';
import { index as varianItemIndex } from '@/routes/master-data/varian-item';
import { index as pasangBatuIndex } from '@/routes/pasang-batu';
import {
    index as polesChromeIndex,
    report as polesChromeReport,
} from '@/routes/poles-chrome';
import {
    index as polesRangkaIndex,
    report as polesRangkaReport,
} from '@/routes/poles-rangka';
import { index as resinIndex } from '@/routes/resin';
import { index as reparasiIndex } from '@/routes/reparasi';
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
    { text: 'SPK Reparasi', href: reparasiIndex.url() },
    { text: 'JewelCAD', href: jewelCadIndex.url() },
    { text: 'Resin', href: resinIndex.url() },
    { text: 'Coran', href: coranIndex.url() },
    { text: 'Finishing', href: finishingIndex.url() },
    { text: 'Poles Rangka', href: polesRangkaIndex.url() },
    { text: 'Pasang Batu', href: pasangBatuIndex.url() },
    { text: 'Poles Chrome', href: polesChromeIndex.url() },
];

export const defaultAnalyticsSubmenus: ShellNavItem[] = [
    { text: 'Work Order', href: workOrder.url() },
    { text: 'Dashboard Kanban', href: home.url() },
    { text: 'Shop Floor', href: shopFloor.url() },
    { text: 'Material & Yield', href: materialYield.url() },
    { text: 'Performance Pengrajin', href: craftsmanPerformance.url() },
    { text: 'Output SKU', href: skuOutput.url() },
    { text: 'Laporan Finishing', href: finishingReport.url() },
    { text: 'Laporan Poles Rangka', href: polesRangkaReport.url() },
    { text: 'Laporan Poles Chrome', href: polesChromeReport.url() },
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
    { text: 'Penambahan Chain' },
    { text: 'Modifikasi Barang Jadi' },
];

export const defaultInventorySubmenus: ShellNavItem[] = [
    { text: 'Batu Dossier', href: diamondDossiersIndex.url() },
    { text: 'Batu Mikro', href: microStonesIndex.url() },
    { text: 'Bahan Emas', href: goldMaterialsIndex.url() },
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
    { text: 'Jatah Susut Finishing', href: finishingShrinkAllowanceEdit.url() },
    { text: 'Matrix CRT Dossier', href: diamondCrtMatrixEdit.url() },
];

/** Dropdown menus rendered after primary items, before module links. */
export const defaultMidDropdowns: ShellNavDropdown[] = [
    { id: 'analytics', text: 'Analytics', items: defaultAnalyticsSubmenus },
];

/** Dropdown menus rendered after SPK, before trailing module links. */
export const defaultPostSpkDropdowns: ShellNavDropdown[] = [];

/** Dropdown menus rendered after trailing module links. */
export const defaultTrailingDropdowns: ShellNavDropdown[] = [
    {
        id: 'pengerjaan-lanjutan',
        text: 'Pengerjaan Lanjutan',
        items: defaultPengerjaanLanjutanSubmenus,
    },
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
