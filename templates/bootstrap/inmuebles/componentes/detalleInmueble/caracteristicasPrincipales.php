<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="d-flex flex-md-row flex-wrap">
    <?php if ( $caracteristicas['areaLote'] > 0 ) : ?>
    <div class=" itemCaracteristica caracteristicaAreaLote">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE914"></i>
            <span class="">Área lote: <?php echo esc_html( $caracteristicas['areaLote'] ); ?> M<sup>2</sup></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $caracteristicas['areaConstruida'] > 0 ) : ?>
    <div class="itemCaracteristica caracteristicaAreaConstruida">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE95E"></i>
            <span>Área cons: <?php echo esc_html( $caracteristicas['areaConstruida'] ); ?> M<sup>2</sup></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $caracteristicas['banos'] > 0 ) : ?>
    <div class="itemCaracteristica caracteristicaAreaLote">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE93E"></i>
            <span><?php echo esc_html( ( $caracteristicas['banos'] === 1 ) ? 'Baño: ' . $caracteristicas['banos'] : 'Baños: ' . $caracteristicas['banos'] ); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $caracteristicas['alcobas'] > 0 ) : ?>
    <div class="itemCaracteristica caracteristicaAlcobas">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE951"></i>
            <span><?php echo esc_html( ( $caracteristicas['alcobas'] === 1 ) ? 'Alcoba: ' . $caracteristicas['alcobas'] : 'Alcobas: ' . $caracteristicas['alcobas'] ); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $caracteristicas['garajes'] > 0 ) : ?>
    <div class="itemCaracteristica caracteristicaGarajes">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE9A5"></i>
            <span><?php echo esc_html( ( $caracteristicas['garajes'] === 1 ) ? 'Garaje: ' . $caracteristicas['garajes'] : 'Garajes: ' . $caracteristicas['garajes'] ); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $caracteristicas['estrato'] > 0 ) : ?>
    <div class="itemCaracteristica caracteristicaEstrato">
        <div class="flex d-flex align-items-center">
           <i class="icon-homlity icon-uniE9AF"></i>
           <span> Estrato: <?php
           if ( homlity_sync_integrator_current_is_wasi() ) {
                if ( $caracteristicas['estrato'] == 7 ) {
                    echo esc_html__( 'Rural', 'homlity-real-estate' );
                } elseif ( $caracteristicas['estrato'] == 8 ) {
                    echo esc_html__( 'Comercial', 'homlity-real-estate' );
                } else {
                    echo esc_html( $caracteristicas['estrato'] );
                }
           } elseif ( homlity_sync_integrator_current_is_simi() ) {
                if ( $caracteristicas['estrato'] == 8 ) {
                    echo esc_html__( 'Rural', 'homlity-real-estate' );
                } elseif ( $caracteristicas['estrato'] == 7 ) {
                    echo esc_html__( 'Comercial', 'homlity-real-estate' );
                } else {
                    echo esc_html( $caracteristicas['estrato'] );
                }
           } else {
                echo esc_html( $caracteristicas['estrato'] );
           }
        ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $caracteristicas['edad'] > 0 ) : ?>
    <div class="itemCaracteristica caracteristicaEdad">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE98A"></i>
            <span>Edad: <?php echo esc_html( ( $caracteristicas['edad'] === 1 ) ? $caracteristicas['edad'] . ' año' : $caracteristicas['edad'] . ' años' ); ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>
