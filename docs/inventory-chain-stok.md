# Kebutuhan Stok Chain (Inventory)

Dokumen ini merangkum keputusan dan kebutuhan fungsional untuk **master chain** (`mschain`) dan **stok unit** (`trxchain`) di submenu Inventory. Dipakai sebagai acuan pengembangan.

## Konteks

| Lapisan | Tabel | Peran |
| --- | --- | --- |
| Master | `mschain` | SKU chain: kategori, item, kode, panjang standar, `item_name`, deskripsi |
| Stok unit (baru) | **`trxchain`** | Satu baris = satu lembar/sisa fisik di gudang (masuk/keluar, berat, warna, supplier) |
| Arsip | `trchain` | Data lama sistem sebelumnya; **hanya arsip** — tidak di-copy, tidak di-migrate ke `trxchain` |
| SPK | `spk_chains` | Kebutuhan chain pada SPK (`mschain_id`, dll.) |

Master chain CRUD sudah ada di **Inventory → Chain**. Stok dan transaksi mengikuti model **`trxchain`** di koneksi `third`, **bukan** ledger seperti `trmaterialgold` / `trstone` (tanpa `period_id` / `transtype_id` untuk chain).

### Mengapa `trxchain` (bukan memperluas `trchain`)

| Aspek | `trchain` (legacy) | `trxchain` (baru) |
| --- | --- | --- |
| Data | ~400+ baris arsip historis | Stok operasional dimulai dari nol di WPS |
| Kolom | Tidak punya `mschain_id`, `source_type`, `parent_row_id` dari awal | Schema lengkap untuk stok + sisa potongan |
| Aplikasi baru | **Tidak** diakses fitur inventory chain (arsip DB saja) | Semua insert/update stok inventory chain |

**Keputusan:** fitur stok chain (masuk, keluar, saldo, history, retur sisa) **hanya menulis ke `trxchain`**. Tabel **`trchain` tetap di database sebagai arsip**; **tidak ada** job migrasi, copy, atau backfill dari `trchain` ke `trxchain`. Saldo dan history di UI hanya mencerminkan data `trxchain` setelah go-live fitur stok.

## Keputusan arsitektur

### Model stok: unit (`trxchain`), bukan ledger penuh

- **Masuk:** insert baris `trxchain` (tanggal masuk, atribut fisik).
- **Keluar:** update `out_date` (+ `spk_id` / flag pemakaian), bukan insert transaksi IN/OUT terpisah seperti emas.
- **Saldo:** hitung unit yang masih di gudang (lihat aturan saldo di bawah).
- Ledger klasik (`mstranstype`, agregat per periode) **tidak** dipakai untuk chain kecuali ada keputusan baru.

### Satu modul stok chain (bukan modul terpisah untuk sisa)

Semua stok (pembelian, sisa potongan, keluar SPK) berada di **pool yang sama**, dibedakan dengan **tipe/sumber pergerakan** (`source_type`: `purchase`, `remnant`, keluar SPK), bukan menu inventory terpisah hanya untuk sisa.

### Master tidak diubah per retur sisa

- `mschain` tetap definisi resmi Chain ABC (45 cm, kode, dll.).
- Retur sisa **tidak** membuat baris `mschain` baru hanya karena nama berbeda atau panjang 3 cm.
- Agregat **Masuk / Keluar / Saldo** di index master mengacu ke **`mschain_id`** yang sama.

## Schema `trxchain` (koneksi `third`)

Tabel baru; kolom menggabungkan kebutuhan legacy yang relevan + field WPS.

| Kolom | Tipe (indikatif) | Tujuan |
| --- | --- | --- |
| `row_id` | PK increment | Identitas baris |
| `mschain_id` | FK → `mschain.row_id`, indexed, NOT NULL (data baru) | Agregat stok per master |
| `source_type` | string, indexed | `purchase`, `remnant`, `adjustment`, … |
| `parent_row_id` | FK → `trxchain.row_id`, nullable | Jejak lembar induk (potong / retur) |
| `name` | string | Label tampilan (termasuk `… Pot. Sisa {spk_no}`) |
| `item_code` | string, nullable | Salinan dari master (laporan) |
| `chain_length` | decimal | Panjang fisik aktual (cm) |
| `color` | string, nullable | Warna unit |
| `weight` | decimal, nullable | Berat (g) |
| `supplier` | string, nullable | Pemasok (masuk pembelian) |
| `entry_date` | date, nullable | Tanggal masuk |
| `out_date` | date, nullable | Tanggal keluar; NULL = masih stok |
| `spk_id` | int, nullable, indexed | SPK pemakaian / asal retur |
| `is_used` | int, default 0 | Selaraskan dengan `out_date` saat keluar |
| `notes` | text, nullable | Catatan manual |
| `is_deleted` | int, default 0 | Soft delete |
| `created_date`, `created_by`, `modified_date`, `modified_by`, `deleted_date`, `deleted_by` | audit | Konvensi tabel `third` |

Tidak ada kolom penghubung ke `trchain` — arsip legacy tidak diimpor.

## UI master Chain (target)

### Index `mschain`

Kolom tambahan (agregat dari **`trxchain`**):

| Kolom | Makna |
| --- | --- |
| Masuk | Jumlah unit yang pernah masuk (`entry_date` terisi) |
| Keluar | Jumlah unit yang sudah keluar (`out_date` terisi) |
| Saldo | Unit masih di gudang |

Aksi per baris master:

| Aksi | Fungsi |
| --- | --- |
| Tambah stok | Form masuk dari supplier → insert `trxchain` |
| Kurang stok | Manual/koreksi (opsional fase awal; keluar utama nanti dari SPK) |
| History | Daftar unit `trxchain` untuk `mschain_id` tersebut |

Opsional: **total panjang (cm)** in-stock = `SUM(chain_length)` untuk unit dengan `out_date` NULL.

### Form tambah stok (pembelian / masuk gudang)

| Field | Map ke `trxchain` |
| --- | --- |
| Tanggal masuk | `entry_date` |
| Supplier | `supplier` |
| Berat | `weight` |
| Warna | `color` |
| (implisit) | `mschain_id`, `source_type = purchase`, `item_code` / `name` dari master |

### Keluar stok

- **Fase berikutnya:** otomatis saat chain **sudah diinput / dipakai di SPK** (pemasangan komponen / finishing).
- Update `out_date`, `spk_id`, `is_used` pada baris `trxchain` (atau split + baris baru untuk sisa).

### History

Per master chain: semua baris **`trxchain`** — tanggal masuk/keluar, nama, panjang, berat, warna, supplier, SPK, `source_type`.

## Aturan saldo

- **Saldo =** `is_deleted = 0` AND `entry_date` IS NOT NULL AND `out_date` IS NULL.
- Saat keluar / retur / potong, set **`is_used`** konsisten dengan `out_date`.

Agregat hanya dari **`trxchain`**. Data arsip **`trchain`** tidak ikut dihitung.

## Kasus potong: chain 45 cm dipasang ke SPK, sisa 3 cm

### Prinsip

Pemakaian sebagian = **pecah (split)**:

1. Catat **pemakaian** ke SPK (panjang terpakai, mis. 42 cm) pada baris terkait.
2. **Sisa** = **insert `trxchain` baru** (`source_type = remnant`) dengan panjang dan berat hasil timbang.

### Retur sisa dari pemasangan (finishing / komponen chain)

- Sisa retur = **entry row baru** di `trxchain`.
- **Nama tampilan** (`name`), master `mschain` **tidak** diubah:

```text
{label_asal} Pot. Sisa {spk_no}
```

Contoh: `Chain ABC Pot. Sisa 2026/PRD/01601` (`spk_no` dari `production.spk_no`).

| Field retur | Nilai |
| --- | --- |
| `mschain_id` | Master Chain ABC |
| `name` | Generated `… Pot. Sisa {spk_no}` |
| `source_type` | `remnant` |
| `entry_date` | Tanggal retur |
| `chain_length` | Panjang sisa (mis. 3) |
| `weight` | Berat aktual |
| `color` | Dari induk atau input |
| `out_date` | NULL |
| `spk_id` | SPK pemasangan asal |
| `parent_row_id` | Baris yang dipotong |
| `supplier` | NULL atau `Retur produksi` |

**Edge case:** beberapa retur per SPK → suffix `(2)` / `- L2` pada `name`.

### Service aplikasi

`ChainStockService` (atau setara) hanya mengoperasikan **`trxchain`**:

| Operasi | Efek |
| --- | --- |
| `receiveFromSupplier()` | Insert `trxchain`, `source_type = purchase` |
| `returnRemnant()` | Insert `trxchain`, nama potongan, `source_type = remnant` |
| `issueToSpk()` | Update keluar / split + link SPK |

Opsional: **`trxchain_movements`** untuk audit (IN_PURCHASE, IN_REMNANT, OUT_SPK, OUT_CUT) tanpa mengganti model unit.

## Yang tidak dilakukan (kecuali keputusan baru)

- Menulis stok baru ke **`trchain`**.
- **Copy / migrate / backfill** data dari **`trchain`** ke **`trxchain`**.
- Menampilkan atau menggabungkan saldo arsip `trchain` dengan stok `trxchain` di UI inventory.
- Mengubah `trxchain` menjadi ledger seperti `trstone` tanpa kebutuhan laporan periode yang eksplisit.
- Modul inventory terpisah hanya untuk sisa.
- Baris **`mschain` baru** per sisa hanya karena nama/panjang berbeda.

## Fase implementasi (saran)

1. Migration **`create_trxchain_table`** (+ model `TrxChain` / factory jika perlu).
2. Agregat Masuk/Keluar/Saldo di index Chain dari **`trxchain`** + History per master.
3. Form tambah stok + endpoint insert **`trxchain`**.
4. `returnRemnant()` + penamaan `Pot. Sisa {spk_no}`.
5. Integrasi keluar / potong dari SPK & finishing.

## Referensi di codebase

| Area | Lokasi |
| --- | --- |
| Master CRUD | `MsChainController`, `resources/js/pages/inventory/chain/*` |
| Model master | `App\Models\MsChain` |
| SPK chain lines | `App\Models\SpkChain`, `SpkService::syncChains()` |
| Pola stok ledger (referensi saja) | `GoldMaterialLedger`, `StoneLedger` |
| Shared table config | `config/spk_processes.php` → **`trxchain`** |
| Arsip (tidak dipakai app) | Tabel `trchain` di DB `third` — tidak di-migrate |

---

*Terakhir diperbarui: stok operasional di `trxchain`; `trchain` arsip saja, tanpa copy ke `trxchain`.*
