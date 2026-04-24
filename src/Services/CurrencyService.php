<?php
/**
 * Currency helpers for formatting and conversion.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class CurrencyService implements ServiceInterface
{
    private string $settingsOption = HOMLITY_PLUGIN_SETTINGS_OPTION;

    public function register(): void
    {
        add_filter('homlity_plugin_supported_currencies', [$this, 'supportedCurrencies']);
        add_filter('homlity_plugin_format_price', [$this, 'formatPrice'], 10, 3);
        add_filter('homlity_plugin_convert_price', [$this, 'convertPrice'], 10, 3);
    }

    public function supportedCurrencies($currencies = []): array
    {
        if (!is_array($currencies) || empty($currencies)) {
            $currencies = [
                'USD',
                'EUR',
                'GBP',
                'COP',
                'MXN',
                'CLP',
                'ARS',
                'BRL',
                'PEN',
                'CAD',
                'AUD',
                'NZD',
                'CHF',
                'JPY',
                'CNY',
            ];
        }
        return array_values(array_unique(array_filter(array_map('strtoupper', $currencies))));
    }

    public function baseCurrency(): string
    {
        $settings = get_option($this->settingsOption, ['base_currency' => 'USD']);
        $base = strtoupper(sanitize_text_field($settings['base_currency'] ?? 'USD'));
        $allowed = \homlity_plugin_apply_filters('homlity_plugin_supported_currencies', null, []);
        $looksLikeCurrency = (bool) preg_match('/^[A-Z]{3,5}$/', $base);
        return (in_array($base, $allowed, true) || $looksLikeCurrency) ? $base : 'USD';
    }

    public function formatPrice($price, ?string $currency = null, ?string $locale = null): string
    {
        $currency = $currency ?: $this->baseCurrency();
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'COP' => '$',
            'MXN' => '$',
            'CLP' => '$',
        ];
        $symbol = $symbols[$currency] ?? '';

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale ?: get_locale(), \NumberFormatter::CURRENCY);
            return $formatter->formatCurrency((float) $price, $currency) ?: $symbol . number_format((float) $price, 2);
        }

        return $symbol . number_format((float) $price, 2);
    }

    public function convertPrice($price, ?string $from = null, ?string $to = null): float
    {
        $from = $from ?: $this->baseCurrency();
        $to = $to ?: $this->baseCurrency();

        if ($from === $to) {
            return (float) $price;
        }

        $rates = \homlity_plugin_apply_filters('homlity_plugin_exchange_rates', null, [
            'USD' => 1.0,
            'EUR' => 0.92,
            'GBP' => 0.80,
            'COP' => 3800.0,
            'MXN' => 17.0,
            'CLP' => 950.0,
        ]);

        if (!isset($rates[$from], $rates[$to]) || $rates[$from] <= 0) {
            return (float) $price;
        }

        $usdValue = (float) $price / $rates[$from];
        return $usdValue * $rates[$to];
    }
}
