# Product & Hero Images — Design

**Date:** 2026-08-12
**Status:** Approved

## Problem

The storefront renders placeholder blocks instead of imagery:

- **Products** — `products.image` is `NULL` for all four seeded rows. The Blade views
  already contain complete `@if ($p->image) … @else …placeholder… @endif` branches and the
  `image` column exists and is fillable, so nothing is missing except the data.
- **Landing hero** — has no image support at all. It is a hardcoded
  `<div class="aspect-[4/5] bg-ink-100">` containing the word "Labasa."

Result: the storefront reads as unfinished in screenshots and demos.

## Constraint that drives the design

`storage/app/public/` is gitignored (`storage/app/public/.gitignore` contains `*`). Files
written there do not survive a fresh clone, and are not restored by `migrate:fresh --seed`.
Seed imagery must therefore be committed somewhere else and copied in at seed time.

## Design

### 1. Product images

Four 4:5 SVGs are committed at `database/seeders/assets/products/`:

| File | Product |
|---|---|
| `gamis-anaya-navy.svg` | Gamis Anaya Navy |
| `koko-modern-sage.svg` | Koko Modern Sage |
| `tunik-basic-cream.svg` | Tunik Basic Cream |
| `hijab-pashmina-plain.svg` | Hijab Pashmina Plain |

`DatabaseSeeder` copies each into `storage/app/public/products/` and sets
`products.image = 'products/<file>.svg'`.

Consequences:

- **No Blade changes for products.** The existing `asset('storage/' . $p->image)` resolves
  correctly on shop index, landing grid, product detail, cart, customer orders, and admin
  product show/edit.
- Survives `migrate:fresh --seed` and a fresh clone.
- Uses the same storage path a real upload uses, so admin edit previews work and a real
  photo can replace the SVG later with no code change.

### 2. Hero image

The hero is a design asset, not a database entity, so it is committed directly to
`public/images/hero.svg` (not gitignored) and referenced from
`resources/views/customer/shop/landing.blade.php`, replacing the placeholder `<div>`.

The floating "Produksi sendiri / Workshop Surabaya" card stays, overlaid on the image.

### 3. Fallbacks

Every existing `@else` placeholder branch is left intact, so a product with no image still
degrades gracefully rather than rendering a broken-image icon.

### 4. Artwork

Each SVG uses the app's own palette — `ink` (slate: `#f8fafc`…`#0f172a`) and `accent`
(emerald: `#ecfdf5`, `#10b981`, `#059669`, `#047857`).

Composition per file: soft vertical gradient ground, a subtle woven-fabric texture via
`<pattern>`, a centered garment silhouette whose shape differs per category (gamis = long
A-line; koko = collared tunic; tunik = shorter hip-length; hijab = draped rectangle), and a
small uppercase category label.

Self-contained: no external fonts, no network references, no raster embeds. Target ≈3–5 KB
each so they stay diff-friendly in git.

## Accepted trade-off

SVG silhouettes read as tasteful illustration, not photography. This is acceptable for a
consistent portfolio/demo appearance. Real photographs would be more convincing for a
storefront; they can be dropped into the same seeder asset folder later without any code
change.

## Out of scope

- Image upload UX changes (upload already works).
- Responsive `srcset` / image optimisation.
- Per-size or gallery images (`ProductSize` has no image column).
