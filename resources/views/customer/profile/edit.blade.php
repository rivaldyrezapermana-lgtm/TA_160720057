@extends('layouts.customer')
@section('title', 'Profil Saya')

@section('content')
<style>
    .profile-shell { max-width: 56rem; margin: 0 auto; padding: 4rem 1.5rem 5rem; }
    .profile-kicker {
        font-family: 'Fraunces', serif; font-style: italic; font-weight: 500;
        font-size: 11px; text-transform: uppercase; letter-spacing: 0.32em;
        color: #48604a;
        display: inline-flex; align-items: center; gap: .65rem; margin-bottom: 1.25rem;
    }
    .profile-kicker::before { content:''; width:14px; height:1px; background:#5b7553; }
    .profile-kicker::after { content:''; width:4px; height:4px; border:1px solid #5b7553; transform:rotate(45deg); }

    .profile-head {
        font-family: 'Fraunces', serif; font-weight: 500;
        font-size: clamp(2rem, 4vw, 2.6rem); line-height: 1.05;
        letter-spacing: -0.022em; color: #1a1a16; margin-bottom: .55rem;
    }
    .profile-lead { font-family: 'Fraunces', serif; font-style: italic; font-size: 14.5px; color: #54544c; margin-bottom: 2.5rem; }

    .profile-grid { display: grid; grid-template-columns: 1fr; gap: 3rem; }
    @media (min-width: 900px) { .profile-grid { grid-template-columns: minmax(0,2fr) minmax(0,3fr); } }

    .profile-aside {
        position: relative;
        padding: 1.75rem;
        border: 1px solid #eeeeec;
        background: linear-gradient(180deg, rgba(244,246,243,.45), rgba(255,255,255,1));
    }
    .profile-aside::before, .profile-aside::after {
        content:''; position:absolute; width:9px; height:9px;
        border:1px solid #5b7553; pointer-events:none;
    }
    .profile-aside::before { top:-4px; left:-4px; border-right:0; border-bottom:0; }
    .profile-aside::after { bottom:-4px; right:-4px; border-left:0; border-top:0; }

    .profile-aside .avatar-big {
        width: 72px; height: 72px;
        background: #fff; border: 1px solid #d8d8d4;
        display: inline-flex; align-items: center; justify-content: center;
        font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.85rem; color: #1a1a16;
    }
    .profile-aside .name { font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.25rem; color: #1a1a16; line-height: 1.15; margin-top: 1rem; }
    .profile-aside .email { font-family: 'Inter Tight', sans-serif; font-size: 12.5px; color: #6b6b63; margin-top: 4px; }
    .profile-aside .role-stamp {
        display:inline-flex; align-items:center; gap:.45rem;
        margin-top: 1rem; padding: 4px 10px;
        font-family: 'Inter Tight', sans-serif;
        font-size: 9.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.22em;
        color: #3a4d3d; border: 1px solid rgba(91,117,83,.32); border-radius: 999px;
    }
    .profile-aside .role-stamp .pip { width:5px; height:5px; background:#5b7553; border-radius:50%; }

    .profile-aside .meta { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed #d8d8d4; font-family: 'Fraunces', serif; font-style: italic; font-size: 12px; color: #8a8a82; line-height: 1.7; }

    .section-title {
        font-family: 'Fraunces', serif; font-style: italic; font-weight: 500;
        font-size: 11px; text-transform: uppercase; letter-spacing: 0.3em;
        color: #6b6b63;
        margin: 0 0 1.5rem;
        display: flex; align-items: center; gap: .65rem;
    }
    .section-title::before { content:''; width:12px; height:1px; background:#5b7553; }
    .section-title::after { content:''; flex:1; height:1px; background: linear-gradient(90deg, #d8d8d4, transparent); }

    .profile-cta {
        display:inline-flex; align-items:center; justify-content:center; gap:.65rem;
        padding: 14px 26px; background:#1a1a16; color:#fff; border:1px solid #1a1a16;
        border-radius: 999px;
        font-family: 'Inter Tight', sans-serif;
        font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.32em;
        cursor: pointer;
        transition: box-shadow .35s ease;
    }
    .profile-cta:hover { box-shadow: 0 0 0 6px rgba(26,26,22,.06), 0 1px 0 0 #5b7553; }
    .profile-cta svg { width: 14px; height: 14px; transition: transform .3s cubic-bezier(.2,.8,.2,1); }
    .profile-cta:hover svg { transform: translateX(3px); }

    .danger-block {
        margin-top: 4rem;
        padding-top: 2rem;
        border-top: 1px solid #eeeeec;
    }
    .danger-form .danger-cta {
        display:inline-flex; align-items:center; gap:.55rem;
        padding: 11px 18px; background:#fff; color:#b91c1c;
        border:1px solid rgba(185,28,28,.4); border-radius: 999px;
        font-family: 'Inter Tight', sans-serif;
        font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.28em;
        cursor: pointer;
        transition: background .2s ease, color .2s ease, border-color .2s ease;
    }
    .danger-form .danger-cta:hover { background:#b91c1c; color:#fff; border-color:#b91c1c; }
    .danger-form .danger-cta svg { width: 13px; height: 13px; }
</style>

<div class="profile-shell">
    <p class="profile-kicker">Akun · Pengaturan</p>
    <h1 class="profile-head">Profil saya</h1>
    <p class="profile-lead">Perbarui detail akun, kontak pengiriman, dan kata sandi Anda.</p>

    <div class="profile-grid">
        {{-- ── LEFT: identity card ─────────────────── --}}
        <aside class="profile-aside">
            <span class="avatar-big">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <h2 class="name">{{ $user->name }}</h2>
            <p class="email">{{ $user->email }}</p>
            <span class="role-stamp"><span class="pip"></span>{{ ucfirst($user->role) }}</span>

            <div class="meta">
                <p>Bergabung {{ $user->created_at?->translatedFormat('d F Y') ?? '—' }}.</p>
                @if ($user->phone)
                    <p>Kontak: {{ $user->phone }}.</p>
                @else
                    <p style="color:#b4b4ad;">Belum ada nomor telepon.</p>
                @endif
            </div>
        </aside>

        {{-- ── RIGHT: forms ─────────────────── --}}
        <section>
            @if (session('success'))
                <div style="margin-bottom:2rem; padding:12px 16px; border-left:2px solid #5b7553; background:rgba(244,246,243,.6); font-family:'Fraunces',serif; font-style:italic; font-size:13.5px; color:#3a4d3d;">
                    ✓ {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('profile.update') }}" method="POST" novalidate>
                @csrf
                @method('PATCH')

                <p class="section-title"><span>Identitas</span></p>

                <div class="field">
                    <label class="label" for="name">Nama Lengkap <span class="req">·</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                        class="input {{ $errors->has('name') ? 'has-error' : '' }}">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label class="label" for="email">Alamat Email <span class="req">·</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                        class="input {{ $errors->has('email') ? 'has-error' : '' }}">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div style="margin-top: 3rem;">
                    <p class="section-title"><span>Kontak &amp; Pengiriman</span></p>

                    <div class="field">
                        <label class="label" for="phone">No. WhatsApp</label>
                        <input type="tel" name="phone" id="phone" value="{{ old('phone', $user->phone) }}"
                            placeholder="0812-0000-0000"
                            class="input {{ $errors->has('phone') ? 'has-error' : '' }}">
                        @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label class="label" for="address">Alamat Pengiriman</label>
                        <textarea name="address" id="address" rows="3"
                            placeholder="Jalan, kelurahan, kota, kode pos"
                            class="input {{ $errors->has('address') ? 'has-error' : '' }}">{{ old('address', $user->address) }}</textarea>
                        @error('address')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div style="margin-top: 3rem;">
                    <p class="section-title"><span>Kata Sandi</span></p>
                    <p style="font-family:'Fraunces',serif; font-style:italic; font-size:12.5px; color:#8a8a82; margin: -.75rem 0 1.5rem;">
                        Kosongkan jika tidak ingin mengubah kata sandi.
                    </p>

                    <div class="field">
                        <label class="label" for="current_password">Kata Sandi Saat Ini</label>
                        <input type="password" name="current_password" id="current_password" autocomplete="current-password"
                            class="input {{ $errors->has('current_password') ? 'has-error' : '' }}">
                        @error('current_password')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label class="label" for="new_password">Kata Sandi Baru</label>
                        <input type="password" name="new_password" id="new_password" autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            class="input {{ $errors->has('new_password') ? 'has-error' : '' }}">
                        @error('new_password')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label class="label" for="new_password_confirmation">Konfirmasi Kata Sandi Baru</label>
                        <input type="password" name="new_password_confirmation" id="new_password_confirmation" autocomplete="new-password"
                            class="input">
                    </div>
                </div>

                <div style="margin-top: 2.5rem;">
                    <button type="submit" class="profile-cta">
                        Simpan Perubahan
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </div>
            </form>

            {{-- Logout — second, separate, clearly visible --}}
            <div class="danger-block">
                <p class="section-title"><span>Sesi</span></p>
                <p style="font-family:'Fraunces',serif; font-style:italic; font-size:12.5px; color:#8a8a82; margin: -.75rem 0 1.25rem;">
                    Keluar dari akun ini di perangkat ini.
                </p>
                <form action="{{ route('logout') }}" method="POST" class="danger-form">
                    @csrf
                    <button type="submit" class="danger-cta">
                        Keluar dari Akun
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17 16l4-4m0 0l-4-4m4 4H7"/></svg>
                    </button>
                </form>
            </div>
        </section>
    </div>
</div>
@endsection
