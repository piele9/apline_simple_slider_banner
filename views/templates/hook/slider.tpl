{*
 * APLINE Simple Slider Banner module for PrestaShop 9.
 *
 * Front-end slider render. Renders TWO independent sliders — one for
 * desktop viewports and one for mobile — toggled via CSS @media at the
 * 767px breakpoint (front.css). Each slider only holds the slides that
 * have both the matching image and the matching show_on_* flag (filtered
 * server-side in buildSlides($viewport)). No <picture> element, no
 * cross-viewport fallback: a slide without a matching image+flag pair
 * is excluded from that viewport's slider entirely.
 *
 * Slides without a URL render as <div>; slides with a URL render as
 * <a target="_self" rel="noopener"> so the whole slide is clickable.
 *
 * Container layout (ASSB_CONTAINER) — a single shared outer wrapper
 * (.container / .container-fluid / none) hugs BOTH sliders so the
 * page-width decision applies to both viewports.
 *
 * Custom CSS class (ASSB_CUSTOM_CLASS) — appended to each slider root
 * so an admin's selector reaches both viewports.
 *
 * Variables expected:
 *   $slides_desktop  array of slide dicts: id, src, alt, url (desktop)
 *   $slides_mobile   array of slide dicts: id, src, alt, url (mobile)
 *   $config          {speed, autoplay, loop, pause_on_hover, navigation,
 *                     transition, container, custom_class}
 *
 * @author APLINE Arkadiusz Pielechowski
 *}
{if ($slides_desktop|@count) || ($slides_mobile|@count)}
{if $config.container == 'container'}<div class="container">{/if}
{if $config.container == 'container-fluid'}<div class="container-fluid">{/if}

{if $slides_desktop|@count}
<div class="apline-simple-slider-banner assb-slider assb-slider--desktop{if $config.custom_class} {$config.custom_class|escape:'html':'UTF-8'}{/if}"
     data-assb-speed="{$config.speed|intval}"
     data-assb-autoplay="{if $config.autoplay}1{else}0{/if}"
     data-assb-loop="{if $config.loop}1{else}0{/if}"
     data-assb-pause-hover="{if $config.pause_on_hover}1{else}0{/if}"
     data-assb-navigation="{$config.navigation|escape:'html':'UTF-8'}"
     data-assb-transition="{$config.transition|escape:'html':'UTF-8'}">

  <div class="assb-track">
    {foreach from=$slides_desktop item=slide}
      {if $slide.url}
        <a href="{$slide.url|escape:'html':'UTF-8'}"
           class="assb-slide"
           rel="noopener"
           data-assb-index="{$slide@index}">
          <img src="{$slide.src|escape:'html':'UTF-8'}"
               alt="{$slide.alt|escape:'html':'UTF-8'}"
               loading="lazy">
        </a>
      {else}
        <div class="assb-slide" data-assb-index="{$slide@index}">
          <img src="{$slide.src|escape:'html':'UTF-8'}"
               alt="{$slide.alt|escape:'html':'UTF-8'}"
               loading="lazy">
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

{if $slides_mobile|@count}
<div class="apline-simple-slider-banner assb-slider assb-slider--mobile{if $config.custom_class} {$config.custom_class|escape:'html':'UTF-8'}{/if}"
     data-assb-speed="{$config.speed|intval}"
     data-assb-autoplay="{if $config.autoplay}1{else}0{/if}"
     data-assb-loop="{if $config.loop}1{else}0{/if}"
     data-assb-pause-hover="{if $config.pause_on_hover}1{else}0{/if}"
     data-assb-navigation="{$config.navigation|escape:'html':'UTF-8'}"
     data-assb-transition="{$config.transition|escape:'html':'UTF-8'}">

  <div class="assb-track">
    {foreach from=$slides_mobile item=slide}
      {if $slide.url}
        <a href="{$slide.url|escape:'html':'UTF-8'}"
           class="assb-slide"
           rel="noopener"
           data-assb-index="{$slide@index}">
          <img src="{$slide.src|escape:'html':'UTF-8'}"
               alt="{$slide.alt|escape:'html':'UTF-8'}"
               loading="lazy">
        </a>
      {else}
        <div class="assb-slide" data-assb-index="{$slide@index}">
          <img src="{$slide.src|escape:'html':'UTF-8'}"
               alt="{$slide.alt|escape:'html':'UTF-8'}"
               loading="lazy">
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

{if $config.container != 'none'}</div>{/if}
{/if}
