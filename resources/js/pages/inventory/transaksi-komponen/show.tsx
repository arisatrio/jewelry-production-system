import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    edit,
    index as transaksiKomponenIndex,
} from '@/routes/inventory/component-transactions';

type TransaksiKomponenDetail = {
    id: number;
    jenisKomponen: string | null;
    name: string | null;
    itemCode: string | null;
    supplier: string | null;
    length: string | null;
    color: string | null;
    weight: string | null;
    spkId: number | null;
    isUsed: number | null;
    isReserved: number | null;
    entryDate: string | null;
    outDate: string | null;
    createdBy: string | null;
    createdDate: string | null;
    modifiedBy: string | null;
    modifiedDate: string | null;
};

type TransaksiKomponenShowProps = {
    item: TransaksiKomponenDetail;
};

function formatFlag(value: number | null): string {
    if (value === 1) {
        return 'Ya';
    }

    if (value === 0) {
        return 'Tidak';
    }

    return '—';
}

export default function TransaksiKomponenShow({
    item,
}: TransaksiKomponenShowProps) {
    const rows = [
        { label: 'Jenis Komponen', value: item.jenisKomponen ?? '—' },
        { label: 'Nama', value: item.name ?? '—' },
        { label: 'Kode Item', value: item.itemCode ?? '—' },
        { label: 'Supplier', value: item.supplier ?? '—' },
        { label: 'Tanggal Masuk', value: item.entryDate ?? '—' },
        { label: 'Tanggal Keluar', value: item.outDate ?? '—' },
        { label: 'Panjang', value: item.length ?? '—' },
        { label: 'Warna', value: item.color ?? '—' },
        { label: 'Berat', value: item.weight ?? '—' },
        { label: 'SPK ID', value: item.spkId?.toString() ?? '—' },
        { label: 'Terpakai', value: formatFlag(item.isUsed) },
        { label: 'Reserved', value: formatFlag(item.isReserved) },
        {
            label: 'Dibuat',
            value: [item.createdDate, item.createdBy].filter(Boolean).join(' · ') || '—',
        },
        {
            label: 'Diubah',
            value: [item.modifiedDate, item.modifiedBy].filter(Boolean).join(' · ') || '—',
        },
    ];

    return (
        <>
            <Head title={`Transaksi Komponen · ${item.id}`} />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Detail Transaksi Komponen</h1>
                        <p className="masterDataSubtitle">
                            ID #{item.id}
                            {item.name ? ` · ${item.name}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={transaksiKomponenIndex.url()}>Kembali</Link>
                        </Button>
                        <Button asChild>
                            <Link href={edit.url(item.id)}>Edit</Link>
                        </Button>
                    </div>
                </div>

                <div className="fioriVarianceDetailCard">
                    <span className="fioriVarianceDetailTitle">
                        Detail Komponen
                    </span>
                    <dl className="fioriVarianceDetailGrid">
                        {rows.map((row) => (
                            <div
                                key={row.label}
                                className="fioriVarianceDetailRow"
                            >
                                <dt>{row.label}</dt>
                                <dd>{row.value}</dd>
                            </div>
                        ))}
                    </dl>
                </div>
            </div>
        </>
    );
}

TransaksiKomponenShow.layout = {
    activeMenu: 'Transaksi Komponen',
    pageTitle: 'Detail Transaksi Komponen',
};
