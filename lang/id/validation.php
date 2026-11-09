<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Baris Bahasa Validasi (Bahasa Indonesia)
|--------------------------------------------------------------------------
|
| Pesan kesalahan validasi. Hanya rule yang dipakai aplikasi yang
| diterjemahkan; rule lain memakai fallback bahasa Inggris (lang/en).
|
*/

return [
    'accepted' => 'Kolom :attribute harus disetujui.',
    'after_or_equal' => 'Kolom :attribute harus berupa tanggal yang sama dengan atau setelah :date.',
    'array' => 'Kolom :attribute harus berupa daftar.',
    'between' => [
        'array' => 'Kolom :attribute harus memiliki :min sampai :max item.',
        'file' => 'Ukuran :attribute harus antara :min dan :max kilobita.',
        'numeric' => 'Kolom :attribute harus antara :min dan :max.',
        'string' => 'Kolom :attribute harus terdiri atas :min sampai :max karakter.',
    ],
    'boolean' => 'Kolom :attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
    'different' => 'Kolom :attribute dan :other harus berbeda.',
    'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'file' => 'Kolom :attribute harus berupa berkas.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'max' => [
        'array' => 'Kolom :attribute tidak boleh lebih dari :max item.',
        'file' => 'Ukuran :attribute tidak boleh lebih dari :max kilobita.',
        'numeric' => 'Kolom :attribute tidak boleh lebih dari :max.',
        'string' => 'Kolom :attribute tidak boleh lebih dari :max karakter.',
    ],
    'mimes' => 'Kolom :attribute harus berupa berkas dengan format: :values.',
    'min' => [
        'array' => 'Kolom :attribute harus memiliki minimal :min item.',
        'file' => 'Ukuran :attribute minimal :min kilobita.',
        'numeric' => 'Kolom :attribute minimal :min.',
        'string' => 'Kolom :attribute minimal :min karakter.',
    ],
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'required' => 'Kolom :attribute wajib diisi.',
    'required_with' => 'Kolom :attribute wajib diisi jika :values diisi.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'unique' => ':Attribute sudah digunakan.',
    'uploaded' => ':Attribute gagal diunggah.',

    /*
    |--------------------------------------------------------------------------
    | Nama Kolom Khusus
    |--------------------------------------------------------------------------
    |
    | Memetakan nama kolom teknis ke label yang dimengerti pengguna.
    |
    */

    'attributes' => [
        'name' => 'nama',
        'description' => 'deskripsi',
        'sku' => 'SKU',
        'category_id' => 'kategori',
        'product_id' => 'produk',
        'price' => 'harga',
        'stock' => 'stok',
        'image' => 'foto',
        'code' => 'kode',
        'status' => 'status',
        'notes' => 'catatan',
        'note' => 'catatan',
        'keterangan' => 'keterangan',
        'sort_order' => 'urutan',

        'size' => 'ukuran',
        'qty' => 'jumlah',
        'sizes' => 'ukuran',
        'sizes.*' => 'jumlah per ukuran',
        'sizes.*.size' => 'ukuran',
        'sizes.*.chest_cm' => 'lingkar dada',
        'sizes.*.length_cm' => 'panjang',
        'sizes.*.sleeve_cm' => 'lengan',
        'sizes.*.stock' => 'stok ukuran',
        'materials' => 'bahan',
        'materials.*.qty_required' => 'jumlah kebutuhan bahan',

        'unit' => 'satuan',
        'min_stock' => 'stok minimum',
        'unit_cost' => 'harga per satuan',
        'supplier_id' => 'pemasok',
        'purchase_date' => 'tanggal pembelian',
        'items' => 'bahan',
        'contact_person' => 'narahubung',

        'production_machine_id' => 'mesin',
        'production_machine_ids' => 'mesin',
        'production_machine_ids.*' => 'mesin',
        'machine_category_id' => 'kategori mesin',
        'capacity' => 'kapasitas',
        'stage' => 'tahap',
        'planned_qty' => 'target jumlah',
        'actual_qty' => 'jumlah aktual',
        'input_qty' => 'jumlah masuk',
        'output_qty' => 'jumlah hasil',
        'sample_size' => 'ukuran sampel',
        'size_qty' => 'jumlah per ukuran',
        'size_qty.*' => 'jumlah per ukuran',
        'size_output' => 'hasil per ukuran',
        'size_output.*' => 'hasil per ukuran',
        'start_date' => 'tanggal mulai',
        'end_date' => 'tanggal selesai',
        'sketches' => 'sketsa',
        'sketches.*' => 'sketsa',
        'demand' => 'permintaan',

        'phone' => 'telepon',
        'email' => 'email',
        'address' => 'alamat',
        'role' => 'peran',
        'password' => 'kata sandi',
        'current_password' => 'kata sandi saat ini',
        'new_password' => 'kata sandi baru',

        'recipient' => 'nama penerima',
        'shipping_address' => 'alamat pengiriman',
        'payment_method' => 'metode pembayaran',
        'proof' => 'bukti pembayaran',
        'body' => 'pesan',
    ],

    'custom' => [],
];
