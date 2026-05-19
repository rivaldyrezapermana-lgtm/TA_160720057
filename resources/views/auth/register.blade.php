@extends('layouts.auth')
@section('title', 'Daftar')

@section('content')
    <h1 class="text-xl font-semibold text-slate-900 mb-1">Daftar Akun</h1>
    <p class="text-sm text-slate-500 mb-6">Buat akun untuk mulai berbelanja di Labasa.</p>

    @if ($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('register.attempt') }}" method="POST">
        @csrf

        <div class="field">
            <label class="label" for="name">Nama Lengkap</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus
                placeholder="Contoh: Siti Nurhaliza"
                class="input {{ $errors->has('name') ? 'has-error' : '' }}">
        </div>

        <div class="field">
            <label class="label" for="email">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                placeholder="anda@labasa.id"
                class="input {{ $errors->has('email') ? 'has-error' : '' }}">
        </div>

        <div class="field">
            <label class="label" for="phone">No. WhatsApp</label>
            <input type="tel" name="phone" id="phone" value="{{ old('phone') }}"
                placeholder="0812-0000-0000"
                class="input {{ $errors->has('phone') ? 'has-error' : '' }}">
        </div>

        <div class="field">
            <label class="label" for="password">Kata Sandi</label>
            <input type="password" name="password" id="password" required
                placeholder="Minimal 8 karakter"
                class="input {{ $errors->has('password') ? 'has-error' : '' }}">
        </div>

        <div class="field">
            <label class="label" for="password_confirmation">Konfirmasi Kata Sandi</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required
                placeholder="Tulis ulang kata sandi"
                class="input">
        </div>

        <button type="submit" class="btn btn-primary mt-2">Daftar Sekarang</button>
    </form>

    <p class="text-sm text-slate-600 text-center mt-6">
        Sudah punya akun?
        <a href="{{ route('login') }}" class="text-emerald-700 hover:text-emerald-800 font-medium">Masuk</a>
    </p>
@endsection
