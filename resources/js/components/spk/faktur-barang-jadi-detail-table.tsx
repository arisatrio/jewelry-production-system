import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import { Button } from '@ui5/webcomponents-react/Button';
import type { ReactNode } from 'react';
import { show as spkShow } from '@/routes/spk';

export type FakturBarangJadiPrintDetailRow = {
    pcs: string;
    carat: string;
    perPieceCarat: string;
    shape: string;
    gia: string;
};

export type FakturBarangJadiPrintGroup = {
    spkRowId: number;
    spkNo: string;
    categorySkuPrimary: string;
    skuCode: string;
    productionType: string;
    goldColor: string;
    completionDate: string;
    imageUrl: string | null;
    goldWeight: string;
    finalWeight: string;
    remarks: string;
    detailRows: FakturBarangJadiPrintDetailRow[];
};

type FakturBarangJadiDetailTableProps = {
    groups: FakturBarangJadiPrintGroup[];
    canEditSpk: boolean;
    jumlahSpk: number;
    detachProcessing?: boolean;
    onDetach?: (spkRowId: number, spkNo: string) => void;
};

function normalizeDetailRows(
    rows: FakturBarangJadiPrintDetailRow[],
): FakturBarangJadiPrintDetailRow[] {
    if (rows.length > 0) {
        return rows;
    }

    return [
        {
            pcs: '—',
            carat: '—',
            perPieceCarat: '—',
            shape: '—',
            gia: '',
        },
    ];
}

function SpkLink({ spkNo }: { spkNo: string }): ReactNode {
    const trimmed = spkNo.trim();

    if (trimmed === '' || trimmed === '-') {
        return <span>{spkNo}</span>;
    }

    return (
        <a
            href={spkShow.url(trimmed)}
            target="_blank"
            rel="noopener noreferrer"
            className="spkProduksiLink"
        >
            {spkNo}
        </a>
    );
}

function DetachButton({
    spkRowId,
    spkNo,
    jumlahSpk,
    detachProcessing,
    onDetach,
}: {
    spkRowId: number;
    spkNo: string;
    jumlahSpk: number;
    detachProcessing: boolean;
    onDetach?: (spkRowId: number, spkNo: string) => void;
}): ReactNode {
    return (
        <Button
            design="Transparent"
            icon={deleteIcon}
            accessibleName={`Keluarkan ${spkNo}`}
            disabled={jumlahSpk <= 1 || detachProcessing}
            onClick={() => onDetach?.(spkRowId, spkNo)}
        />
    );
}

function StoneDetailTable({
    rows,
}: {
    rows: FakturBarangJadiPrintDetailRow[];
}): ReactNode {
    return (
        <table className="spkTable spkFbjDetailStoneSubTable">
            <thead>
                <tr>
                    <th className="spkTableColCenter">Pcs</th>
                    <th className="spkTableColCenter">Total Crt</th>
                    <th className="spkTableColCenter">Crt per butir</th>
                    <th className="spkTableColCenter">Shape</th>
                    <th className="spkTableColCenter">No GIA</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((detail, index) => (
                    <tr key={`stone-${index}`}>
                        <td className="spkTableColCenter tabular-nums">
                            {detail.pcs}
                        </td>
                        <td className="spkTableColCenter tabular-nums">
                            {detail.carat}
                        </td>
                        <td className="spkTableColCenter tabular-nums">
                            {detail.perPieceCarat}
                        </td>
                        <td className="spkTableColCenter">{detail.shape}</td>
                        <td className="spkTableColCenter">
                            {detail.gia !== '' ? detail.gia : '—'}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function MobileField({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}): ReactNode {
    return (
        <div className="spkDetailField">
            <span className="spkDetailFieldLabel">{label}</span>
            <span className="spkDetailValue">{children}</span>
        </div>
    );
}

export function FakturBarangJadiDetailTable({
    groups,
    canEditSpk,
    jumlahSpk,
    detachProcessing = false,
    onDetach,
}: FakturBarangJadiDetailTableProps) {
    const columnCount = canEditSpk ? 11 : 10;

    return (
        <>
            <div className="spkFbjDetailTableWrap spkFbjDetailTableWrap--desktop">
                <table className="spkTable spkFbjDetailTable">
                    <thead>
                        <tr>
                            {canEditSpk ? (
                                <th className="spkFbjColAction spkTableColCenter">
                                    Aksi
                                </th>
                            ) : null}
                            <th className="spkFbjColPrd spkTableColDoc spkTableColDoc--spk">
                                No PRD
                            </th>
                            <th className="spkFbjColCategory spkReceiptHistoryColWrap">
                                Item
                            </th>
                            <th className="spkFbjColType spkTableColCenter">
                                Tipe Produksi
                            </th>
                            <th className="spkFbjColGoldColor spkTableColCenter">
                                Warna Emas
                            </th>
                            <th className="spkFbjColStoneDetail spkTableColCenter">
                                Detail batu
                            </th>
                            <th className="spkFbjColDone spkTableColCenter">
                                Tanggal Selesai
                            </th>
                            <th className="spkFbjColImage spkTableColCenter">
                                Gambar
                            </th>
                            <th className="spkFbjColGoldWt spkTableColCenter">
                                Berat Emas
                            </th>
                            <th className="spkFbjColFinalWt spkTableColCenter">
                                Berat Barang Jadi
                            </th>
                            <th className="spkFbjColNotes spkReceiptHistoryColWrap">
                                Keterangan
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {groups.length === 0 ? (
                            <tr>
                                <td colSpan={columnCount}>
                                    Tidak ada SPK pada faktur ini.
                                </td>
                            </tr>
                        ) : (
                            groups.map((group) => {
                                const detailRows = normalizeDetailRows(
                                    group.detailRows,
                                );

                                return (
                                    <tr
                                        key={group.spkRowId}
                                        className="spkFbjDetailGroupRow"
                                    >
                                        {canEditSpk ? (
                                            <td className="spkTableColCenter spkChainTableActionCell spkFakturBarangJadiReceiptHistoryColAction spkFbjColAction">
                                                <DetachButton
                                                    spkRowId={group.spkRowId}
                                                    spkNo={group.spkNo}
                                                    jumlahSpk={jumlahSpk}
                                                    detachProcessing={
                                                        detachProcessing
                                                    }
                                                    onDetach={onDetach}
                                                />
                                            </td>
                                        ) : null}
                                        <td className="spkTableColDoc spkTableColDoc--spk">
                                            <SpkLink spkNo={group.spkNo} />
                                        </td>
                                        <td className="spkReceiptHistoryColWrap">
                                            {group.categorySkuPrimary}
                                            {group.skuCode !== '' ? (
                                                <span className="spkFbjCategorySkuCode">
                                                    {group.skuCode}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="spkFbjProductionType spkTableColCenter">
                                            {group.productionType}
                                        </td>
                                        <td className="spkTableColCenter">
                                            {group.goldColor}
                                        </td>
                                        <td className="spkFbjColStoneDetail">
                                            <StoneDetailTable
                                                rows={detailRows}
                                            />
                                        </td>
                                        <td className="spkTableColCenter whitespace-nowrap">
                                            {group.completionDate}
                                        </td>
                                        <td className="spkTableColCenter">
                                            {group.imageUrl ? (
                                                <img
                                                    src={group.imageUrl}
                                                    alt=""
                                                    className="spkFbjItemImage"
                                                />
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="spkTableColCenter tabular-nums">
                                            {group.goldWeight}
                                        </td>
                                        <td className="spkTableColCenter tabular-nums">
                                            {group.finalWeight}
                                        </td>
                                        <td className="spkFbjColNotes spkReceiptHistoryColWrap">
                                            {group.remarks !== ''
                                                ? group.remarks
                                                : '—'}
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            <nav
                className="spkFbjDetailMobileList"
                aria-label="Daftar SPK faktur"
            >
                {groups.length === 0 ? (
                    <p className="spkFbjDetailMobileEmpty">
                        Tidak ada SPK pada faktur ini.
                    </p>
                ) : (
                    groups.map((group, groupIndex) => {
                        const detailRows = normalizeDetailRows(
                            group.detailRows,
                        );

                        return (
                            <article
                                key={group.spkRowId}
                                className="spkFbjDetailMobileCard"
                            >
                                <header className="spkFbjDetailMobileCardHeader">
                                    <span className="spkFbjDetailMobileNo">
                                        #{groupIndex + 1}
                                    </span>
                                    <SpkLink spkNo={group.spkNo} />
                                    {canEditSpk ? (
                                        <DetachButton
                                            spkRowId={group.spkRowId}
                                            spkNo={group.spkNo}
                                            jumlahSpk={jumlahSpk}
                                            detachProcessing={
                                                detachProcessing
                                            }
                                            onDetach={onDetach}
                                        />
                                    ) : null}
                                </header>

                                <div className="spkFbjDetailMobileGrid">
                                    <MobileField label="Item">
                                        {group.categorySkuPrimary}
                                        {group.skuCode !== '' ? (
                                            <span className="spkFbjCategorySkuCode">
                                                {group.skuCode}
                                            </span>
                                        ) : null}
                                    </MobileField>
                                    <MobileField label="Tipe Produksi">
                                        <span className="spkFbjProductionType">
                                            {group.productionType}
                                        </span>
                                    </MobileField>
                                    <MobileField label="Warna Emas">
                                        {group.goldColor}
                                    </MobileField>
                                    <MobileField label="Tanggal Selesai">
                                        {group.completionDate}
                                    </MobileField>
                                    <MobileField label="Berat Emas">
                                        {group.goldWeight}
                                    </MobileField>
                                    <MobileField label="Berat Barang Jadi">
                                        {group.finalWeight}
                                    </MobileField>
                                    <MobileField label="Keterangan">
                                        {group.remarks !== ''
                                            ? group.remarks
                                            : '—'}
                                    </MobileField>
                                </div>

                                {group.imageUrl ? (
                                    <img
                                        src={group.imageUrl}
                                        alt=""
                                        className="spkFbjDetailMobileImage"
                                    />
                                ) : null}

                                <section className="spkFbjDetailMobileStones">
                                    <h3 className="spkFbjDetailMobileStonesTitle">
                                        Detail batu
                                    </h3>
                                    <StoneDetailTable rows={detailRows} />
                                </section>
                            </article>
                        );
                    })
                )}
            </nav>
        </>
    );
}