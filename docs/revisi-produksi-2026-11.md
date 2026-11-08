# Revisi Sistem Produksi Labasa (November 2026)

Jalankan `php artisan migrate` setelah menarik perubahan ini.

## Perubahan per revisi

| No | Revisi | Perubahan utama |
|---|---|---|
| 1 | Master ukuran | Tabel `sizes`, `App\Models\Size`, `SizeSeeder`, `Admin\SizeController`, halaman `admin/sizes`, menu sidebar. Form produk dan urutan filter toko membaca master. Ukuran yang sudah dipakai tidak dapat diganti namanya atau dihapus. |
| 2 | Pilih ukuran saat produksi | Form buat dan ubah batch memakai centang per ukuran dengan jumlah masing-masing. Rincian disimpan di tabel `production_sizes` yang sudah ada. Ukuran master yang belum menjadi varian produk dibuat otomatis dengan stok 0. |
| 3 | Log produksi | Tabel `log_produksi`, `App\Models\ProductionLog`, tombol Pause dan kartu Log Produksi pada halaman detail. Waktu tampil dalam WIB. Pause hanya mencatat, status batch tidak berubah. |
| 4 | Perbaikan kata | Lihat daftar di bawah. |
| 5 | ID mesin | Kolom unik `kode_mesin` menggantikan `code`. Kode dibuat otomatis (`MSN-001`) dan tidak dapat diubah. Tampilan memakai `display_name`, mis. `MSN-001 — Mesin Jahit Juki A`. |
| 6 | Tombol di bawah | Form tahap produksi, unggah sketsa, QC sampel, filter laporan produksi, dan tombol Buat Pesanan. |
| 7 | Persediaan Fuzzy | Input persediaan dibaca dari `products.stock` di server dan tampil hanya-baca. Nilai `stock` yang dikirim lewat form diabaikan. Halaman daftar rekomendasi juga memakai stok produk. |

## Daftar perubahan kata (revisi 4)

Satu-satunya salah ketik nyata: `id` menjadi `ID` pada `admin/categories/index`. Sisanya penyeragaman istilah dan bahasa formal.

### Istilah yang diseragamkan di seluruh aplikasi

| Sebelum | Sesudah |
|---|---|
| Edit | Ubah |
| Size | Ukuran |
| Qty, Target Qty, Aktual Qty | Jumlah, Target Jumlah, Jumlah Aktual |
| Unit (satuan bahan) | Satuan |
| Plan | Rencana |
| Supplier | Pemasok |
| Contact Person, Contact, Kontak | Narahubung |
| User | Pengguna |
| Role | Peran |
| Password | Kata Sandi |
| No. HP | Telepon |
| PO, Purchase Order | Pesanan Pembelian |
| Pending | Menunggu |
| Quality Check | Pemeriksaan Kualitas |
| Dashboard | Dasbor |
| Master Data | Data Master |
| Live Chat | Chat Langsung |
| Online | Daring |
| Upload, diupload, terupload | Unggah, diunggah, berhasil diunggah |
| Update | Perbarui |
| Checkout | Pembayaran, Lanjut ke Pembayaran |
| Ongkir | Ongkos Kirim |
| Export CSV | Ekspor CSV |
| Best Seller | Produk Terlaris |
| Range | Rentang |
| storefront | toko |
| workshop | rumah produksi |
| File | Berkas |
| auto (placeholder) | Otomatis |
| Berlangsung (status tahap) | Berjalan |
| Min Stok, Minimum Stok | Stok Minimum |

### Bahasa informal menjadi formal

| Sebelum | Sesudah |
|---|---|
| tidak bisa, bisa | tidak dapat, dapat |
| kalau | jika |
| dulu | terlebih dahulu |
| punya | memiliki |
| pakai, memakai, dipakai | menggunakan, digunakan |
| jadi (penghubung) | sehingga |
| lewat | melalui |
| Yuk pilih produk dulu. | Silakan pilih produk terlebih dahulu. |
| Coba ubah kata kunci atau kategori. | Silakan ubah kata kunci atau kategori. |
| Tanya langsung sebelum order — kami balas selama jam toko. | Tanyakan langsung sebelum memesan — kami membalas selama jam operasional toko. |
| perlu re-stock | perlu pengisian ulang |
| Hapus? | Hapus pemasok ini? |

### Tampilan lain

- Heading dan tombol memakai huruf kapital di awal kata, mis. `Keranjang Kosong`, `Lihat Bukti`.
- Pesan dan teks bantuan berupa kalimat lengkap dengan titik di akhir.
- Tombol `Simpan` pada form ubah menjadi `Simpan Perubahan`.
- Dropdown status pesanan dan produksi menampilkan label Indonesia, bukan nilai mentah seperti `in_progress`. Nilai yang dikirim tidak berubah.
- Teks bawaan DataTables berbahasa Inggris pada tabel kosong kini berbahasa Indonesia.
- `lang/id/validation.php`: aturan `after_or_equal`, `not_regex`, dan `required_with` ditambahkan; nama atribut dilengkapi dan ditulis huruf kecil, mis. `Kolom nama wajib diisi.`

### Istilah yang sengaja dipertahankan

Pause, Batch, Sampel, Desain, Pola, Cutting, Sewing, QC & Packing, Massal, HPP, SKU, Email, Chat, serta istilah Fuzzy Mamdani. Peran `Pembeli` juga tetap, sedangkan teks umum memakai `Pelanggan`.
