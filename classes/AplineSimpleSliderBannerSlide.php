<?php
/**
 * APLINE Simple Slider Banner module for PrestaShop 9.
 *
 * ObjectModel for the `assb_slide` table.
 *
 * Each row represents one slide with separate desktop and mobile images,
 * optional click URL, per-viewport visibility flags and position ordering.
 *
 * CP01 stub: minimal $definition so the module installs and the main .php
 * `require_once` resolves. CP02 adds helper methods (getActiveSlides,
 * getNextPosition, add() override) and full field validation rules.
 *
 * @author    APLINE Arkadiusz Pielechowski
 * @copyright APLINE Arkadiusz Pielechowski
 * @license   Custom Attribution License v1.0 - see LICENSE.md
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
     * Minimal definition for CP01 — full field rules + length caps added in CP02.
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
}
