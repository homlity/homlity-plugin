<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<p>Podemos notificarte cuando encontremos inmuebles que se ajusten a tus necesidades, por favor llena el siguiente
    formulario y uno de nuestros asesores se comunicará con tigo.</p>
<?php 
$version = wp_rand(0, 1) === 0 ? 'A' : 'B';
if ($version == 'A') {  
    $path = "leads/buscarinmueble";
} else {
    $path = "leads/buscarinmueblestep";
}
echo do_shortcode('[visualinmu_lead_shortcode height="600" path="'.$path.'"]'); ?>

