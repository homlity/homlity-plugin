<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php
if (!isset($post_id)) {
    $post_id = get_the_ID();
}
?>
<div class="property-title-widget"><?php echo esc_html(get_the_title($post_id)); ?></div>
