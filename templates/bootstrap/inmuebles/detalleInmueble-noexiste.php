<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<h1>INMUEBLE NO EXISTE</h1>
<?php 
if(isset($message)):
?>
<p><?php echo esc_html( $message ); ?></p>
<?php endif; ?>
<hr>
<?php
if (function_exists('visualinmu_load_template')) {
    visualinmu_load_template("inmuebles/componentes/search/search-widgets.php");
}
?>
