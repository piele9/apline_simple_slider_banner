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
    {l s='Slider banerów APLINE' d='Modules.Aplinesimplesliderbanner.Admin'}
  </div>
  <p>
    {l s='Zarządzaj slajdami: osobne obrazy na komputer i telefon, opcjonalne linki, teksty alternatywne i widoczność dla każdego ekranu.' d='Modules.Aplinesimplesliderbanner.Admin'}
  </p>
  <p>
    <a class="btn btn-primary btn-lg apline-btn-duzy" href="{$assb_manage_url|escape:'html':'UTF-8'}">
      <i class="icon-list"></i>
      {l s='Zarządzaj slajdami' d='Modules.Aplinesimplesliderbanner.Admin'}
    </a>
  </p>
  <p class="text-muted small">
    <i class="icon-info-circle"></i>
    {l s='Podczas instalacji powstają 3 przykładowe slajdy w różnych kolorach. Zastąp je własnymi banerami w panelu zarządzania slajdami.' d='Modules.Aplinesimplesliderbanner.Admin'}
  </p>
</div>
