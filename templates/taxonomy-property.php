<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php
/**
 * Generic template for property taxonomies (category, type, operation, location).
 * Can be overridden in theme at homlity-real-estate/taxonomy-property.php
 */

use Homlity\PluginInmobiliario\Services\TemplateService;

get_header();
?>
<main class="property-taxonomy">
    <header class="property-taxonomy__header">
        <h1><?php single_term_title(); ?></h1>
        <?php if (term_description()) : ?>
            <p class="property-taxonomy__description"><?php echo term_description(); ?></p>
        <?php endif; ?>
    </header>

    <?php if (have_posts()) : ?>
        <div class="property-taxonomy__grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php TemplateService::includeComponent('property-card.php', ['post_id' => get_the_ID()]); ?>
            <?php endwhile; ?>
        </div>
        <div class="property-taxonomy__pagination">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('No hay inmuebles en esta categoría.', 'homlity-real-estate'); ?></p>
    <?php endif; ?>
</main>

<?php
get_footer();
