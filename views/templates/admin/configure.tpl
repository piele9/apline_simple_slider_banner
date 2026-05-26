{*
 * APLINE Simple Slider Banner module for PrestaShop 9.
 *
 * Module configuration entry: "Manage slides" panel with link to the
 * hidden admin tab. The global slider settings form (display location,
 * speed, navigation style, transition) is appended by getContent() below
 * this panel (final form lives in CP06).
 *
 * @author APLINE Arkadiusz Pielechowski
 *}
<div class="panel">
  <div class="panel-heading">
    <i class="icon-picture"></i>
    {l s='APLINE Simple Slider Banner' d='Modules.Aplinesimplesliderbanner.Admin'}
  </div>
  <p>
    {l s='Manage your slides — each slide can carry a separate desktop and mobile image, an optional click-through URL, alt texts for accessibility, and independent visibility flags per viewport.' d='Modules.Aplinesimplesliderbanner.Admin'}
  </p>
  <p>
    <a class="btn btn-primary" href="{$assb_manage_url|escape:'html':'UTF-8'}">
      <i class="icon-list"></i>
      {l s='Manage slides' d='Modules.Aplinesimplesliderbanner.Admin'}
    </a>
  </p>
</div>
