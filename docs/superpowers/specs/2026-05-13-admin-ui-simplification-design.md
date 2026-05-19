# UI Simplification — Design

**Date:** 2026-05-13
**Scope:** All three UI layouts (admin, customer, auth) + their shared components + the auth pages.

## Goal

Replace the current "atelier" design — Fraunces serif, italic uppercase labels, custom `ink`/`accent` palettes, octagram brand marks, gradient nav rails, "selvage" hairlines, animated star watermarks, "pill-cart" with ping animation, "account-stamp" with textile-tag corner ticks, underline-only form inputs — with a minimal, professional design that a beginner Laravel developer can read and modify without learning custom CSS conventions.

## Non-goals

- No backend changes (controllers, models, routes, middleware unchanged).
- No new component libraries. No Alpine/Livewire. Keep jQuery + DataTables.
- No restructuring of `routes/web.php` or page-level controllers.
- No build-pipeline change. Keep Tailwind via CDN.
- Page-level views under `resources/views/admin/**` and `resources/views/customer/**` are NOT individually rewritten. They keep working through preserved class names + Tailwind color aliases (see "Backwards compatibility" below).

## Design tokens

### Colors

Use Tailwind's built-in palettes. No custom palette names except aliases for backwards compatibility.

- **Neutral:** `slate-*` (50/100/200/300/500/700/900)
- **Primary / accent:** `emerald-*` (50/500/600/700) — close to current sage
- **Status:** `emerald` (success), `amber` (warn), `red` (error), `blue` (info)

### Typography

- **One font:** Inter, loaded from Google Fonts.
- Drop Fraunces, drop italic uppercase labels, drop optical sizing tweaks, drop `<em>` flourishes in headlines (they'll just render as italic Inter, which is fine).
- Body 14px, h1 24px, h2 18px. Labels: 13px medium, normal case.

### Spacing & shape

- Cards: `rounded-lg` (8px), `border border-slate-200`, `p-6`.
- Buttons: `rounded-md` (6px), `px-4 py-2`.
- Inputs: `rounded-md`, `border border-slate-300`, `px-3 py-2`.
- Main content padding: `p-6` everywhere.

## Shared component class library (defined once in each layout's `<style>` block)

Every layout will define the same small set of utility classes. A beginner only has to learn these names:

| Class | Style (plain English) |
| --- | --- |
| `.btn` | base button — inline-flex, gap, padding, font-medium, rounded |
| `.btn-primary` | `.btn` + emerald-600 background, white text |
| `.btn-secondary` | `.btn` + white background, slate border |
| `.btn-danger` | `.btn` + red-600 background, white text |
| `.label` | block, text-sm, font-medium, slate-700, mb-1 |
| `.input` | full-width, bordered, rounded, focus ring emerald-500 |
| `.field` | wrapper div with mb-4 |
| `.field-help` | small slate-500 helper text |
| `.field-error` | small red-600 error text |
| `.badge` | inline-flex, px-2 py-0.5, rounded text-xs |
| `.badge-green / amber / red / gray / blue` | colored badge variants |
| `.card` | white bg, slate-200 border, rounded-lg, p-6 |
| `.stat-card` | same as `.card` (alias for backwards compat) |
| `.table-clean` | full-width table with light borders & slate-50 header |

**Rule:** each component class is a single CSS rule. No nested selectors, no `@apply` chains with 6+ utilities, no pseudo-elements with gradients, no animations. A beginner reads the class and predicts what it does.

## Layouts

### 1. Admin layout (`layouts/admin.blade.php`)

```
+----------+---------------------------------+
| sidebar  | topbar (h-16, white, border)    |
|  w-64    +---------------------------------+
| white    |                                 |
| border   |  main (bg-slate-50, p-6)        |
|          |                                 |
+----------+---------------------------------+
```

**Sidebar** (`components/admin/sidebar.blade.php`)
- White bg, right border `border-slate-200`. No radial gradients, no watermarks.
- Brand row: text "Labasa Admin" in `font-semibold text-lg`. No SVG star.
- Collapse button: simple chevron, top-right.
- Nav section headers: `text-xs uppercase tracking-wide text-slate-400 font-semibold px-3 py-2`. No decorative dashes/dots.
- Nav links: `flex items-center gap-3 px-3 py-2 rounded-md text-sm text-slate-700 hover:bg-slate-100`.
- Active: `bg-emerald-50 text-emerald-700 font-medium border-l-2 border-emerald-600`. No font-family swap, no `::after` ribbon, no italic-on-active.
- User card: avatar circle (`bg-emerald-100 text-emerald-700`), name, role text. No corner ticks, no Fraunces.
- Collapse JS preserved.

**Topbar** (`components/admin/topbar.blade.php`)
- White bg, bottom border, `h-16`, `px-6`.
- Left: page title `text-lg font-semibold text-slate-900` from `@yield('title')`. Breadcrumb slot still supported.
- Right: simple "Keluar" button (`.btn-secondary`).
- Drop: datestamp with crescent moon, kicker with diamond ornament, "selvage" hairline with octagram, "no. 001" index counter.

### 2. Customer layout (`layouts/customer.blade.php`)

**Header** (sticky top, white bg, h-16, bottom border)
- Logo: text "Labasa" in `font-semibold text-xl text-slate-900` (no SVG octagram, no hover rotate, no italic period).
- Desktop nav: simple text links, slate-700 default, emerald-600 active with a `border-b-2 border-emerald-600` underline (no gradient).
- Cart icon: standard icon button with a small emerald-600 number badge (no white pulse animation).
- Account: text-only "Profil" link + "Keluar" button. No "account-stamp" with corner ticks.
- Guest: "Masuk" link + "Daftar" `.btn-primary`. No uppercase letter-spacing extravaganza.
- Mobile hamburger and panel: simple bordered button, panel uses `max-h` transition (kept) but link styles are flat (no italic-on-active, no `mobile-section` dashes).

**Footer** (`border-t border-slate-200`, `py-12`)
- Three-column grid: "Labasa" tagline, contact info, store info. Sans-serif headings, slate-500 body.
- Copyright bar at bottom.

### 3. Auth layout (`layouts/auth.blade.php`)

Replace the two-pane "atelier" shell with a **single centered card**:

```
                  +--------------------+
                  |   Labasa Admin     |
                  |                    |
                  |   Headline         |
                  |   Lead text        |
                  |                    |
                  |   [form fields]    |
                  |                    |
                  |   [submit button]  |
                  |                    |
                  |   Switch link      |
                  +--------------------+
```

- Body: `min-h-screen flex items-center justify-center bg-slate-50 p-6`.
- Card: `max-w-md w-full bg-white border border-slate-200 rounded-lg p-8 shadow-sm`.
- Logo: text "Labasa" at top, centered, `font-semibold text-2xl`.
- Drop: animated drifting star watermark, secondary star-mark, "Atelier · Sejak 2024" credo, "Filosofi Toko Labasa" attribution, "panel-bottom" with selvage divider, "lot no. 001", crumbtrail, kicker with diamond ornament.

### 4. Auth pages (`auth/login.blade.php` + `auth/register.blade.php`)

These directly reference layout-specific classes (`.kicker`, `.headline`, `.lead`, `.form-banner`, `.check`, `.cta`, `.switch-link`, `.footnote`) and embed inline atelier copy. Since the layout shell changes, the pages need updating:

- Drop `.kicker` "Masuk · Akun Atelier". Just show heading.
- `.headline` → `<h1 class="text-2xl font-semibold text-slate-900 mb-2">`.
- `.lead` → `<p class="text-sm text-slate-600 mb-6">`.
- `.form-banner` → standard red error block (same style as admin layout uses).
- `.check` → standard `<input type="checkbox">` with a simple label.
- `.cta` → `.btn-primary` with full-width modifier.
- `.switch-link` → plain `text-emerald-600 hover:text-emerald-700 font-medium`.
- `.footnote` (demo accounts box) → simple `bg-slate-50 border border-slate-200 rounded p-3 text-xs text-slate-600` block at the bottom.
- "Tujuh hari" Fraunces flourish next to "Remember me" → removed.

## UI components (`components/ui/*` and `components/admin/*`)

| File | Change |
| --- | --- |
| `components/ui/card.blade.php` | swap `bg-ink-100` → `bg-slate-200`, `border-ink-100` → `border-slate-200`, `font-display` → drop (Inter is the only font) |
| `components/ui/badge.blade.php` | swap `bg-ink-100 text-ink-700` → `bg-slate-100 text-slate-700` (the green/amber/red/blue/dark tones already use standard Tailwind colors — keep) |
| `components/ui/input.blade.php` | already uses `.field` / `.label` / `.input` / `.field-help` / `.field-error` — only the CSS definitions change, no component edit |
| `components/ui/select.blade.php` | same as input |
| `components/ui/textarea.blade.php` | same as input |
| `components/ui/modal.blade.php` | swap `bg-ink-900/40` → `bg-slate-900/40`, `border-ink-100` → `border-slate-200`, `font-display` → drop, `hover:bg-ink-100` → `hover:bg-slate-100` |
| `components/ui/status-badge.blade.php` | no change (delegates to `x-ui.badge`) |
| `components/admin/stat-card.blade.php` | swap ink/accent → slate/emerald, drop `font-display` |
| `components/admin/data-table.blade.php` | swap `border-ink-100` → `border-slate-200`, keep DataTables init |
| `components/admin/breadcrumb.blade.php` | check content; likely just color swap |
| `components/admin/sidebar.blade.php` | rewrite (see "Admin layout · Sidebar" above) |
| `components/admin/topbar.blade.php` | rewrite (see "Admin layout · Topbar" above) |

## Backwards compatibility

Page-level views (~45 files under `resources/views/admin/**` and `resources/views/customer/**`) reference custom Tailwind color names like `text-ink-900`, `bg-ink-50`, `border-ink-100`, `text-accent-600`, plus the `font-display` class. **I will NOT rewrite these pages individually.**

Two compatibility mechanisms keep them working:

**1. Tailwind config aliases.** In each layout's `tailwind.config` script block:
```js
colors: {
    // Aliases — points old "ink" palette at slate, old "accent" at emerald.
    ink:    { 50:'#f8fafc', 100:'#f1f5f9', 200:'#e2e8f0', 300:'#cbd5e1',
              400:'#94a3b8', 500:'#64748b', 600:'#475569', 700:'#334155',
              800:'#1e293b', 900:'#0f172a' },  // = slate-50..900
    accent: { 50:'#ecfdf5', 100:'#d1fae5', 500:'#10b981', 600:'#059669',
              700:'#047857' },                  // = emerald subset
}
```
This means `bg-ink-100` still works in every existing page, it just renders as `bg-slate-100`.

**2. Preserved class names.** All these class names continue to exist (with new, simpler styles):
- `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-danger`
- `.input`, `.label`, `.field`, `.field-help`, `.field-error`
- `.badge`, `.badge-green`, `.badge-amber`, `.badge-red`, `.badge-gray`, `.badge-blue`
- `.stat-card`, `.table-clean`, `.card`
- `.font-display` — kept as a class, but redefined to `font-family: Inter` (so pages using it still compile; they just won't look serif anymore)
- `.nav-link`, `.nav-icon`, `.nav-section`, `.badge-pill`, `.badge-fuzzy`, `.badge-chat` — only used inside the sidebar, but kept so any external reference compiles

**Visual exceptions:** Some pages have inline atelier flourishes (the customer landing's `<em class="font-normal">dijahit dengan rapi.</em>` headline) that will render slightly differently with Inter italic vs. Fraunces italic. That's an acceptable visual regression for a beginner-friendly redesign.

## Out of scope (deliberate)

- Rewriting individual page views (admin/customer pages keep working via aliases).
- `welcome.blade.php` — unreachable, the app routes `/` to `Customer\ShopController@landing` which uses `customer/shop/landing.blade.php`. Leave alone.
- Customer chat or admin chat page-level views — they reference the same compat classes.
- Replacing Tailwind CDN with Vite build pipeline.
- Replacing jQuery/DataTables.
- Migrating away from `ink-*` / `accent-*` references at the page level — handled by aliases.

## Verification

After all changes:

1. Start: `php artisan serve` (admin/customer use CDN Tailwind, no `npm run dev` required for visual check).
2. Visit each layout while logged in:
   - **Admin** as `admin@labasa.test` / `password` → `/admin/dashboard`, `/admin/products`, `/admin/products/create`, `/admin/recommendations/create`, `/admin/orders`.
   - **Customer** as `pembeli@labasa.test` → `/`, `/shop`, `/shop/product/1`, `/cart`, `/orders`, `/profile`.
   - **Auth** logged out → `/login`, `/register`.
3. Confirm: pages render, DataTables work, forms submit, sidebar collapse persists across reloads, flash messages show, mobile customer menu opens, no broken icons, no obvious unstyled elements.
4. No new PHP errors in `php artisan pail`.

## Follow-up — 2026-05-16

The original spec left two files on the old "atelier" design: `components/admin/sidebar.blade.php` (254-line `<style>` block) and `customer/profile/edit.blade.php` (93-line `<style>` block, originally out of scope as a page-level view). Both are now brought in line with the simplified design.

- **Sidebar:** `<style>` block deleted entirely. Flat Tailwind utilities — white bg, `border-r border-slate-200`, emerald active state. Uses the `.sidebar-label / .sidebar-section / .sidebar-brand-text / .sidebar-user-meta / .sidebar-badge` class names the admin layout's collapse CSS already expects, plus `.sidebar-brandbar / .sidebar-link / .sidebar-user` hooks for collapsed-state centering.
- **Profile page:** `<style>` block deleted. Single-column layout — identity card + form card + session card — built from Tailwind utilities and the customer layout's existing `.field / .label / .input / .field-error / .badge` classes.
- **Admin layout:** removed redundant `border-radius`/`padding` from `.btn-primary/-secondary/-danger` (already set by `.btn`); added collapsed-state centering + toggle-chevron rotation. Customer and auth layout `<style>` blocks were already minimal — left unchanged.
