import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { SpkStoneAllocatedItem } from '@/components/spk/types';
import { show as diamondDossierShow } from '@/routes/inventory/diamond-dossiers';
import { index as microStonesIndex } from '@/routes/inventory/micro-stones';

const GIA_REPORT_CHECK_BASE_URL =
    'https://www.gia.edu/report-check?locale=en_US&reportno=';

function giaReportCheckUrl(certificate: string): string {
    return `${GIA_REPORT_CHECK_BASE_URL}${encodeURIComponent(certificate)}`;
}

function resolveStoneLabelText(
    item: Pick<SpkStoneAllocatedItem, 'label' | 'code' | 'displayId'>,
): string {
    const label = item.label?.trim() ?? '';

    if (label !== '') {
        return label;
    }

    const code = item.code?.trim() ?? '';

    if (code !== '') {
        return code;
    }

    const displayId = item.displayId?.trim() ?? '';

    if (displayId !== '') {
        return displayId;
    }

    return '-';
}

function resolveSourceKey(source: string | null | undefined): string {
    return (source ?? '').trim().toLowerCase();
}

function microStoneInventoryUrl(msStoneId: number): string {
    return microStonesIndex.url({
        query: {
            stone_id: String(msStoneId),
        },
    });
}

export function InventoryCertificateLink({
    certificate,
}: {
    certificate: string | null | undefined;
}): ReactNode {
    return <SpkAllocatedCertificateLink certificate={certificate} />;
}

export function InventoryDossierCodeLink({
    diamondId,
    code,
}: {
    diamondId: number | null | undefined;
    code: string | null | undefined;
}): ReactNode {
    const text = code?.trim() ?? '';

    if (text === '') {
        return '-';
    }

    if (diamondId !== null && diamondId !== undefined && diamondId > 0) {
        return (
            <Link
                href={diamondDossierShow.url(diamondId)}
                className="spkProduksiLink tabular-nums"
            >
                {text}
            </Link>
        );
    }

    return text;
}

export function SpkAllocatedCertificateLink({
    certificate,
}: {
    certificate: string | null | undefined;
}): ReactNode {
    const trimmed = certificate?.trim() ?? '';

    if (trimmed === '') {
        return '-';
    }

    return (
        <a
            href={giaReportCheckUrl(trimmed)}
            target="_blank"
            rel="noopener noreferrer"
            className="spkProduksiLink"
            title="Cek sertifikat di GIA Report Check"
        >
            {trimmed}
        </a>
    );
}

export function SpkAllocatedStoneLabelLink({
    item,
}: {
    item: Pick<
        SpkStoneAllocatedItem,
        'source' | 'label' | 'code' | 'displayId' | 'diamondId' | 'msStoneId'
    >;
}): ReactNode {
    const text = resolveStoneLabelText(item);

    if (text === '—' || text === '-') {
        return '-';
    }

    const source = resolveSourceKey(item.source);

    if (source === 'dossier' && item.diamondId !== null && item.diamondId > 0) {
        return (
            <Link
                href={diamondDossierShow.url(item.diamondId)}
                className="spkProduksiLink"
            >
                {text}
            </Link>
        );
    }

    if (
        source === 'micro' &&
        item.msStoneId !== null &&
        item.msStoneId !== undefined &&
        item.msStoneId > 0
    ) {
        return (
            <Link
                href={microStoneInventoryUrl(item.msStoneId)}
                className="spkProduksiLink"
            >
                {text}
            </Link>
        );
    }

    return text;
}

export type SpkDiamondDetailStoneRow = {
    source: string;
    sourceLabel: string;
    certificate: string | null;
    shape: string;
    label: string;
    diamondId: number | null;
    msStoneId: number | null;
    crtPerPcs: string | null;
    reservedPcs: string;
    reservedCrt: string | null;
};

export function SpkDiamondDetailCertificateLink({
    certificate,
}: {
    certificate: string | null;
}): ReactNode {
    return <SpkAllocatedCertificateLink certificate={certificate} />;
}

export function SpkDiamondDetailStoneLabelLink({
    row,
}: {
    row: SpkDiamondDetailStoneRow;
}): ReactNode {
    const text = row.label.trim() !== '' ? row.label.trim() : '—';

    if (text === '—' || text === '-') {
        return '-';
    }

    if (
        resolveSourceKey(row.source) === 'dossier' &&
        row.diamondId !== null &&
        row.diamondId > 0
    ) {
        return (
            <Link
                href={diamondDossierShow.url(row.diamondId)}
                className="spkProduksiLink"
            >
                {text}
            </Link>
        );
    }

    if (
        resolveSourceKey(row.source) === 'micro' &&
        row.msStoneId !== null &&
        row.msStoneId > 0
    ) {
        return (
            <Link
                href={microStoneInventoryUrl(row.msStoneId)}
                className="spkProduksiLink"
            >
                {text}
            </Link>
        );
    }

    return text;
}
