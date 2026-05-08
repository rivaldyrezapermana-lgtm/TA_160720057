<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\SalesHistory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Users ----------------------------------------------------
        User::insert([
            ['name'=>'Pemilik Toko','email'=>'admin@labasa.test','password'=>Hash::make('password'),'role'=>'admin','phone'=>'081200000001','address'=>'Surabaya','created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Karyawan Produksi','email'=>'karyawan@labasa.test','password'=>Hash::make('password'),'role'=>'karyawan','phone'=>'081200000002','address'=>'Surabaya','created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Siti Nurhaliza','email'=>'pembeli@labasa.test','password'=>Hash::make('password'),'role'=>'pembeli','phone'=>'081200000003','address'=>'Jl. Pahlawan No. 123, Surabaya','created_at'=>now(),'updated_at'=>now()],
        ]);

        // ---- Categories -----------------------------------------------
        $cats = ['Gamis','Koko','Tunik','Hijab'];
        foreach ($cats as $c) {
            Category::create(['name'=>$c,'slug'=>Str::slug($c),'description'=>$c.' Toko Labasa']);
        }

        // ---- Products + sizes -----------------------------------------
        $products = [
            ['Gamis Anaya Navy', 1, 225000, 48],
            ['Koko Modern Sage', 2, 180000, 32],
            ['Tunik Basic Cream', 3, 145000, 21],
            ['Hijab Pashmina Plain', 4, 50000, 86],
        ];
        foreach ($products as $i => $p) {
            $product = Product::create([
                'category_id' => $p[1],
                'name'        => $p[0],
                'sku'         => 'SKU-'.str_pad($i+1, 4, '0', STR_PAD_LEFT),
                'description' => $p[0].' — produk demo Toko Labasa.',
                'price'       => $p[2],
                'stock'       => $p[3],
                'is_active'   => true,
            ]);
            foreach (['S','M','L','XL'] as $j => $size) {
                ProductSize::create([
                    'product_id'=>$product->id,
                    'size'=>$size,
                    'chest_cm'=>92 + $j*4,
                    'length_cm'=>135 + $j*2,
                    'sleeve_cm'=>56 + $j,
                    'stock'=>rand(2, 20),
                ]);
            }
        }

        // ---- Materials -------------------------------------------------
        $materials = [
            ['Kain Katun Premium','meter',12,30,45000],
            ['Kain Rayon','meter',60,30,38000],
            ['Benang Hitam','roll',4,10,15000],
            ['Benang Putih','roll',24,10,15000],
            ['Kancing Bulat','pcs',1500,500,500],
            ['Resleting 30cm','pcs',22,50,3500],
            ['Label Brand','pcs',780,200,1500],
            ['Plastik Packing','pcs',420,150,800],
        ];
        foreach ($materials as $i => $m) {
            Material::create([
                'name'=>$m[0],'code'=>'MAT-'.str_pad($i+1,4,'0',STR_PAD_LEFT),
                'unit'=>$m[1],'stock'=>$m[2],'min_stock'=>$m[3],'unit_cost'=>$m[4],
            ]);
        }

        // ---- Suppliers ------------------------------------------------
        Supplier::insert([
            ['name'=>'CV Tekstil Jaya','contact_person'=>'Pak Budi','phone'=>'081234567890','email'=>'tekstiljaya@example.com','address'=>'Surabaya','created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Toko Benang Mulia','contact_person'=>'Bu Ratna','phone'=>'081300000000','email'=>'benangmulia@example.com','address'=>'Sidoarjo','created_at'=>now(),'updated_at'=>now()],
            ['name'=>'PT Kancing Sentosa','contact_person'=>'Pak Hadi','phone'=>'081311112222','email'=>'kancing@example.com','address'=>'Malang','created_at'=>now(),'updated_at'=>now()],
        ]);

        // ---- Sales history (for Fuzzy Mamdani training data) ----------
        $hist = [
            [1792,1535,4023], [9868,3761,8580], [6809,2473,5316], [2647,980,2410],
            [486,743,1774],   [5132,2021,6228], [8752,3117,8148], [6767,2513,6741],
            [8379,2487,6661], [1017,769,1335],  [6271,2178,1254], [6473,2135,7135],
        ];
        foreach ($hist as $i => $row) {
            SalesHistory::create([
                'product_id'=>1,
                'year'=>2025,
                'month'=>$i+1,
                'demand'=>$row[0],
                'stock_end'=>$row[1],
                'produced'=>$row[2],
            ]);
        }
    }
}
