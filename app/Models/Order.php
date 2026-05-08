<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['user_id', 'code', 'total', 'status', 'shipping_address'];

    protected $casts = ['total' => 'decimal:2'];

    public const STATUSES = ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];

    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function payment() { return $this->hasOne(Payment::class); }
}
