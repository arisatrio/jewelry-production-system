import { Link } from '@inertiajs/react';
import { Button } from '@ui5/webcomponents-react/Button';
import { Text } from '@ui5/webcomponents-react/Text';
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import type { SpkStoneAllocatedItem, SpkStoneItem } from '@/components/spk/types';
import { formatDecimal3Id } from '@/components/spk/spk-stone-decimal-format';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as diamondDossierShow } from '@/routes/inventory/diamond-dossiers';

export type SpkDiamondCreateContext = {
    rowId: number;
    produksiNo: string;
};

export type SpkDiamondCreateLine = SpkStoneAllocatedItem & {
    spkStoneLineId: string;
    positionName?: string;
};

function todayInputValue(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

export function spkStoneLineFullyAllocated(stone: SpkStoneItem): boolean {
    const required = Number(stone.pcs) || 0;
    const allocated = stone.allocation?.allocatedPcs ?? 0;

    if (required <= 0) {
        return (stone.allocatedItems?.length ?? 0) > 0;
    }

    return allocated >= required;
}

export function allSpkStoneLinesFullyAllocated(stones: SpkStoneItem[]): boolean {
    if (stones.length === 0) {
        return false;
    }

    return stones.every(spkStoneLineFullyAllocated);
}

export function allocatedDiamondLinesForCreate(
    stones: SpkStoneItem[],
): SpkDiamondCreateLine[] {
    return stones.flatMap((stone) =>
        (stone.allocatedItems ?? []).map((item) => ({
            ...item,
            spkStoneLineId: stone.id,
            positionName: stone.positionName,
        })),
    );
}

function emptyDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const text = String(value).trim();

    return text !== '' ? text : '-';
}

function sourceLabel(source: string): string {
    return source === 'dossier' ? 'Dossier' : source === 'micro' ? 'Mikro' : source;
}

function allocatedItemIdCell(item: SpkDiamondCreateLine): ReactNode {
    const idText = emptyDash(item.displayId ?? item.code);

    if (
        item.source === 'dossier' &&
        item.diamondId !== null &&
        item.displayId !== null &&
        item.displayId.trim() !== ''
    ) {
        return (
            <Link
                href={diamondDossierShow.url(item.diamondId)}
                className="spkFormLink tabular-nums"
            >
                {item.displayId}
            </Link>
        );
    }

    return idText;
}

type SpkDiamondCreateDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    spk: SpkDiamondCreateContext;
    stones: SpkStoneItem[];
};

export function SpkDiamondCreateDialog({
    open,
    onOpenChange,
    spk,
    stones,
}: SpkDiamondCreateDialogProps) {
    const [docNo, setDocNo] = useState('');
    const [tanggal, setTanggal] = useState(todayInputValue());
    const [docNoError, setDocNoError] = useState<string | null>(null);
    const [tanggalError, setTanggalError] = useState<string | null>(null);

    const lines = useMemo(
        () => allocatedDiamondLinesForCreate(stones),
        [stones],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        setDocNo('');
        setTanggal(todayInputValue());
        setDocNoError(null);
        setTanggalError(null);
    }, [open]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const trimmedDocNo = docNo.trim();
        let hasError = false;

        if (trimmedDocNo === '') {
            setDocNoError('No SPK Diamond wajib diisi.');
            hasError = true;
        } else {
            setDocNoError(null);
        }

        if (tanggal.trim() === '') {
            setTanggalError('Tanggal wajib diisi.');
            hasError = true;
        } else {
            setTanggalError(null);
        }

        if (hasError || lines.length === 0) {
            return;
        }

        toast.info(
            'Penyimpanan dokumen SPK Diamond akan diaktifkan pada tahap berikutnya.',
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStoneStockModal spkChainAllocateDialog spkDiamondCreateDialog">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Buat SPK Diamond</DialogTitle>
                        <DialogDescription>
                            Isi nomor dan tanggal SPK Diamond. Daftar batu
                            di bawah adalah batu yang sudah dialokasikan pada
                            SPK produksi{' '}
                            <strong>{spk.produksiNo}</strong>.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="spkDiamondCreateForm">
                        <div className="spkDiamondCreateFormRow">
                            <div className="spkDiamondCreateField">
                                <Label htmlFor="spk-diamond-doc-no">
                                    No SPK Diamond
                                </Label>
                                <Input
                                    id="spk-diamond-doc-no"
                                    name="doc_no"
                                    value={docNo}
                                    autoComplete="off"
                                    placeholder="MKDM"
                                    aria-invalid={docNoError !== null}
                                    onChange={(event) => {
                                        setDocNo(event.target.value);
                                        if (docNoError !== null) {
                                            setDocNoError(null);
                                        }
                                    }}
                                />
                                {docNoError !== null ? (
                                    <Text className="spkFioriError">
                                        {docNoError}
                                    </Text>
                                ) : null}
                            </div>
                            <div className="spkDiamondCreateField">
                                <Label htmlFor="spk-diamond-tanggal">
                                    Tanggal
                                </Label>
                                <Input
                                    id="spk-diamond-tanggal"
                                    name="tanggal"
                                    type="date"
                                    value={tanggal}
                                    aria-invalid={tanggalError !== null}
                                    onChange={(event) => {
                                        setTanggal(event.target.value);
                                        if (tanggalError !== null) {
                                            setTanggalError(null);
                                        }
                                    }}
                                />
                                {tanggalError !== null ? (
                                    <Text className="spkFioriError">
                                        {tanggalError}
                                    </Text>
                                ) : null}
                            </div>
                        </div>

                        <div className="spkDiamondCreateField">
                            <Label htmlFor="spk-diamond-produksi-no">
                                No SPK produksi
                            </Label>
                            <Input
                                id="spk-diamond-produksi-no"
                                name="produksi_no"
                                value={spk.produksiNo}
                                readOnly
                                tabIndex={-1}
                                className="spkDiamondCreateReadOnlyInput"
                            />
                        </div>

                        <div className="spkDiamondCreateListSection">
                            <div className="spkDiamondCreateListHeader">
                                <Label>Batu yang dialokasikan</Label>
                                <Text className="spkFioriDetailBlockMeta">
                                    {lines.length} baris
                                </Text>
                            </div>

                            {lines.length === 0 ? (
                                <Text className="spkStoneEmpty">
                                    Tidak ada batu teralokasi.
                                </Text>
                            ) : (
                                <div className="spkDiamondCreateTableWrap">
                                    <table className="spkAlertModalTable spkStoneAllocateTable spkStoneAllocateTable--dossier spkDiamondCreateTable">
                                        <thead>
                                            <tr>
                                                <th>Posisi</th>
                                                <th className="spkChainIssuedColUnit">
                                                    ID
                                                </th>
                                                <th className="spkStoneAllocateColType">
                                                    Sumber
                                                </th>
                                                <th className="spkStoneAllocateColType">
                                                    Tipe
                                                </th>
                                                <th className="spkStoneAllocateColShape">
                                                    Bentuk
                                                </th>
                                                <th className="spkChainIssuedColLength">
                                                    Jumlah Butir (pcs)
                                                </th>
                                                <th className="spkChainIssuedColWeight">
                                                    Total CRT
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {lines.map((line) => (
                                                <tr key={line.id}>
                                                    <td>
                                                        {emptyDash(
                                                            line.positionName,
                                                        )}
                                                    </td>
                                                    <td className="spkChainIssuedColUnit">
                                                        {allocatedItemIdCell(
                                                            line,
                                                        )}
                                                    </td>
                                                    <td className="spkStoneAllocateColType">
                                                        {sourceLabel(
                                                            line.source,
                                                        )}
                                                    </td>
                                                    <td className="spkStoneAllocateColType">
                                                        {emptyDash(
                                                            line.diamondType,
                                                        )}
                                                    </td>
                                                    <td className="spkStoneAllocateColShape">
                                                        {emptyDash(line.shape)}
                                                    </td>
                                                    <td className="spkChainIssuedColLength tabular-nums">
                                                        {emptyDash(
                                                            line.reservedPcs,
                                                        )}
                                                    </td>
                                                    <td className="spkChainIssuedColWeight tabular-nums">
                                                        {formatDecimal3Id(
                                                            line.reservedCrt,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>

                    <DialogFooter className="spkDiamondCreateFooter">
                        <Button
                            type="Button"
                            design="Transparent"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="Submit"
                            design="Emphasized"
                            disabled={lines.length === 0}
                        >
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
