<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php
wp_add_inline_script(
    'homlity-real-estate-contact-tracking',
    'window["homlity-real-estate_INMUEBLES"] = ' . wp_kses_post( $inmuebles ) . ';',
    'before'
);
?>
<div class="mapa-inmueble">

    <div id="homlity-real-estate-map">
        <homlity-real-estate-map></homlity-real-estate-map>
    </div>
</div>
