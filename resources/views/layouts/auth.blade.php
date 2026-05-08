<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Masuk') — Labasa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,500;1,9..144,600&family=Inter+Tight:wght@400;450;500;600&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { display: ['Fraunces', 'serif'], sans: ['Inter Tight', 'sans-serif'] },
                    colors: {
                        ink:    { 50:'#f7f7f6', 100:'#eeeeec', 200:'#d8d8d4', 300:'#b4b4ad', 400:'#8a8a82', 500:'#6b6b63', 700:'#42423b', 800:'#2c2c27', 900:'#1a1a16' },
                        accent: { 50:'#f4f6f3', 500:'#5b7553', 600:'#48604a', 700:'#3a4d3d' },
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --paper: #fbfaf7;
            --paper-warm: #f6f5f1;
            --ink: #1a1a16;
            --ink-soft: #54544c;
            --ink-mute: #8a8a82;
            --hair: #d8d8d4;
            --hair-soft: #eeeeec;
            --sage: #5b7553;
            --sage-deep: #48604a;
            --sage-mist: #f4f6f3;
            --error: #b91c1c;
        }

        * { -webkit-font-smoothing: antialiased; }
        body { font-family: 'Inter Tight', sans-serif; background: var(--paper); color: var(--ink); }
        .font-display { font-family: 'Fraunces', serif; }

        /* ── Atelier shell ─────────────────────────── */
        .atelier {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr;
        }
        @media (min-width: 900px) {
            .atelier { grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); }
        }

        /* Left hero panel */
        .atelier-hero {
            display: none;
            position: relative;
            background:
                radial-gradient(circle at 20% 25%, rgba(91,117,83,.12) 0, transparent 40%),
                radial-gradient(circle at 80% 75%, rgba(26,26,22,.08) 0, transparent 35%),
                linear-gradient(180deg, #f6f5f1 0%, #ecebe5 100%);
            overflow: hidden;
            padding: 3rem;
        }
        @media (min-width: 900px) { .atelier-hero { display: flex; flex-direction: column; justify-content: space-between; } }

        .atelier-hero .nav-back {
            font-family: 'Inter Tight', sans-serif;
            font-size: 11px; font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.28em;
            color: var(--ink-soft);
            display: inline-flex; align-items: center; gap: .55rem;
            transition: color .2s ease, gap .25s cubic-bezier(.2,.8,.2,1);
        }
        .atelier-hero .nav-back:hover { color: var(--ink); gap: .9rem; }
        .atelier-hero .nav-back svg { width: 14px; height: 14px; }

        .atelier-hero .star-mark {
            position: absolute;
            right: -120px; top: -120px;
            width: 540px; height: 540px;
            color: var(--sage);
            opacity: .14;
            pointer-events: none;
            animation: drift 18s ease-in-out infinite alternate;
        }
        @keyframes drift {
            0%   { transform: rotate(0deg) translateY(0); }
            100% { transform: rotate(22deg) translateY(20px); }
        }
        .atelier-hero .star-mark-2 {
            position: absolute;
            left: -80px; bottom: -80px;
            width: 280px; height: 280px;
            color: var(--ink);
            opacity: .05;
            pointer-events: none;
        }

        .atelier-hero .credo {
            position: relative; z-index: 2;
            max-width: 28rem;
        }
        .atelier-hero .credo .opener {
            font-family: 'Fraunces', serif;
            font-style: italic; font-weight: 500;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.34em;
            color: var(--sage-deep);
            display: inline-flex; align-items: center; gap: .65rem;
            margin-bottom: 1.5rem;
        }
        .atelier-hero .credo .opener::before {
            content: ''; width: 28px; height: 1px; background: var(--sage);
        }
        .atelier-hero .credo .quote {
            font-family: 'Fraunces', serif;
            font-optical-sizing: auto;
            font-variation-settings: "opsz" 144;
            font-weight: 400;
            font-size: clamp(1.75rem, 3.2vw, 2.6rem);
            line-height: 1.18;
            letter-spacing: -0.022em;
            color: var(--ink);
        }
        .atelier-hero .credo .quote em {
            font-style: italic;
            color: var(--sage-deep);
            font-weight: 500;
        }
        .atelier-hero .credo .attribution {
            margin-top: 1.5rem;
            font-family: 'Inter Tight', sans-serif;
            font-size: 11px; font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.22em;
            color: var(--ink-mute);
            display: flex; align-items: center; gap: .65rem;
        }
        .atelier-hero .credo .attribution::before {
            content: ''; width: 18px; height: 1px; background: var(--ink-mute);
        }

        .atelier-hero .footer-credit {
            position: relative; z-index: 2;
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-size: 11px;
            color: var(--ink-mute);
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(216,216,212,.5);
        }
        .atelier-hero .footer-credit .lot {
            letter-spacing: 0.06em;
        }

        /* Right form panel */
        .atelier-form {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--paper);
            padding: 2rem 1.5rem;
            min-height: 100vh;
        }
        @media (min-width: 900px) { .atelier-form { padding: 3rem 4rem; } }
        @media (min-width: 1280px) { .atelier-form { padding: 3.5rem 6rem; } }

        .atelier-form .panel-top {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem;
            margin-bottom: 4rem;
        }
        .atelier-form .wordmark {
            font-family: 'Fraunces', serif;
            font-optical-sizing: auto;
            font-variation-settings: "opsz" 144;
            font-weight: 600;
            font-size: 1.5rem;
            line-height: 1;
            letter-spacing: -0.012em;
            color: var(--ink);
            display: inline-flex; align-items: center; gap: .55rem;
        }
        .atelier-form .wordmark em { font-style: italic; color: var(--sage-deep); font-weight: 500; }
        .atelier-form .wordmark .star {
            width: 1.5rem; height: 1.5rem; color: var(--ink);
        }
        .atelier-form .panel-top .crumbtrail {
            font-family: 'Inter Tight', sans-serif;
            font-size: 10.5px; font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.26em;
            color: var(--ink-mute);
        }

        /* Form inner */
        .atelier-form .panel-inner {
            max-width: 28rem;
            margin: auto 0;
            width: 100%;
        }
        .atelier-form .panel-inner .kicker {
            font-family: 'Fraunces', serif;
            font-style: italic; font-weight: 500;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.32em;
            color: var(--sage-deep);
            display: inline-flex; align-items: center; gap: .65rem;
            margin-bottom: 1.25rem;
        }
        .atelier-form .panel-inner .kicker::before {
            content: ''; width: 14px; height: 1px; background: var(--sage);
        }
        .atelier-form .panel-inner .kicker::after {
            content: ''; width: 4px; height: 4px;
            border: 1px solid var(--sage);
            transform: rotate(45deg);
        }
        .atelier-form .panel-inner .headline {
            font-family: 'Fraunces', serif;
            font-optical-sizing: auto;
            font-variation-settings: "opsz" 144;
            font-weight: 500;
            font-size: clamp(2.2rem, 4vw, 2.8rem);
            line-height: 1.05;
            letter-spacing: -0.022em;
            color: var(--ink);
            margin-bottom: .55rem;
        }
        .atelier-form .panel-inner .lead {
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-size: 14px;
            color: var(--ink-soft);
            margin-bottom: 2.25rem;
        }

        .atelier-form .panel-bottom {
            margin-top: auto;
            padding-top: 2rem;
            font-family: 'Inter Tight', sans-serif;
            font-size: 11px;
            color: var(--ink-mute);
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem;
        }
        .atelier-form .panel-bottom .selvage {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--hair), transparent);
        }

        /* ── Form primitives — fountain pen on parchment ── */
        .field { position: relative; padding-top: 8px; padding-bottom: 4px; }
        .field + .field { margin-top: 1.4rem; }

        .label {
            display: block;
            font-family: 'Fraunces', serif;
            font-style: italic; font-weight: 500;
            font-size: 10.5px;
            color: var(--ink-mute);
            text-transform: uppercase;
            letter-spacing: 0.28em;
            margin-bottom: 9px;
        }
        .label .req { color: var(--sage); margin-left: 4px; font-style: normal; }

        .input,
        input.input,
        select.input,
        textarea.input {
            width: 100%;
            -webkit-appearance: none; appearance: none;
            background: transparent;
            border: 0;
            border-bottom: 1px solid var(--hair);
            border-radius: 0;
            padding: 6px 0 10px;
            font-family: 'Inter Tight', sans-serif;
            font-size: 14.5px;
            font-weight: 450;
            color: var(--ink);
            outline: none;
            box-shadow: none;
            transition: border-color .25s ease, background .25s ease, box-shadow .3s ease, padding-left .25s ease;
        }
        .input::placeholder { color: var(--ink-mute); font-style: italic; opacity: .55; }
        .input:hover { border-bottom-color: var(--ink-mute); }
        .input:focus {
            border-bottom-color: var(--ink);
            background: linear-gradient(180deg, transparent 60%, rgba(244,246,243,.55) 100%);
            box-shadow: 0 1px 0 0 var(--sage);
        }
        .input:focus + .focus-mark { opacity: 1; transform: translateX(0); }

        .field-error,
        p.field-error {
            font-family: 'Fraunces', serif;
            font-style: italic; font-weight: 500;
            font-size: 12px;
            color: var(--error);
            margin-top: 8px;
            padding-left: 16px;
            position: relative;
        }
        .field-error::before {
            content: '—';
            position: absolute; left: 0; top: 0;
            color: var(--error);
        }
        .input.has-error { border-bottom-color: var(--error); }
        .input.has-error:focus { box-shadow: 0 1px 0 0 var(--error); }

        /* Select chevron — sage, replaces the native arrow */
        select.input {
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'><path d='M3 4.5l3 3 3-3' fill='none' stroke='%235b7553' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round'/></svg>");
            background-repeat: no-repeat;
            background-position: right 2px center;
            background-size: 12px;
            padding-right: 22px;
            cursor: pointer;
        }

        /* Textarea */
        textarea.input { min-height: 96px; resize: vertical; padding-top: 10px; line-height: 1.65; }

        /* Help text */
        .field-help {
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-size: 11.5px;
            color: var(--ink-mute);
            margin-top: 8px;
        }

        /* Custom checkbox */
        .check {
            display: inline-flex; align-items: center; gap: .65rem;
            font-family: 'Inter Tight', sans-serif;
            font-size: 11.5px; font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: var(--ink-soft);
            cursor: pointer;
            user-select: none;
        }
        .check input { position: absolute; opacity: 0; pointer-events: none; }
        .check .box {
            width: 16px; height: 16px;
            border: 1px solid var(--hair);
            background: white;
            display: inline-flex; align-items: center; justify-content: center;
            transition: border-color .15s ease, background .15s ease;
        }
        .check .box svg {
            width: 10px; height: 10px;
            color: var(--sage-deep);
            opacity: 0;
            transform: scale(.6);
            transition: opacity .18s ease, transform .25s cubic-bezier(.2,1.6,.3,1);
        }
        .check input:checked + .box { border-color: var(--sage); background: var(--sage-mist); }
        .check input:checked + .box svg { opacity: 1; transform: scale(1); }
        .check:hover .box { border-color: var(--ink); }

        /* CTA pill */
        .cta {
            display: inline-flex; align-items: center; justify-content: center;
            gap: .65rem;
            width: 100%;
            padding: 14px 24px;
            background: var(--ink);
            color: white;
            border: 1px solid var(--ink);
            border-radius: 999px;
            font-family: 'Inter Tight', sans-serif;
            font-size: 11px; font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.32em;
            cursor: pointer;
            transition: background .25s ease, box-shadow .35s ease, padding-left .3s cubic-bezier(.2,.8,.2,1);
            position: relative;
        }
        .cta svg {
            width: 14px; height: 14px;
            transition: transform .35s cubic-bezier(.2,.8,.2,1);
        }
        .cta:hover {
            background: var(--ink);
            box-shadow: 0 0 0 6px rgba(26,26,22,.06), 0 1px 0 0 var(--sage);
        }
        .cta:hover svg { transform: translateX(4px); }
        .cta-ghost {
            background: transparent;
            color: var(--ink);
            border-color: var(--hair);
        }
        .cta-ghost:hover { background: var(--paper-warm); border-color: var(--ink); }

        /* Error banner (form-wide) */
        .form-banner {
            border-left: 2px solid var(--error);
            padding: 10px 14px;
            background: rgba(185,28,28,.04);
            color: var(--error);
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-size: 13px;
            margin-bottom: 1.75rem;
        }
        .form-banner ul { margin: 0; padding: 0; list-style: none; }
        .form-banner li::before { content: '— '; color: var(--error); }

        /* Footnote box (demo accounts) */
        .footnote {
            margin-top: 2rem;
            padding-top: 1.25rem;
            border-top: 1px dashed var(--hair);
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-size: 11.5px;
            color: var(--ink-mute);
        }
        .footnote .head {
            font-family: 'Inter Tight', sans-serif;
            font-style: normal; font-weight: 600;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.24em;
            color: var(--ink-soft);
            margin-bottom: 8px;
        }
        .footnote code {
            font-family: 'Inter Tight', sans-serif;
            font-style: normal;
            font-size: 11px;
            background: var(--paper-warm);
            color: var(--ink);
            padding: 1px 6px;
            border: 1px solid var(--hair);
            border-radius: 3px;
        }

        .switch-link {
            font-family: 'Fraunces', serif;
            font-style: italic;
            font-weight: 500;
            color: var(--ink);
            border-bottom: 1px solid var(--hair);
            transition: border-color .15s ease;
        }
        .switch-link:hover { border-color: var(--ink); }
    </style>
</head>
<body>

<div class="atelier">
    {{-- Left hero panel --}}
    <aside class="atelier-hero">
        <a href="{{ route('home') }}" class="nav-back">
            <svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M5 12l7-7M5 12l7 7"/></svg>
            kembali ke toko
        </a>

        {{-- Drifting octagram watermark --}}
        <svg class="star-mark" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
            <rect x="20" y="20" width="60" height="60"/>
            <rect x="20" y="20" width="60" height="60" transform="rotate(45 50 50)"/>
            <circle cx="50" cy="50" r="22"/>
            <circle cx="50" cy="50" r="6"/>
            <circle cx="50" cy="50" r="38" stroke-dasharray="2 4"/>
        </svg>
        <svg class="star-mark-2" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
            <rect x="20" y="20" width="60" height="60"/>
            <rect x="20" y="20" width="60" height="60" transform="rotate(45 50 50)"/>
        </svg>

        <div class="credo">
            <p class="opener">Atelier · Sejak 2024</p>
            <p class="quote">Dijahit dengan <em>rapi</em>, dipakai dengan <em>bangga</em>.</p>
            <p class="attribution">Filosofi Toko Labasa</p>
        </div>

        <div class="footer-credit">
            <span>© {{ date('Y') }} Toko Labasa</span>
            <span class="lot">no. {{ str_pad((string)(crc32(request()->path()) % 999), 3, '0', STR_PAD_LEFT) }}</span>
        </div>
    </aside>

    {{-- Right form panel --}}
    <main class="atelier-form">
        <div class="panel-top">
            <a href="{{ route('home') }}" class="wordmark">
                <svg class="star" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <rect x="5" y="5" width="14" height="14" stroke="currentColor" stroke-width="1.4"/>
                    <rect x="5" y="5" width="14" height="14" stroke="currentColor" stroke-width="1.4" transform="rotate(45 12 12)"/>
                    <circle cx="12" cy="12" r="2" fill="currentColor"/>
                </svg>
                Labasa<em>.</em>
            </a>
            <span class="crumbtrail">@yield('title', 'Atelier')</span>
        </div>

        <div class="panel-inner">
            @yield('content')
        </div>

        <div class="panel-bottom">
            <span>Toko Labasa · Surabaya</span>
            <span class="selvage" aria-hidden="true"></span>
            <span>{{ now()->format('Y') }}</span>
        </div>
    </main>
</div>

</body>
</html>
