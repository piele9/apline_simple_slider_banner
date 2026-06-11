# Changelog

All notable changes to **APLINE Simple Slider Banner for PrestaShop 9**
will be documented in this file. Format based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] – 2026-06-11

Two pre-release polish features: fixed slider dimensions (so the
slider height stops jumping when slides have differently sized
images) and a per-viewport Bootstrap container wrapper.

### Added
- **Fixed slider size (`ASSB_SIZING_MODE` = `natural` | `fixed`).**
  In `fixed` mode every slide shares set dimensions, configured
  **separately for desktop and mobile**
  (`ASSB_FIXED_W_DESKTOP` / `ASSB_FIXED_H_DESKTOP` /
  `ASSB_FIXED_W_MOBILE` / `ASSB_FIXED_H_MOBILE`). The admin picks a
  suggested preset or types a custom width × height. Implemented in
  CSS as `aspect-ratio` + `max-width`, so a fixed slider scales down
  proportionally on screens narrower than the chosen width instead of
  overflowing. Default is `natural` — identical to v1.1.0 behaviour.
- **Image fit for fixed mode (`ASSB_FILL_MODE` = `cover` | `fill` |
  `contain`).** `cover` (default) fills and crops without distortion;
  `fill` stretches to the exact box; `contain` letterboxes. Rendered
  as CSS `object-fit`.
- **Per-viewport Bootstrap container.** A single
  `ASSB_CONTAINER` from v1.1.0 is replaced by an explicit
  "My theme uses Bootstrap" switch (`ASSB_BOOTSTRAP`) plus
  **independent** desktop and mobile container selects
  (`ASSB_CONTAINER_DESKTOP` / `ASSB_CONTAINER_MOBILE`, each
  `none` / `container` / `container-fluid`). Each slider now sits in
  its own outer wrapper (`.assb-outer--desktop` / `.assb-outer--mobile`)
  so the page-width decision can differ per viewport. Bootstrap is an
  explicit toggle, not auto-detected (server-side detection of a
  theme's loaded CSS is unreliable).
- **Configuration form regrouped** into three fieldsets — *Slider
  behaviour*, *Slide size*, *Page layout* — with a small progressive-
  enhancement script that fills the width/height fields from a preset
  and hides the fixed-size fields in natural mode.

### Changed
- **Dimension validation rejects, never clamps** (workspace policy):
  out-of-range width/height or a proportion that would flip a
  landscape banner into a portrait one returns a specific form error,
  leaving the previous values intact. A defensive clamp is applied
  only at render time, so a tampered Configuration row can't break the
  markup.

### Removed
- The single `ASSB_CONTAINER` configuration key (superseded by the
  per-viewport `ASSB_BOOTSTRAP` + `ASSB_CONTAINER_DESKTOP` +
  `ASSB_CONTAINER_MOBILE`).

### Upgrade notes
- **Fresh install / reinstall.** No upgrade script ships with 1.2.0
  (the module had not been publicly released between 1.1.0 and 1.2.0).
  `ASSB_CONTAINER` is dropped on uninstall; the new keys are seeded
  with safe defaults (natural sizing, Bootstrap off) on install, so a
  fresh install behaves exactly like 1.1.0 until you opt into the new
  options.

## [1.1.0] – 2026-05-27

Three regressions / feature requests from the v1.0.0 smoke test:
the display-location picker was friction, the slider needed a
container-layout option, and the cross-viewport image fallback was
silently masking configuration mistakes.

### Changed
- **Slider now auto-renders only on `displayHome`.** The four-option
  "Display location" selector (and the `ASSB_HOOK` configuration key,
  the `getAvailableHooks()` helper, and the three optional
  `hookDisplay*` methods) have been removed. The Widget API
  (`{widget name='apline_simple_slider_banner'}`) is preserved for
  explicit theme embedding.
- **Mobile and desktop are now rendered as two independent sliders**
  (`.assb-slider--desktop` / `.assb-slider--mobile`) toggled via
  `@media (max-width: 767px)`. Each slider only includes slides that
  have both the matching image and the matching `show_on_*` flag.
  The `<picture>` element is gone — each slider has its own `<img>`
  per slide. The vanilla JS slider's multi-instance init from v1.0.0
  picks both sliders up with no changes.
- **"Show on desktop" / "Show on mobile" switch descriptions**
  updated to reflect strict per-viewport rendering (no more fallback
  language).

### Added
- **`ASSB_CONTAINER` configuration** — pick between edge-to-edge
  (default, matches v1.0.x behaviour), `.container` (page width) or
  `.container-fluid` (full browser width with padding). One shared
  wrapper hugs both viewport sliders.
- **`ASSB_CUSTOM_CLASS` configuration** — optional CSS class added
  to both slider roots (alphanumeric + space / dash / underscore,
  max 64 chars, server-side validated and HTML-escaped in the
  template).
- **Admin form rejects slides where a viewport is enabled but the
  matching image is missing** — previously this combination silently
  rendered the wrong image as fallback. Now it returns a specific
  form error: *"Desktop visibility is enabled but no desktop image
  is set."* (or the symmetric version for mobile).
- **Admin form QoL** — a viewport's visibility radio is `disabled`
  until the matching image is uploaded (defensive UI nudge; the
  server-side validation in `handleSubmission` is the source of
  truth).

### Removed
- `ASSB_HOOK` configuration key and its 4-option select from the
  configuration form.
- `getAvailableHooks()` helper and the `hookDisplayTop`,
  `hookDisplayFooter`, `hookDisplayContentWrapperTop` hook methods.
- `<picture>` element with `<source media>` from the front
  template — each viewport-specific slider has its own `<img>` per
  slide.
- Automatic image fallback between desktop and mobile in
  `buildSlides()` (the v1.0.x silent rendering of the "other"
  viewport's image when the matching one was missing).

### Upgrade notes
- **Fresh install required.** No upgrade script ships with 1.1.0.
  Uninstall the module from BO, then re-upload the new zip — this
  drops `ASSB_HOOK` from `ps_configuration` and re-seeds the 3 demo
  slides. Custom slides created in 1.0.x will be lost on uninstall
  (export the `ps_assb_slide` table first if you need to preserve
  them). The slide schema is unchanged between 1.0.x and 1.1.0, so
  a manual DB dump can be re-imported after re-install if needed.

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
