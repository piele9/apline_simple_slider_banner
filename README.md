# APLINE Simple Slider Banner for PrestaShop 9

A lightweight, distributable PrestaShop **9.0.x** module that displays
a configurable **image slider / carousel** with **separate desktop
and mobile images per slide**. Native `<picture>` element handles the
viewport switch with zero JavaScript cost. Optional click-through URL,
per-image alt text, drag-and-drop ordering and on/off toggles per
viewport. Configurable navigation (dots / arrows / both / none) and
transitions (slide / fade). Vanilla JS, no Swiper, no Glide, no jQuery
on the front-end.

> Created by **[APLINE](https://apline.pl)** — custom PrestaShop
> development, performance optimization and integrations.

---

## ✨ Features

- ✅ **Separate desktop and mobile images** per slide (no more
  stretched 16:9 banners on 4:3 phones)
- ✅ **Native `<picture>` element** with `<source media>` — the
  browser picks the right image, no JS scaling
- ✅ **WebP support** in the upload pipeline (JPG / PNG / WEBP all
  accepted)
- ✅ Per-slide **click-through URL** (empty = non-clickable slide,
  rendered as `<div>` not `<a>`)
- ✅ Independent **show-on-desktop / show-on-mobile** toggles with
  automatic fallback if one viewport's image is missing
- ✅ Per-image **alt text** for accessibility and SEO, auto-filled
  from the file name if left empty
- ✅ Configurable **display location**: home page, top of every page,
  footer, above main content — or anywhere via
  `{widget name='apline_simple_slider_banner'}`
- ✅ Configurable **navigation**: dots only, arrows only, both, or
  none (autoplay-only)
- ✅ Configurable **transition**: horizontal slide or opacity fade
- ✅ **Autoplay** with configurable speed (500-30000 ms), **pause on
  hover** (optional), **loop forever** or stop after the last slide
- ✅ **Touch swipe** on mobile (50 px threshold, passive listeners)
- ✅ **Keyboard accessibility** — Tab to dots/arrows, Enter/Space to
  activate, `aria-selected` toggle on dot buttons
- ✅ **Drag & drop** ordering in the admin, enable/disable per slide
- ✅ Strict, English-only validation:
  - title required, 255-char limit (rejected, never silently
    truncated)
  - URL format check via `Validate::isUrl` (accepts both absolute
    `https://...` and relative `/category/foo`)
  - at-least-one-image, at-least-one-viewport, alt-required-when-image
  - image upload hardened: **JPG / PNG / WEBP only**, real MIME
    inspection (not just the extension), **4 MB** size cap → blocks
    disguised executables
  - **Remove current image** switch per file slot — clear an image
    without uploading a replacement
- ✅ **Crash-safe**: a rendering or data error yields an empty block,
  never a 500; a failed install rolls back to a clean state
- ✅ **3 demo slides seeded at install** (steel blue / purple / brown
  placeholders generated on the fly via PHP GD) so you can verify
  the slider works immediately
- ✅ Multiple sliders on one page supported (each gets its own JS
  instance)
- ✅ No DRM, no telemetry
- ✅ Public GitHub, custom attribution license, modifiable
- ✅ Released for **PrestaShop 9**

## 📦 Requirements

- PrestaShop **9.0.x** (tested on 9.0.1; not supported on 1.7 / 8.x
  — the module's `ps_versions_compliancy` blocks installation outside
  9.0.x)
- PHP compatible with your PrestaShop 9 install
- PHP **GD extension** (mandatory for PrestaShop anyway, used here for
  on-the-fly placeholder seed generation at install time — if GD is
  missing the install still succeeds, just without demo slides)
- Writable `views/img/` directory (for slide image uploads)

> Always test on a staging copy of your shop before installing on
> production. The module is crash-safe by design (a render error
> yields an empty block, never a 500), but every shop's theme and
> module mix is different.

## 🚀 Installation

**Via Back Office**

1. Download `apline_simple_slider_banner.zip` from the
   *Releases* page on GitHub. The archive contains the
   `apline_simple_slider_banner/` folder at its root with
   forward-slash paths.
2. *Modules → Module Manager → Upload a module* → select the ZIP →
   install.

**Via FTP**

1. Upload the `apline_simple_slider_banner/` folder to `modules/`.
2. *Modules* → find **APLINE Simple Slider Banner for PrestaShop 9**
   → Install.

On install, 3 demo placeholder slides are created with distinct
background colours (steel blue, muted purple, warm brown) so you can
verify the slider works on your home page immediately. Replace them
with your own banners via *Configure → Manage slides*.

## 🧹 Uninstall

**From Back Office** (recommended): *Modules → Module Manager → find
**APLINE Simple Slider Banner for PrestaShop 9** → Uninstall*.

Uninstall is **destructive and idempotent**:

- the `ps_assb_slide` table is dropped — all slide definitions are
  deleted
- all uploaded images in `views/img/assb_*` are removed from disk
  (including the seeded demo placeholders)
- the 7 `ASSB_*` configuration entries are removed
- the hidden admin tab (`AdminAplineSimpleSliderBannerSlide`) is
  removed
- module hook registrations are unregistered

If you want to keep your slide definitions, **back up the
`ps_assb_slide` table and the `views/img/` folder before
uninstalling**. There is no built-in export.

## ⚙️ Usage

### 1. Configure global settings

*Modules* → configure **APLINE Simple Slider Banner for PrestaShop 9**.
Set:

- **Display location** — where the slider renders (home page is the
  default)
- **Speed** — milliseconds between auto-advance (5000 = 5 sec)
- **Autoplay**, **Pause on hover**, **Loop forever** — three
  independent switches
- **Navigation** — dots / arrows / both / none
- **Transition** — slide (horizontal) or fade (opacity)

### 2. Manage slides

*Configure → Manage slides* → *Add new slide*. For each slide:

- **Title** (internal, not shown on the front-end — just helps you
  tell slides apart in the admin list)
- **Desktop image** + **Alt text (desktop)** — suggested ratio 16:9
  (e.g. 1920×1080)
- **Mobile image** + **Alt text (mobile)** — suggested ratio 4:3
  (e.g. 800×600) or 1:1 (e.g. 800×800)
- **Link URL** (optional) — clicking the slide leads here; leave
  empty for a non-clickable slide. Both absolute (`https://...`) and
  relative (`/category/foo`) URLs work.
- **Show on desktop** / **Show on mobile** — independent switches.
  If a viewport is on but its image is missing, the other viewport's
  image is used as a fallback.
- **Active** — global on/off

Reorder slides by drag & drop. The order in the admin list is the
order they appear on the front-end.

### 3. Embed elsewhere (optional)

```smarty
{widget name='apline_simple_slider_banner'}
```

Drop this anywhere in your theme to render the slider, regardless of
the configured display location.

## 🖼️ Screenshots

**Module configuration page** — global settings:

![Module configuration page](docs/config.png)

**Slides management** — drag & drop, dual image preview, viewport
chip:

![Slides management list](docs/slides.png)

**Front-end** — slider on the product page:

![Slider on the front-end](docs/front.png)

## 🛠️ Troubleshooting

### "This file doesn't seem to be a valid zip module"

PrestaShop's installer requires that the **folder name**, the **main
`.php` file name** and the **PHP class name** all match — and the zip
must contain that folder at its root with **forward-slash** paths.

- Re-download the official zip from the GitHub repository's
  *Releases* page; do not rezip the source folder with Windows
  Explorer (it sometimes writes `\` separators that PrestaShop
  rejects).
- If you must rebuild the zip yourself, on PowerShell 5.1 avoid
  `Compress-Archive` — see the build recipe in [CLAUDE.md](CLAUDE.md).

### Slider doesn't show up on the front-end

- *Modules → APLINE Simple Slider Banner → Configure* — make sure
  the **Display location** dropdown is set to where you expect
  (default: *Home page*).
- Make sure at least one slide has the **Active** switch on and at
  least one of *Show on desktop* / *Show on mobile* on.
- Some themes strip the `displayHome` hook on non-home pages. Try
  *Top of every page* instead, or embed the slider manually with
  `{widget name='apline_simple_slider_banner'}` in your theme
  template.
- Clear the PrestaShop cache (*Advanced Parameters → Performance →
  Clear cache*).

### Image upload fails / silent rejection

- The upload folder `modules/apline_simple_slider_banner/views/img/`
  must be writable by PHP. A red warning on the configuration page
  signals it is not — fix the permissions (`chmod 0775` on Linux).
- Only **JPG / PNG / WEBP** files up to **4 MB** are accepted. The
  module inspects the real file content, not just the extension —
  renamed executables will be rejected as "not a valid image".
- To clear an existing image without uploading a new one, toggle
  **Remove current desktop image** or **Remove current mobile image**
  on the edit form and save.

### Demo slides didn't appear after install

The seed step requires PHP's GD extension and a writable
`views/img/` directory. If either is missing, install still
succeeds but the slide list starts empty. Add your own slides via
*Manage slides*. You can verify GD is loaded by running
`php -m | grep -i gd` on your server.

## 📝 License

Custom Attribution License v1.0 — see [LICENSE.md](LICENSE.md).

You may use, modify, distribute and ship this module commercially
and in client projects. You may **not** remove or hide the APLINE
attribution link on the module configuration page. The attribution
must stay visible, link to <https://apline.pl>, and use a readable
font size (≥ 12px).

## 🏢 About APLINE

Need custom PrestaShop development, performance optimization or
integrations?

→ **[APLINE.PL](https://apline.pl)**
