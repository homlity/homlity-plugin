<?php
if (!isset($post_id)) {
    $post_id = get_the_ID();
}
$tag = isset($title_tag) ? strtolower((string) $title_tag) : 'h1';
$allowed = ['h1','h2','h3','h4','h5','h6','p','span','div'];
if (!in_array($tag, $allowed, true)) {
    $tag = 'h1';
}
?>
<<?php echo esc_attr($tag); ?> class="property-title-widget"><?php echo esc_html(get_the_title($post_id)); ?></<?php echo esc_attr($tag); ?>>
