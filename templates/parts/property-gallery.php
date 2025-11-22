<?php
/**
 * Property gallery component.
 * Overridable at plugin-inmobiliario/parts/property-gallery.php
 *
 * Expected args: $post_id (int)
 */

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$images = [];
$metaGallery = get_post_meta($post_id, '_property_gallery', true);
$galleryIds = array_filter(array_map('absint', explode(',', (string) $metaGallery)));

if ($galleryIds) {
    foreach ($galleryIds as $attachmentId) {
        $url = wp_get_attachment_image_url($attachmentId, 'large');
        if ($url && !in_array($url, $images, true)) {
            $images[] = $url;
        }
    }
}

if (!$images && has_post_thumbnail($post_id)) {
    $images[] = get_the_post_thumbnail_url($post_id, 'large');
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
        $url = wp_get_attachment_image_url($attachmentId, 'large');
        if ($url && !in_array($url, $images, true)) {
            $images[] = $url;
        }
    }
}
?>
<div class="property-gallery">
    <?php foreach ($images as $imageUrl): ?>
        <figure class="property-gallery__item">
            <img src="<?php echo esc_url($imageUrl); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>">
        </figure>
    <?php endforeach; ?>
</div>
