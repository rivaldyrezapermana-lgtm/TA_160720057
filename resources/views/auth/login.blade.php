@extends('layouts.auth')
@section('title', 'Masuk')

@section('content')
    <h1 class="text-xl font-semibold text-slate-900 mb-1">Masuk</h1>
    <p class="text-sm text-slate-500 mb-6">Masuk untuk melanjutkan ke akun Anda.</p>

    @if ($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('login.attempt') }}" method="POST">
        @csrf

        <div class="field">
            <label class="label" for="email">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                placeholder="anda@labasa.id"
                class="input {{ $errors->has('email') ? 'has-error' : '' }}">
        </div>

        <div class="field">
            <label class="label" for="password">Kata Sandi</label>
            <input type="password" name="password" id="password" required
                placeholder="••••••••"
                class="input {{ $errors->has('password') ? 'has-error' : '' }}">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600 mb-6">
            <input type="checkbox" name="remember" class="rounded-md border-slate-300">
            Ingat saya
        </label>

        <button type="submit" class="btn btn-primary">Masuk</button>
    </form>

    <p class="text-sm text-slate-600 text-center mt-6">
        Belum punya akun?
        <a href="{{ route('register') }}" class="text-emerald-700 hover:text-emerald-800 font-medium">Daftar</a>
    </p>

    <div class="mt-6 pt-4 border-t border-slate-200 text-xs text-slate-500">
        <p class="font-medium text-slate-700 mb-1">Akun Demo</p>
        <p>admin@labasa.test · karyawan@labasa.test · pembeli@labasa.test</p>
        <p>Kata sandi: <code class="bg-slate-100 px-1 py-0.5 rounded">password</code></p>
    </div>
@endsection
