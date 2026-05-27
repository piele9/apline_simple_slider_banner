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
                'title' => $this->trans('Desktop', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'align' => 'center',
                'callback' => 'printImageDesktop',
                'orderby' => false,
                'search' => false,
            ],
            'image_mobile' => [
                'title' => $this->trans('Mobile', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'align' => 'center',
                'callback' => 'printImageMobile',
                'orderby' => false,
                'search' => false,
            ],
            'title' => [
                'title' => $this->trans('Title (internal)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
            ],
            'url' => [
                'title' => $this->trans('Link URL', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'callback' => 'printUrl',
                'search' => false,
            ],
            'viewports' => [
                'title' => $this->trans('Viewports', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'align' => 'center',
                'callback' => 'printViewports',
                'orderby' => false,
                'search' => false,
            ],
            'active' => [
                'title' => $this->trans('Displayed', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'align' => 'center',
                'active' => 'active',
                'type' => 'bool',
                'orderby' => false,
            ],
            'position' => [
                'title' => $this->trans('Position', [], 'Modules.Aplinesimplesliderbanner.Admin'),
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
                'text' => $this->trans('Delete selected', [], 'Admin.Actions'),
                'confirm' => $this->trans('Delete selected slides?', [], 'Admin.Notifications.Warning'),
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
            'desc' => $this->trans('Back to configuration', [], 'Modules.Aplinesimplesliderbanner.Admin'),
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
            . $this->trans('Back to configuration', [], 'Modules.Aplinesimplesliderbanner.Admin')
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
            return '<em class="text-muted">' . $this->trans('no link', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</em>';
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
            return '<span class="badge badge-success" title="Both viewports">'
                . $this->trans('Desktop + Mobile', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</span>';
        }
        if ($desktop) {
            return '<span class="badge badge-info">' . $this->trans('Desktop only', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</span>';
        }
        if ($mobile) {
            return '<span class="badge badge-info">' . $this->trans('Mobile only', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</span>';
        }

        return '<span class="badge badge-danger">' . $this->trans('Hidden', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</span>';
    }

    // ------------------------------------------------------------------
    // Edit form
    // ------------------------------------------------------------------

    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->trans('Slide', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                'icon' => 'icon-picture',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Title (internal)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'title',
                    'required' => true,
                    'desc' => $this->trans('Internal label used only to tell slides apart in this admin list. NOT shown on the front-end.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'file',
                    'label' => $this->trans('Desktop image', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'image_desktop_file',
                    'desc' => $this->trans('Optional. Suggested ratio 16:9 (e.g. 1920x1080). Allowed: JPG, PNG, WEBP. Max 4 MB.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Remove current desktop image', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'remove_image_desktop',
                    'is_bool' => true,
                    'desc' => $this->trans('Turn on and save to delete the current desktop image. Ignored when a new desktop image is uploaded above.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'values' => [
                        ['id' => 'remove_desktop_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                        ['id' => 'remove_desktop_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Alt text (desktop)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'alt_desktop',
                    'desc' => $this->trans('Description for screen readers and SEO. Auto-filled from the filename if left empty.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'file',
                    'label' => $this->trans('Mobile image', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'image_mobile_file',
                    'desc' => $this->trans('Optional. Suggested ratio 4:3 (e.g. 800x600) or 1:1 (e.g. 800x800). Allowed: JPG, PNG, WEBP. Max 4 MB.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Remove current mobile image', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'remove_image_mobile',
                    'is_bool' => true,
                    'desc' => $this->trans('Turn on and save to delete the current mobile image. Ignored when a new mobile image is uploaded above.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'values' => [
                        ['id' => 'remove_mobile_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                        ['id' => 'remove_mobile_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Alt text (mobile)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'alt_mobile',
                    'desc' => $this->trans('Description for screen readers and SEO. Auto-filled from the filename if left empty.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Link URL', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'url',
                    'desc' => $this->trans('Optional. Clicking the slide leads here. Leave empty to render a non-clickable slide. Accepts both absolute ("https://...") and relative ("/category/foo") URLs.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Show on desktop', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'show_on_desktop',
                    'is_bool' => true,
                    'desc' => $this->trans('Whether this slide appears in the desktop slider (viewports >= 768px). Requires a desktop image — the slide is rejected on save if this is on and no desktop image is uploaded. There is no fallback to the mobile image.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'values' => [
                        ['id' => 'show_desktop_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                        ['id' => 'show_desktop_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Show on mobile', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'show_on_mobile',
                    'is_bool' => true,
                    'desc' => $this->trans('Whether this slide appears in the mobile slider (viewports <= 767px). Requires a mobile image — the slide is rejected on save if this is on and no mobile image is uploaded. There is no fallback to the desktop image.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'values' => [
                        ['id' => 'show_mobile_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                        ['id' => 'show_mobile_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Displayed', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'name' => 'active',
                    'is_bool' => true,
                    'values' => [
                        ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                        ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                    ],
                ],
            ],
            'submit' => ['title' => $this->trans('Save', [], 'Admin.Actions')],
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
                    $this->errors[] = $this->trans('The slide you are trying to edit does not exist.', [], 'Modules.Aplinesimplesliderbanner.Admin');

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
            $this->errors[] = $this->trans('Title is required.', [], 'Modules.Aplinesimplesliderbanner.Admin');
        } elseif (mb_strlen($title) > self::MAX_STRING) {
            $this->errors[] = $this->trans('Title exceeds the maximum length of %d characters.', [self::MAX_STRING], 'Modules.Aplinesimplesliderbanner.Admin');
        }

        // ---------- Validation 5: max length for alt fields ----------
        foreach (['Alt (desktop)' => $altDesktop, 'Alt (mobile)' => $altMobile] as $label => $value) {
            if (mb_strlen($value) > self::MAX_STRING) {
                $this->errors[] = $this->trans('The field "%s" exceeds the maximum length of %d characters.', [$label, self::MAX_STRING], 'Modules.Aplinesimplesliderbanner.Admin');
            }
        }

        // ---------- Validation 4 + 5: URL format + max length ----------
        if ($url !== '') {
            if (mb_strlen($url) > self::MAX_URL) {
                $this->errors[] = $this->trans('URL exceeds the maximum length of %d characters.', [self::MAX_URL], 'Modules.Aplinesimplesliderbanner.Admin');
            } elseif (!Validate::isUrl($url)) {
                $this->errors[] = $this->trans('The URL is not valid.', [], 'Modules.Aplinesimplesliderbanner.Admin');
            }
        }

        // ---------- Validation 6: at-least-one viewport on ----------
        if (!$showOnDesktop && !$showOnMobile) {
            $this->errors[] = $this->trans('The slide must be visible on at least one viewport (desktop or mobile).', [], 'Modules.Aplinesimplesliderbanner.Admin');
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
            $this->errors[] = $this->trans('The slide must have at least one image (desktop or mobile).', [], 'Modules.Aplinesimplesliderbanner.Admin');
        }

        // ---------- Validation v1.1.0: visibility flag requires matching image ----------
        // CP09 (front 3) renders desktop and mobile as two independent sliders
        // with no cross-viewport fallback. A slide with show_on_desktop=1 but
        // no desktop image would silently disappear from the desktop slider.
        // Reject the submission instead so the admin explicitly chooses:
        // upload the image, or disable the visibility flag.
        if ($showOnDesktop && empty($effectiveDesktop)) {
            $this->errors[] = $this->trans('Desktop visibility is enabled but no desktop image is set. Upload a desktop image or disable "Show on desktop".', [], 'Modules.Aplinesimplesliderbanner.Admin');
        }
        if ($showOnMobile && empty($effectiveMobile)) {
            $this->errors[] = $this->trans('Mobile visibility is enabled but no mobile image is set. Upload a mobile image or disable "Show on mobile".', [], 'Modules.Aplinesimplesliderbanner.Admin');
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
     * workspace CLAUDE.md §3.3 5-layer hardening (extension whitelist +
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
        $label = $fieldName === 'image_desktop_file' ? 'desktop' : 'mobile';

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->trans('The %s image upload failed. Please try again.', [$label], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            $this->errors[] = $this->trans('Invalid %s image format. Allowed formats: JPG, PNG, WEBP.', [$label], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        if ((int) $file['size'] > self::MAX_IMG_BYTES) {
            $this->errors[] = $this->trans('The %s image is too large. Maximum size is 4 MB.', [$label], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        // Inspect real content, not just the extension: blocks an executable
        // payload renamed with an image extension (workspace CLAUDE.md §3.3).
        $info = @getimagesize($file['tmp_name']);
        $realMime = is_array($info) && isset($info['mime']) ? $info['mime'] : '';
        $isRealImage = class_exists('ImageManager')
            ? ImageManager::isRealImage($file['tmp_name'], $file['type'], self::ALLOWED_MIME)
            : in_array($realMime, self::ALLOWED_MIME, true);

        if (!$info || !in_array($realMime, self::ALLOWED_MIME, true) || !$isRealImage) {
            $this->errors[] = $this->trans('The uploaded %s file is not a valid image.', [$label], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        $fileName = 'assb_' . uniqid('', true) . '.' . $ext;
        $dest = $this->module->getUploadDir() . $fileName;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            $this->errors[] = $this->trans('Could not save the uploaded %s image. Check folder permissions.', [$label], 'Modules.Aplinesimplesliderbanner.Admin');

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
