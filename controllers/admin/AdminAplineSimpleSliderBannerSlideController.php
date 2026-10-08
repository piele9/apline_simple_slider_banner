<?php
/**
 * APLINE Simple Slider Banner module for PrestaShop 9.
 *
 * Hidden admin controller for CRUD on `assb_slide`. Reachable from the
 * module configuration page via the "Manage slides" link.
 *
 * Notable: every slide has TWO image fields (desktop + mobile) with
 * independent upload hardening, optional "remove current image" toggles
 * and independent alt-text fields. The pattern is borrowed from the
 * PDF Instructions module hot-fix where a single image had a removal
 * switch — here we apply it twice in parallel.
 *
 * @author    APLINE Arkadiusz Pielechowski
 * @copyright APLINE Arkadiusz Pielechowski
 * @license   Custom Attribution License v1.0 - see LICENSE.md
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'apline_simple_slider_banner/classes/AplineSimpleSliderBannerSlide.php';

class AdminAplineSimpleSliderBannerSlideController extends ModuleAdminController
{
    const MAX_IMG_BYTES = 4194304; // 4 MB (sliders carry larger graphics than icons)
    const MAX_STRING = 255;
    const MAX_URL = 2048;
    const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp'];
    const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'assb_slide';
        $this->className = 'AplineSimpleSliderBannerSlide';
        $this->identifier = 'id_assb_slide';
        $this->position_identifier = 'id_assb_slide';
        $this->lang = false;
        $this->allow_export = false;

        parent::__construct();

        $this->fields_list = [
            'id_assb_slide' => [
                'title' => $this->trans('ID', [], 'Admin.Global'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'image_desktop' => [
                'title' => $this->trans('Komputer', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'align' => 'center',
                'callback' => 'printImageDesktop',
                'orderby' => false,
                'search' => false,
            ],
            'image_mobile' => [
                'title' => $this->trans('Telefon', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'align' => 'center',
                'callback' => 'printImageMobile',
                'orderby' => false,
                'search' => false,
            ],
            'title' => [
                'title' => $this->trans('Tytuł (wewnętrzny)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
            ],
            'url' => [
                'title' => $this->trans('Adres linku', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'callback' => 'printUrl',
                'search' => false,
            ],
            'viewports' => [
                'title' => $this->trans('Ekrany', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'align' => 'center',
                'callback' => 'printViewports',
                'orderby' => false,
                'search' => false,
            ],
            'active' => [
                'title' => $this->trans('Widoczny', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'align' => 'center',
                'active' => 'active',
                'type' => 'bool',
                'orderby' => false,
            ],
            'position' => [
                'title' => $this->trans('Pozycja', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'align' => 'center',
                'position' => 'position',
                'search' => false,
            ],
        ];

        $this->_defaultOrderBy = 'position';
        $this->_defaultOrderWay = 'ASC';

        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->trans('Usuń zaznaczone', [], 'Admin.Actions'),
                'confirm' => $this->trans('Usunąć zaznaczone slajdy?', [], 'Admin.Notifications.Warning'),
            ],
        ];
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addJqueryUI('ui.sortable');
    }

    /**
     * Module configuration URL (so the user can get back from the slide list).
     *
     * @return string
     */
    private function getConfigUrl()
    {
        return $this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => 'apline_simple_slider_banner',
            'module_name' => 'apline_simple_slider_banner',
        ]);
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();

        $this->page_header_toolbar_btn['back_to_config'] = [
            'href' => $this->getConfigUrl(),
            'desc' => $this->trans('Wróć do konfiguracji', [], 'Modules.Aplinesimplesliderbanner.Admin'),
            'icon' => 'process-icon-back',
        ];
    }

    public function renderList()
    {
        $list = parent::renderList();

        // Breadcrumb-style back link + mandatory APLINE attribution under the table.
        $back = '<div style="margin:10px 0;"><a class="btn btn-default" href="'
            . htmlspecialchars($this->getConfigUrl(), ENT_QUOTES)
            . '"><i class="icon-chevron-left"></i> '
            . $this->trans('Wróć do konfiguracji', [], 'Modules.Aplinesimplesliderbanner.Admin')
            . '</a></div>';

        $credit = method_exists($this->module, 'renderAplineFooter')
            ? $this->module->renderAplineFooter()
            : '';

        return $back . $list . $credit;
    }

    // ------------------------------------------------------------------
    // Print callbacks for the slide list (fields_list columns)
    // ------------------------------------------------------------------

    /**
     * @param string $image stored public path
     *
     * @return string list cell HTML
     */
    public function printImageDesktop($image, $row)
    {
        if (!empty($image)) {
            return '<img src="' . htmlspecialchars($image, ENT_QUOTES) . '" style="max-height:45px;max-width:80px;" alt="">';
        }

        return '<span class="text-muted">&mdash;</span>';
    }

    /**
     * @param string $image stored public path
     *
     * @return string list cell HTML
     */
    public function printImageMobile($image, $row)
    {
        if (!empty($image)) {
            return '<img src="' . htmlspecialchars($image, ENT_QUOTES) . '" style="max-height:60px;max-width:45px;" alt="">';
        }

        return '<span class="text-muted">&mdash;</span>';
    }

    /**
     * @return string list cell HTML
     */
    public function printUrl($value, $row)
    {
        $url = trim((string) $value);
        if ($url === '') {
            return '<em class="text-muted">' . $this->trans('bez linku', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</em>';
        }

        $display = $url;
        if (preg_match('#^https?://([^/]+)#i', $url, $m)) {
            $display = $m[1]; // just the host
        } elseif (mb_strlen($url) > 40) {
            $display = mb_substr($url, 0, 37) . '...';
        }

        return '<i class="icon-link"></i> ' . htmlspecialchars($display, ENT_QUOTES);
    }

    /**
     * @return string list cell HTML — viewport chip (desktop / mobile / both)
     */
    public function printViewports($value, $row)
    {
        $desktop = !empty($row['show_on_desktop']);
        $mobile = !empty($row['show_on_mobile']);

        if ($desktop && $mobile) {
            return '<span class="badge badge-success" title="Komputer i telefon">'
                . $this->trans('Komputer i telefon', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</span>';
        }
        if ($desktop) {
            return '<span class="badge badge-info">' . $this->trans('Tylko komputer', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</span>';
        }
        if ($mobile) {
            return '<span class="badge badge-info">' . $this->trans('Tylko telefon', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</span>';
        }

        return '<span class="badge badge-danger">' . $this->trans('Ukryty', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</span>';
    }

    // ------------------------------------------------------------------
    // Edit form
    // ------------------------------------------------------------------

    public function renderForm()
    {
        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->fields_form = [
            'legend' => [
                'title' => $this->trans('Slajd', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'icon' => 'icon-picture',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Tytuł (wewnętrzny)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'title',
                    'required' => true,
                    'desc' => $this->trans('Nazwa do rozróżniania slajdów na liście w panelu. Nie jest wyświetlana klientom.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'file',
                    'label' => $this->trans('Obraz na komputer', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'image_desktop_file',
                    'desc' => $this->trans('Opcjonalny. Zalecane proporcje 16:9 (np. 1920×1080). Formaty: JPG, PNG, WEBP. Maks. 4 MB.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Usuń obecny obraz na komputer', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'remove_image_desktop',
                    'is_bool' => true,
                    'desc' => $this->trans('Włącz i zapisz, aby usunąć obecny obraz. Opcja jest pomijana, jeśli przesyłasz nowy obraz powyżej.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'values' => [
                        ['id' => 'remove_desktop_on', 'value' => 1, 'label' => $this->trans('Tak', [], 'Admin.Global')],
                        ['id' => 'remove_desktop_off', 'value' => 0, 'label' => $this->trans('Nie', [], 'Admin.Global')],
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Tekst alternatywny (komputer)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'alt_desktop',
                    'desc' => $this->trans('Opis dla czytników ekranu i SEO. Jeśli pusty, zostanie uzupełniony z nazwy pliku.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'file',
                    'label' => $this->trans('Obraz na telefon', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'image_mobile_file',
                    'desc' => $this->trans('Opcjonalny. Zalecane proporcje 4:3 (np. 800×600) lub 1:1 (np. 800×800). Formaty: JPG, PNG, WEBP. Maks. 4 MB.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Usuń obecny obraz na telefon', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'remove_image_mobile',
                    'is_bool' => true,
                    'desc' => $this->trans('Włącz i zapisz, aby usunąć obecny obraz na telefon. Opcja jest pomijana, jeśli przesyłasz nowy obraz powyżej.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'values' => [
                        ['id' => 'remove_mobile_on', 'value' => 1, 'label' => $this->trans('Tak', [], 'Admin.Global')],
                        ['id' => 'remove_mobile_off', 'value' => 0, 'label' => $this->trans('Nie', [], 'Admin.Global')],
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Tekst alternatywny (telefon)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'alt_mobile',
                    'desc' => $this->trans('Opis dla czytników ekranu i SEO. Jeśli pusty, zostanie uzupełniony z nazwy pliku.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Adres linku', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'url',
                    'desc' => $this->trans('Opcjonalny adres docelowy po kliknięciu slajdu. Pusty oznacza slajd bez linku. Obsługiwane są adresy bezwzględne (https://...) i względne (/kategoria/przyklad).', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Pokaż na komputerze', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'show_on_desktop',
                    'is_bool' => true,
                    'desc' => $this->trans('Wyświetlaj na ekranach od 768 px. Wymagany jest obraz na komputer; formularz odrzuci zapis bez niego. Obraz telefonu nie jest używany zastępczo.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'values' => [
                        ['id' => 'show_desktop_on', 'value' => 1, 'label' => $this->trans('Tak', [], 'Admin.Global')],
                        ['id' => 'show_desktop_off', 'value' => 0, 'label' => $this->trans('Nie', [], 'Admin.Global')],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Pokaż na telefonie', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'show_on_mobile',
                    'is_bool' => true,
                    'desc' => $this->trans('Wyświetlaj na ekranach do 767 px. Wymagany jest obraz na telefon; formularz odrzuci zapis bez niego. Obraz komputera nie jest używany zastępczo.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'values' => [
                        ['id' => 'show_mobile_on', 'value' => 1, 'label' => $this->trans('Tak', [], 'Admin.Global')],
                        ['id' => 'show_mobile_off', 'value' => 0, 'label' => $this->trans('Nie', [], 'Admin.Global')],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Widoczny', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'active',
                    'is_bool' => true,
                    'values' => [
                        ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Tak', [], 'Admin.Global')],
                        ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('Nie', [], 'Admin.Global')],
                    ],
                ],
            ],
            'submit' => ['class' => 'btn btn-primary btn-lg apline-btn-duzy pull-right', 'title' => $this->trans('Zapisz', [], 'Admin.Actions')],
        ];

        // Preview of the current images when editing.
        if (($obj = $this->loadObject(true)) && Validate::isLoadedObject($obj)) {
            if (!empty($obj->image_desktop)) {
                // Input index for image_desktop_file in the input array above is 1.
                $this->fields_form['input'][1]['image'] =
                    '<img src="' . htmlspecialchars($obj->image_desktop, ENT_QUOTES) . '" style="max-height:80px;max-width:200px;border:1px solid #ddd;padding:2px;">';
            }
            if (!empty($obj->image_mobile)) {
                // Input index for image_mobile_file in the input array above is 4.
                $this->fields_form['input'][4]['image'] =
                    '<img src="' . htmlspecialchars($obj->image_mobile, ENT_QUOTES) . '" style="max-height:120px;max-width:90px;border:1px solid #ddd;padding:2px;">';
            }
        }

        // Sensible defaults for the "add" form (PrestaShop reuses fields_value).
        if (!Tools::getValue($this->identifier)) {
            $this->fields_value = [
                'show_on_desktop' => 1,
                'show_on_mobile' => 1,
                'active' => 1,
                'remove_image_desktop' => 0,
                'remove_image_mobile' => 0,
            ];
        }

        return parent::renderForm() . $this->renderViewportToggleScript();
    }

    /**
     * Inline JS appended to the slide edit form (CP09 QoL).
     *
     * Disables the "Show on desktop" / "Show on mobile" radio pair until
     * the matching image is present (either already saved on this slide,
     * or just selected via the file input). Prevents the admin from
     * submitting a known-bad combination — although handleSubmission()
     * is the source of truth and rejects the same case on the server.
     *
     * If the admin un-checks an image (no fresh file selected and no
     * existing image), the visibility radio flips to "No" so the form
     * stays in a coherent state at submit time.
     *
     * The script is defensive: if the form markup ever changes and the
     * radios / file inputs are not found, the script no-ops and the
     * server-side validation still catches mismatches.
     *
     * @return string
     */
    private function renderViewportToggleScript()
    {
        $hasDesktopImage = false;
        $hasMobileImage = false;
        if (($obj = $this->loadObject(true)) && Validate::isLoadedObject($obj)) {
            $hasDesktopImage = !empty($obj->image_desktop);
            $hasMobileImage = !empty($obj->image_mobile);
        }

        $desktopInitial = $hasDesktopImage ? 'true' : 'false';
        $mobileInitial = $hasMobileImage ? 'true' : 'false';

        return '
<script>
(function () {
    function toggleViewport(viewport, hasImage) {
        var radios = document.querySelectorAll(
            \'input[name="show_on_\' + viewport + \'"]\'
        );
        if (!radios.length) { return; }
        for (var i = 0; i < radios.length; i++) {
            radios[i].disabled = !hasImage;
        }
        if (!hasImage) {
            var onRadio = document.querySelector(
                \'input[name="show_on_\' + viewport + \'"][value="1"]\'
            );
            var offRadio = document.querySelector(
                \'input[name="show_on_\' + viewport + \'"][value="0"]\'
            );
            if (onRadio && onRadio.checked && offRadio) {
                offRadio.checked = true;
            }
        }
    }
    function wireField(viewport, initiallyHasImage) {
        toggleViewport(viewport, initiallyHasImage);
        var fileInput = document.querySelector(
            \'input[type="file"][name="image_\' + viewport + \'_file"]\'
        );
        if (fileInput) {
            fileInput.addEventListener("change", function () {
                var picked = fileInput.files && fileInput.files.length > 0;
                toggleViewport(viewport, picked || initiallyHasImage);
            });
        }
    }
    document.addEventListener("DOMContentLoaded", function () {
        wireField("desktop", ' . $desktopInitial . ');
        wireField("mobile", ' . $mobileInitial . ');
    });
})();
</script>';
    }

    // ------------------------------------------------------------------
    // Save flow
    // ------------------------------------------------------------------

    public function postProcess()
    {
        $isAdd = Tools::isSubmit('submitAdd' . $this->table) && !Tools::getValue($this->identifier);
        $isUpdate = Tools::isSubmit('submitAdd' . $this->table) && Tools::getValue($this->identifier);

        if ($isAdd || $isUpdate) {
            $existing = null;
            if ($isUpdate) {
                $existing = new AplineSimpleSliderBannerSlide((int) Tools::getValue($this->identifier));
                if (!Validate::isLoadedObject($existing)) {
                    $this->errors[] = $this->trans('Slajd, który próbujesz edytować, nie istnieje.', [], 'Modules.Aplinesimplesliderbanner.Admin');

                    return false;
                }
            }

            if (!$this->handleSubmission($existing)) {
                // Errors already pushed to $this->errors: abort before any DB
                // write and keep the form open so the user can fix the input.
                $this->display = $isUpdate ? 'edit' : 'add';

                return false;
            }
        }

        return parent::postProcess();
    }

    /**
     * Validate input and the optional uploaded images (both desktop AND
     * mobile, independently), then inject the resulting values into $_POST
     * so the standard ObjectModel save picks them up.
     * On any failure, populate $this->errors and return false (no save happens).
     *
     * @param AplineSimpleSliderBannerSlide|null $existing
     *
     * @return bool
     */
    private function handleSubmission($existing)
    {
        // ---------- Read raw POST values ----------
        $title = trim((string) Tools::getValue('title'));
        $altDesktop = trim((string) Tools::getValue('alt_desktop'));
        $altMobile = trim((string) Tools::getValue('alt_mobile'));
        $url = trim((string) Tools::getValue('url'));
        $showOnDesktop = (int) Tools::getValue('show_on_desktop') ? 1 : 0;
        $showOnMobile = (int) Tools::getValue('show_on_mobile') ? 1 : 0;
        $active = (int) Tools::getValue('active') ? 1 : 0;
        $removeDesktop = (int) Tools::getValue('remove_image_desktop') === 1;
        $removeMobile = (int) Tools::getValue('remove_image_mobile') === 1;

        // ---------- Validation 1: title required + length ----------
        if ($title === '') {
            $this->errors[] = $this->trans('Tytuł jest wymagany.', [], 'Modules.Aplinesimplesliderbanner.Admin');
        } elseif (mb_strlen($title) > self::MAX_STRING) {
            $this->errors[] = $this->trans('Tytuł przekracza limit %d znaków.', [self::MAX_STRING], 'Modules.Aplinesimplesliderbanner.Admin');
        }

        // ---------- Validation 5: max length for alt fields ----------
        foreach (['Tekst alternatywny (komputer)' => $altDesktop, 'Tekst alternatywny (telefon)' => $altMobile] as $label => $value) {
            if (mb_strlen($value) > self::MAX_STRING) {
                $this->errors[] = $this->trans('Pole "%s" przekracza limit %d znaków.', [$label, self::MAX_STRING], 'Modules.Aplinesimplesliderbanner.Admin');
            }
        }

        // ---------- Validation 4 + 5: URL format + max length ----------
        if ($url !== '') {
            if (mb_strlen($url) > self::MAX_URL) {
                $this->errors[] = $this->trans('Adres linku przekracza limit %d znaków.', [self::MAX_URL], 'Modules.Aplinesimplesliderbanner.Admin');
            } elseif (!Validate::isUrl($url)) {
                $this->errors[] = $this->trans('Adres linku jest nieprawidłowy.', [], 'Modules.Aplinesimplesliderbanner.Admin');
            }
        }

        // ---------- Validation 6: at-least-one viewport on ----------
        if (!$showOnDesktop && !$showOnMobile) {
            $this->errors[] = $this->trans('Slajd musi być widoczny na co najmniej jednym ekranie (komputer lub telefon).', [], 'Modules.Aplinesimplesliderbanner.Admin');
        }

        // ---------- Validation 7: upload hardening for BOTH file fields ----------
        $newDesktopPath = $this->handleUpload('image_desktop_file');
        if ($newDesktopPath === false) {
            // errors already pushed by handleUpload
            $newDesktopPath = null;
        }
        $newMobilePath = $this->handleUpload('image_mobile_file');
        if ($newMobilePath === false) {
            $newMobilePath = null;
        }

        // ---------- Resolve effective image paths (remove_image takes priority over keep-existing) ----------
        $effectiveDesktop = $newDesktopPath;
        if (null === $effectiveDesktop && !$removeDesktop && $existing && !empty($existing->image_desktop)) {
            $effectiveDesktop = $existing->image_desktop;
        }
        $effectiveMobile = $newMobilePath;
        if (null === $effectiveMobile && !$removeMobile && $existing && !empty($existing->image_mobile)) {
            $effectiveMobile = $existing->image_mobile;
        }

        // ---------- Validation 2: at-least-one image after the smoke clears ----------
        if (empty($effectiveDesktop) && empty($effectiveMobile)) {
            $this->errors[] = $this->trans('Slajd musi mieć co najmniej jeden obraz (komputer lub telefon).', [], 'Modules.Aplinesimplesliderbanner.Admin');
        }

        // ---------- Validation v1.1.0: visibility flag requires matching image ----------
        // CP09 (front 3) renders desktop and mobile as two independent sliders
        // with no cross-viewport fallback. A slide with show_on_desktop=1 but
        // no desktop image would silently disappear from the desktop slider.
        // Reject the submission instead so the admin explicitly chooses:
        // upload the image, or disable the visibility flag.
        if ($showOnDesktop && empty($effectiveDesktop)) {
            $this->errors[] = $this->trans('Włączono widoczność na komputerze, ale brakuje obrazu. Prześlij obraz lub wyłącz opcję Pokaż na komputerze.', [], 'Modules.Aplinesimplesliderbanner.Admin');
        }
        if ($showOnMobile && empty($effectiveMobile)) {
            $this->errors[] = $this->trans('Włączono widoczność na telefonie, ale brakuje obrazu. Prześlij obraz lub wyłącz opcję Pokaż na telefonie.', [], 'Modules.Aplinesimplesliderbanner.Admin');
        }

        // ---------- Validation 3: alt required when image is set; auto-fill from filename ----------
        if (!empty($effectiveDesktop) && $altDesktop === '') {
            $altDesktop = $this->altFromFilename($effectiveDesktop);
        }
        if (!empty($effectiveMobile) && $altMobile === '') {
            $altMobile = $this->altFromFilename($effectiveMobile);
        }

        // If any error so far, clean up the freshly uploaded files (so we don't litter disk).
        if (!empty($this->errors)) {
            if ($newDesktopPath) {
                @unlink($this->module->getUploadDir() . basename($newDesktopPath));
            }
            if ($newMobilePath) {
                @unlink($this->module->getUploadDir() . basename($newMobilePath));
            }

            return false;
        }

        // ---------- Cleanup: remove the previous file when replaced or explicitly removed ----------
        $shouldUnlinkOldDesktop = $existing
            && !empty($existing->image_desktop)
            && ($newDesktopPath || $removeDesktop);
        if ($shouldUnlinkOldDesktop) {
            $old = $this->module->getUploadDir() . basename($existing->image_desktop);
            if (is_file($old)) {
                @unlink($old);
            }
        }
        $shouldUnlinkOldMobile = $existing
            && !empty($existing->image_mobile)
            && ($newMobilePath || $removeMobile);
        if ($shouldUnlinkOldMobile) {
            $old = $this->module->getUploadDir() . basename($existing->image_mobile);
            if (is_file($old)) {
                @unlink($old);
            }
        }

        // ---------- Feed validated values into the standard ObjectModel save flow ----------
        $_POST['title'] = $title;
        $_POST['image_desktop'] = $effectiveDesktop ? $effectiveDesktop : '';
        $_POST['image_mobile'] = $effectiveMobile ? $effectiveMobile : '';
        $_POST['alt_desktop'] = $altDesktop;
        $_POST['alt_mobile'] = $altMobile;
        $_POST['url'] = $url;
        $_POST['show_on_desktop'] = $showOnDesktop;
        $_POST['show_on_mobile'] = $showOnMobile;
        $_POST['active'] = $active;

        return true;
    }

    /**
     * Validates an uploaded file from $_FILES[$fieldName] using the
     * Upload validation 5-layer hardening (extension whitelist +
     * byte cap + getimagesize + ImageManager::isRealImage + @unlink fail
     * cleanup). Returns the public relative path of the saved file on
     * success, null if no file was sent, or false on validation error
     * (errors pushed to $this->errors).
     *
     * @param string $fieldName
     *
     * @return string|null|false
     */
    private function handleUpload($fieldName)
    {
        if (!isset($_FILES[$fieldName])
            || !isset($_FILES[$fieldName]['error'])
            || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
        ) {
            return null; // no file uploaded — caller treats as "keep existing"
        }

        $file = $_FILES[$fieldName];
        $label = $fieldName === 'image_desktop_file' ? 'komputer' : 'telefon';

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->trans('Nie udało się przesłać obrazu (%s). Spróbuj ponownie.', [$label], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            $this->errors[] = $this->trans('Nieprawidłowy format obrazu (%s). Dozwolone: JPG, PNG, WEBP.', [$label], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        if ((int) $file['size'] > self::MAX_IMG_BYTES) {
            $this->errors[] = $this->trans('Obraz (%s) jest zbyt duży. Maksymalny rozmiar to 4 MB.', [$label], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        // Inspect real content, not just the extension: blocks an executable
        // payload renamed with an image extension .
        $info = @getimagesize($file['tmp_name']);
        $realMime = is_array($info) && isset($info['mime']) ? $info['mime'] : '';
        $isRealImage = class_exists('ImageManager')
            ? ImageManager::isRealImage($file['tmp_name'], $file['type'], self::ALLOWED_MIME)
            : in_array($realMime, self::ALLOWED_MIME, true);

        if (!$info || !in_array($realMime, self::ALLOWED_MIME, true) || !$isRealImage) {
            $this->errors[] = $this->trans('Przesłany plik (%s) nie jest poprawnym obrazem.', [$label], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        $fileName = 'assb_' . uniqid('', true) . '.' . $ext;
        $dest = $this->module->getUploadDir() . $fileName;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            $this->errors[] = $this->trans('Nie udało się zapisać obrazu (%s). Sprawdź uprawnienia katalogu.', [$label], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        @chmod($dest, 0644);

        return __PS_BASE_URI__ . 'modules/apline_simple_slider_banner/views/img/' . $fileName;
    }

    /**
     * Build a human-readable alt text from a stored image path.
     * Strips the `assb_<uniqid>.<ext>` prefix and turns underscores into
     * spaces so admins who skip the alt field get something better than
     * the random uniqid in the rendered <img alt>.
     *
     * @param string $path
     *
     * @return string
     */
    private function altFromFilename($path)
    {
        $base = pathinfo((string) $path, PATHINFO_FILENAME);
        // Strip our own assb_<uniqid> prefix if present (uploads always have it).
        $base = preg_replace('/^assb_[0-9a-f.]+$/i', '', $base);
        if ($base === '' || $base === null) {
            // Generic fallback when nothing useful remains.
            return 'Slider banner';
        }
        $base = str_replace(['_', '-'], ' ', $base);

        return trim(ucfirst($base));
    }

    /**
     * Delete the associated image files when the row is deleted.
     */
    public function processDelete()
    {
        $obj = $this->loadObject(true);
        if (Validate::isLoadedObject($obj)) {
            foreach (['image_desktop', 'image_mobile'] as $field) {
                if (!empty($obj->$field)) {
                    $file = $this->module->getUploadDir() . basename($obj->$field);
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }

        return parent::processDelete();
    }

    public function ajaxProcessUpdatePositions()
    {
        $positions = Tools::getValue($this->table);

        if (!is_array($positions)) {
            die(json_encode(['success' => false]));
        }

        // Reindex deterministically from the order posted by the sortable list:
        // the array order is the new visual order, so assign 1..n sequentially.
        $pos = 1;
        foreach ($positions as $value) {
            // Row token looks like "<table>_<id>" or "<table>_<x>_<id>";
            // the object id is always the last numeric segment.
            $parts = explode('_', (string) $value);
            $id = (int) end($parts);
            if (!$id) {
                continue;
            }
            Db::getInstance()->update(
                'assb_slide',
                ['position' => $pos++],
                'id_assb_slide = ' . $id
            );
        }

        die(json_encode(['success' => true]));
    }
}
