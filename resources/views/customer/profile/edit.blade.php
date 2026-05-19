@extends('layouts.customer')
@section('title', 'Profil Saya')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-10">
    <h1 class="text-2xl font-semibold text-slate-900">Profil saya</h1>
    <p class="text-sm text-slate-600 mt-1 mb-6">Perbarui detail akun, kontak pengiriman, dan kata sandi Anda.</p>

    @if (session('success'))
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Identity summary --}}
    <div class="bg-white border border-slate-200 rounded-lg p-5 flex items-center gap-4 mb-6">
        <span class="w-14 h-14 flex-shrink-0 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-semibold">
            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
        </span>
        <div class="min-w-0">
            <p class="text-base font-semibold text-slate-900 truncate">{{ $user->name }}</p>
            <p class="text-sm text-slate-500 truncate">{{ $user->email }}</p>
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5 text-xs text-slate-500">
                <span class="badge badge-green capitalize">{{ $user->role }}</span>
                <span>Bergabung {{ $user->created_at?->translatedFormat('d F Y') ?? '—' }}</span>
            </div>
        </div>
    </div>

    {{-- Profile form --}}
    <form action="{{ route('profile.update') }}" method="POST" novalidate
          class="bg-white border border-slate-200 rounded-lg p-6">
        @csrf
        @method('PATCH')

        <h2 class="text-sm font-semibold text-slate-900 mb-4">Identitas</h2>

        <div class="field">
            <label class="label" for="name">Nama Lengkap</label>
            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="input">
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="email">Alamat Email</label>
            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="input">
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <h2 class="text-sm font-semibold text-slate-900 mt-8 mb-4 pt-6 border-t border-slate-200">Kontak &amp; Pengiriman</h2>

        <div class="field">
            <label class="label" for="phone">No. WhatsApp</label>
            <input type="tel" name="phone" id="phone" value="{{ old('phone', $user->phone) }}" placeholder="0812-0000-0000" class="input">
            @error('phone')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="address">Alamat Pengiriman</label>
            <textarea name="address" id="address" rows="3" placeholder="Jalan, kelurahan, kota, kode pos" class="input">{{ old('address', $user->address) }}</textarea>
            @error('address')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <h2 class="text-sm font-semibold text-slate-900 mt-8 mb-1 pt-6 border-t border-slate-200">Kata Sandi</h2>
        <p class="text-xs text-slate-500 mb-4">Kosongkan jika tidak ingin mengubah kata sandi.</p>

        <div class="field">
            <label class="label" for="current_password">Kata Sandi Saat Ini</label>
            <input type="password" name="current_password" id="current_password" autocomplete="current-password" class="input">
            @error('current_password')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="new_password">Kata Sandi Baru</label>
            <input type="password" name="new_password" id="new_password" autocomplete="new-password" placeholder="Minimal 8 karakter" class="input">
            @error('new_password')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="new_password_confirmation">Konfirmasi Kata Sandi Baru</label>
            <input type="password" name="new_password_confirmation" id="new_password_confirmation" autocomplete="new-password" class="input">
        </div>

        <div class="mt-6">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>

    {{-- Session --}}
    <div class="bg-white border border-slate-200 rounded-lg p-6 mt-6">
        <h2 class="text-sm font-semibold text-slate-900 mb-1">Sesi</h2>
        <p class="text-xs text-slate-500 mb-4">Keluar dari akun ini di perangkat ini.</p>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border border-red-300 text-red-700 hover:bg-red-50">
                Keluar dari Akun
            </button>
        </form>
    </div>
</div>
@endsection
