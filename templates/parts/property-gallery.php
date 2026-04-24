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
<?php if ($galleryMode === 'owl_gallery') : ?>
    <div class="property-gallery property-gallery--owl" data-homlity-gallery="owl">
        <div class="property-gallery__track owl-carousel">
            <?php foreach ($images as $image): ?>
                <figure class="property-gallery__slide">
                    <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
<?php else : ?>
    <div class="property-gallery property-gallery--light" data-homlity-gallery="light">
        <?php foreach ($images as $image): ?>
            <a class="property-gallery__item property-gallery__item--light" href="<?php echo esc_url($image['full']); ?>">
                <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
