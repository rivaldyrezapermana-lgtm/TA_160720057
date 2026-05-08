@extends('layouts.auth')
@section('title', 'Masuk')

@section('content')
    <p class="kicker">Masuk · Akun Atelier</p>
    <h1 class="headline">Selamat datang kembali.</h1>
    <p class="lead">Masuk untuk melanjutkan ke ruang kerja Anda.</p>

    @if ($errors->any())
        <div class="form-banner" role="alert">
            <ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('login.attempt') }}" method="POST" novalidate>
        @csrf

        <div class="field">
            <label class="label" for="email">Alamat Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                placeholder="anda@labasa.id"
                class="input {{ $errors->has('email') ? 'has-error' : '' }}">
        </div>

        <div class="field">
            <label class="label" for="password">Kata Sandi</label>
            <input type="password" name="password" id="password" required autocomplete="current-password"
                placeholder="••••••••"
                class="input {{ $errors->has('password') ? 'has-error' : '' }}">
        </div>

        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:1.6rem; margin-bottom:1.75rem;">
            <label class="check">
                <input type="checkbox" name="remember">
                <span class="box">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                </span>
                <span>Ingat saya</span>
            </label>
            <span style="font-family:'Fraunces', serif; font-style:italic; font-size:11.5px; color:var(--ink-mute);">tujuh hari</span>
        </div>

        <button type="submit" class="cta">
            Masuk
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
        </button>
    </form>

    <p style="text-align:center; margin-top:2rem; font-family:'Fraunces', serif; font-style:italic; font-size:14px; color:var(--ink-soft);">
        Belum punya akun? <a href="{{ route('register') }}" class="switch-link">Daftar sebagai pembeli</a>
    </p>

    <div class="footnote">
        <p class="head">Akun Demo · Hanya Pengembangan</p>
        <p style="line-height:1.7;">
            <span style="display:block;">admin@labasa.test &nbsp;·&nbsp; karyawan@labasa.test &nbsp;·&nbsp; pembeli@labasa.test</span>
            <span style="display:block; margin-top:4px;">kata sandi: <code>password</code></span>
        </p>
    </div>
@endsection
