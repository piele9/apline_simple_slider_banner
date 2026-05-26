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
     * Module configuration page. Renders the global slider settings form
     * (display location, speed, autoplay, pause-on-hover, loop, navigation,
     * transition), the "Manage slides" link to the hidden admin tab, the
     * upload-dir warning if applicable, and the mandatory APLINE attribution.
     *
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitAssbConfig')) {
            $output .= $this->saveConfigForm();
        }

        if (!$this->isUploadDirWritable()) {
            $output .= $this->displayWarning($this->trans('The upload folder is not writable: %s. Image uploads will fail until you fix its permissions (e.g. chmod 0775).', [$this->getUploadDir()], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        $manageUrl = $this->context->link->getAdminLink(self::ADMIN_CONTROLLER);
        $this->context->smarty->assign(['assb_manage_url' => $manageUrl]);
        $output .= $this->display(__FILE__, 'views/templates/admin/configure.tpl');

        return $output . $this->renderConfigForm() . $this->renderLikeBox() . $this->renderAplineFooter();
    }

    /**
     * Validate and persist the global slider settings posted from the
     * configuration form. Whitelist-validates ASSB_HOOK / ASSB_NAVIGATION /
     * ASSB_TRANSITION, clamps ASSB_SPEED to [500, 30000] ms, casts the bool
     * switches and returns a display banner (confirmation or error).
     *
     * @return string
     */
    private function saveConfigForm()
    {
        $hook = (string) Tools::getValue(self::HOOK_KEY);
        if (!array_key_exists($hook, self::getAvailableHooks())) {
            return $this->displayError($this->trans('Invalid display location selected.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        $navigation = (string) Tools::getValue(self::NAVIGATION_KEY);
        if (!in_array($navigation, self::NAVIGATION_MODES, true)) {
            return $this->displayError($this->trans('Invalid navigation mode selected.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        $transition = (string) Tools::getValue(self::TRANSITION_KEY);
        if (!in_array($transition, self::TRANSITION_MODES, true)) {
            return $this->displayError($this->trans('Invalid transition mode selected.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        $speed = (int) Tools::getValue(self::SPEED_KEY);
        if ($speed < 500 || $speed > 30000) {
            return $this->displayError($this->trans('Speed must be between 500 and 30000 milliseconds.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
        }

        Configuration::updateValue(self::HOOK_KEY, $hook);
        Configuration::updateValue(self::NAVIGATION_KEY, $navigation);
        Configuration::updateValue(self::TRANSITION_KEY, $transition);
        Configuration::updateValue(self::SPEED_KEY, $speed);
        Configuration::updateValue(self::AUTOPLAY_KEY, (int) Tools::getValue(self::AUTOPLAY_KEY) ? 1 : 0);
        Configuration::updateValue(self::PAUSE_ON_HOVER_KEY, (int) Tools::getValue(self::PAUSE_ON_HOVER_KEY) ? 1 : 0);
        Configuration::updateValue(self::LOOP_KEY, (int) Tools::getValue(self::LOOP_KEY) ? 1 : 0);

        return $this->displayConfirmation($this->trans('Slider settings saved.', [], 'Modules.Aplinesimplesliderbanner.Admin'));
    }

    /**
     * Build the global settings HelperForm: display location, speed,
     * autoplay/pause/loop switches, navigation mode, transition mode.
     *
     * @return string
     */
    private function renderConfigForm()
    {
        $hookOptions = [];
        foreach (self::getAvailableHooks() as $hookName => $label) {
            $hookOptions[] = ['id' => $hookName, 'name' => $label];
        }

        $navigationOptions = [
            ['id' => 'dots', 'name' => $this->trans('Dots only', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'arrows', 'name' => $this->trans('Arrows only', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'both', 'name' => $this->trans('Dots + arrows', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'none', 'name' => $this->trans('No navigation (autoplay only)', [], 'Modules.Aplinesimplesliderbanner.Admin')],
        ];

        $transitionOptions = [
            ['id' => 'slide', 'name' => $this->trans('Slide (horizontal)', [], 'Modules.Aplinesimplesliderbanner.Admin')],
            ['id' => 'fade', 'name' => $this->trans('Fade (opacity)', [], 'Modules.Aplinesimplesliderbanner.Admin')],
        ];

        $boolSwitch = function ($idPrefix) {
            return [
                ['id' => $idPrefix . '_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                ['id' => $idPrefix . '_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
            ];
        };

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Slider settings', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->trans('Display location', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::HOOK_KEY,
                        'options' => ['query' => $hookOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Where the slider is rendered on the front-end. You can also embed it anywhere with {widget name=\'apline_simple_slider_banner\'}.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Speed (ms)', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::SPEED_KEY,
                        'class' => 'fixed-width-sm',
                        'suffix' => 'ms',
                        'desc' => $this->trans('Time between slides in milliseconds. 5000 = 5 seconds. Allowed range: 500-30000.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Autoplay', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::AUTOPLAY_KEY,
                        'is_bool' => true,
                        'values' => $boolSwitch('autoplay'),
                        'desc' => $this->trans('Auto-advance through slides on a timer.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Pause on hover', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::PAUSE_ON_HOVER_KEY,
                        'is_bool' => true,
                        'values' => $boolSwitch('pause_on_hover'),
                        'desc' => $this->trans('Stop auto-advance while the cursor is over the slider.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Loop forever', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::LOOP_KEY,
                        'is_bool' => true,
                        'values' => $boolSwitch('loop'),
                        'desc' => $this->trans('After the last slide, wrap back to the first. If off, the slider stops at the last slide (the user can still navigate manually).', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Navigation', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::NAVIGATION_KEY,
                        'options' => ['query' => $navigationOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Which manual navigation controls are visible.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Transition', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                        'name' => self::TRANSITION_KEY,
                        'options' => ['query' => $transitionOptions, 'id' => 'id', 'name' => 'name'],
                        'desc' => $this->trans('Slide horizontally or fade between slides.', [], 'Modules.Aplinesimplesliderbanner.Admin'),
                    ],
                ],
                'submit' => ['title' => $this->trans('Save', [], 'Admin.Actions')],
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
            self::HOOK_KEY => Configuration::get(self::HOOK_KEY) ?: 'displayHome',
            self::SPEED_KEY => (int) (Configuration::get(self::SPEED_KEY) ?: 5000),
            self::AUTOPLAY_KEY => (int) Configuration::get(self::AUTOPLAY_KEY),
            self::PAUSE_ON_HOVER_KEY => (int) Configuration::get(self::PAUSE_ON_HOVER_KEY),
            self::LOOP_KEY => (int) Configuration::get(self::LOOP_KEY),
            self::NAVIGATION_KEY => Configuration::get(self::NAVIGATION_KEY) ?: 'dots',
            self::TRANSITION_KEY => Configuration::get(self::TRANSITION_KEY) ?: 'slide',
        ];

        return $helper->generateForm([$fields_form]);
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
                ['rgb' => [54, 96, 153],  'label' => 'Sample Slide 1'],   // steel blue
                ['rgb' => [102, 51, 102], 'label' => 'Sample Slide 2'],   // muted purple
                ['rgb' => [153, 102, 51], 'label' => 'Sample Slide 3'],   // warm brown
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
                    'alt_desktop' => pSQL($slide['label'] . ' (desktop)'),
                    'alt_mobile' => pSQL($slide['label'] . ' (mobile)'),
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
            $hint = 'Replace via Manage slides';
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
        return $this->renderForHook('displayHome', $params);
    }

    public function hookDisplayTop($params)
    {
        return $this->renderForHook('displayTop', $params);
    }

    public function hookDisplayFooter($params)
    {
        return $this->renderForHook('displayFooter', $params);
    }

    public function hookDisplayContentWrapperTop($params)
    {
        return $this->renderForHook('displayContentWrapperTop', $params);
    }

    /**
     * Render the slider only on the hook selected in configuration.
     * Wrapped so any failure yields an empty block instead of a 500
     * (workspace CLAUDE.md §3.1 crash-safety).
     *
     * @param string $hookName
     * @param array $params
     *
     * @return string
     */
    private function renderForHook($hookName, $params = [])
    {
        try {
            if (Configuration::get(self::HOOK_KEY) !== $hookName) {
                return '';
            }

            $slides = $this->buildSlides();
            if (!$slides) {
                return '';
            }

            $this->smarty->assign([
                'slides' => $slides,
                'config' => $this->getRenderConfig(),
            ]);

            return $this->display(__FILE__, 'views/templates/hook/slider.tpl');
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('assb: ' . $e->getMessage(), 3);

            return '';
        }
    }

    /**
     * Build the list of slides to render. Applies per-slide visibility
     * flags and automatic fallback when a viewport is enabled but its
     * image is missing (uses the other image instead, so admins don't
     * have to upload both for every slide).
     *
     * @return array[] each entry: [id, desktop_src, mobile_src, alt_desktop, alt_mobile, url]
     */
    private function buildSlides()
    {
        $out = [];

        foreach (AplineSimpleSliderBannerSlide::getActiveSlides() as $slide) {
            $showDesktop = !empty($slide['show_on_desktop']);
            $showMobile = !empty($slide['show_on_mobile']);

            // Both off → globally hidden (validation prevents this but
            // double-check at render time so a broken row never crashes us).
            if (!$showDesktop && !$showMobile) {
                continue;
            }

            $imageDesktop = isset($slide['image_desktop']) ? (string) $slide['image_desktop'] : '';
            $imageMobile = isset($slide['image_mobile']) ? (string) $slide['image_mobile'] : '';

            // Fallback logic: if a viewport is on but its image is missing,
            // use the other viewport's image as a backup.
            $desktopSrc = $imageDesktop !== ''
                ? $imageDesktop
                : ($showDesktop && $imageMobile !== '' ? $imageMobile : '');
            $mobileSrc = $imageMobile !== ''
                ? $imageMobile
                : ($showMobile && $imageDesktop !== '' ? $imageDesktop : '');

            // Final guard — if both ended up empty (shouldn't happen with
            // proper admin validation), skip the slide entirely.
            if ($desktopSrc === '' && $mobileSrc === '') {
                continue;
            }

            $altDesktop = isset($slide['alt_desktop']) ? (string) $slide['alt_desktop'] : '';
            $altMobile = isset($slide['alt_mobile']) ? (string) $slide['alt_mobile'] : '';

            $out[] = [
                'id' => (int) $slide['id_assb_slide'],
                'desktop_src' => $showDesktop ? $desktopSrc : '',
                'mobile_src' => $showMobile ? $mobileSrc : '',
                'alt_desktop' => $altDesktop !== '' ? $altDesktop : $altMobile,
                'alt_mobile' => $altMobile !== '' ? $altMobile : $altDesktop,
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

        return [
            'speed' => $speed,
            'pause_on_hover' => (bool) Configuration::get(self::PAUSE_ON_HOVER_KEY),
            'loop' => (bool) Configuration::get(self::LOOP_KEY),
            'autoplay' => (bool) Configuration::get(self::AUTOPLAY_KEY),
            'navigation' => $navigation,
            'transition' => $transition,
        ];
    }

    // --------------------------------------------------------------------
    // Widget API — explicit embed via {widget name='apline_simple_slider_banner'}
    // anywhere in the theme. Unlike the hook methods, this one does NOT
    // gate on ASSB_HOOK (the widget IS the explicit placement).
    // --------------------------------------------------------------------

    public function renderWidget($hookName = null, array $configuration = [])
    {
        try {
            $slides = $this->buildSlides();
            if (!$slides) {
                return '';
            }

            $this->smarty->assign([
                'slides' => $slides,
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
                'slides' => $this->buildSlides(),
                'config' => $this->getRenderConfig(),
            ];
        } catch (\Throwable $e) {
            return ['slides' => [], 'config' => $this->getRenderConfig()];
        }
    }
}
