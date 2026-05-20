<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$checkLink = !(isset($attrs) && isset($attrs['withLinktoDetail']) && $attrs['withLinktoDetail'] == false);
?>
<?php
if ($checkLink) :
?>
    <a href="<?php echo esc_url( visualinmu_route_detalleInmueble($inmueble->slug()) ); ?>" class="inmueblelink" target="_blank">
    <?php
endif;
    ?>
    <style>
    .property-noavaliable::after {
        content: "<?php echo esc_html( $inmueble->gestion()->esAriendo() ?  __('ARRENDADO','homlity-real-estate') : __('VENDIDO','homlity-real-estate') ); ?>"; 
    }
    </style>
    <div class="imagenportada portada<?php echo esc_attr( $cont ); ?> <?php echo esc_attr( $inmueble->retirado() ? 'property-noavaliable' : '' );?>" style=" background: url(<?php echo esc_url( $inmueble->fotoPortada() ); ?>);  background-size: cover; background-position: center; ">
        <?php if ($inmueble->gestion()->esArriendoVenta()) : ?>
            <span class="badge rounded-pill bg-primary">Venta</span>
            <span class="badge rounded-pill bg-primary">Arriendo</span>
        <?php elseif ($inmueble->gestion()->esAriendo()) : ?>
            <span class="badge rounded-pill bg-primary">Arriendo</span>
        <?php else : ?>
            <span class="badge rounded-pill bg-primary">Venta</span>
        <?php endif; ?>
        <?php foreach ($inmueble->tags() as $tag) { ?>
            <?php if ($tag->key() === "VISUALINMU_DESTACADO" || $tag->key() == "DESTACADO") { ?>
                <span class="badge rounded-pill bg-primary badgePropiedadDestacada">Propiedad destacada</span>
            <?php } ?>
            <?php if ($tag->key() === "VISUALINMU_DELUJO") { ?>
                <span class="badge rounded-pill bg-primary badgePropiedadDeLujo">Propiedad de lujo</span>
            <?php } ?>
            <?php if ($tag->key() === "VISUALINMU_VERIFICADO") { ?>
                <span class="badge rounded-pill bg-primary badgePropiedadverificada" data-bs-toggle="tooltip" data-bs-placement="top" title="Este inmueble y sus propietarios fueron previamente verificados a través de nuestro software de análisis jurídico y se encuentra CALIFICADA para ser comercializada; encuentra la información legal de esta propiedad descargando gratis la ficha técnica."><i class="icon-homlity icon-uniE9AF"></i> Propiedad verificada</span>
            <?php } ?>            
        <?php } ?>
    </div>
<?php if ($checkLink) : ?>
    </a>
<?php endif; ?>
