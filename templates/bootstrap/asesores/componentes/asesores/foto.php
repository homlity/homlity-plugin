
 <?php if (!empty($asesor->fotoUrl())) { ?>
        <img src="<?php echo $asesor->fotoUrl(); ?>" class="card-img-top text-center img-thumbnail"
            alt="<?php echo $asesor->nombre(); ?>" />
    <?php } else {
        $custom_logo_id = get_theme_mod('custom_logo');
        $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        ?>
        <img src="<?php echo $logo_url; ?>" class="custom card-img-top text-center img-thumbnail"
            alt="<?php echo $asesor->nombre(); ?>" />
    <?php } ?>