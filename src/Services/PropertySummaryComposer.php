<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Services\Ai\LlmsFullGenerator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compone texto factual sin reescribir la descripción recibida del CRM.
 *
 * @since 2.9.16
 */
class PropertySummaryComposer
{
    /**
     * Devuelve un resumen con los datos disponibles y una referencia estable.
     *
     * @since 2.9.16
     */
    public function compose(int $postId): string
    {
        $type = $this->termNames($postId, PropertyTaxonomies::TAXONOMY_TYPE);
        $operation = $this->termNames($postId, PropertyTaxonomies::TAXONOMY_OPERATION);
        $location = implode(', ', array_filter([
            $this->termNames($postId, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD),
            $this->termNames($postId, PropertyTaxonomies::TAXONOMY_CITY),
        ], static fn(string $part): bool => $part !== ''));
        $heading = $type;
        foreach ([$operation, $location] as $part) {
            if ($part !== '') {
                $heading .= ($heading !== '' ? ' en ' : '') . $part;
            }
        }
        $parts = $heading !== '' ? [$heading] : [];
        foreach (['area' => 'm²', 'bedrooms' => 'hab', 'bathrooms' => 'baños'] as $key => $unit) {
            $value = trim((string) get_post_meta($postId, '_property_' . $key, true));
            if (is_numeric($value) && (float) $value > 0) {
                $parts[] = $value . ' ' . $unit;
            }
        }
        $prices = [];
        foreach (['sale' => 'Venta', 'rent' => 'Arriendo'] as $key => $label) {
            $value = (string) get_post_meta($postId, '_property_price_' . $key, true);
            $currency = (string) get_post_meta($postId, '_property_currency_' . $key, true);
            $price = LlmsFullGenerator::formatPrice($value, $currency);
            if ($price !== '') {
                $prices[$label] = $price;
            }
        }
        foreach ($prices as $label => $price) {
            $parts[] = (count($prices) > 1 ? $label . ': ' : '') . $price;
        }
        // El ID mantiene la unicidad en fichas que no tienen código.
        $code = sanitize_text_field((string) get_post_meta($postId, '_property_code', true));
        $parts[] = 'Ref. ' . ($code !== '' ? $code : (string) $postId);
        return implode(' · ', $parts);
    }

    private function termNames(int $postId, string $taxonomy): string
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (!$terms || is_wp_error($terms)) {
            return '';
        }
        return implode(' / ', array_unique(array_map(static fn($term): string => sanitize_text_field($term->name), $terms)));
    }
}
