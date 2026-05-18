<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<style>
    @media (max-width: 700px) {
        .mapa-inmueble {
            width: 96%;
        }
    }
</style>
<div class="mapa-inmueble">
    
    <div id="homlity-plugin-map">
        <homlity-plugin-map></homlity-plugin-map>
    </div>
    <script type="text/javascript">
        window.homlity-plugin_INMUEBLES = <?php echo wp_kses_post( $inmuebles ); ?>;
    </script>
</div>
