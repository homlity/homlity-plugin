<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php if ( ! empty( $asesor->fotoUrl() ) ) { ?>
        <img src="<?php echo esc_url( $asesor->fotoUrl() ); ?>" class="card-img-top text-center img-thumbnail"
            alt="<?php echo esc_attr( $asesor->nombre() ); ?>" />
    <?php } else {
        $homlity_custom_logo_id = get_theme_mod( 'custom_logo' );
        $homlity_logo_url       = wp_get_attachment_image_url( $homlity_custom_logo_id, 'full' );
        ?>
        <img src="<?php echo esc_url( $homlity_logo_url ); ?>" class="custom card-img-top text-center img-thumbnail"
            alt="<?php echo esc_attr( $asesor->nombre() ); ?>" />
    <?php } ?>
