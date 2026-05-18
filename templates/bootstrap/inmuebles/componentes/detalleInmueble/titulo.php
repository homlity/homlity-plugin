<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<h1 class="h2"><?php echo esc_html( $nombre ); ?></h1>
<ul class="listadeprecios d-flex flex-column flex-md-row ">
    <li>

        <p><i class="icon-homlity icon-uniE91C"></i> <?php echo esc_html( $barrio ); ?> - <?php echo esc_html( $ciudad ); ?>
        <?php echo $departamento == "SIN_DEPARTAMENTO" ? "" : " - " . esc_html( $departamento ); ?></p>
    </li>
    <li><p><i class="icon-homlity icon-uniE978"></i> Código inmueble <?php echo esc_html( $codigo ); ?></p></li>
</ul>
