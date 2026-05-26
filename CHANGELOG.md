# Changelog

All notable changes to **APLINE Simple Slider Banner for PrestaShop 9**
will be documented in this file. Format based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] – 2026-05-26

Initial public release.

### Added
- **Image slider** for PrestaShop **9.0.x** with separate **desktop
  and mobile images per slide**, rendered via a native `<picture>`
  element with `<source media="(max-width: 767px)">` — the browser
  picks the right image, no JS scaling on our side.
- Per-slide **click-through URL** (optional) — populated URL renders
  the slide as `<a rel="noopener">`, empty URL renders as `<div>`.
  Accepts both absolute (`https://...`) and relative (`/category/foo`)
  URLs.
- Per-slide **`show_on_desktop` / `show_on_mobile`** flags with
  automatic fallback: if a viewport is on but its image is missing,
  the other viewport's image is used as the source.
- Per-image **alt text** (auto-filled from the file name if left
  blank in the admin form).
- Configurable **display location**: home page, top of every page,
  footer, above main content — or anywhere via
  `{widget name='apline_simple_slider_banner'}`.
- Configurable **navigation**: dots / arrows / both / none.
- Configurable **transition**: horizontal slide (CSS transform
  translateX) or fade (opacity toggle on `.assb-active`).
- **Autoplay** (configurable speed 500-30000 ms), **pause on hover**
  (configurable), **loop forever** or stop after the last slide.
- **Touch swipe** on mobile (50 px threshold, passive listeners,
  touchcancel handler to avoid ghost-nav).
- **Keyboard accessibility** — Tab + Enter/Space on dots, `aria-selected`
  toggle, `role="tab"`, `:focus-visible` outline.
- **Multi-instance support** — `document.querySelectorAll('.assb-slider')`
  iterates and gives each its own `AssbSlider` JS instance. Idempotent
  bootstrap so AJAX re-renders don't double-init.
- **Vanilla JS** (~287 lines) — no Swiper, no Glide, no jQuery on
  the front-end. `window.AssbSlider` exported for theme developers
  who want programmatic instances.
- **Drag & drop** slide ordering in the admin, enable/disable per
  slide, dual image preview in the edit form.
- **Strict English-only validation**: title required, 255-char limit
  rejected (not silently truncated), `Validate::isUrl` for URLs (max
  2048), at-least-one-image after upload+remove resolution,
  at-least-one-viewport, alt-required-when-image with auto-fill from
  filename, **Remove current image** switch per file slot.
- **Hardened image upload**: JPG / PNG / WEBP only (WebP-friendly),
  real MIME inspection via `getimagesize` + `ImageManager::isRealImage`,
  4 MB size cap → blocks disguised executables, automatic `@unlink`
  cleanup on validation failure or when removing/replacing images.
- **Crash-safe** hooks and `WidgetInterface` rendering (`try/catch`
  → empty block + log, never a 500).
- **Install rollback** through `$this->uninstall()` if any
  installDb / installConfiguration / installHooks / installTab step
  fails; **uninstall is idempotent** (`DROP TABLE IF EXISTS`, guarded
  `Tab::getIdFromClassName`, `glob('assb_*') @unlink`).
- **3 demo slides seeded at install** via PHP GD on the fly (steel
  blue / muted purple / warm brown placeholders, 1200×675 desktop +
  600×450 mobile, ~25 KB each). Slide 3 is desktop-only and
  non-clickable — demonstrates the per-viewport flags + the `<div>`
  vs `<a>` wrapper modes. Seed step skips gracefully if GD is missing
  or the upload dir is read-only — install still succeeds.
- *Back to configuration* breadcrumb button on the slides list.
- APLINE attribution block on the configuration page **and** under
  the slides list, with a "Like this module?" call to action linking
  to https://apline.pl.
- Custom Attribution License v1.0 ([LICENSE.md](LICENSE.md)).

### Naming convention (SIMPLE family)
This module is part of the **APLINE SIMPLE** family of PrestaShop
modules. The convention is:

- folder / main `.php` file / PHP class / `$this->name`:
  `apline_simple_<feature>` (all four MUST match — otherwise the
  back-office upload rejects the zip)
- DB table: `<abbrev>_<entity>` (here: `assb_slide`)
- Configuration keys: `<ABBREV>_*` (here: `ASSB_HOOK`, `ASSB_SPEED`,
  etc., seven keys total)
- Translation domain: `Modules.Aplinesimple<feature>.Admin`
  (underscores stripped, ucfirst — here:
  `Modules.Aplinesimplesliderbanner.Admin`)
- ObjectModel class **must be ≤ 32 characters** (PrestaShop's
  `ps_log.object_type` is VARCHAR(32) and `get_class($this)` is
  written there on every ObjectModel validation error) — this module
  uses `AplineSimpleSliderBannerSlide` (29 chars, 3-char margin).
- Repository: `https://github.com/piele9/apline_simple_slider_banner`
