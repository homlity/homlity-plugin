<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="<?php echo esc_url( $page['back'] ); ?>">Inmuebles</a></li>        
    </ol>
</nav>
<h1>OCURRIO UN PROBLEMA CON LA SINCRONIZACIÓN</h1>
<?php 
if(isset($message)):
?>
<p><i><?php echo esc_html( $message ); ?></i></p>
<?php endif; ?>
<hr>
<?php visualinmu_load_template("inmuebles/componentes/search/search-widgets.php"); ?>
