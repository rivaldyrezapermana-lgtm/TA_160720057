@extends('layouts.auth')
@section('title', 'Daftar')

@section('content')
    <p class="kicker">Daftar · Akun Pembeli</p>
    <h1 class="headline">Mulai berbelanja di Labasa.</h1>
    <p class="lead">Buat akun untuk menyimpan pesanan dan riwayat belanja Anda.</p>

    @if ($errors->any())
        <div class="form-banner" role="alert">
            <ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('register.attempt') }}" method="POST" novalidate>
        @csrf

        <div class="field">
            <label class="label" for="name">Nama Lengkap <span class="req">·</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                placeholder="Contoh: Siti Nurhaliza"
                class="input {{ $errors->has('name') ? 'has-error' : '' }}">
        </div>

        <div class="field">
            <label class="label" for="email">Alamat Email <span class="req">·</span></label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="email"
                placeholder="anda@labasa.id"
                class="input {{ $errors->has('email') ? 'has-error' : '' }}">
        </div>

        <div class="field">
            <label class="label" for="phone">No. WhatsApp</label>
            <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" autocomplete="tel"
                placeholder="0812-0000-0000"
                class="input {{ $errors->has('phone') ? 'has-error' : '' }}">
            <p class="field-help">Digunakan untuk konfirmasi pesanan.</p>
        </div>

        <div class="field">
            <label class="label" for="password">Kata Sandi <span class="req">·</span></label>
            <input type="password" name="password" id="password" required autocomplete="new-password"
                placeholder="Minimal 8 karakter"
                class="input {{ $errors->has('password') ? 'has-error' : '' }}">
        </div>

        <div class="field">
            <label class="label" for="password_confirmation">Konfirmasi Kata Sandi <span class="req">·</span></label>
            <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password"
                placeholder="Tulis ulang kata sandi"
                class="input">
        </div>

        <button type="submit" class="cta" style="margin-top:2.25rem;">
            Daftar Sekarang
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
        </button>
    </form>

    <p style="text-align:center; margin-top:2rem; font-family:'Fraunces', serif; font-style:italic; font-size:14px; color:var(--ink-soft);">
        Sudah punya akun? <a href="{{ route('login') }}" class="switch-link">Masuk di sini</a>
    </p>
@endsection
