<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<ul class="read-more-wrap-c">
    <?php foreach ($ciudades as $keyc => $ciudad) {
        if ($keyc > 2) { ?>
            <li class="read-more-target"><a href="<?php echo esc_url( visualinmu_route_search( [
                'city' => $ciudad->nombre(),
            ] ) ); ?>" target="_blank">Encuentra
                    inmuebles en <?php echo esc_html( $ciudad->nombre() ); ?></a></li>
        <?php } else { ?>
            <li><a href="<?php echo esc_url( visualinmu_route_search( [
                'city' => $ciudad->nombre(),
            ] ) ); ?>" target="_blank">Encuentra inmuebles en
                    <?php echo esc_html( $ciudad->nombre() ); ?></a></li>
        <?php }
    } ?>
</ul>
