{*
 * APLINE Simple Slider Banner module for PrestaShop 9.
 *
 * Front-end slider render. Renders TWO independent sliders — one for
 * desktop viewports and one for mobile — each in its own outer wrapper
 * (.assb-outer--desktop / .assb-outer--mobile) toggled via CSS @media at
 * the 767px breakpoint (front.css). Each slider only holds the slides
 * that have both the matching image and the matching show_on_* flag
 * (filtered server-side in buildSlides($viewport)). No <picture> element,
 * no cross-viewport fallback.
 *
 * Slides without a URL render as <div>; slides with a URL render as
 * <a rel="noopener"> so the whole slide is clickable.
 *
 * Container layout (Task 2, v1.2.0) — per viewport. When $config.bootstrap
 * is on, each outer wrapper carries the chosen Bootstrap class
 * (.container / .container-fluid) for that viewport; "none" (or Bootstrap
 * off) leaves the wrapper as a plain full-width block. Because desktop and
 * mobile are separate elements toggled by @media, the page-width decision
 * can differ per viewport.
 *
 * Fixed size (Task 1, v1.2.0) — when $config.sizing is "fixed", each slider
 * root gets the .assb-slider--fixed class plus inline custom properties
 * (--assb-w / --assb-h / --assb-fit) carrying that viewport's dimensions.
 * front.css turns those into aspect-ratio + max-width + object-fit so all
 * slides share one size and scale down proportionally on narrow screens.
 *
 * Variables expected:
 *   $slides_desktop  array of slide dicts: id, src, alt, url (desktop)
 *   $slides_mobile   array of slide dicts: id, src, alt, url (mobile)
 *   $config          {speed, autoplay, loop, pause_on_hover, navigation,
 *                     transition, sizing, fill, w_desktop, h_desktop,
 *                     w_mobile, h_mobile, bootstrap, container_desktop,
 *                     container_mobile, custom_class}
 *
 * @author Arkadiusz Pielechowski
 *}
{if ($slides_desktop|@count) || ($slides_mobile|@count)}
{assign var=assb_cd value=''}
{assign var=assb_cm value=''}
{if $config.bootstrap}
  {if $config.container_desktop == 'container' || $config.container_desktop == 'container-fluid'}{assign var=assb_cd value=$config.container_desktop}{/if}
  {if $config.container_mobile == 'container' || $config.container_mobile == 'container-fluid'}{assign var=assb_cm value=$config.container_mobile}{/if}
{/if}

{if $slides_desktop|@count}
<div class="assb-outer assb-outer--desktop{if $assb_cd} {$assb_cd}{/if}">
<div class="apline-simple-slider-banner assb-slider assb-slider--desktop{if $config.sizing == 'fixed'} assb-slider--fixed{/if}{if $config.custom_class} {$config.custom_class|escape:'html':'UTF-8'}{/if}"
     {if $config.sizing == 'fixed'}style="--assb-w:{$config.w_desktop|intval};--assb-h:{$config.h_desktop|intval};--assb-fit:{$config.fill|escape:'html':'UTF-8'};"{/if}
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
    <button type="button" class="assb-arrow assb-arrow-prev" aria-label="Poprzedni slajd">&#8249;</button>
    <button type="button" class="assb-arrow assb-arrow-next" aria-label="Następny slajd">&#8250;</button>
  {/if}

  {if $config.navigation == 'dots' || $config.navigation == 'both'}
    <div class="assb-dots" role="tablist"></div>
  {/if}

</div>
</div>
{/if}

{if $slides_mobile|@count}
<div class="assb-outer assb-outer--mobile{if $assb_cm} {$assb_cm}{/if}">
<div class="apline-simple-slider-banner assb-slider assb-slider--mobile{if $config.sizing == 'fixed'} assb-slider--fixed{/if}{if $config.custom_class} {$config.custom_class|escape:'html':'UTF-8'}{/if}"
     {if $config.sizing == 'fixed'}style="--assb-w:{$config.w_mobile|intval};--assb-h:{$config.h_mobile|intval};--assb-fit:{$config.fill|escape:'html':'UTF-8'};"{/if}
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
    <button type="button" class="assb-arrow assb-arrow-prev" aria-label="Poprzedni slajd">&#8249;</button>
    <button type="button" class="assb-arrow assb-arrow-next" aria-label="Następny slajd">&#8250;</button>
  {/if}

  {if $config.navigation == 'dots' || $config.navigation == 'both'}
    <div class="assb-dots" role="tablist"></div>
  {/if}

</div>
</div>
{/if}
{/if}
