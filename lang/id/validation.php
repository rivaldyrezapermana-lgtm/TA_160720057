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
    'array' => 'Kolom :attribute harus berupa array.',
    'between' => [
        'array' => 'Kolom :attribute harus memiliki antara :min dan :max item.',
        'file' => 'Kolom :attribute harus antara :min dan :max kilobita.',
        'numeric' => 'Kolom :attribute harus antara :min dan :max.',
        'string' => 'Kolom :attribute harus antara :min dan :max karakter.',
    ],
    'boolean' => 'Kolom :attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => 'Kolom :attribute bukan tanggal yang valid.',
    'different' => 'Kolom :attribute dan :other harus berbeda.',
    'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'file' => 'Kolom :attribute harus berupa file.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => 'Kolom :attribute harus berupa angka bulat.',
    'max' => [
        'array' => 'Kolom :attribute tidak boleh lebih dari :max item.',
        'file' => 'Kolom :attribute tidak boleh lebih dari :max kilobita.',
        'numeric' => 'Kolom :attribute tidak boleh lebih dari :max.',
        'string' => 'Kolom :attribute tidak boleh lebih dari :max karakter.',
    ],
    'mimes' => 'Kolom :attribute harus berupa file bertipe: :values.',
    'min' => [
        'array' => 'Kolom :attribute harus memiliki minimal :min item.',
        'file' => 'Kolom :attribute minimal harus :min kilobita.',
        'numeric' => 'Kolom :attribute minimal harus :min.',
        'string' => 'Kolom :attribute minimal harus :min karakter.',
    ],
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'required' => 'Kolom :attribute wajib diisi.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => 'Kolom :attribute gagal diunggah.',

    /*
    |--------------------------------------------------------------------------
    | Nama Kolom Khusus
    |--------------------------------------------------------------------------
    |
    | Memetakan nama kolom teknis ke label yang dimengerti pengguna.
    |
    */

    'attributes' => [
        'name' => 'Nama',
        'slug' => 'Slug',
        'description' => 'Deskripsi',
        'sku' => 'SKU',
        'category_id' => 'Kategori',
        'price' => 'Harga',
        'stock' => 'Stok',
        'image' => 'Foto',
        'code' => 'Kode',
        'unit' => 'Satuan',
        'min_stock' => 'Minimum Stok',
        'unit_cost' => 'Harga per Unit',
        'contact_person' => 'Contact Person',
        'phone' => 'Telepon',
        'email' => 'Email',
        'address' => 'Alamat',
        'role' => 'Role',
        'password' => 'Password',
    ],

    'custom' => [],
];
