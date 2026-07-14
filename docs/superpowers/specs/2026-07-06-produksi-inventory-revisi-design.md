# Revisi Modul Produksi & Inventaris — Design

**Tanggal:** 2026-07-06
**Status:** Approved (menunggu review spec)
**Konteks:** Labasa (Laravel 11, TA). Lima revisi yang semuanya berpusat pada modul produksi + inventaris.

## Ringkasan Revisi

1. Tahap produksi bisa saling tumpang-tindih: tahap berikutnya boleh mulai begitu tahap sebelumnya mencapai 50% target, dengan pencatatan **input/output qty** per tahap.
2. Catat **bahan baku** dan **mesin** yang dipakai saat produksi.
3. Tampilkan **perhitungan HPP** sebelum admin memasukkan harga jual produk.
4. Buat **kategori mesin** agar mesin masuk ke proses produksi sesuai fungsinya (kategori → tahap).
5. **Stok tidak bisa diubah manual** — hanya berubah lewat transaksi (penjualan, pembelian, produksi).

## Prinsip Aliran Stok (menyatukan #2 & #5)

Produksi menjadi jembatan antara dua jenis stok:

- **Stok bahan baku (Material.stock):** naik dari **pembelian** (`Purchase::receive`), turun dari **konsumsi produksi** (batch selesai).
- **Stok produk (Product.stock):** naik dari **output produksi** (batch selesai), turun dari **penjualan** (`Order` confirm).

Tidak ada field stok yang boleh diedit tangan setelah pembuatan awal.

---

## 1. Perubahan Database

| Perubahan | Detail |
|---|---|
| **baru** `machine_categories` | `id, name, code (unique), stage (nullable enum tahap), notes, timestamps`. Satu kategori dipetakan ke satu tahap produksi. |
| `production_machines` +kolom | `machine_category_id` (nullable FK ke `machine_categories`, `nullOnDelete`) |
| `production_stages` +kolom | `input_qty` (int, default 0), `output_qty` (int, default 0), `production_machine_id` (nullable FK, `nullOnDelete`) |
| **buat ulang** `production_materials` | `id, production_id (FK cascade), material_id (FK cascade), qty_used (int), timestamps`. Mencatat konsumsi aktual per batch. |
| `products` +kolom | `hpp` decimal(12,2) default 0. Snapshot biaya, dihitung ulang tiap simpan. |

Enum `stage` pada `machine_categories` memakai daftar yang sama dengan `ProductionStage::STAGES` = `['design','sample','cutting','sewing','qc','packing']`.

Migrasi ditulis idempoten dan aman untuk `migrate:fresh --seed` (MySQL default; SQLite in-memory untuk test).

---

## 2. Revisi #1 — Tahap tumpang-tindih dengan input/output qty

### Model data
`ProductionStage` mendapat `input_qty`, `output_qty`, `production_machine_id`.

- **input_qty**: jumlah unit yang masuk ke tahap. Default tahap pertama = `production.planned_qty`; tahap berikutnya default = `output_qty` tahap pendahulunya.
- **output_qty**: jumlah unit selesai di tahap ini. Batasan: `output_qty <= input_qty` (menangkap loss/cacat per tahap).

### Aturan gate 50%
Fungsi bantu `Production` / `ProductionStage`:

- `gateQty()` = `ceil(0.5 * planned_qty)`.
- Tahap ke-`n` boleh **Mulai** hanya jika `output_qty` tahap ke-`n-1` `>=` `gateQty()`. Tahap pertama selalu boleh mulai.
- `progressPct(stage)` = `planned_qty > 0 ? round(output_qty / planned_qty * 100) : 0`.

### Status tahap
Tetap `pending | in_progress | completed`.
- `pending → in_progress`: saat pertama kali input output (atau tombol Mulai) dan gate terbuka. Set `started_at`.
- `in_progress → completed`: saat admin menandai selesai; `output_qty` final tercatat, `finished_at` diisi.

### Penyelesaian batch
Saat tahap **packing** ditandai selesai (atau `output_qty` packing final direkam):
- `production.status = 'completed'`, `actual_qty = packing.output_qty`, `end_date` diisi bila kosong.
- **Kredit stok produk**: `Product.stock += actual_qty`.
- **Konsumsi bahan** (lihat §3): tulis `production_materials` dan kurangi `Material.stock`.

Idempotensi: penyelesaian batch hanya boleh menjalankan kredit stok & konsumsi **sekali** (guard: jika `production.status` sudah `completed`, jangan ulangi).

### Endpoint
`PATCH /admin/productions/{production}/stages/{stage}` (`admin.productions.stage`) diperluas: menerima `input_qty`, `output_qty`, `production_machine_id`, dan `action` (`start`/`save`/`finish`). Validasi server menegakkan `output_qty <= input_qty`, gate 50%, dan kecocokan kategori mesin dengan tahap.

---

## 3. Revisi #4 (kategori mesin) & #2 (mesin/bahan dipakai)

### Kategori mesin
- CRUD baru `MachineCategoryController` di `/admin/machine-categories` (+ endpoint datatables `datatables.machine-categories`), mengikuti pola resource admin + jQuery DataTables yang ada.
- Model `MachineCategory` (`name, code, stage, notes`; `hasMany(ProductionMachine)`).
- Form mesin (`production-machines/create|edit`) mendapat dropdown **Kategori**. `ProductionMachineController::rules()` + validasi `machine_category_id` nullable exists.

### Mesin per tahap
- Pemilihan mesin **pindah dari batch ke tiap tahap**. Di halaman batch (`productions/show`), tiap tahap punya dropdown mesin yang **hanya menampilkan mesin dengan `category.stage == stage.stage`**.
- Kolom lama `productions.production_machine_id` **dibiarkan** (nullable) demi kompatibilitas tetapi tidak lagi dipakai di UI; select mesin dihapus dari `productions/create`.

### Bahan terpakai
- Dihitung otomatis dari resep produk: untuk tiap `ProductMaterial`, `qty_used = qty_required * actual_qty`.
- Ditulis ke `production_materials` saat batch selesai, dan `Material.stock` dikurangi sejumlah itu (guard idempoten).
- Panel **"Bahan Terpakai"** di `productions/show` menampilkan daftar bahan + qty (estimasi sebelum selesai, aktual setelah selesai).

---

## 4. Revisi #3 — HPP sebelum harga

- **Perhitungan:** `HPP = Σ(qty_required × material.unit_cost)` dari resep produk. Hanya komponen bahan (tanpa tenaga kerja/overhead).
- **UI:** panel HPP live di `products/create|edit`. Saat admin mencentang bahan & mengatur qty, JS menjumlahkan biaya per bahan (unit_cost tiap material dikirim ke view via `data-*` atau objek JS) dan menampilkan total HPP.
- **Peringatan:** jika `price < HPP`, tampilkan banner ⚠ (client-side). Server juga menambahkan **flash warning** bila tersimpan dengan `price < hpp`, **tetapi simpan tetap berhasil** (tidak diblokir).
- **Snapshot:** `products.hpp` dihitung ulang di server tiap `store`/`update` (setelah `syncMaterials`), agar list/laporan bisa menampilkan margin tanpa join berat.

Helper: `Product::computeHpp(): float` yang menjumlahkan resep (memuat `materials.material`).

---

## 5. Revisi #5 — Kunci stok

- **Produk:** field "Stok Awal" hanya muncul & bisa diisi di `products/create`. Di `products/edit`, field stok **read-only** (menampilkan nilai saat ini) dan `ProductController::update` **mengabaikan** nilai `stock` yang dikirim — nilai DB dipertahankan.
- **Bahan:** sama — `materials/create` boleh isi stok awal; `materials/edit` read-only dan `MaterialController::update` mengabaikan `stock`.
- Setelah itu stok hanya bergerak lewat: order (produk ↓), `Purchase::receive` (bahan ↑), penyelesaian produksi (produk ↑ / bahan ↓).
- **Di luar cakupan:** `ProductSize.stock` bersifat deskriptif dan tidak terhubung ke transaksi; dibiarkan tetap bisa diedit.

---

## 6. Model & File Terdampak

**Model baru:** `MachineCategory`.
**Model diubah:** `ProductionMachine` (+category), `ProductionStage` (+input/output/machine, helper), `Production` (relasi `productionMaterials`, helper gate/penyelesaian), `Product` (+`hpp`, `computeHpp`).
**Model dipakai ulang:** `ProductMaterial` (resep untuk HPP & konsumsi).

**Controller baru:** `Admin\MachineCategoryController`.
**Controller diubah:** `ProductionController` (store stages input awal, `updateStage` berbasis qty + penyelesaian + konsumsi), `ProductionMachineController` (+kategori), `ProductController` (HPP + kunci stok), `MaterialController` (kunci stok).

**View diubah:** `productions/show` (timeline → input/output + mesin per tahap + panel bahan), `productions/create` (hapus select mesin batch), `products/create|edit` (panel HPP + kunci stok), `materials/create|edit` (kunci stok), `production-machines/create|edit` (+dropdown kategori). **View baru:** `machine-categories/{index,create,edit}`.

**Routing:** daftarkan resource `machine-categories` + `datatables.machine-categories` di grup admin.

**Seeder:** tambah beberapa `MachineCategory` (mis. Mesin Potong→cutting, Mesin Jahit→sewing) tertaut ke mesin yang ada; backfill `products.hpp`.

---

## 7. Pengujian (TDD untuk bagian logika)

- **HPP:** `Product::computeHpp()` menjumlahkan resep dengan benar; `hpp` tersimpan saat store/update.
- **Gate 50%:** tahap berikutnya terkunci saat output pendahulu `< ceil(0.5·planned)`, terbuka saat `>=`.
- **Batasan qty:** `output_qty <= input_qty` ditegakkan; penyelesaian packing mengkredit stok produk tepat sekali.
- **Konsumsi bahan:** penyelesaian batch mengurangi `Material.stock` = `qty_required × actual_qty` dan menulis `production_materials`; idempoten.
- **Kunci stok:** `ProductController::update` & `MaterialController::update` mengabaikan `stock` yang dikirim (nilai DB tak berubah).

Test feature memakai SQLite in-memory (uncomment baris di `phpunit.xml`). String UI dalam Bahasa Indonesia mengikuti konvensi yang ada.

## 8. Di Luar Cakupan / YAGNI

- Tenaga kerja/overhead pada HPP (dipilih: bahan saja).
- Blokir harga di bawah HPP (dipilih: peringatan saja).
- Penguncian `ProductSize.stock`.
- Banyak mesin per tahap (satu mesin per tahap sudah cukup).
- Konsumsi bahan bertahap per stage (dipilih: sekaligus saat batch selesai).
