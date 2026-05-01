<?php
/**
 * Property gallery component.
 * Overridable at homlity-plugin/parts/property-gallery.php
 *
 * Expected args: $post_id (int)
 */

if (!isset($post_id)) {
    $post_id = get_the_ID();
}
if (!isset($layout)) {
    $layout = 'light_gallery';
}
$layout = in_array($layout, ['slider_show', 'masonry', 'light_gallery'], true) ? $layout : 'light_gallery';
if (!isset($style_preset)) {
    $style_preset = 'minimal';
}
$style_preset = in_array($style_preset, ['minimal', 'rounded', 'shadow_card'], true) ? $style_preset : 'minimal';

$settings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
$galleryMode = isset($settings['detail_gallery_mode']) && in_array($settings['detail_gallery_mode'], ['light_gallery', 'owl_gallery'], true)
    ? $settings['detail_gallery_mode']
    : 'light_gallery';

$images = [];
$metaGallery = get_post_meta($post_id, '_property_gallery', true);
$galleryIds = array_filter(array_map('absint', explode(',', (string) $metaGallery)));

if ($galleryIds) {
    foreach ($galleryIds as $attachmentId) {
        $full = wp_get_attachment_image_url($attachmentId, 'large');
        $thumb = wp_get_attachment_image_url($attachmentId, 'medium_large') ?: $full;
        $alt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true) ?: get_the_title($post_id);

        if ($full && !isset($images[$full])) {
            $images[$full] = [
                'full' => $full,
                'thumb' => $thumb,
                'alt' => $alt,
            ];
        }
    }
}

if (!$images && has_post_thumbnail($post_id)) {
    $thumbId = get_post_thumbnail_id($post_id);
    $full = get_the_post_thumbnail_url($post_id, 'large');
    if ($full) {
        $images[$full] = [
            'full' => $full,
            'thumb' => get_the_post_thumbnail_url($post_id, 'large'),
            'alt' => get_post_meta($thumbId, '_wp_attachment_image_alt', true) ?: get_the_title($post_id),
        ];
    }
}

if (!$galleryIds) {
    $attachments = get_posts([
        'post_type' => 'attachment',
        'posts_per_page' => 8,
        'post_status' => 'inherit',
        'post_parent' => $post_id,
        'post_mime_type' => 'image',
        'orderby' => 'menu_order',
        'fields' => 'ids',
    ]);

    foreach ($attachments as $attachmentId) {
        $full = wp_get_attachment_image_url($attachmentId, 'large');
        $thumb = wp_get_attachment_image_url($attachmentId, 'medium_large') ?: $full;
        $alt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true) ?: get_the_title($post_id);

        if ($full && !isset($images[$full])) {
            $images[$full] = [
                'full' => $full,
                'thumb' => $thumb,
                'alt' => $alt,
            ];
        }
    }
}

$images = array_values($images);

if (!$images) {
    return;
}
?>
<?php if (in_array($layout, ['slider_show', 'masonry'], true)) : ?>
    <div class="property-gallery property-gallery--swiper <?php echo $layout === 'masonry' ? 'property-gallery--swiper-masonry' : ''; ?> property-gallery--preset-<?php echo esc_attr($style_preset); ?>"
         data-homlity-swiper-gallery="1"
         data-layout="<?php echo esc_attr($layout === 'slider_show' ? 'slider' : $layout); ?>"
         data-autoplay="<?php echo !empty($autoplay) ? '1' : '0'; ?>"
         data-slides-desktop="<?php echo esc_attr((int) ($slides_desktop ?? 3)); ?>"
         data-slides-tablet="<?php echo esc_attr((int) ($slides_tablet ?? 2)); ?>"
         data-slides-mobile="<?php echo esc_attr((int) ($slides_mobile ?? 1)); ?>"
         data-loop="<?php echo !empty($loop) ? '1' : '0'; ?>"
         data-show-arrows="<?php echo !empty($show_arrows) ? '1' : '0'; ?>"
         data-show-pagination="<?php echo !empty($show_pagination) ? '1' : '0'; ?>"
         data-speed="<?php echo esc_attr((int) ($speed ?? 520)); ?>">
        <div class="swiper">
            <div class="swiper-wrapper">
                <?php foreach ($images as $idx => $image): ?>
                    <figure class="property-gallery__slide swiper-slide <?php echo $layout === 'masonry' ? 'is-masonry-' . (($idx % 6) + 1) : ''; ?>">
                        <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                    </figure>
                <?php endforeach; ?>
            </div>
            <?php if ($layout === 'slider_show' && !empty($show_pagination)): ?>
                <div class="swiper-pagination"></div>
                <?php if (!empty($show_arrows)): ?>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ($layout === 'slider_show' && !empty($show_thumbs)): ?>
            <div class="swiper property-gallery__thumbs">
                <div class="swiper-wrapper">
                    <?php foreach ($images as $image): ?>
                        <div class="swiper-slide">
                            <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php elseif ($layout === 'light_gallery') : ?>
    <div class="property-gallery property-gallery--light property-gallery--preset-<?php echo esc_attr($style_preset); ?>" data-homlity-gallery="light">
        <?php foreach ($images as $image): ?>
            <a class="property-gallery__item property-gallery__item--light" href="<?php echo esc_url($image['full']); ?>">
                <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
            </a>
        <?php endforeach; ?>
    </div>
<?php elseif ($galleryMode === 'owl_gallery') : ?>
    <div class="property-gallery property-gallery--owl property-gallery--preset-<?php echo esc_attr($style_preset); ?>" data-homlity-gallery="owl">
        <div class="property-gallery__track owl-carousel">
            <?php foreach ($images as $image): ?>
                <figure class="property-gallery__slide">
                    <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
<?php else : ?>
    <div class="property-gallery property-gallery--light property-gallery--preset-<?php echo esc_attr($style_preset); ?>" data-homlity-gallery="light">
        <?php foreach ($images as $image): ?>
            <a class="property-gallery__item property-gallery__item--light" href="<?php echo esc_url($image['full']); ?>">
                <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
