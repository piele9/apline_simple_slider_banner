{*
 * APLINE Simple Slider Banner module for PrestaShop 9.
 *
 * Front-end slider render. Uses <picture> with <source media="(max-width: 767px)">
 * for native viewport-aware image switching (zero JS cost for the
 * desktop/mobile decision — the browser does it).
 *
 * Slides without a URL render as <div>; slides with a URL render as
 * <a target="_self" rel="noopener"> so the whole slide is clickable.
 *
 * CP04: structural render only. The actual transitions, autoplay,
 * dots/arrows interactivity, swipe and a11y states are wired by the
 * vanilla JS slider in CP05.
 *
 * Variables expected:
 *   $slides   array of slide dicts: id, desktop_src, mobile_src,
 *             alt_desktop, alt_mobile, url
 *   $config   {speed, autoplay, loop, pause_on_hover, navigation, transition}
 *
 * @author APLINE Arkadiusz Pielechowski
 *}
{if $slides|@count}
<div class="apline-simple-slider-banner assb-slider"
     data-assb-speed="{$config.speed|intval}"
     data-assb-autoplay="{if $config.autoplay}1{else}0{/if}"
     data-assb-loop="{if $config.loop}1{else}0{/if}"
     data-assb-pause-hover="{if $config.pause_on_hover}1{else}0{/if}"
     data-assb-navigation="{$config.navigation|escape:'html':'UTF-8'}"
     data-assb-transition="{$config.transition|escape:'html':'UTF-8'}">

  <div class="assb-track">
    {foreach from=$slides item=slide}
      {if $slide.url}
        <a href="{$slide.url|escape:'html':'UTF-8'}"
           class="assb-slide"
           rel="noopener"
           data-assb-index="{$slide@index}">
          <picture>
            {if $slide.mobile_src}
              <source media="(max-width: 767px)" srcset="{$slide.mobile_src|escape:'html':'UTF-8'}">
            {/if}
            {if $slide.desktop_src}
              <img src="{$slide.desktop_src|escape:'html':'UTF-8'}"
                   alt="{$slide.alt_desktop|escape:'html':'UTF-8'}"
                   loading="lazy">
            {elseif $slide.mobile_src}
              <img src="{$slide.mobile_src|escape:'html':'UTF-8'}"
                   alt="{$slide.alt_mobile|escape:'html':'UTF-8'}"
                   loading="lazy">
            {/if}
          </picture>
        </a>
      {else}
        <div class="assb-slide" data-assb-index="{$slide@index}">
          <picture>
            {if $slide.mobile_src}
              <source media="(max-width: 767px)" srcset="{$slide.mobile_src|escape:'html':'UTF-8'}">
            {/if}
            {if $slide.desktop_src}
              <img src="{$slide.desktop_src|escape:'html':'UTF-8'}"
                   alt="{$slide.alt_desktop|escape:'html':'UTF-8'}"
                   loading="lazy">
            {elseif $slide.mobile_src}
              <img src="{$slide.mobile_src|escape:'html':'UTF-8'}"
                   alt="{$slide.alt_mobile|escape:'html':'UTF-8'}"
                   loading="lazy">
            {/if}
          </picture>
        </div>
      {/if}
    {/foreach}
  </div>

  {if $config.navigation == 'arrows' || $config.navigation == 'both'}
    <button type="button" class="assb-arrow assb-arrow-prev" aria-label="Previous slide">&#8249;</button>
    <button type="button" class="assb-arrow assb-arrow-next" aria-label="Next slide">&#8250;</button>
  {/if}

  {if $config.navigation == 'dots' || $config.navigation == 'both'}
    <div class="assb-dots" role="tablist"></div>
  {/if}

</div>
{/if}
