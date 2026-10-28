# Alur Produksi & Fase Sampel — Design

**Date:** 2026-09-03
**Status:** Approved

## Problem

Urutan tahapan produksi yang ada di sistem tidak sesuai proses sebenarnya di Labasa.

`ProductionStage::STAGES` saat ini adalah:

```
design → sample → cutting → sewing → qc → packing
```

Tiga hal salah di sini:

1. **Tidak ada tahap Pembuatan Pola.** Setelah desain diambil sebagai referensi, pola
   digambar di kertas dulu sebelum kain dipotong. Tahap ini hilang dari sistem.
2. **`sample` diperlakukan sebagai satu tahap linear di antara desain dan cutting.**
   Kenyataannya sampel bukan satu langkah — sampel adalah **satu putaran penuh**
   Cutting → Sewing → QC untuk 1 pcs, yang dijalankan lebih dulu supaya kesalahan
   ketahuan sebelum kain ditumpuk dan dipotong banyak. Model linear sekarang tidak bisa
   menyatakan pengulangan itu.
3. **QC dan Packing terpisah**, padahal di lapangan keduanya satu tahap: baju dicek,
   diberi hangtag dan label, disetrika, lalu dimasukkan plastik.

Akibatnya diagram alur di dokumen TA tidak bisa dipetakan ke data yang benar-benar
disimpan sistem, dan aturan inti proses — *buat satu dulu, perbaiki kalau salah, baru
potong banyak* — sama sekali tidak dijamin sistem.

## Proses sebenarnya

| Tahap | Input | Output | Punya qty? |
|---|---|---|---|
| Desain | Referensi baju jadi (Instagram, Pinterest) | Desain yang dipakai | Tidak |
| Pembuatan Pola | Desain + kertas | Gambar pola di atas kertas | Tidak |
| Persiapan & Cutting | Kain sesuai kebutuhan, ditumpuk | Kain terpotong | Ya |
| Sewing | Kain potongan + perlengkapan (kancing, resleting, benang) | Baju jadi mentahan | Ya |
| QC & Packing | Baju mentahan | Baju siap jual (hangtag, label, disetrika, terbungkus) | Ya |

Alur sampel memakai urutan yang sama tetapi hanya untuk 1 pcs. Setelah sampel jadi dan
benar, Cutting → Sewing → QC diulang untuk produksi massal.

Wujud fisik tiap keluaran: **Desain** adalah gambar baju jadi dari referensi, **Pola**
adalah kertas yang sudah digambar, **Sampel** adalah baju fisik yang benar-benar jadi.

### Di luar cakupan pekerjaan ini

Kalau produksi berasal dari pesanan konsumen ada dua proses tambahan: **Pemesanan**
sebelum Desain, dan **Pengiriman** setelah Packing (packing untuk pengiriman ini di luar
proses produksi standar).

```
Pemesanan → [ Desain → Pola → Sampel → Massal ] → Pengiriman
             └──────── cakupan kode ────────┘
```

Keduanya sudah ditangani modul Order (`Order`, status `shipped`) dan **tidak diikat ke
modul produksi** dalam pekerjaan ini. Dicatat di sini supaya diagram alur di dokumen TA
lengkap.

## Design

### 1. Urutan tahap berfase

```
Desain  →  Pola  →  ┌─ SAMPEL (1 pcs) ─┐  →  ┌─ MASSAL (N pcs) ────────┐
 (tanpa qty)        │ Cutting          │     │ Cutting                 │
                    │ Sewing           │     │ Sewing                  │
                    │ QC Sampel        │     │ QC & Packing ■ selesai  │
                    └──────────────────┘     └─────────────────────────┘
                              ↑
                    disetujui dulu, baru massal terbuka
```

Desain dan Pola berjalan sekali (fase `common`) dan tidak membawa angka qty — keduanya
tidak menghasilkan pcs, hanya status mulai/selesai. Cutting, Sewing, dan QC & Packing
membawa qty dan muncul di dua fase.

**Batch 1 pcs tidak punya fase sampel.** Kalau `planned_qty = 1`, produk yang dibuat itu
sendiri adalah sampelnya, jadi batch hanya berisi fase `common` + fase `mass` berisi
1 pcs. Halaman batch menampilkan catatan: *"Batch 1 pcs — produk ini sekaligus
sampelnya."*

### 2. Penamaan tahap akhir

Tahap akhir bernama `qc_packing` di kedua fase; **fase** yang menentukan labelnya:

| Fase | Label tampil |
|---|---|
| `sample` | QC Sampel |
| `mass` | QC & Packing |

Satu nilai enum, bukan dua, supaya pemetaan kategori mesin cukup satu baris per kategori
dan alat QC tetap bisa dipilih saat memeriksa sampel. Pekerjaan packing (hangtag, label,
setrika, plastik) hanya relevan di fase massal.

### 3. Skema database

```
production_stages
  + phase       enum('common','sample','mass')
  + sort_order  int
  ~ stage       enum('design','pola','cutting','sewing','qc_packing')

productions
  + sample_approved_at    timestamp nullable
  + sample_revision_count int default 0

production_sample_revisions            (tabel baru)
  id, production_id, revision_no, notes, user_id, created_at

machine_categories
  ~ stage       enum('design','pola','cutting','sewing','qc_packing') nullable
```

`sort_order` wajib ada karena nama tahap sekarang berulang di dua fase. `array_search`
atas `ProductionStage::STAGES` yang dipakai `Production::stageUnlocked()`,
`stageMaxInput()`, dan `stageMinOutput()` tidak lagi bisa menemukan tahap sebelum/sesudah
secara unik, dan ketiga helper itu beralih ke `sort_order`.

Tahap dibuat oleh `ProductionController@store` dengan `sort_order` berurutan mulai 1:

| sort_order | phase | stage | input awal |
|---|---|---|---|
| 1 | common | design | — |
| 2 | common | pola | — |
| 3 | sample | cutting | 1 |
| 4 | sample | sewing | 0 |
| 5 | sample | qc_packing | 0 |
| 6 | mass | cutting | `planned_qty` |
| 7 | mass | sewing | 0 |
| 8 | mass | qc_packing | 0 |

Baris 3–5 dilewati kalau `planned_qty = 1`; `sort_order` tetap berurutan tanpa lubang.

### 4. Aturan gate

| Transisi | Syarat |
|---|---|
| Desain → Pola | Desain berstatus `completed` |
| Pola → Cutting sampel | Pola berstatus `completed` |
| Antar tahap sampel | Tahap sebelumnya `completed`; qty dikunci 1 |
| **QC Sampel → Cutting massal** | **`sample_approved_at` terisi** |
| Pola → Cutting massal (batch 1 pcs) | Pola berstatus `completed` |
| Antar tahap massal | Gate 50%: output tahap sebelumnya ≥ `gateQty()` |

Aturannya jadi dua jenis:

- **Tahap tanpa qty dan tahap fase sampel** — gate berbasis status: tahap sebelumnya
  harus `completed`.
- **Tahap fase massal selain yang pertama** — gate 50% yang sudah ada dipertahankan
  (`Production::gateQty()` = `ceil(0.5 × planned_qty)`).

Gate sampel → massal adalah **gate keras**: tidak ada jalan memulai cutting massal selama
sampel belum disetujui. Ini terjemahan langsung dari aturan bisnisnya — jangan potong
banyak sebelum satu yang benar terbukti jadi.

### 5. Persetujuan dan revisi sampel

Tahap QC Sampel punya dua aksi selain tombol biasa:

- **`approve_sample`** — mengisi `productions.sample_approved_at = now()`. Fase massal
  terbuka. Hanya boleh saat tahap QC sampel sudah `completed` dengan `output_qty = 1`.
- **`revise_sample`** — wajib mengisi catatan. Efeknya:
  - `sample_revision_count` naik 1;
  - satu baris `production_sample_revisions` ditulis (`revision_no` = nilai baru
    `sample_revision_count`, `notes`, `user_id` = operator);
  - ketiga tahap fase sampel dikembalikan ke `status = 'pending'`, `output_qty = 0`,
    `started_at` dan `finished_at` dikosongkan; `input_qty` cutting sampel kembali 1;
  - `sample_approved_at` dikosongkan kalau sebelumnya sempat terisi.

Riwayat revisi ditampilkan di halaman batch, terbaru di atas.

### 6. Stok dan pemakaian bahan

Selesainya tahap **QC & Packing fase massal** tetap satu-satunya pemicu penyelesaian
batch, dan tetap dijaga agar hanya berjalan sekali lewat `status !== 'completed'`.

- `actual_qty` = `output_qty` QC & Packing massal.
- `Product.stock += actual_qty`. **Sampel tidak masuk stok** — sampel dipakai sebagai
  contoh atau display, bukan barang jual.
- Pemakaian bahan per baris resep = `qty_required × (actual_qty + sample_units)`.

`sample_units` dihitung dari **baris tahap yang benar-benar ada**, bukan dari `planned_qty`:

```
tidak ada baris phase = 'sample'  → sample_units = 0
ada baris phase = 'sample'        → sample_units = 1 + sample_revision_count
```

Untuk batch yang dibuat sistem ini, keduanya setara: `store()` hanya membuat fase sampel kalau
`planned_qty > 1`. Bedanya muncul pada batch lama hasil migrasi, yang punya target massal tapi tidak
punya fase sampel — kainnya memang tidak pernah dipotong untuk sampel, jadi tidak boleh dibebani.

Setiap revisi berarti satu sampel dipotong ulang dari kain baru, jadi kainnya benar-benar
terpakai dan ikut dihitung. Batch tanpa revisi memakai bahan tepat 1 pcs sampel.

Contoh, target 50 pcs dengan resep 3 m kain per pcs, sampel lolos tanpa revisi:

```
sampel   1 pcs   → contoh, tidak dijual
massal  50 pcs   → stok +50
bahan   51 × 3 m = 153 m dipotong
```

Kalau sampel direvisi dua kali sebelum disetujui, bahan menjadi `53 × 3 m = 159 m`.

Angka target di layar sama dengan angka yang masuk stok; sampel selalu tambahan di luar
target.

### 7. Aturan tepi

**Status batch.** `Production::STATUSES` tidak berubah. Status `qc` sekarang diset saat
tahap QC & Packing **fase massal dimulai** (`action = 'start'`), bukan saat selesai —
sebelumnya diset ketika tahap `qc` yang terpisah selesai, dan tahap itu tidak ada lagi.
Menyelesaikan QC & Packing massal langsung memindahkan batch ke `completed`.

**Tahap tanpa qty.** `design` dan `pola` tidak menampilkan kolom input/output maupun bar
progres di halaman batch — hanya tombol Mulai dan Selesai. `stageProgressPct()` tidak
dipanggil untuk keduanya.

**Qty fase sampel dikunci.** Ketiga tahap fase sampel menampilkan input dan output
sebagai kolom baca-saja bernilai 1. Validasi `updateStage` menolak nilai selain 1 untuk
tahap berfase `sample`.

**Mengubah target batch.** `ProductionController@update` menolak perubahan `planned_qty`
yang melintasi batas 1 ↔ lebih dari 1, karena struktur tahap sudah terlanjur dibuat
dengan atau tanpa fase sampel. Pesannya: *"Target tidak bisa diubah dari 1 pcs ke
produksi massal (atau sebaliknya). Buat batch baru."* Perubahan target di dalam rentang
yang sama tetap boleh, dan penjaga `planned_qty` minimum yang sudah ada tetap berlaku.

### 8. Kategori mesin

`MachineCategory.stage` memakai enum tahap yang sama. Pemetaan di `DatabaseSeeder`:

| Kategori | `stage` |
|---|---|
| Mesin Potong (`CAT-CUT`) | `cutting` |
| Mesin Jahit (`CAT-SEW`) | `sewing` |
| Alat QC & Finishing (`CAT-QC`) | `qc_packing` |
| Mesin Packing (`CAT-PACK`) | `qc_packing` |

Dua kategori memetakan ke `qc_packing`, jadi dropdown mesin tahap itu memuat keduanya.
Tahap `design` dan `pola` tidak punya mesin; peringatan "belum ada mesin aktif untuk
tahap ini" di `show.blade.php` tidak ditampilkan untuk keduanya.

### 9. Migrasi data lama

Migrasi mengubah enum `stage`, jadi baris lama harus dipetakan:

- `packing` → `qc_packing`;
- baris `qc` lama dihapus (pekerjaannya melebur ke `qc_packing`);
- baris `sample` lama dihapus;
- baris `pola` disisipkan setelah `design`;
- `phase` dan `sort_order` diisi mundur: `design`/`pola` → `common`, sisanya → `mass`;
- **`sample_approved_at` diisi untuk setiap batch lama dengan `planned_qty > 1`.**

Butir terakhir itu wajib, bukan kerapian. Batch lama tidak punya baris fase sampel, sehingga tidak
punya tahap QC sampel yang bisa menyetujuinya — tanpa pengisian ini, cutting massalnya terkunci
selamanya menunggu persetujuan yang tombolnya tidak pernah ada. Batch tersebut dibuat di bawah model
lama yang tidak mengenal sampel, jadi memperlakukan sampelnya sebagai sudah disetujui adalah
rekonstruksi yang jujur.

Backfill ini best-effort untuk database pengembangan. Jalur yang direkomendasikan tetap
`php artisan migrate:fresh --seed`, karena isi database ini data demo dan batch lama tidak
punya fase sampel yang bisa direkonstruksi.

## Files

| File | Perubahan |
|---|---|
| `database/migrations/2026_09_03_000001_restructure_production_stages.php` | Baru — kolom `phase`/`sort_order`, enum `stage`, kolom sampel di `productions`, tabel revisi, enum `machine_categories.stage` |
| `app/Models/ProductionStage.php` | `STAGES`, `PHASES`, `phase`/`sort_order` fillable, label per fase |
| `app/Models/Production.php` | Helper urutan pindah ke `sort_order`; gate dua jenis; `hasSamplePhase()`, `sampleApproved()`, `sampleUnits()`; relasi `sampleRevisions()` |
| `app/Models/ProductionSampleRevision.php` | Baru |
| `app/Http/Controllers/Admin/ProductionController.php` | `store()` membangun tahap berfase; `updateStage()` gate baru, qty sampel dikunci, aksi `approve_sample`/`revise_sample`; `update()` menolak lintas batas 1 ↔ massal; `completeBatch()` hitung bahan sampel |
| `resources/views/admin/productions/show.blade.php` | Dua blok fase, label per fase, tombol setujui/revisi, riwayat revisi, catatan batch 1 pcs |
| `resources/views/admin/productions/create.blade.php` | Info fase sampel muncul saat target > 1 |
| `database/seeders/DatabaseSeeder.php` | `stage` kategori mesin |

## Testing

Dikerjakan TDD. Dua berkas tes yang ada menulis `'sample'` dan `'packing'` secara literal
sehingga akan gagal total; keduanya ditulis ulang mengikuti alur baru.

`tests/Unit/ProductionStageFlowTest.php`:

- urutan tahap batch > 1 pcs adalah 8 baris dengan `phase` dan `sort_order` yang benar;
- batch 1 pcs hanya berisi 5 baris, tanpa fase sampel;
- tahap tanpa qty (`design`, `pola`) memakai gate status, bukan gate 50%;
- tahap massal selain yang pertama tetap memakai gate 50%;
- `stageMaxInput`/`stageMinOutput` mencari tetangga lewat `sort_order`, bukan nama tahap.

`tests/Feature/Admin/ProductionFlowTest.php`:

- cutting massal ditolak selama `sample_approved_at` kosong;
- cutting massal diterima setelah sampel disetujui;
- `revise_sample` tanpa catatan ditolak; dengan catatan mengembalikan ketiga tahap sampel
  ke `pending`, menaikkan `sample_revision_count`, dan menulis baris riwayat;
- `approve_sample` ditolak kalau QC sampel belum selesai;
- qty tahap fase sampel selain 1 ditolak;
- selesainya QC & Packing massal menambah stok sebesar `actual_qty` saja;
- bahan yang dipotong = `(actual_qty + 1) × qty_required` untuk batch berfase sampel tanpa
  revisi, `(actual_qty + 3) × qty_required` setelah dua kali revisi, dan
  `actual_qty × qty_required` untuk batch 1 pcs;
- mengubah `planned_qty` dari 1 ke lebih dari 1 (dan sebaliknya) ditolak;
- penyelesaian batch tetap hanya berjalan sekali.
