<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<ul class="listadeprecios d-flex flex-column flex-md-row">
    <?php
    if ( $gestion->esArriendoVenta() ) {
        echo '<li><p><i class="icon-homlity icon-uniE9C0"></i> Arriendo <span><a href="' . esc_url( visualinmu_route_search( [ 'precioMax' => $valores['canon'] ] ) ) . '" target="_blank">$' . esc_html( $valores['canon'] ) . ' <sub>COP</sub></a></span></p></li>';
        if ( $precioConAdmin ) {
            echo esc_html__( 'Valor incluye administración', 'homlity-plugin' );
        } elseif ( $valores['administracion'] > 0 ) {
            echo '<li><p><i class="icon-homlity icon-uniE9C0"></i> Administración <span><a href="' . esc_url( visualinmu_route_search( [ 'precioMax' => $valores['administracion'], 'type_bussiness' => $gestion->nombre() ] ) ) . '" target="_blank">$' . esc_html( isset( $valores['administracion_format'] ) ? $valores['administracion_format'] : $valores['administracion'] ) . ' <sub>COP</sub></a></span></p></li>';
        }
        echo '<li><p><i class="icon-homlity icon-uniE9C0"></i> Venta <span><a href="' . esc_url( visualinmu_route_search( [ 'precioMax' => $valores['venta'], 'type_bussiness' => $gestion->nombre() ] ) ) . '" target="_blank">$' . esc_html( $valores['venta'] ) . ' <sub>COP</sub></a></span></p></li>';
    } elseif ( $gestion->esAriendo() ) {
        echo '<li><p><i class="icon-homlity icon-uniE9C0"></i> Arriendo <span><a href="' . esc_url( visualinmu_route_search( [ 'precioMax' => $valores['canon'], 'type_bussiness' => $gestion->nombre() ] ) ) . '" target="_blank">$' . esc_html( $valores['canon'] ) . ' <sub>COP</sub></a></span></p></li>';
        if ( $precioConAdmin ) {
            echo esc_html__( 'Valor incluye administración', 'homlity-plugin' );
        } elseif ( $valores['administracion'] > 0 ) {
            echo '<li><p><i class="icon-homlity icon-uniE9C0"></i> Administración <span><a href="' . esc_url( visualinmu_route_search( [ 'precioMax' => $valores['administracion'], 'type_bussiness' => $gestion->nombre() ] ) ) . '" target="_blank">$' . esc_html( isset( $valores['administracion_format'] ) ? $valores['administracion_format'] : $valores['administracion'] ) . ' <sub>COP</sub></a></span></p></li>';
        }
    } else {
        echo '<li><p><i class="icon-homlity icon-uniE9C0"></i> Venta <span><a href="' . esc_url( visualinmu_route_search( [ 'precioMax' => $valores['venta'], 'type_bussiness' => $gestion->nombre() ] ) ) . '" target="_blank">$' . esc_html( $valores['venta'] ) . ' <sub>COP</sub></a></span></p></li>';
        if ( $precioConAdmin ) {
            echo esc_html__( 'Valor incluye administración', 'homlity-plugin' );
        } elseif ( $valores['administracion'] > 0 ) {
            echo '<li><p><i class="icon-homlity icon-uniE9C0"></i> Administración <span><a href="' . esc_url( visualinmu_route_search( [ 'precioMax' => $valores['administracion'], 'type_bussiness' => $gestion->nombre() ] ) ) . '" target="_blank">$' . esc_html( $valores['administracion_format'] ) . ' <sub>COP</sub></a></span></p></li>';
        }
    }
    ?>
</ul>
