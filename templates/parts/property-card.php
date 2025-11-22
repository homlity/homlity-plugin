<?php
/**
 * Property card component.
 * Overridable at plugin-inmobiliario/parts/property-card.php inside theme or child theme.
 *
 * Expected args: $post_id (int)
 */

use Codwelt\PluginInmobiliario\Services\CurrencyService;
use Codwelt\PluginInmobiliario\Services\PropertyPostType;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta = (new PropertyPostType())->metaKeys();
$currencyService = new CurrencyService();

$settings = get_option('plugin_inmobiliario_settings', [
    'listing_fields' => ['price', 'excerpt', 'features', 'whatsapp'],
]);
$listingFields = $settings['listing_fields'] ?? ['price', 'excerpt', 'features', 'whatsapp'];

$price = get_post_meta($post_id, $meta['price_sale'], true);
$currency = get_post_meta($post_id, $meta['currency_sale'], true) ?: $currencyService->baseCurrency();
$area = get_post_meta($post_id, $meta['area'], true);
$bedrooms = get_post_meta($post_id, $meta['bedrooms'], true);
$bathrooms = get_post_meta($post_id, $meta['bathrooms'], true);
$agentPhone = get_post_meta($post_id, $meta['agent_phone'], true);
$agentId = (int) get_post_meta($post_id, $meta['agent_id'], true);
$agentPhoneDigits = $agentPhone ? preg_replace('/\D+/', '', $agentPhone) : '';

$images = [];
if (has_post_thumbnail($post_id)) {
    $images[] = get_the_post_thumbnail_url($post_id, 'medium');
}

$attachments = get_posts([
    'post_type' => 'attachment',
    'posts_per_page' => 3,
    'post_status' => 'inherit',
    'post_parent' => $post_id,
    'post_mime_type' => 'image',
    'orderby' => 'menu_order',
    'fields' => 'ids',
]);

foreach ($attachments as $attachmentId) {
    $url = wp_get_attachment_image_url($attachmentId, 'medium');
    if ($url && !in_array($url, $images, true) && count($images) < 3) {
        $images[] = $url;
    }
}

$whatsAppLink = '';
if ($agentPhoneDigits) {
    $msg = rawurlencode(get_the_title($post_id) . ' - ' . get_permalink($post_id));
    $whatsAppLink = 'https://wa.me/' . $agentPhoneDigits . '?text=' . $msg;
}
?>
<article <?php post_class('property-card', $post_id); ?>>
    <a href="<?php echo esc_url(get_permalink($post_id)); ?>">
        <div class="property-card__gallery">
            <?php foreach (array_slice($images, 0, 3) as $img): ?>
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>">
            <?php endforeach; ?>
        </div>
        <h2 class="property-card__title"><?php echo esc_html(get_the_title($post_id)); ?></h2>
        <?php if (in_array('excerpt', $listingFields, true)): ?>
            <p class="property-card__excerpt">
                <?php echo esc_html(wp_trim_words(get_the_excerpt($post_id), 20)); ?>
            </p>
        <?php endif; ?>
        <?php if (in_array('features', $listingFields, true)): ?>
            <ul class="property-card__features">
                <?php if ($area): ?>
                    <li><?php echo esc_html($area); ?> m²</li>
                <?php endif; ?>
                <?php if ($bedrooms): ?>
                    <li><?php echo esc_html($bedrooms); ?> <?php esc_html_e('Habitaciones', 'plugin-inmobiliario'); ?></li>
                <?php endif; ?>
                <?php if ($bathrooms): ?>
                    <li><?php echo esc_html($bathrooms); ?> <?php esc_html_e('Baños', 'plugin-inmobiliario'); ?></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
        <?php if ($price && in_array('price', $listingFields, true)): ?>
            <p class="property-card__price">
                <?php echo esc_html(apply_filters('plugin_inmobiliario_format_price', $price, $currency)); ?>
            </p>
        <?php endif; ?>
    </a>

    <?php if ($whatsAppLink && in_array('whatsapp', $listingFields, true)): ?>
        <a class="property-card__whatsapp" href="<?php echo esc_url($whatsAppLink); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Contactar por WhatsApp', 'plugin-inmobiliario'); ?>
        </a>
    <?php endif; ?>
</article>
