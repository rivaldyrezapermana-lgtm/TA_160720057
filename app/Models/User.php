<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_KARYAWAN = 'karyawan';
    public const ROLE_PEMBELI = 'pembeli';

    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'address',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships
    public function orders() { return $this->hasMany(Order::class); }
    public function productions() { return $this->hasMany(Production::class); }
    public function chatMessages() { return $this->hasMany(ChatMessage::class, 'sender_id'); }

    // Helpers
    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN; }
    public function isKaryawan(): bool { return $this->role === self::ROLE_KARYAWAN; }
    public function isPembeli(): bool { return $this->role === self::ROLE_PEMBELI; }
}
