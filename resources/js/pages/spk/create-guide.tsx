import { Head, router } from '@inertiajs/react';
import { type ReactNode } from 'react';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import paperPlaneIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import pictureIcon from '@ui5/webcomponents-icons/dist/picture.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import saveIcon from '@ui5/webcomponents-icons/dist/save.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { ComboBox } from '@ui5/webcomponents-react/ComboBox';
import { DatePicker } from '@ui5/webcomponents-react/DatePicker';
import { FileUploader } from '@ui5/webcomponents-react/FileUploader';
import { Form } from '@ui5/webcomponents-react/Form';
import { FormGroup } from '@ui5/webcomponents-react/FormGroup';
import { FormItem } from '@ui5/webcomponents-react/FormItem';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Label } from '@ui5/webcomponents-react/Label';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { Text } from '@ui5/webcomponents-react/Text';
import { TextArea } from '@ui5/webcomponents-react/TextArea';
import { GoldWeightRowLabel } from '@/components/spk/spk-stone-list';
import { index as spkIndex } from '@/routes/spk';

type SpkCreateGuideProps = {
    formDocumentNo: string;
};

function GuideField({
    children,
    hint,
}: {
    children: ReactNode;
    hint: string;
}) {
    return (
        <div className="spkCreateGuideField">
            {children}
            <Text className="spkCreateGuideHint">{hint}</Text>
        </div>
    );
}

export default function SpkCreateGuide({ formDocumentNo }: SpkCreateGuideProps) {
    return (
        <>
            <Head title="Panduan Form SPK" />

            <div className="spkDetailShell spkCreateGuidePage">
                <div className="spkDetailStack">
                    <div className="spkFioriForm">
                        <section className="spkFioriFormCard">
                            <div className="spkFioriFormCardHeader">
                                <div className="spkFioriFormCardHeaderMain">
                                    <h2 className="spkFioriFormCardTitle">
                                        Form Pembuatan / Edit SPK
                                    </h2>
                                    <p className="spkFioriFormCardSubtitle">
                                        No. Form Dokumen: {formDocumentNo}
                                    </p>
                                </div>
                                <div className="spkFioriFormCardActions spkCreateGuideNoPrint">
                                    <Button
                                        design="Default"
                                        icon={printIcon}
                                        onClick={() => window.print()}
                                    >
                                        Cetak / PDF
                                    </Button>
                                    <Button
                                        design="Default"
                                        className="spkFioriFormCardBtnGrey"
                                        icon={declineIcon}
                                        onClick={() =>
                                            router.visit(spkIndex.url())
                                        }
                                    >
                                        Kembali
                                    </Button>
                                    <Button
                                        design="Attention"
                                        className="spkFioriFormCardBtnYellow"
                                        icon={saveIcon}
                                        disabled
                                    >
                                        Simpan Draft
                                    </Button>
                                    <Button
                                        design="Emphasized"
                                        icon={paperPlaneIcon}
                                        disabled
                                    >
                                        Kirim ke Manager
                                    </Button>
                                </div>
                            </div>

                            <Form
                                accessibleMode="Display"
                                layout="S1 M2 L2 XL2"
                                labelSpan="S12 M4 L4 XL4"
                                itemSpacing="Normal"
                                headerText="Informasi Produksi"
                            >
                                <FormGroup>
                                    <FormItem
                                        labelContent={
                                            <Label showColon>No. SPK</Label>
                                        }
                                    >
                                        <GuideField hint="Nomor otomatis saat draft disimpan, format tahun/PRD/urut (contoh: 2026/PRD/01601). Tidak diisi manual.">
                                            <Input
                                                value="auto-generated"
                                                readonly
                                            />
                                        </GuideField>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Tipe Produksi
                                            </Label>
                                        }
                                    >
                                        <GuideField hint="Wajib saat create: Stock, Pesanan, Exchange, Refund, atau Reparasi. Saat edit, tipe terkunci.">
                                            <Select
                                                accessibleName="Tipe Produksi"
                                                disabled
                                            >
                                                <Option value="Stock" selected>
                                                    Stock
                                                </Option>
                                                <Option value="Pesanan">
                                                    Pesanan
                                                </Option>
                                                <Option value="Exchange">
                                                    Exchange
                                                </Option>
                                                <Option value="Refund">
                                                    Refund
                                                </Option>
                                                <Option value="Reparasi">
                                                    Reparasi
                                                </Option>
                                            </Select>
                                        </GuideField>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Pesanan
                                            </Label>
                                        }
                                    >
                                        <GuideField hint="Wajib untuk tipe Pesanan. Klik Pilih, lalu pilih request order. Contoh: DP-0009303 (Vera) (Lunas). Kosong untuk Stock.">
                                            <div className="spkCreateSelectorRow">
                                                <div className="spkCreateSelectorValue">
                                                    <span>
                                                        Belum ada pesanan
                                                        dipilih
                                                    </span>
                                                </div>
                                                <Button design="Default" disabled>
                                                    Pilih
                                                </Button>
                                            </div>
                                        </GuideField>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon>Customer</Label>
                                        }
                                    >
                                        <GuideField hint="Terisi otomatis setelah pesanan dipilih. Tidak diisi manual.">
                                            <Input value="" readonly />
                                        </GuideField>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon>Item</Label>
                                        }
                                    >
                                        <GuideField hint="Terisi otomatis setelah pesanan dipilih. Tidak diisi manual.">
                                            <Input value="" readonly />
                                        </GuideField>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                SPK Referensi
                                            </Label>
                                        }
                                    >
                                        <GuideField hint="Wajib untuk Exchange, Refund, atau Reparasi. Pilih SPK sumber berstatus SPKDONE.">
                                            <div className="spkCreateSelectorRow">
                                                <div className="spkCreateSelectorValue">
                                                    <span>
                                                        Belum ada SPK referensi
                                                        dipilih
                                                    </span>
                                                </div>
                                                <Button design="Default" disabled>
                                                    Pilih
                                                </Button>
                                            </div>
                                        </GuideField>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Tanggal Permintaan
                                            </Label>
                                        }
                                    >
                                        <GuideField hint="Wajib. Tanggal permintaan produksi (dd/MM/yyyy).">
                                            <DatePicker
                                                value=""
                                                valueFormat="yyyy-MM-dd"
                                                displayFormat="dd/MM/yyyy"
                                                readonly
                                            />
                                        </GuideField>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Tanggal Estimasi Selesai
                                            </Label>
                                        }
                                    >
                                        <GuideField hint="Wajib. Tanggal target selesai. Estimasi hari kerja dihitung otomatis dari selisih tanggal.">
                                            <DatePicker
                                                value=""
                                                valueFormat="yyyy-MM-dd"
                                                displayFormat="dd/MM/yyyy"
                                                readonly
                                            />
                                        </GuideField>
                                    </FormItem>
                                </FormGroup>

                                <FormGroup className="spkFioriFormGroupContinuation">
                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Tipe Item
                                            </Label>
                                        }
                                    >
                                        <GuideField hint="Wajib. Pilih tipe item terlebih dahulu sebelum SKU.">
                                            <ComboBox
                                                accessibleName="Tipe Item"
                                                className="spkFioriComboBox"
                                                placeholder="Cari / pilih tipe item"
                                                readonly
                                            />
                                        </GuideField>
                                    </FormItem>

                                    <FormItem
                                        columnSpan={2}
                                        labelContent={
                                            <Label showColon required>
                                                SKU
                                            </Label>
                                        }
                                    >
                                        <GuideField hint="Wajib. Setelah dipilih, mengisi otomatis deskripsi, berat emas, warna emas, file JewelCAD (file_jwlcad), gambar desain (design_image), dan daftar batu dari master SKU.">
                                            <ComboBox
                                                accessibleName="SKU"
                                                className="spkFioriComboBox"
                                                placeholder="Cari SKU (SKU)"
                                                readonly
                                            />
                                        </GuideField>
                                    </FormItem>

                                    <FormItem
                                        labelContent={
                                            <Label showColon required>
                                                Qty
                                            </Label>
                                        }
                                    >
                                        <GuideField hint="Wajib. Pilih 1 Pcs, 1 Pasang, atau 1/2 Pasang.">
                                            <Select
                                                accessibleName="Qty"
                                                disabled
                                            >
                                                <Option value="1|Pcs" selected>
                                                    1 Pcs
                                                </Option>
                                                <Option value="1|Pasang">
                                                    1 Pasang
                                                </Option>
                                                <Option value="1|Setengah Pasang">
                                                    1/2 Pasang
                                                </Option>
                                            </Select>
                                        </GuideField>
                                    </FormItem>
                                </FormGroup>
                            </Form>

                            <div className="spkFioriDetailBlock">
                                <div className="spkFioriDetailBlockTitle">
                                    Detail Item
                                </div>
                                <div className="spkItemDetailGrid">
                                    <div
                                        className="spkItemImageCol"
                                        aria-label="Gambar item"
                                    >
                                        <GuideField hint="Menampilkan preview gambar dari kolom design_image master SKU setelah SKU dipilih.">
                                            <div className="spkItemImagePlaceholder">
                                                <Icon
                                                    name={pictureIcon}
                                                    mode="Decorative"
                                                />
                                                <span>Gambar item</span>
                                            </div>
                                        </GuideField>
                                        <div className="spkFioriDetailFile">
                                            <GuideField hint="Opsional. Unggah gambar ke SPK; file tersimpan di SPK dan juga mengisi design_image master SKU. Preview tampil di samping form.">
                                                <FileUploader
                                                    accept=".jpg,.jpeg,.png,.pdf,.webp"
                                                    placeholder="Upload gambar"
                                                    disabled
                                                />
                                            </GuideField>
                                        </div>
                                    </div>

                                    <div className="spkItemFieldsCol">
                                        <table className="spkItemMetaTable spkItemMetaTable--sm">
                                            <tbody>
                                                <tr>
                                                    <th scope="row">
                                                        Tipe Item | SKU
                                                    </th>
                                                    <td>
                                                        <GuideField hint="Terisi otomatis: baris 1 kode tipe | nama item, baris 2 kode SKU.">
                                                            <span>—</span>
                                                        </GuideField>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        Deskripsi Item
                                                    </th>
                                                    <td>
                                                        <GuideField hint="Terisi otomatis dari komponen SKU. Contoh: Rose Gold Bangle Netizen Asimetris Heart Diamond Dossier 0.3">
                                                            <span>—</span>
                                                        </GuideField>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        <GoldWeightRowLabel
                                                            currentWeight="6.90"
                                                            masterWeight="5.75"
                                                        />
                                                    </th>
                                                    <td>
                                                        <GuideField hint="Wajib. Terisi otomatis dari SKU (gram), lalu dapat diubah manual. Teks *Berat emas diubah dari Master SKU hanya muncul jika nilai form berbeda dari gold_weight di sku_master.">
                                                            <Input
                                                                accessibleName="Berat Emas"
                                                                className="spkItemMetaInput"
                                                                type="Number"
                                                                placeholder="Masukkan berat emas"
                                                                readonly
                                                            />
                                                        </GuideField>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        Warna Emas
                                                    </th>
                                                    <td>
                                                        <GuideField hint="Terisi otomatis dari SKU: White / Yellow / Rose / Two Tones.">
                                                            <span>—</span>
                                                        </GuideField>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        File JewelCAD 3D
                                                    </th>
                                                    <td>
                                                        <GuideField hint="Terisi otomatis dari file_jwlcad master SKU saat SKU dipilih. Dapat disesuaikan manual jika diperlukan.">
                                                            <span>—</span>
                                                        </GuideField>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Ukuran</th>
                                                    <td>
                                                        <div className="spkItemUkuranFields">
                                                            <div className="spkItemUkuranField">
                                                                <Label>
                                                                    Panjang
                                                                    (mm)
                                                                </Label>
                                                                <GuideField hint="Panjang item dalam mm. Contoh: 10">
                                                                    <Input
                                                                        placeholder="Masukkan panjang"
                                                                        readonly
                                                                    />
                                                                </GuideField>
                                                            </div>
                                                            <div className="spkItemUkuranField">
                                                                <Label>
                                                                    Dimensi PxL
                                                                    (mm)
                                                                </Label>
                                                                <GuideField hint="Panjang × lebar dalam mm. Contoh: 150">
                                                                    <Input
                                                                        placeholder="Masukkan dimensi"
                                                                        readonly
                                                                    />
                                                                </GuideField>
                                                            </div>
                                                            <div className="spkItemUkuranField">
                                                                <Label>
                                                                    Ring Size
                                                                </Label>
                                                                <GuideField hint="Ukuran cincin. Contoh: 12 HK">
                                                                    <Input
                                                                        placeholder="Masukkan ring size"
                                                                        readonly
                                                                    />
                                                                </GuideField>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Catatan</th>
                                                    <td>
                                                        <GuideField hint="Catatan produksi tambahan (opsional).">
                                                            <TextArea
                                                                className="spkFioriNotesTextArea"
                                                                value=""
                                                                rows={4}
                                                                placeholder="Masukkan catatan"
                                                                readonly
                                                            />
                                                        </GuideField>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <Text
                                    className="spkMasterSyncAlert"
                                    role="alert"
                                >
                                    Perubahan pada detail item akan disimpan ke
                                    Master SKU
                                </Text>

                                <div className="spkEditStoneCard">
                                    <div className="spkEditStoneHeader">
                                        Daftar Batu
                                    </div>
                                    <table className="spkStoneTable spkStoneTable--editable">
                                        <thead>
                                            <tr>
                                                <th>Posisi</th>
                                                <th>Bentuk</th>
                                                <th>
                                                    Diameter / Dimensi PxL (mm)
                                                </th>
                                                <th>Carat per Butir (pcs)</th>
                                                <th>Jumlah Butir (pcs)</th>
                                                <th>Total Carat</th>
                                                <th className="spkStoneTableActionCol">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <Text className="spkCreateGuideHint">
                                        Terisi otomatis dari sku_master_diamond
                                        dan dapat diedit sebelum simpan. Jika
                                        nilai berbeda dari master, teks merah
                                        *Diubah dari Master SKU (xx) muncul di
                                        bawah field. Baris kosong untuk
                                        menambah batu. Total Carat = carat per
                                        butir × jumlah butir.
                                    </Text>
                                </div>
                            </div>

                            <footer
                                className="spkApprovalFooter"
                                aria-label="Persetujuan"
                            >
                                {[
                                    'Dibuat Oleh',
                                    'Disetujui Oleh',
                                    'Manager Produksi',
                                ].map((title) => (
                                    <div
                                        key={title}
                                        className="spkApprovalFooterCol"
                                    >
                                        <div className="spkApprovalFooterTitle">
                                            {title}
                                        </div>
                                        <div className="spkApprovalFooterMeta">
                                            <div className="spkApprovalFooterMetaRow">
                                                <span className="spkApprovalFooterMetaLabel">
                                                    Nama
                                                </span>
                                                <span className="spkApprovalFooterMetaValue">
                                                    -
                                                </span>
                                            </div>
                                            <div className="spkApprovalFooterMetaRow">
                                                <span className="spkApprovalFooterMetaLabel">
                                                    Tanggal
                                                </span>
                                                <span className="spkApprovalFooterMetaValue">
                                                    -
                                                </span>
                                            </div>
                                        </div>
                                        <Text className="spkCreateGuideHint">
                                            Nama dan tanggal terisi otomatis
                                            dari alur persetujuan.
                                        </Text>
                                    </div>
                                ))}
                            </footer>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

SpkCreateGuide.layout = {
    activeMenu: 'SPK',
    pageTitle: 'Panduan Form SPK',
};
