<?php
/**
 * APLINE Simple Slider Banner module for PrestaShop 9.
 *
 * ObjectModel for the `assb_slide` table.
 *
 * Each row represents one slide with separate desktop and mobile images,
 * optional click URL, per-viewport visibility flags and position ordering.
 *
 * @author    Arkadiusz Pielechowski
 * @copyright Arkadiusz Pielechowski
 * @license   MIT - see LICENSE.md
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AplineSimpleSliderBannerSlide extends ObjectModel
{
    /** @var string admin-only label, not rendered on the front-end */
    public $title;
    /** @var string|null relative public path to the desktop image */
    public $image_desktop;
    /** @var string|null relative public path to the mobile image */
    public $image_mobile;
    /** @var string|null alt text for the desktop image (a11y + SEO) */
    public $alt_desktop;
    /** @var string|null alt text for the mobile image (a11y + SEO) */
    public $alt_mobile;
    /** @var string|null click-through URL; empty = non-clickable slide */
    public $url;
    /** @var bool */
    public $show_on_desktop;
    /** @var bool */
    public $show_on_mobile;
    /** @var bool */
    public $active;
    /** @var int */
    public $position;
    /** @var string */
    public $date_add;
    /** @var string */
    public $date_upd;

    /**
     * @see ObjectModel::$definition
     *
     * Field rules:
     *   - title: required admin label, isGenericName, max 255
     *   - image_desktop / image_mobile: optional public path to uploaded
     *     image (stored as relative URL produced by the AdminController
     *     upload handler), max 255
     *   - alt_desktop / alt_mobile: optional alt text per image, max 255
     *   - url: optional click-through URL, isUrl accepts both absolute
     *     and relative (e.g. "/category/foo"), max 2048
     *   - show_on_desktop / show_on_mobile / active: bool flags
     *   - position: 0-based ordering, set automatically by add() if empty
     *
     * Hard validation (at-least-one image / alt-required-when-image /
     * remove_image switches / upload hardening) lives in the
     * AdminController handleSubmission — CP03 wires that.
     */
    public static $definition = [
        'table' => 'assb_slide',
        'primary' => 'id_assb_slide',
        'multilang' => false,
        'fields' => [
            'title' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'image_desktop' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 255],
            'image_mobile' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 255],
            'alt_desktop' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 255],
            'alt_mobile' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 255],
            'url' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'size' => 2048],
            'show_on_desktop' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'show_on_mobile' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Active slides ordered by position, for front rendering.
     * Guarded so a missing or corrupted table never breaks the shop front
     *  — render code (CP04) treats
     * an empty array as "render nothing".
     *
     * @return array
     */
    public static function getActiveSlides()
    {
        try {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'assb_slide`
                WHERE `active` = 1
                ORDER BY `position` ASC, `id_assb_slide` ASC';

            $result = Db::getInstance()->executeS($sql);

            return is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Detect remaining installer images, including inactive slides.
     * Uploaded replacements use a different filename prefix.
     *
     * @return bool
     */
    public static function hasDemoImages()
    {
        try {
            $rows = Db::getInstance()->executeS(
                'SELECT `image_desktop`, `image_mobile` FROM `' . _DB_PREFIX_ . 'assb_slide`'
            );
            foreach (is_array($rows) ? $rows : [] as $row) {
                foreach (['image_desktop', 'image_mobile'] as $field) {
                    if (preg_match('/^assb_seed_[1-3]_(desktop|mobile)_[a-f0-9]{13,14}\.[0-9]{8}\.jpg$/', basename((string) ($row[$field] ?? '')))) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Next free position value, used to default new rows to the end of
     * the list. Falls back to 1 if the table is empty or unreachable.
     *
     * @return int
     */
    public static function getNextPosition()
    {
        try {
            $max = (int) Db::getInstance()->getValue(
                'SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . 'assb_slide`'
            );

            return $max + 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * @see ObjectModel::add()
     *
     * Auto-assign position at the end of the list when the admin doesn't
     * specify one explicitly — so new slides always show up after existing
     * ones and the drag&drop list is never broken by zero-position duplicates.
     */
    public function add($auto_date = true, $null_values = false)
    {
        if (empty($this->position)) {
            $this->position = self::getNextPosition();
        }

        return parent::add($auto_date, $null_values);
    }
}
