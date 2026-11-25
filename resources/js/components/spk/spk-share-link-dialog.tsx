import shareIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import { useEffect, useState } from 'react';
import { Icon } from '@ui5/webcomponents-react/Icon';
import {
    spkProsesTerakhirLabel,
    spkProsesTerakhirLabelIsEmpty,
} from '@/components/spk/spk-proses-terakhir-display';
import type { SpkDetail, SpkItemDetail } from '@/components/spk/types';
import { useClipboard } from '@/hooks/use-clipboard';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@ui5/webcomponents-react/Button';

type SpkShareLinkDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    shareUrl: string;
    production: SpkDetail;
    item: SpkItemDetail;
};

const SHARE_MESSAGE_MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
] as const;

export function formatSpkShareGeneratedAt(date: Date): string {
    const day = String(date.getDate()).padStart(2, '0');
    const month = SHARE_MESSAGE_MONTHS[date.getMonth()] ?? '';
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${day}-${month}-${year} ${hours}:${minutes}`;
}

function formatShareSkuLine(item: SpkItemDetail): string {
    const sku = (item.skuCode ?? '').trim();
    const description = (item.description ?? '').trim();
    const parts: string[] = [];

    if (sku !== '' && sku !== '-') {
        parts.push(sku);
    }

    if (description !== '' && description !== '-') {
        parts.push(description);
    }

    if (parts.length > 0) {
        return parts.join(' ');
    }

    const typeCode = (item.typeCode ?? '').trim();
    const productItemName = (item.productItemName ?? '').trim();
    const typeParts = [typeCode, productItemName].filter(
        (part) => part !== '' && part !== '-',
    );

    if (typeParts.length > 0) {
        return typeParts.join(' | ');
    }

    const name = (item.name ?? '').trim();

    if (name !== '' && name !== '-') {
        return name;
    }

    return '—';
}

function formatShareLastProcess(production: SpkDetail): string {
    const label = spkProsesTerakhirLabel(production);

    if (spkProsesTerakhirLabelIsEmpty(label)) {
        return 'Belum diproses';
    }

    const date = (production.prosesTerakhirDate ?? '').trim();

    if (date !== '' && date !== '-') {
        return `${label} pada ${date}`;
    }

    return label;
}

function formatShareStatus(production: SpkDetail): string {
    const workflowLabel = production.workflowStatus?.label?.trim() ?? '';

    if (workflowLabel !== '') {
        if (production.workflowStatus?.isOverdue === true) {
            return `${workflowLabel} (Overdue)`;
        }

        return workflowLabel;
    }

    const status = production.status?.trim() ?? '';

    if (status !== '' && status !== '-') {
        return status;
    }

    return '—';
}

export function buildSpkPublicShareChatMessage(
    spkNo: string,
    skuLine: string,
    statusLine: string,
    lastProcess: string,
    lastUpdatedAt: string,
    shareUrl: string,
): string {
    return [
        `No SPK : ${spkNo}`,
        `SKU : ${skuLine}`,
        `Status : ${statusLine}`,
        `Proses Terakhir : ${lastProcess}`,
        `Terakhir diperbarui : ${lastUpdatedAt}`,
        '',
        `klik ${shareUrl} untuk melihat informasi selengkapnya`,
    ].join('\n');
}

export function SpkShareLinkDialog({
    open,
    onOpenChange,
    shareUrl,
    production,
    item,
}: SpkShareLinkDialogProps) {
    const spkNo = production.produksiNo.trim();
    const [generatedAt, setGeneratedAt] = useState(() => new Date());

    useEffect(() => {
        if (open) {
            setGeneratedAt(new Date());
        }
    }, [open]);

    const lastUpdatedAt = formatSpkShareGeneratedAt(generatedAt);
    const shareMessage = buildSpkPublicShareChatMessage(
        spkNo !== '' ? spkNo : '—',
        formatShareSkuLine(item),
        formatShareStatus(production),
        formatShareLastProcess(production),
        lastUpdatedAt,
        shareUrl,
    );
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === shareMessage;

    const handleCopy = async (): Promise<void> => {
        await copy(shareMessage);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkShareLinkDialog">
                <DialogHeader>
                    <DialogTitle className="spkShareLinkDialogTitle">
                        <Icon name={shareIcon} mode="Decorative" />
                        Bagikan informasi produksi
                    </DialogTitle>
                    <DialogDescription>
                        Salin pesan berikut untuk dibagikan lewat chat.
                    </DialogDescription>
                </DialogHeader>
                <div className="spkShareLinkField">
                    <textarea
                        className="spkShareLinkMessage"
                        value={shareMessage}
                        readOnly
                        rows={9}
                        aria-label="Pesan bagikan SPK"
                        onFocus={(event) => event.currentTarget.select()}
                    />
                </div>
                <DialogFooter className="spkShareLinkDialogFooter">
                    <Button
                        design="Transparent"
                        onClick={() => onOpenChange(false)}
                    >
                        Tutup
                    </Button>
                    <Button design="Emphasized" onClick={() => void handleCopy()}>
                        {copied ? 'Tersalin' : 'Salin pesan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}