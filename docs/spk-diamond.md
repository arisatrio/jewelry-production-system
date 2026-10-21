# SPK Diamond

Modul **SPK Diamond** mendokumentasikan setting awal batu/diamond sebelum proses **Pasang Batu**.

## Relasi

- Satu SPK produksi → banyak SPK Diamond (penambahan batu = dokumen baru).
- Satu SPK Diamond → maksimal satu dokumen Pasang Batu (`diamondmounting.spk_diamond_id` unique).

## Status

| Status | Label |
|--------|--------|
| `DRAFT` | Draft |
| `SUBMITTED` | Kirim ke produksi |
| `MOUNTING` | Sedang proses pasang batu |
| `DONE` | Selesai proses pasang batu |

Stok micro stone keluar pada **SUBMITTED** (`trstone`, transtype 18). Diamond dossier di-reserve (`trdiamond.spk_diamond_id`, `is_used=1`).

## Pasang Batu

Buat dokumen dengan memilih **SPK Diamond** (bukan SPK produksi). Setting awal ditampilkan read-only; retur/terpasang via aksi checklist dan retur.

Saat simpan Pasang Batu, baris `trstone` SPK Diamond di-repoint ke dokumen Pasang Batu (transtype 7). Diamond dipindahkan ke `diamondmounting_id`. Saat Pasang Batu selesai, status SPK Diamond → `DONE`.
