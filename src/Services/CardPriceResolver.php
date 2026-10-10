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
            self::resolve($raw, $baseOperationId, $opSlugAndName, self::operationLabelsForTerm($term instanceof \WP_Term ? $term : null)),
            $postId
        );
    }

    /** @return array{rent:bool,sale:bool} */
    public static function operationTypes(int $baseOperationId, string $opSlugAndName): array
    {
        // Identity remains authoritative even when the display name changes.
        if ($baseOperationId > 0) {
            return ['rent' => in_array($baseOperationId, [1, 3], true), 'sale' => in_array($baseOperationId, [2, 3], true)];
        }
        $operation = mb_strtolower($opSlugAndName);
        return [
            'rent' => in_array($baseOperationId, [1, 3], true)
                || preg_match('/(?:^|[\s\/_-])(?:arriendo|arrendamiento|alquiler|renta|rent)(?:$|[\s\/_-])/u', $operation) === 1,
            'sale' => in_array($baseOperationId, [2, 3], true) || strpos($operation, 'venta') !== false,
        ];
    }

    /**
     * Business identities choose the price; editable term names choose its label.
     * Mixed operations use the configured names of the individual operations.
     *
     * @return array{rent:string,sale:string}
     */
    public static function operationLabelsForTerm(?\WP_Term $term): array
    {
        $labels = [];
        $configured = [];
        foreach (['rent' => 1, 'sale' => 2] as $type => $baseId) {
            $baseTerm = PropertyTaxonomies::baseOperationTermById($baseId);
            if ($baseTerm instanceof \WP_Term) {
                $baseTerm = PropertyTaxonomies::canonicalOperationTerm($baseTerm);
            }
            $labels[$type] = $baseTerm instanceof \WP_Term && trim($baseTerm->name) !== ''
                ? $baseTerm->name
                : PropertyTaxonomies::baseOperations()[$baseId]['name'];
            $configured[$type] = $labels[$type] !== PropertyTaxonomies::baseOperations()[$baseId]['name'];
        }

        if ($term instanceof \WP_Term && trim($term->name) !== '') {
            $types = self::operationTypes(PropertyTaxonomies::baseOperationIdForTerm($term), $term->slug . ' ' . $term->name);
            if ($types['rent'] !== $types['sale']) {
                $labels[$types['rent'] ? 'rent' : 'sale'] = $term->name;
            } elseif ($types['rent'] && $types['sale']) {
                foreach (preg_split('/\s*(?:\/|\by\b|&)\s*/iu', $term->name) ?: [] as $part) {
                    $partTypes = self::operationTypes(0, $part);
                    if ($partTypes['rent'] !== $partTypes['sale']) {
                        $type = $partTypes['rent'] ? 'rent' : 'sale';
                        if (!$configured[$type]) {
                            $labels[$type] = $part;
                        }
                    }
                }
            }
        }

        return $labels;
    }

    /**
     * @param array<string,mixed> $raw Values keyed by the logical meta names.
     * @param array<string,string> $labels Display names keyed by rent/sale.
     * @return array<int,array{type:string,label:string,amount:string,admin:?string,admin_included:bool}>
     */
    public static function resolve(array $raw, int $baseOperationId, string $opSlugAndName, array $labels = []): array
    {
        $types = self::operationTypes($baseOperationId, $opSlugAndName);
        $unknown = !$types['sale'] && !$types['rent'];
        $currencyService = new CurrencyService();
        $lines = [];

        foreach ($types as $type => $enabled) {
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
                'label' => $labels[$type] ?? ($type === 'rent' ? __('Arriendo', 'homlity-real-estate') : __('Venta', 'homlity-real-estate')),
                'amount' => (string) homlity_plugin_apply_filters('homlity_plugin_format_price', null, $amount, $currency),
                'admin' => $admin,
                'admin_included' => $type === 'rent' && (bool) ($raw['admin_included'] ?? false),
            ];
        }

        return $lines;
    }
}
