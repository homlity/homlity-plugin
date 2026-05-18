<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php
/**
 * Property summary component.
 * Overridable at homlity-plugin/parts/property-summary.php
 *
 * Expected args: $post_id (int)
 */

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$content = apply_filters('the_content', get_post_field('post_content', $post_id));

if (empty($content)) {
    return;
}
?>
<div class="property-summary">
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
