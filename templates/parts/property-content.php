<?php
if (!isset($post_id)) {
    $post_id = get_the_ID();
}
$tag = isset($content_tag) ? strtolower((string) $content_tag) : 'div';
$allowed = ['div','section','article','p'];
if (!in_array($tag, $allowed, true)) {
    $tag = 'div';
}
?>
<<?php echo esc_attr($tag); ?> class="property-content-widget">
    <?php echo apply_filters('the_content', get_post_field('post_content', $post_id)); ?>
</<?php echo esc_attr($tag); ?>>
