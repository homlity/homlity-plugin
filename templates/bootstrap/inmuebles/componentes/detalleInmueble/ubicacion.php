<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<!--- <h3 class="homlity-plugin-titulos-ubicacion">Ubicación de <?php // echo $nombre; ?></h3> ---->
<section>
<?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/ubicacion-header.php",["mapa" => $mapa]);?>
    <ul class="nav nav-pills mb-3 tabs-media" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button onclick="gtag('event', 'prop_mapa_tab', {
    'origin': 'property_ubicacion',
    'label': 'Mapa inmueble',
    'value': '<?php echo esc_js( $nombre ); ?>' // Este valor puede ser un número
  });" class="nav-link active" id="pills-home-tab" data-bs-toggle="pill" data-bs-target="#mapa" type="button"
                role="tab" aria-controls="mapa" aria-selected="true">
                <i class="icon-homlity icon-uniE9C1"></i> Mapa
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button onclick="gtag('event', 'prop_streeview_tab', {
    'origin': 'property_ubicacion',
    'label': 'Mapa inmueble',
    'value': '<?php echo esc_js( $nombre ); ?>' // Este valor puede ser un número
  });" class="nav-link" id="pills-street-view-tab" data-bs-toggle="pill" data-bs-target="#street-view" type="button"
                role="tab" aria-controls="street-view" aria-selected="true">
                <i class="icon-homlity icon-uniE91B"></i> Vista de la calle</a>
            </button>
        </li>
    </ul>
    <div class="tab-content" id="pills-tabContent">
        <div class="tab-pane fade show active" id="mapa" role="tabpanel" aria-labelledby="mapa-tab">
            <?php visualinmu_load_template("inmuebles/componentes/search/mapa.php", ["inmuebles" => $mapa['propiedadesSimilares']]); ?>
        </div>
        <div class="tab-pane fade" id="street-view" role="tabpanel" aria-labelledby="street-view-tab">
            <iframe
                src="https://www.google.com/maps/embed?pb=!4v1638990616651!6m8!1m7!1toZz0mw!2m2!1d<?php echo esc_attr( $mapa['latitud'] ); ?>!2d<?php echo esc_attr( $mapa['longitud'] ); ?>!3f90!4f0!5f0.7820865974627469"
                style="border:0; width: 100%; height: 60vh;" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </div>
</section>
