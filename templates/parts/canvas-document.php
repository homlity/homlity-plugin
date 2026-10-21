<?php
/** Documento canvas compartido. @since 2.9.17 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <?php wp_head(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php $content(); ?>
<?php wp_footer(); ?>
</body>
</html>
