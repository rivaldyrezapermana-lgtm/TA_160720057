<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Master ukuran. Tabel lain (product_sizes, production_sizes, order_items)
 * menyimpan nama ukuran sebagai teks, jadi ukuran yang sudah dipakai tidak
 * boleh diganti namanya atau dihapus.
 */
class Size extends Model
{
    protected $fillable = ['name', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function isUsed(): bool
    {
        return DB::table('product_sizes')->where('size', $this->getOriginal('name'))->exists()
            || DB::table('production_sizes')->where('size', $this->getOriginal('name'))->exists()
            || DB::table('order_items')->where('size', $this->getOriginal('name'))->exists();
    }
}
