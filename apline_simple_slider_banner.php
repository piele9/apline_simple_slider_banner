<?php
/**
 * APLINE Simple Slider Banner module for PrestaShop 9.
 *
 * Lightweight image slider/carousel with separate desktop and mobile
 * banners, optional URL per slide, configurable navigation (dots / arrows /
 * both / none) and transitions (slide / fade). Vanilla JS, no external
 * dependencies. WebP-friendly upload.
 *
 * @author    Arkadiusz Pielechowski
 * @copyright Arkadiusz Pielechowski
 * @license   MIT - see LICENSE.md
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/AplineSimpleSliderBannerSlide.php';

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class apline_simple_slider_banner extends Module implements WidgetInterface
{
    const SPEED_KEY = 'ASSB_SPEED';
    const PAUSE_ON_HOVER_KEY = 'ASSB_PAUSE_ON_HOVER';
    const LOOP_KEY = 'ASSB_LOOP';
    const AUTOPLAY_KEY = 'ASSB_AUTOPLAY';
    const NAVIGATION_KEY = 'ASSB_NAVIGATION';
    const TRANSITION_KEY = 'ASSB_TRANSITION';
    const CUSTOM_CLASS_KEY = 'ASSB_CUSTOM_CLASS';

    // Task 1 (v1.2.0) — fixed slider dimensions, per viewport.
    const SIZING_MODE_KEY = 'ASSB_SIZING_MODE';
    const FILL_MODE_KEY = 'ASSB_FILL_MODE';
    const FIXED_W_DESKTOP_KEY = 'ASSB_FIXED_W_DESKTOP';
    const FIXED_H_DESKTOP_KEY = 'ASSB_FIXED_H_DESKTOP';
    const FIXED_W_MOBILE_KEY = 'ASSB_FIXED_W_MOBILE';
    const FIXED_H_MOBILE_KEY = 'ASSB_FIXED_H_MOBILE';

    // Task 2 (v1.2.0) — Bootstrap container wrapper, per viewport.
    // Replaces the single ASSB_CONTAINER key from v1.1.0 (pre-release
    // restructure — no shops carry saved settings yet, legacy layout).
    const BOOTSTRAP_KEY = 'ASSB_BOOTSTRAP';
    const CONTAINER_DESKTOP_KEY = 'ASSB_CONTAINER_DESKTOP';
    const CONTAINER_MOBILE_KEY = 'ASSB_CONTAINER_MOBILE';

    const ADMIN_CONTROLLER = 'AdminAplineSimpleSliderBannerSlide';

    /** @var string */
    private $templateFile = 'module:apline_simple_slider_banner/views/templates/hook/slider.tpl';

    /**
     * Whitelist of valid navigation modes (used by global config + JS).
     *
     * @var string[]
     */
    const NAVIGATION_MODES = ['dots', 'arrows', 'both', 'none'];

    /**
     * Whitelist of valid transition modes (used by global config + JS).
     *
     * @var string[]
     */
    const TRANSITION_MODES = ['slide', 'fade'];

    /**
     * Whitelist of valid container layouts (used by global config + template).
     * - none: no outer wrapper (edge-to-edge, default)
     * - container: wraps the slider in <div class="container"> (page width)
     * - container-fluid: wraps in <div class="container-fluid"> (full width)
     *
     * @var string[]
     */
    const CONTAINER_MODES = ['none', 'container', 'container-fluid'];

    /**
     * Maximum length of the user-defined custom CSS class (added to the
     * slider root element).
     */
    const CUSTOM_CLASS_MAX_LEN = 64;

    /** Whitelist of slide sizing modes (global config + template + CSS). */
    const SIZING_MODES = ['natural', 'fixed'];

    /** Whitelist of object-fit modes used when sizing mode is "fixed". */
    const FILL_MODES = ['cover', 'fill', 'contain'];

    /**
     * Fixed-dimension bounds (px) and aspect-ratio (W/H) guards, per
     * viewport. Out-of-range or wrong-orientation values are REJECTED
     * with a form error  — never silently
     * clamped on save. getRenderConfig() applies a defensive clamp at
     * render time only, so a tampered Configuration row can't produce
     * broken markup.
     *
     * The ratio guard is what stops an admin turning a landscape banner
     * into a portrait one (or an absurd ultra-wide strip).
     */
    const FIXED_W_MIN_DESKTOP = 320;
    const FIXED_W_MAX_DESKTOP = 3840;
    const FIXED_H_MIN_DESKTOP = 120;
    const FIXED_H_MAX_DESKTOP = 2160;
    const FIXED_RATIO_MIN_DESKTOP = 1.0;
    const FIXED_RATIO_MAX_DESKTOP = 6.0;

    const FIXED_W_MIN_MOBILE = 320;
    const FIXED_W_MAX_MOBILE = 2160;
    const FIXED_H_MIN_MOBILE = 160;
    const FIXED_H_MAX_MOBILE = 2400;
    const FIXED_RATIO_MIN_MOBILE = 0.5;
    const FIXED_RATIO_MAX_MOBILE = 3.0;

    /** Default fixed dimensions (install defaults + form fallbacks). */
    const FIXED_W_DESKTOP_DEFAULT = 1920;
    const FIXED_H_DESKTOP_DEFAULT = 600;
    const FIXED_W_MOBILE_DEFAULT = 768;
    const FIXED_H_MOBILE_DEFAULT = 480;

    public function __construct()
    {
        $this->name = 'apline_simple_slider_banner';
        $this->tab = 'front_office_features';
        $this->version = '1.3.1';
        $this->author = 'Arkadiusz Pielechowski';
        $this->need_instance = false;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('APLINE — slider banerów dla PrestaShop 9', [], 'Modules.Aplinesimplesliderbanner.Admin');
        $this->description = $this->trans('Lekki slider z osobnymi banerami na komputer i telefon, opcjonalnymi linkami oraz ustawieniami nawigacji i przejść.', [], 'Modules.Aplinesimplesliderbanner.Admin');
        $this->confirmUninstall = $this->trans('Czy chcesz odinstalować moduł? Wszystkie slajdy zostaną usunięte.', [], 'Modules.Aplinesimplesliderbanner.Admin');

        $this->ps_versions_compliancy = ['min' => '9.0', 'max' => _PS_VERSION_];
    }

    /**
     * @return string absolute path to the upload directory
     */
    public function getUploadDir()
    {
        return _PS_MODULE_DIR_ . $this->name . '/views/img/';
    }

    /**
     * @return bool whether the upload directory is writable
     */
    public function isUploadDirWritable()
    {
        $dir = $this->getUploadDir();

        return is_dir($dir) && is_writable($dir);
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->installDb()
            || !$this->installConfiguration()
            || !$this->installHooks()
            || !$this->installTab()
        ) {
            // Roll back to a clean state so the shop is never left half-installed.
            $this->uninstall();
            $this->_errors[] = $this->trans('Instalacja nie powiodła się i została wycofana. Sprawdź uprawnienia katalogów i spróbuj ponownie.', [], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        // Demo slides are a nice-to-have, not a hard requirement — if seed
        // generation fails (e.g. GD missing or upload dir read-only) we log
        // and continue with an empty list, the admin can add their own.
        $this->installDemoSlides();

        return true;
    }

    public function uninstall()
    {
        // Each step is idempotent; uninstall must not fail because something is already gone.
        $this->uninstallTab();
        $this->deleteUploadedFiles();

        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'assb_slide`');

        Configuration::deleteByName(self::SPEED_KEY);
        Configuration::deleteByName(self::PAUSE_ON_HOVER_KEY);
        Configuration::deleteByName(self::LOOP_KEY);
        Configuration::deleteByName(self::AUTOPLAY_KEY);
        Configuration::deleteByName(self::NAVIGATION_KEY);
        Configuration::deleteByName(self::TRANSITION_KEY);
        Configuration::deleteByName(self::CUSTOM_CLASS_KEY);
        Configuration::deleteByName(self::SIZING_MODE_KEY);
        Configuration::deleteByName(self::FILL_MODE_KEY);
        Configuration::deleteByName(self::FIXED_W_DESKTOP_KEY);
        Configuration::deleteByName(self::FIXED_H_DESKTOP_KEY);
        Configuration::deleteByName(self::FIXED_W_MOBILE_KEY);
        Configuration::deleteByName(self::FIXED_H_MOBILE_KEY);
        Configuration::deleteByName(self::BOOTSTRAP_KEY);
        Configuration::deleteByName(self::CONTAINER_DESKTOP_KEY);
        Configuration::deleteByName(self::CONTAINER_MOBILE_KEY);
        // Legacy single-container key (v1.1.0, replaced in v1.2.0) — clean
        // up if a dev/test install still carries it.
        Configuration::deleteByName('ASSB_CONTAINER');

        return parent::uninstall();
    }

    /**
     * Creates the `assb_slide` table. No demo seed in this checkpoint — demo
     * slides + their sample images are added in CP06.
     *
     * @return bool
     */
    private function installDb()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'assb_slide` (
            `id_assb_slide` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(255) NOT NULL,
            `image_desktop` VARCHAR(255) DEFAULT NULL,
            `image_mobile` VARCHAR(255) DEFAULT NULL,
            `alt_desktop` VARCHAR(255) DEFAULT NULL,
            `alt_mobile` VARCHAR(255) DEFAULT NULL,
            `url` VARCHAR(2048) DEFAULT NULL,
            `show_on_desktop` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `show_on_mobile` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `position` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_assb_slide`),
            KEY `idx_active_position` (`active`, `position`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return (bool) Db::getInstance()->execute($sql);
    }

    /**
     * @return bool
     */
    private function installConfiguration()
    {
        return Configuration::updateValue(self::SPEED_KEY, 5000)
            && Configuration::updateValue(self::PAUSE_ON_HOVER_KEY, 1)
            && Configuration::updateValue(self::LOOP_KEY, 1)
            && Configuration::updateValue(self::AUTOPLAY_KEY, 1)
            && Configuration::updateValue(self::NAVIGATION_KEY, 'dots')
            && Configuration::updateValue(self::TRANSITION_KEY, 'slide')
            && Configuration::updateValue(self::CUSTOM_CLASS_KEY, '')
            // Task 1 — fixed dimensions (default: natural, i.e. v1.1.0 behaviour).
            && Configuration::updateValue(self::SIZING_MODE_KEY, 'natural')
            && Configuration::updateValue(self::FILL_MODE_KEY, 'cover')
            && Configuration::updateValue(self::FIXED_W_DESKTOP_KEY, self::FIXED_W_DESKTOP_DEFAULT)
            && Configuration::updateValue(self::FIXED_H_DESKTOP_KEY, self::FIXED_H_DESKTOP_DEFAULT)
            && Configuration::updateValue(self::FIXED_W_MOBILE_KEY, self::FIXED_W_MOBILE_DEFAULT)
            && Configuration::updateValue(self::FIXED_H_MOBILE_KEY, self::FIXED_H_MOBILE_DEFAULT)
            // Task 2 — Bootstrap container (default: off, i.e. edge-to-edge).
            && Configuration::updateValue(self::BOOTSTRAP_KEY, 0)
            && Configuration::updateValue(self::CONTAINER_DESKTOP_KEY, 'none')
            && Configuration::updateValue(self::CONTAINER_MOBILE_KEY, 'none');
    }

    /**
     * @return bool
     */
    private function installHooks()
    {
        $ok = $this->registerHook('actionFrontControllerSetMedia');
        $ok = $ok && $this->registerHook('displayHome');

        return $ok;
    }

    /**
     * @return bool
     */
    private function installTab()
    {
        if (Tab::getIdFromClassName(self::ADMIN_CONTROLLER)) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = self::ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->active = 1;
        // Hidden tab (no visible parent): managed from the module configuration page.
        $tab->id_parent = -1;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Slider banerów APLINE';
        }

        return (bool) $tab->add();
    }

    /**
     * @return bool
     */
    private function uninstallTab()
    {
        $id = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);
        if (!$id) {
            return true;
        }

        try {
            $tab = new Tab($id);

            return (bool) $tab->delete();
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Remove uploaded images. Only ever touches files inside the module folder.
     */
    private function deleteUploadedFiles()
    {
        $dir = $this->getUploadDir();
        if (!is_dir($dir)) {
            return;
        }

        foreach ((array) glob($dir . 'assb_*') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Module configuration page. Renders the global slider settings form
     * (display location, speed, autoplay, pause-on-hover, loop, navigation,
     * transition), the "Manage slides" link to the hidden admin tab, the
     * upload-dir warning if applicable, and the author credit.
     *
     * @return string
     */
    public function getContent()
    {
        $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin.css');
        $output = '';

        if (Tools::isSubmit('submitAssbConfig')) {
            $output .= $this->saveConfigForm();
        }

        if (!$this->isUploadDirWritable()) {
            $output .= $this->displayWarning($this->trans('Brak prawa zapisu w katalogu obrazów: %s. Przesyłanie obrazów wymaga poprawnych uprawnień (np. chmod 0775).', [$this->getUploadDir()], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        $manageUrl = $this->context->link->getAdminLink(self::ADMIN_CONTROLLER);
        $this->context->smarty->assign([
            'assb_manage_url' => $manageUrl,
            'assb_has_demo_images' => AplineSimpleSliderBannerSlide::hasDemoImages(),
        ]);
        $output .= $this->display(__FILE__, 'views/templates/admin/configure.tpl');

        return $output . $this->renderConfigForm() . $this->renderLikeBox() . $this->renderAplineFooter();
    }

    /**
     * Validate and persist the global slider settings posted from the
     * configuration form. Whitelist-validates ASSB_NAVIGATION and
     * ASSB_TRANSITION, clamps ASSB_SPEED to [500, 30000] ms, casts the bool
     * switches and returns a display banner (confirmation or error).
     *
     * @return string
     */
    private function saveConfigForm()
    {
        $navigation = (string) Tools::getValue(self::NAVIGATION_KEY);
        if (!in_array($navigation, self::NAVIGATION_MODES, true)) {
            return $this->displayError($this->trans('Wybrano nieprawidłowy tryb nawigacji.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        $transition = (string) Tools::getValue(self::TRANSITION_KEY);
        if (!in_array($transition, self::TRANSITION_MODES, true)) {
            return $this->displayError($this->trans('Wybrano nieprawidłowy rodzaj przejścia.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        $speed = (int) Tools::getValue(self::SPEED_KEY);
        if ($speed < 500 || $speed > 30000) {
            return $this->displayError($this->trans('Czas musi wynosić od 500 do 30000 milisekund.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        // --- Task 2: Bootstrap container wrapper (per viewport) ---
        $bootstrap = (int) Tools::getValue(self::BOOTSTRAP_KEY) ? 1 : 0;

        $containerDesktop = (string) Tools::getValue(self::CONTAINER_DESKTOP_KEY);
        if (!in_array($containerDesktop, self::CONTAINER_MODES, true)) {
            return $this->displayError($this->trans('Wybrano nieprawidłowy układ kontenera na komputerze.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        $containerMobile = (string) Tools::getValue(self::CONTAINER_MOBILE_KEY);
        if (!in_array($containerMobile, self::CONTAINER_MODES, true)) {
            return $this->displayError($this->trans('Wybrano nieprawidłowy układ kontenera na telefonie.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        // --- Task 1: fixed slider dimensions (per viewport) ---
        $sizingMode = (string) Tools::getValue(self::SIZING_MODE_KEY);
        if (!in_array($sizingMode, self::SIZING_MODES, true)) {
            return $this->displayError($this->trans('Wybrano nieprawidłowy tryb rozmiaru slajdów.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        $fillMode = (string) Tools::getValue(self::FILL_MODE_KEY);
        if (!in_array($fillMode, self::FILL_MODES, true)) {
            return $this->displayError($this->trans('Wybrano nieprawidłowy sposób dopasowania obrazów.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        // Dimensions are always validated (the fields are always posted), so
        // the stored values stay sane and a later switch to "fixed" always
        // has usable numbers. Reject out-of-range / wrong-orientation values
        // instead of silently clamping .
        $wDesktop = (int) Tools::getValue(self::FIXED_W_DESKTOP_KEY);
        $hDesktop = (int) Tools::getValue(self::FIXED_H_DESKTOP_KEY);
        $wMobile = (int) Tools::getValue(self::FIXED_W_MOBILE_KEY);
        $hMobile = (int) Tools::getValue(self::FIXED_H_MOBILE_KEY);

        $dimError = $this->validateFixedDimensions(
            $wDesktop, $hDesktop,
            self::FIXED_W_MIN_DESKTOP, self::FIXED_W_MAX_DESKTOP,
            self::FIXED_H_MIN_DESKTOP, self::FIXED_H_MAX_DESKTOP,
            self::FIXED_RATIO_MIN_DESKTOP, self::FIXED_RATIO_MAX_DESKTOP,
            $this->trans('Komputer', [], 'Modules.Aplinesimplesliderbanner.Admin')
        );
        if ($dimError !== '') {
            return $this->displayError($dimError);
        }

        $dimError = $this->validateFixedDimensions(
            $wMobile, $hMobile,
            self::FIXED_W_MIN_MOBILE, self::FIXED_W_MAX_MOBILE,
            self::FIXED_H_MIN_MOBILE, self::FIXED_H_MAX_MOBILE,
            self::FIXED_RATIO_MIN_MOBILE, self::FIXED_RATIO_MAX_MOBILE,
            $this->trans('Telefon', [], 'Modules.Aplinesimplesliderbanner.Admin')
        );
        if ($dimError !== '') {
            return $this->displayError($dimError);
        }

        // Custom CSS class: validated regex (letters, digits, space, dash,
        // underscore — same charset as a CSS identifier list). Reject on
        // length / charset mismatch instead of silently stripping —
        // Validation rejects invalid values without truncation.
        $customClass = trim((string) Tools::getValue(self::CUSTOM_CLASS_KEY));
        if (mb_strlen($customClass) > self::CUSTOM_CLASS_MAX_LEN) {
            return $this->displayError($this->trans('Własna klasa CSS może mieć najwyżej %d znaków.', [self::CUSTOM_CLASS_MAX_LEN], 'Modules.Aplinesimplesliderbanner.Admin'));
        }
        if ($customClass !== '' && !preg_match('/^[a-zA-Z0-9 _-]+$/', $customClass)) {
            return $this->displayError($this->trans('Własna klasa CSS może zawierać tylko litery, cyfry, spacje, myślniki i podkreślenia.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        Configuration::updateValue(self::NAVIGATION_KEY, $navigation);
        Configuration::updateValue(self::TRANSITION_KEY, $transition);
        Configuration::updateValue(self::SPEED_KEY, $speed);
        Configuration::updateValue(self::AUTOPLAY_KEY, (int) Tools::getValue(self::AUTOPLAY_KEY) ? 1 : 0);
        Configuration::updateValue(self::PAUSE_ON_HOVER_KEY, (int) Tools::getValue(self::PAUSE_ON_HOVER_KEY) ? 1 : 0);
        Configuration::updateValue(self::LOOP_KEY, (int) Tools::getValue(self::LOOP_KEY) ? 1 : 0);
        Configuration::updateValue(self::CUSTOM_CLASS_KEY, $customClass);
        // Task 1 — fixed dimensions.
        Configuration::updateValue(self::SIZING_MODE_KEY, $sizingMode);
        Configuration::updateValue(self::FILL_MODE_KEY, $fillMode);
        Configuration::updateValue(self::FIXED_W_DESKTOP_KEY, $wDesktop);
        Configuration::updateValue(self::FIXED_H_DESKTOP_KEY, $hDesktop);
        Configuration::updateValue(self::FIXED_W_MOBILE_KEY, $wMobile);
        Configuration::updateValue(self::FIXED_H_MOBILE_KEY, $hMobile);
        // Task 2 — Bootstrap container (per viewport).
        Configuration::updateValue(self::BOOTSTRAP_KEY, $bootstrap);
        Configuration::updateValue(self::CONTAINER_DESKTOP_KEY, $containerDesktop);
        Configuration::updateValue(self::CONTAINER_MOBILE_KEY, $containerMobile);

        return $this->displayConfirmation($this->trans('Zapisano ustawienia slidera.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
    }

    /**
     * Validate one viewport's fixed dimensions: width range, height range
     * and aspect-ratio (W/H) guard. Returns a ready-to-display error
     * string, or '' when the dimensions are valid. Rejects rather than
     * clamps . The ratio guard prevents an admin
     * accidentally turning a landscape banner into a portrait one.
     *
     * @param int $w
     * @param int $h
     * @param int $wMin
     * @param int $wMax
     * @param int $hMin
     * @param int $hMax
     * @param float $ratioMin minimum allowed W/H
     * @param float $ratioMax maximum allowed W/H
     * @param string $label already-translated viewport label (Desktop/Mobile)
     *
     * @return string error message, or '' if valid
     */
    private function validateFixedDimensions($w, $h, $wMin, $wMax, $hMin, $hMax, $ratioMin, $ratioMax, $label)
    {
        if ($w < $wMin || $w > $wMax) {
            return $this->trans('%1$s: szerokość musi wynosić od %2$d do %3$d pikseli.', [$label, $wMin, $wMax], 'Modules.Aplinesimplesliderbanner.Admin');
        }
        if ($h < $hMin || $h > $hMax) {
            return $this->trans('%1$s: wysokość musi wynosić od %2$d do %3$d pikseli.', [$label, $hMin, $hMax], 'Modules.Aplinesimplesliderbanner.Admin');
        }

        // $h >= $hMin > 0 is guaranteed by the height check above.
        $ratio = $w / $h;
        if ($ratio < $ratioMin || $ratio > $ratioMax) {
            return $this->trans(
                '%1$s: stosunek szerokości do wysokości musi wynosić od %2$s do %3$s. Chroni to baner przed nieprawidłowymi proporcjami.',
                [$label, (string) $ratioMin, (string) $ratioMax],
                'Modules.Aplinesimplesliderbanner.Admin'
            );
        }

        return '';
    }

    /**
     * Defensive render-time clamp for a stored fixed dimension. Unlike the
     * save-time validation (which rejects), this never blocks rendering —
     * it just keeps a tampered/empty Configuration row from producing
     * broken markup. A non-positive value falls back to $default.
     *
     * @param int $value
     * @param int $min
     * @param int $max
     * @param int $default
     *
     * @return int
     */
    private function clampDimension($value, $min, $max, $default)
    {
        if ($value <= 0) {
            return $default;
        }

        return max($min, min($max, $value));
    }

    /**
     * Build the global settings HelperForm, grouped into three fieldsets:
     * slider behaviour, slide size (fixed dimensions), and page layout
     * (Bootstrap container + custom class). A small inline script wires the
     * size presets and shows/hides the fixed-size fields.
     *
     * @return string
     */
    private function renderConfigForm()
    {
        $navigationOptions = [
            ['id' => 'dots', 'name' => $this->trans('Tylko kropki', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'arrows', 'name' => $this->trans('Tylko strzałki', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'both', 'name' => $this->trans('Kropki i strzałki', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'none', 'name' => $this->trans('Bez nawigacji (tylko automatyczne przewijanie)', [], 'Modules.Aplinesimplesliderbanner.Admin')],
        ];

        $transitionOptions = [
            ['id' => 'slide', 'name' => $this->trans('Przesunięcie w poziomie', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'fade', 'name' => $this->trans('Przenikanie', [], 'Modules.Aplinesimplesliderbanner.Admin')],
        ];

        // Container options reused for both the desktop and the mobile select.
        $containerOptions = [
            ['id' => 'none', 'name' => $this->trans('Od krawędzi do krawędzi (bez kontenera)', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'container', 'name' => $this->trans('Szerokość strony (.container)', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'container-fluid', 'name' => $this->trans('Pełna szerokość przeglądarki z odstępami (.container-fluid)', [], 'Modules.Aplinesimplesliderbanner.Admin')],
        ];

        $sizingOptions = [
            ['id' => 'natural', 'name' => $this->trans('Naturalna wysokość — zgodna z obrazem (domyślnie)', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'fixed', 'name' => $this->trans('Stały rozmiar — jednakowe wymiary slajdów', [], 'Modules.Aplinesimplesliderbanner.Admin')],
        ];

        $fillOptions = [
            ['id' => 'cover', 'name' => $this->trans('Wypełnienie z przycięciem — bez zniekształceń', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'fill', 'name' => $this->trans('Rozciągnięcie — może zniekształcać obraz', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'contain', 'name' => $this->trans('Cały obraz — możliwe puste pasy', [], 'Modules.Aplinesimplesliderbanner.Admin')],
        ];

        // Size presets are pure UI helpers — they prefill the width/height
        // fields client-side (see renderConfigFormScript) and are NOT saved
        // as Configuration. The stored truth is always the px width/height.
        $customLabel = $this->trans('Własny — podaj szerokość i wysokość poniżej', [], 'Modules.Aplinesimplesliderbanner.Admin');
        $presetDesktopOptions = [
            ['id' => '1920x600', 'name' => '1920 × 600 (16:5)'],
            ['id' => '1600x500', 'name' => '1600 × 500 (16:5)'],
            ['id' => '1200x400', 'name' => '1200 × 400 (3:1)'],
            ['id' => '1000x400', 'name' => '1000 × 400 (5:2)'],
            ['id' => 'custom', 'name' => $customLabel],
        ];
        $presetMobileOptions = [
            ['id' => '768x480', 'name' => '768 × 480 (8:5)'],
            ['id' => '640x480', 'name' => '640 × 480 (4:3)'],
            ['id' => '600x600', 'name' => '600 × 600 (1:1)'],
            ['id' => '480x600', 'name' => '480 × 600 (4:5)'],
            ['id' => 'custom', 'name' => $customLabel],
        ];

        $boolSwitch = function ($idPrefix) {
            return [
                ['id' => $idPrefix . '_on', 'value' => 1, 'label' => $this->trans('Tak', [], 'Admin.Global')],
                ['id' => $idPrefix . '_off', 'value' => 0, 'label' => $this->trans('Nie', [], 'Admin.Global')],
            ];
        };

        // Fieldset 1 — slider behaviour (timing + navigation).
        $behaviourForm = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Zachowanie slidera', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Czas zmiany (ms)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::SPEED_KEY,
                        'class' => 'fixed-width-sm',
                        'suffix' => 'ms',
                        'desc' => $this->trans('Czas między slajdami w milisekundach. 5000 = 5 sekund. Zakres: 500–30000.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Automatyczne przewijanie', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::AUTOPLAY_KEY,
                        'is_bool' => true,
                        'values' => $boolSwitch('autoplay'),
                        'desc' => $this->trans('Zmieniaj slajdy automatycznie w ustawionych odstępach.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Pauza po najechaniu', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::PAUSE_ON_HOVER_KEY,
                        'is_bool' => true,
                        'values' => $boolSwitch('pause_on_hover'),
                        'desc' => $this->trans('Zatrzymaj automatyczne przewijanie, gdy kursor znajduje się nad sliderem.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Zapętlenie', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::LOOP_KEY,
                        'is_bool' => true,
                        'values' => $boolSwitch('loop'),
                        'desc' => $this->trans('Po ostatnim slajdzie wróć do pierwszego. Po wyłączeniu slider zatrzyma się na ostatnim slajdzie; nadal można przełączać go ręcznie.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Nawigacja', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::NAVIGATION_KEY,
                        'options' => ['query' => $navigationOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Widoczne przyciski ręcznego przełączania slajdów.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Przejście', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::TRANSITION_KEY,
                        'options' => ['query' => $transitionOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Przesuwaj slajdy w poziomie lub przenikaj między nimi.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                ],
            ],
        ];

        // Fieldset 2 — slide size (fixed dimensions, per viewport).
        $sizeForm = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Rozmiar slajdów', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'icon' => 'icon-picture',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->trans('Tryb rozmiaru', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::SIZING_MODE_KEY,
                        'options' => ['query' => $sizingOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Tryb naturalny zachowuje proporcje każdego obrazu i dopasowuje do niego wysokość slidera. Stały rozmiar zapewnia jednakowe wymiary wszystkich slajdów i zapobiega skokom wysokości. Na wąskich ekranach slider zmniejsza się proporcjonalnie.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Dopasowanie obrazu (stały rozmiar)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::FILL_MODE_KEY,
                        'options' => ['query' => $fillOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Sposób wypełnienia stałego obszaru, gdy proporcje obrazu są inne. Wypełnienie z przycięciem nie zniekształca obrazu i jest zalecane dla banerów.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Gotowy rozmiar na komputer', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => 'assb_preset_desktop',
                        'options' => ['query' => $presetDesktopOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Wybierz sugerowane wymiary lub opcję Własny, aby wpisać szerokość i wysokość.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Szerokość na komputerze', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::FIXED_W_DESKTOP_KEY,
                        'class' => 'fixed-width-sm',
                        'suffix' => 'px',
                        'desc' => $this->trans('Dozwolony zakres: %1$d–%2$d px.', [self::FIXED_W_MIN_DESKTOP, self::FIXED_W_MAX_DESKTOP], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Wysokość na komputerze', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::FIXED_H_DESKTOP_KEY,
                        'class' => 'fixed-width-sm',
                        'suffix' => 'px',
                        'desc' => $this->trans('Dozwolony zakres: %1$d–%2$d px. Stosunek szerokości do wysokości: %3$s–%4$s (układ poziomy).', [self::FIXED_H_MIN_DESKTOP, self::FIXED_H_MAX_DESKTOP, (string) self::FIXED_RATIO_MIN_DESKTOP, (string) self::FIXED_RATIO_MAX_DESKTOP], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Gotowy rozmiar na telefon', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => 'assb_preset_mobile',
                        'options' => ['query' => $presetMobileOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Wybierz sugerowane wymiary lub opcję Własny, aby wpisać szerokość i wysokość telefonu.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Szerokość na telefonie', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::FIXED_W_MOBILE_KEY,
                        'class' => 'fixed-width-sm',
                        'suffix' => 'px',
                        'desc' => $this->trans('Dozwolony zakres: %1$d–%2$d px.', [self::FIXED_W_MIN_MOBILE, self::FIXED_W_MAX_MOBILE], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Wysokość na telefonie', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::FIXED_H_MOBILE_KEY,
                        'class' => 'fixed-width-sm',
                        'suffix' => 'px',
                        'desc' => $this->trans('Dozwolony zakres: %1$d–%2$d px. Stosunek szerokości do wysokości: %3$s–%4$s.', [self::FIXED_H_MIN_MOBILE, self::FIXED_H_MAX_MOBILE, (string) self::FIXED_RATIO_MIN_MOBILE, (string) self::FIXED_RATIO_MAX_MOBILE], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                ],
            ],
        ];

        // Fieldset 3 — page layout (Bootstrap container + custom class).
        $layoutForm = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Układ strony', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'icon' => 'icon-th-large',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Motyw korzysta z Bootstrap', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::BOOTSTRAP_KEY,
                        'is_bool' => true,
                        'values' => $boolSwitch('bootstrap'),
                        'desc' => $this->trans('Włącz tylko, jeśli motyw ładuje Bootstrap (np. Classic). Pozwala to ustawić .container lub .container-fluid osobno dla komputera i telefonu. Po wyłączeniu slider zajmuje całą szerokość i ignoruje ustawienia kontenera.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Kontener na komputerze', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::CONTAINER_DESKTOP_KEY,
                        'options' => ['query' => $containerOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Układ slidera na komputerze (wymaga włączonego Bootstrap).', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Kontener na telefonie', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::CONTAINER_MOBILE_KEY,
                        'options' => ['query' => $containerOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Układ slidera na telefonie (wymaga włączonego Bootstrap).', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Własna klasa CSS', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::CUSTOM_CLASS_KEY,
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->trans('Opcjonalna klasa głównego elementu slidera do własnych stylów CSS. Tylko litery, cyfry, spacje, myślniki i podkreślenia (maks. 64 znaki).', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                ],
                'submit' => ['class' => 'btn btn-primary btn-lg apline-btn-duzy pull-right', 'title' => $this->trans('Zapisz', [], 'Admin.Actions')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitAssbConfig';
        $helper->fields_value = [
            self::SPEED_KEY => (int) (Configuration::get(self::SPEED_KEY) ?: 5000),
            self::AUTOPLAY_KEY => (int) Configuration::get(self::AUTOPLAY_KEY),
            self::PAUSE_ON_HOVER_KEY => (int) Configuration::get(self::PAUSE_ON_HOVER_KEY),
            self::LOOP_KEY => (int) Configuration::get(self::LOOP_KEY),
            self::NAVIGATION_KEY => Configuration::get(self::NAVIGATION_KEY) ?: 'dots',
            self::TRANSITION_KEY => Configuration::get(self::TRANSITION_KEY) ?: 'slide',
            self::SIZING_MODE_KEY => Configuration::get(self::SIZING_MODE_KEY) ?: 'natural',
            self::FILL_MODE_KEY => Configuration::get(self::FILL_MODE_KEY) ?: 'cover',
            // Presets always default to "custom" so they never overwrite the
            // stored width/height on page load — the admin opts in by picking one.
            'assb_preset_desktop' => 'custom',
            self::FIXED_W_DESKTOP_KEY => (int) (Configuration::get(self::FIXED_W_DESKTOP_KEY) ?: self::FIXED_W_DESKTOP_DEFAULT),
            self::FIXED_H_DESKTOP_KEY => (int) (Configuration::get(self::FIXED_H_DESKTOP_KEY) ?: self::FIXED_H_DESKTOP_DEFAULT),
            'assb_preset_mobile' => 'custom',
            self::FIXED_W_MOBILE_KEY => (int) (Configuration::get(self::FIXED_W_MOBILE_KEY) ?: self::FIXED_W_MOBILE_DEFAULT),
            self::FIXED_H_MOBILE_KEY => (int) (Configuration::get(self::FIXED_H_MOBILE_KEY) ?: self::FIXED_H_MOBILE_DEFAULT),
            self::BOOTSTRAP_KEY => (int) Configuration::get(self::BOOTSTRAP_KEY),
            self::CONTAINER_DESKTOP_KEY => Configuration::get(self::CONTAINER_DESKTOP_KEY) ?: 'none',
            self::CONTAINER_MOBILE_KEY => Configuration::get(self::CONTAINER_MOBILE_KEY) ?: 'none',
            self::CUSTOM_CLASS_KEY => Configuration::get(self::CUSTOM_CLASS_KEY) ?: '',
        ];

        return $helper->generateForm([$behaviourForm, $sizeForm, $layoutForm]) . $this->renderConfigFormScript();
    }

    /**
     * Small inline script for the configuration page. Two jobs, both pure
     * progressive enhancement (the server stays the source of truth):
     *   1. When a size preset is picked, fill the matching width/height inputs.
     *   2. Show the fixed-size fields only when sizing mode is "fixed".
     * If the script fails to run, every field stays visible and editable and
     * the save-time validation still applies.
     *
     * @return string
     */
    private function renderConfigFormScript()
    {
        $sizing = self::SIZING_MODE_KEY;
        $fill = self::FILL_MODE_KEY;
        $wd = self::FIXED_W_DESKTOP_KEY;
        $hd = self::FIXED_H_DESKTOP_KEY;
        $wm = self::FIXED_W_MOBILE_KEY;
        $hm = self::FIXED_H_MOBILE_KEY;
        $pd = 'assb_preset_desktop';
        $pm = 'assb_preset_mobile';

        return "
<script>
(function () {
    'use strict';
    function byName(n) { return document.querySelector('[name=\"' + n + '\"]'); }
    function row(el) { return el ? el.closest('.form-group') : null; }

    var presets = {
        '{$pd}': { w: '{$wd}', h: '{$hd}' },
        '{$pm}': { w: '{$wm}', h: '{$hm}' }
    };
    Object.keys(presets).forEach(function (pn) {
        var sel = byName(pn);
        if (!sel) { return; }
        sel.addEventListener('change', function () {
            var v = sel.value;
            if (!v || v === 'custom') { return; }
            var parts = v.split('x');
            if (parts.length !== 2) { return; }
            var wi = byName(presets[pn].w), hi = byName(presets[pn].h);
            if (wi) { wi.value = parseInt(parts[0], 10) || wi.value; }
            if (hi) { hi.value = parseInt(parts[1], 10) || hi.value; }
        });
    });

    var sizing = byName('{$sizing}');
    var fixedRows = ['{$fill}', '{$pd}', '{$wd}', '{$hd}', '{$pm}', '{$wm}', '{$hm}'];
    function sync() {
        if (!sizing) { return; }
        var on = sizing.value === 'fixed';
        fixedRows.forEach(function (n) {
            var r = row(byName(n));
            if (r) { r.style.display = on ? '' : 'none'; }
        });
    }
    if (sizing) {
        sizing.addEventListener('change', sync);
        sync();
    }
})();
</script>";
    }

    /**
     * Seed 3 demo slides at install time so the admin has something
     * working to see on the first front-end visit. Placeholder images
     * are generated on the fly via PHP GD (PrestaShop's system
     * requirements list GD as mandatory, so this is safe) into
     * `views/img/` with the standard `assb_` prefix that
     * `deleteUploadedFiles()` cleans up on uninstall.
     *
     * If GD is missing or the upload directory is not writable, this
     * silently skips — install still succeeds, the slide list is just
     * empty and the admin adds their own slides through the
     * "Manage slides" UI.
     *
     * @return bool true if at least one slide was seeded, false on graceful skip
     */
    private function installDemoSlides()
    {
        try {
            if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
                return false;
            }
            if (!$this->isUploadDirWritable()) {
                return false;
            }

            // Distinct background colours so the admin can visually confirm
            // all 3 demo slides loaded correctly on a fresh install.
            $palette = [
                ['rgb' => [54, 96, 153],  'label' => 'Przykladowy slajd 1'],   // steel blue
                ['rgb' => [102, 51, 102], 'label' => 'Przykladowy slajd 2'],   // muted purple
                ['rgb' => [153, 102, 51], 'label' => 'Przykladowy slajd 3'],   // warm brown
            ];

            $now = date('Y-m-d H:i:s');
            $uploadDir = $this->getUploadDir();
            $baseUrl = __PS_BASE_URI__ . 'modules/' . $this->name . '/views/img/';
            $seeded = 0;

            foreach ($palette as $idx => $slide) {
                $position = $idx + 1;

                $desktopName = 'assb_seed_' . $position . '_desktop_' . uniqid('', true) . '.jpg';
                $mobileName  = 'assb_seed_' . $position . '_mobile_'  . uniqid('', true) . '.jpg';
                $desktopPath = $uploadDir . $desktopName;
                $mobilePath  = $uploadDir . $mobileName;

                // 1200x675 = 16:9 desktop; 600x450 = 4:3 mobile. Smaller than
                // the suggested 1920x1080 / 800x600 from the brief so the zip
                // stays small and the placeholders are clearly "replace me"
                // rather than presentable hero graphics.
                $okDesktop = $this->generatePlaceholderImage($desktopPath, 1200, 675, $slide['rgb'], $slide['label']);
                $okMobile  = $this->generatePlaceholderImage($mobilePath, 600, 450, $slide['rgb'], $slide['label']);

                if (!$okDesktop && !$okMobile) {
                    continue;
                }

                $insertOk = Db::getInstance()->insert('assb_slide', [
                    'title' => pSQL($slide['label']),
                    'image_desktop' => $okDesktop ? pSQL($baseUrl . $desktopName) : '',
                    'image_mobile' => $okMobile ? pSQL($baseUrl . $mobileName) : '',
                    'alt_desktop' => pSQL($slide['label'] . ' (komputer)'),
                    'alt_mobile' => pSQL($slide['label'] . ' (telefon)'),
                    'url' => $position < 3 ? pSQL('https://www.prestashop-project.org') : '',
                    'show_on_desktop' => 1,
                    'show_on_mobile' => $position < 3 ? 1 : 0, // 3rd slide = desktop-only example
                    'active' => 1,
                    'position' => $position,
                    'date_add' => $now,
                    'date_upd' => $now,
                ]);

                if ($insertOk) {
                    $seeded++;
                } else {
                    // Clean up orphaned files from a failed insert.
                    if ($okDesktop) { @unlink($desktopPath); }
                    if ($okMobile) { @unlink($mobilePath); }
                }
            }

            return $seeded > 0;
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('assb: demo seed skipped — ' . $e->getMessage(), 2);

            return false;
        }
    }

    /**
     * Generate a JPG placeholder image with a solid background colour,
     * a thin border and a centred label. Used by installDemoSlides()
     * so the module doesn't have to ship binary sample assets in the
     * git repo.
     *
     * @param string $path absolute target path
     * @param int $width
     * @param int $height
     * @param int[] $rgb three ints 0-255
     * @param string $label text overlay
     *
     * @return bool true on success
     */
    private function generatePlaceholderImage($path, $width, $height, $rgb, $label)
    {
        try {
            $im = @imagecreatetruecolor($width, $height);
            if (!$im) {
                return false;
            }
            $bg = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
            $fg = imagecolorallocate($im, 255, 255, 255);
            $border = imagecolorallocate($im, max(0, $rgb[0] - 40), max(0, $rgb[1] - 40), max(0, $rgb[2] - 40));

            imagefill($im, 0, 0, $bg);
            imagerectangle($im, 0, 0, $width - 1, $height - 1, $border);
            imagerectangle($im, 1, 1, $width - 2, $height - 2, $border);

            // Built-in font 5 is the largest GD font (9x15 px). On 1200x675
            // it's small but readable enough as a "this is a placeholder" hint.
            $fontSize = 5;
            $textW = imagefontwidth($fontSize) * strlen($label);
            $textH = imagefontheight($fontSize);
            $x = (int) (($width - $textW) / 2);
            $y = (int) (($height - $textH) / 2);
            imagestring($im, $fontSize, $x, $y, $label, $fg);

            // Sub-label hint underneath.
            $hint = 'Zmien w: Zarzadzaj slajdami';
            $hintW = imagefontwidth($fontSize) * strlen($hint);
            $hintX = (int) (($width - $hintW) / 2);
            imagestring($im, $fontSize, $hintX, $y + $textH + 12, $hint, $fg);

            $ok = @imagejpeg($im, $path, 85);
            imagedestroy($im);

            if ($ok) {
                @chmod($path, 0644);
            }

            return (bool) $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Author credit with a link to https://pielechowski.pl, shown on the
     * configuration page. The module is MIT-licensed: the credit is kept by
     * default, it is not a license requirement.
     *
     * @return string
     */
    public function renderAplineFooter()
    {
        return '
        <style>
            .apline-credit { margin-top: 24px; font-size: 12px; opacity: 0.9; }
            .apline-credit a { font-weight: 600; }
        </style>
        <div class="apline-credit">
            ' . $this->trans('Autor modułu:', [], 'Modules.Aplinesimplesliderbanner.Admin') . '
            <a href="https://pielechowski.pl" target="_blank" rel="noopener noreferrer">PIELECHOWSKI.PL</a>
        </div>';
    }

    /**
     * Subtle "need custom development?" box shown on the configuration page.
     *
     * @return string
     */
    public function renderLikeBox()
    {
        return '
        <div class="panel">
            <h3>&#9749; ' . $this->trans('Podoba Ci się ten moduł?', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</h3>
            <p>' . $this->trans('Potrzebujesz rozwoju PrestaShop, optymalizacji wydajności lub integracji?', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</p>
            <a class="btn btn-default" href="https://pielechowski.pl" target="_blank" rel="noopener noreferrer">&#8594; PIELECHOWSKI.PL</a>
        </div>';
    }

    // --------------------------------------------------------------------
    // Front-end rendering — registered hooks + render orchestration.
    // --------------------------------------------------------------------

    /**
     * Register the front stylesheet + slider JS on every front-end page.
     * The JS file is a stub in CP04 (added in CP05); registration is safe
     * because the stub is a valid empty JS module.
     */
    public function hookActionFrontControllerSetMedia()
    {
        try {
            $this->context->controller->registerStylesheet(
                'apline-simple-slider-banner',
                'modules/' . $this->name . '/views/css/front.css'
            );
            $this->context->controller->registerJavascript(
                'apline-simple-slider-banner',
                'modules/' . $this->name . '/views/js/slider.js',
                ['position' => 'bottom', 'priority' => 150]
            );
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('assb: ' . $e->getMessage(), 3);
        }
    }

    public function hookDisplayHome($params)
    {
        return $this->renderSlider($params);
    }

    /**
     * Render the slider. Wrapped so any failure yields an empty block
     * instead of a 500 .
     *
     * @param array $params
     *
     * @return string
     */
    private function renderSlider($params = [])
    {
        try {
            $slidesDesktop = $this->buildSlides('desktop');
            $slidesMobile = $this->buildSlides('mobile');
            if (!$slidesDesktop && !$slidesMobile) {
                return '';
            }

            $this->smarty->assign([
                'slides_desktop' => $slidesDesktop,
                'slides_mobile' => $slidesMobile,
                'config' => $this->getRenderConfig(),
            ]);

            return $this->display(__FILE__, 'views/templates/hook/slider.tpl');
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('assb: ' . $e->getMessage(), 3);

            return '';
        }
    }

    /**
     * Build the list of slides to render for ONE viewport. Strict
     * per-viewport filtering — a slide enters the desktop list only if
     * `show_on_desktop=1` AND `image_desktop` is non-empty (symmetric
     * for mobile). No cross-viewport fallback in v1.1.0+: admins must
     * upload the matching image for each viewport they enable; the
     * AdminController validation rejects mismatches at save time.
     *
     * The front template uses this twice (once per viewport) and renders
     * two independent sliders toggled by a CSS media query.
     *
     * @param string $viewport one of 'desktop' or 'mobile'
     *
     * @return array[] each entry: [id, src, alt, url]
     */
    private function buildSlides($viewport)
    {
        $isDesktop = ($viewport === 'desktop');
        $imageKey = $isDesktop ? 'image_desktop' : 'image_mobile';
        $altKey = $isDesktop ? 'alt_desktop' : 'alt_mobile';
        $showKey = $isDesktop ? 'show_on_desktop' : 'show_on_mobile';

        $out = [];

        foreach (AplineSimpleSliderBannerSlide::getActiveSlides() as $slide) {
            if (empty($slide[$showKey])) {
                continue;
            }

            $src = isset($slide[$imageKey]) ? (string) $slide[$imageKey] : '';
            if ($src === '') {
                // Defensive: AdminController validation should already block
                // this combination, but a stale row from v1.0.x would still
                // get filtered out cleanly here.
                continue;
            }

            $alt = isset($slide[$altKey]) ? (string) $slide[$altKey] : '';

            $out[] = [
                'id' => (int) $slide['id_assb_slide'],
                'src' => $src,
                'alt' => $alt,
                'url' => isset($slide['url']) ? (string) $slide['url'] : '',
            ];
        }

        return $out;
    }

    /**
     * Build the render-time config dictionary that the Smarty template +
     * vanilla JS slider read. Whitelist-clamps the values so a tampered
     * Configuration row can't produce broken markup or runaway autoplay.
     *
     * @return array
     */
    public function getRenderConfig()
    {
        $speed = (int) Configuration::get(self::SPEED_KEY);
        $speed = max(500, min(30000, $speed ?: 5000));

        $navigation = Configuration::get(self::NAVIGATION_KEY);
        if (!in_array($navigation, self::NAVIGATION_MODES, true)) {
            $navigation = 'dots';
        }

        $transition = Configuration::get(self::TRANSITION_KEY);
        if (!in_array($transition, self::TRANSITION_MODES, true)) {
            $transition = 'slide';
        }

        $sizing = Configuration::get(self::SIZING_MODE_KEY);
        if (!in_array($sizing, self::SIZING_MODES, true)) {
            $sizing = 'natural';
        }

        $fill = Configuration::get(self::FILL_MODE_KEY);
        if (!in_array($fill, self::FILL_MODES, true)) {
            $fill = 'cover';
        }

        $containerDesktop = Configuration::get(self::CONTAINER_DESKTOP_KEY);
        if (!in_array($containerDesktop, self::CONTAINER_MODES, true)) {
            $containerDesktop = 'none';
        }

        $containerMobile = Configuration::get(self::CONTAINER_MOBILE_KEY);
        if (!in_array($containerMobile, self::CONTAINER_MODES, true)) {
            $containerMobile = 'none';
        }

        return [
            'speed' => $speed,
            'pause_on_hover' => (bool) Configuration::get(self::PAUSE_ON_HOVER_KEY),
            'loop' => (bool) Configuration::get(self::LOOP_KEY),
            'autoplay' => (bool) Configuration::get(self::AUTOPLAY_KEY),
            'navigation' => $navigation,
            'transition' => $transition,
            'sizing' => $sizing,
            'fill' => $fill,
            'w_desktop' => $this->clampDimension((int) Configuration::get(self::FIXED_W_DESKTOP_KEY), self::FIXED_W_MIN_DESKTOP, self::FIXED_W_MAX_DESKTOP, self::FIXED_W_DESKTOP_DEFAULT),
            'h_desktop' => $this->clampDimension((int) Configuration::get(self::FIXED_H_DESKTOP_KEY), self::FIXED_H_MIN_DESKTOP, self::FIXED_H_MAX_DESKTOP, self::FIXED_H_DESKTOP_DEFAULT),
            'w_mobile' => $this->clampDimension((int) Configuration::get(self::FIXED_W_MOBILE_KEY), self::FIXED_W_MIN_MOBILE, self::FIXED_W_MAX_MOBILE, self::FIXED_W_MOBILE_DEFAULT),
            'h_mobile' => $this->clampDimension((int) Configuration::get(self::FIXED_H_MOBILE_KEY), self::FIXED_H_MIN_MOBILE, self::FIXED_H_MAX_MOBILE, self::FIXED_H_MOBILE_DEFAULT),
            'bootstrap' => (bool) Configuration::get(self::BOOTSTRAP_KEY),
            'container_desktop' => $containerDesktop,
            'container_mobile' => $containerMobile,
            'custom_class' => trim((string) Configuration::get(self::CUSTOM_CLASS_KEY)),
        ];
    }

    // --------------------------------------------------------------------
    // Widget API — explicit embed via {widget name='apline_simple_slider_banner'}
    // anywhere in the theme.
    // --------------------------------------------------------------------

    public function renderWidget($hookName = null, array $configuration = [])
    {
        try {
            $slidesDesktop = $this->buildSlides('desktop');
            $slidesMobile = $this->buildSlides('mobile');
            if (!$slidesDesktop && !$slidesMobile) {
                return '';
            }

            $this->smarty->assign([
                'slides_desktop' => $slidesDesktop,
                'slides_mobile' => $slidesMobile,
                'config' => $this->getRenderConfig(),
            ]);

            return $this->fetch($this->templateFile);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('assb: ' . $e->getMessage(), 3);

            return '';
        }
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        try {
            return [
                'slides_desktop' => $this->buildSlides('desktop'),
                'slides_mobile' => $this->buildSlides('mobile'),
                'config' => $this->getRenderConfig(),
            ];
        } catch (\Throwable $e) {
            return [
                'slides_desktop' => [],
                'slides_mobile' => [],
                'config' => $this->getRenderConfig(),
            ];
        }
    }
}
