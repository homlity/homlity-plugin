<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves card prices in operation order, with administration on rent only.
 */
final class CardPriceResolver
{
    /** @return array<int,array{type:string,label:string,amount:string,admin:?string,admin_included:bool}> */
    public static function forPost(int $postId): array
    {
        $meta = (new PropertyPostType())->metaKeys();
        $raw = [];
        foreach (['price_sale', 'currency_sale', 'price_rent', 'currency_rent', 'price_admin', 'currency_admin', 'admin_included'] as $key) {
            $raw[$key] = get_post_meta($postId, $meta[$key], true);
        }

        $operations = wp_get_post_terms($postId, PropertyTaxonomies::TAXONOMY_OPERATION);
        $term = (!is_wp_error($operations) && !empty($operations)) ? $operations[0] : null;
        $baseOperationId = $term instanceof \WP_Term ? PropertyTaxonomies::baseOperationIdForTerm($term) : 0;
        $opSlugAndName = $term instanceof \WP_Term ? $term->slug . ' ' . $term->name : '';

        return homlity_plugin_apply_filters(
            'homlity_plugin_card_price_lines',
            null,
            self::resolve($raw, $baseOperationId, $opSlugAndName),
            $postId
        );
    }

    /**
     * @param array<string,mixed> $raw Values keyed by the logical meta names.
     * @return array<int,array{type:string,label:string,amount:string,admin:?string,admin_included:bool}>
     */
    public static function resolve(array $raw, int $baseOperationId, string $opSlugAndName): array
    {
        $operation = mb_strtolower($opSlugAndName);
        $isSale = in_array($baseOperationId, [2, 3], true) || strpos($operation, 'venta') !== false;
        $isRent = in_array($baseOperationId, [1, 3], true) || strpos($operation, 'arriendo') !== false;
        $unknown = !$isSale && !$isRent;
        $currencyService = new CurrencyService();
        $lines = [];

        foreach (['rent' => $isRent, 'sale' => $isSale] as $type => $enabled) {
            $amount = $raw['price_' . $type] ?? '';
            if ((!$enabled && !$unknown) || $amount === '' || (float) $amount <= 0) {
                continue;
            }

            $currency = ($raw['currency_' . $type] ?? '') ?: $currencyService->baseCurrency();
            $admin = null;
            if ($type === 'rent' && (float) ($raw['price_admin'] ?? 0) > 0) {
                $adminCurrency = ($raw['currency_admin'] ?? '') ?: $currency;
                $admin = (string) homlity_plugin_apply_filters('homlity_plugin_format_price', null, $raw['price_admin'], $adminCurrency);
            }

            $lines[] = [
                'type' => $type,
                'label' => $type === 'rent' ? __('Arriendo', 'homlity-real-estate') : __('Venta', 'homlity-real-estate'),
                'amount' => (string) homlity_plugin_apply_filters('homlity_plugin_format_price', null, $amount, $currency),
                'admin' => $admin,
                'admin_included' => $type === 'rent' && (bool) ($raw['admin_included'] ?? false),
            ];
        }

        return $lines;
    }
}
