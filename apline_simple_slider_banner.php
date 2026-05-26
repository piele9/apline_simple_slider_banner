<?php
/**
 * APLINE Simple Slider Banner module for PrestaShop 9.
 *
 * Lightweight image slider/carousel with separate desktop and mobile
 * banners, optional URL per slide, configurable navigation (dots / arrows /
 * both / none) and transitions (slide / fade). Vanilla JS, no external
 * dependencies. WebP-friendly upload.
 *
 * @author    APLINE Arkadiusz Pielechowski
 * @copyright APLINE Arkadiusz Pielechowski
 * @license   Custom Attribution License v1.0 - see LICENSE.md
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/AplineSimpleSliderBannerSlide.php';

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class apline_simple_slider_banner extends Module implements WidgetInterface
{
    const HOOK_KEY = 'ASSB_HOOK';
    const SPEED_KEY = 'ASSB_SPEED';
    const PAUSE_ON_HOVER_KEY = 'ASSB_PAUSE_ON_HOVER';
    const LOOP_KEY = 'ASSB_LOOP';
    const AUTOPLAY_KEY = 'ASSB_AUTOPLAY';
    const NAVIGATION_KEY = 'ASSB_NAVIGATION';
    const TRANSITION_KEY = 'ASSB_TRANSITION';

    const ADMIN_CONTROLLER = 'AdminAplineSimpleSliderBannerSlide';

    /** @var string */
    private $templateFile = 'module:apline_simple_slider_banner/views/templates/hook/slider.tpl';

    /**
     * Hooks the slider may be displayed on. Key = hook name, value = admin label.
     *
     * @return array
     */
    public static function getAvailableHooks()
    {
        return [
            'displayHome' => 'Home page',
            'displayTop' => 'Top of every page',
            'displayFooter' => 'Footer',
            'displayContentWrapperTop' => 'Above main content',
        ];
    }

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

    public function __construct()
    {
        $this->name = 'apline_simple_slider_banner';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'APLINE Arkadiusz Pielechowski';
        $this->need_instance = false;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('APLINE Simple Slider Banner for PrestaShop 9', [], 'Modules.Aplinesimplesliderbanner.Admin');
        $this->description = $this->trans('Lightweight image slider with separate desktop and mobile banners, optional URL per slide, configurable navigation and transitions.', [], 'Modules.Aplinesimplesliderbanner.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module? All slides will be deleted.', [], 'Modules.Aplinesimplesliderbanner.Admin');

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
            $this->_errors[] = $this->trans('Installation failed and was rolled back. Please check folder permissions and try again.', [], 'Modules.Aplinesimplesliderbanner.Admin');

            return false;
        }

        return true;
    }

    public function uninstall()
    {
        // Each step is idempotent; uninstall must not fail because something is already gone.
        $this->uninstallTab();
        $this->deleteUploadedFiles();

        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'assb_slide`');

        Configuration::deleteByName(self::HOOK_KEY);
        Configuration::deleteByName(self::SPEED_KEY);
        Configuration::deleteByName(self::PAUSE_ON_HOVER_KEY);
        Configuration::deleteByName(self::LOOP_KEY);
        Configuration::deleteByName(self::AUTOPLAY_KEY);
        Configuration::deleteByName(self::NAVIGATION_KEY);
        Configuration::deleteByName(self::TRANSITION_KEY);

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
        return Configuration::updateValue(self::HOOK_KEY, 'displayHome')
            && Configuration::updateValue(self::SPEED_KEY, 5000)
            && Configuration::updateValue(self::PAUSE_ON_HOVER_KEY, 1)
            && Configuration::updateValue(self::LOOP_KEY, 1)
            && Configuration::updateValue(self::AUTOPLAY_KEY, 1)
            && Configuration::updateValue(self::NAVIGATION_KEY, 'dots')
            && Configuration::updateValue(self::TRANSITION_KEY, 'slide');
    }

    /**
     * @return bool
     */
    private function installHooks()
    {
        $ok = $this->registerHook('actionFrontControllerSetMedia');
        foreach (array_keys(self::getAvailableHooks()) as $hook) {
            $ok = $ok && $this->registerHook($hook);
        }

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
            $tab->name[$lang['id_lang']] = 'Simple Slider Banner';
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
     * Module configuration page. CP01 stub: only the attribution + warning
     * that the real configuration form arrives in a later checkpoint.
     *
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (!$this->isUploadDirWritable()) {
            $output .= $this->displayWarning($this->trans('The upload folder is not writable: %s. Image uploads will fail until you fix its permissions (e.g. chmod 0775).', [$this->getUploadDir()], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        $output .= $this->displayWarning($this->trans('Module scaffolding only (checkpoint 01). The full configuration form and slide management UI ship in later checkpoints.', [], 'Modules.Aplinesimplesliderbanner.Admin'));

        return $output . $this->renderLikeBox() . $this->renderAplineFooter();
    }

    /**
     * APLINE attribution block. Required by the module license to stay visible
     * on the configuration page with a working link to https://apline.pl.
     * Rendered server-side as a standalone component (not CSS-only) so it
     * cannot be trivially stripped.
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
            ' . $this->trans('Module created by', [], 'Modules.Aplinesimplesliderbanner.Admin') . '
            <a href="https://apline.pl" target="_blank" rel="noopener noreferrer">APLINE</a>
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
            <h3>&#9749; ' . $this->trans('Like this module?', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</h3>
            <p>' . $this->trans('Need custom PrestaShop development, performance optimization or integrations?', [], 'Modules.Aplinesimplesliderbanner.Admin') . '</p>
            <a class="btn btn-default" href="https://apline.pl" target="_blank" rel="noopener noreferrer">&#8594; APLINE.PL</a>
        </div>';
    }

    // --------------------------------------------------------------------
    // Hook stubs — all return '' until CP04/CP05 implement front rendering.
    // --------------------------------------------------------------------

    public function hookActionFrontControllerSetMedia()
    {
        // CSS + JS registration arrives in CP04.
        return '';
    }

    public function hookDisplayHome($params)
    {
        return '';
    }

    public function hookDisplayTop($params)
    {
        return '';
    }

    public function hookDisplayFooter($params)
    {
        return '';
    }

    public function hookDisplayContentWrapperTop($params)
    {
        return '';
    }

    // --------------------------------------------------------------------
    // Widget API stubs — CP04 wires the real renderer + variables.
    // --------------------------------------------------------------------

    public function renderWidget($hookName = null, array $configuration = [])
    {
        return '';
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        return ['slides' => []];
    }
}
