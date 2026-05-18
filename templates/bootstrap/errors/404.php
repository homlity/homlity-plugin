<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="<?php echo esc_url( isset( $page['back'] ) ? $page['back'] : '' ); ?>">Inmuebles</a></li>        
    </ol>
</nav>
<h1><?php echo esc_html( $error ); ?></h1>
