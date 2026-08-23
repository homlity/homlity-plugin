<?php

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Models;

use Homlity\PluginInmobiliario\Services\CurrencyService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * An amount together with its ISO-4217 currency code.
 *
 * Homlity stores prices as free-form strings in post meta; this object is the
 * stable read model. It is immutable and always carries a currency, falling
 * back to the site's base currency when the stored value has none.
 *
 * @since 2.8.0
 */
final class Money
{
    private float $amount;
    private string $currency;

    /**
     * @since 2.8.0
     *
     * @param float  $amount   Amount, in major units.
     * @param string $currency ISO-4217 code. Empty falls back to the site default.
     */
    public function __construct(float $amount, string $currency = '')
    {
        $currency = strtoupper(trim($currency));

        $this->amount   = $amount;
        $this->currency = $currency !== '' ? $currency : CurrencyService::DEFAULT_CURRENCY;
    }

    /**
     * Build from raw meta values, or null when there is no usable amount.
     *
     * @since 2.8.0
     *
     * @param mixed  $amount   Raw stored amount.
     * @param mixed  $currency Raw stored currency code.
     */
    public static function fromMeta($amount, $currency): ?self
    {
        if ($amount === null || $amount === '' || is_array($amount)) {
            return null;
        }

        $normalized = self::parseAmount((string) $amount);
        if ($normalized === null || $normalized <= 0.0) {
            return null;
        }

        return new self($normalized, is_scalar($currency) ? (string) $currency : '');
    }

    /**
     * Read an amount written in any of the notations a CRM may send.
     *
     * `450000000`, `$ 2.500.000`, `2,500,000.00` and `1.234,56` all mean what
     * a person reading them would think they mean. The rule that does the work
     * is the last one: a lone separator followed by exactly three digits is a
     * thousands separator, because a price with three decimals does not exist
     * but a price written as `2.500` does.
     *
     * Returns null when the string carries no digits at all.
     */
    private static function parseAmount(string $raw): ?float
    {
        $negative = str_starts_with(ltrim($raw), '-');
        $digits   = preg_replace('/[^0-9.,]/', '', $raw) ?? '';

        if ($digits === '' || preg_match('/[0-9]/', $digits) !== 1) {
            return null;
        }

        $lastDot   = strrpos($digits, '.');
        $lastComma = strrpos($digits, ',');

        if ($lastDot !== false && $lastComma !== false) {
            // Both present: the rightmost one is the decimal separator.
            $decimalAt = max($lastDot, $lastComma);
        } elseif ($lastDot !== false || $lastComma !== false) {
            $decimalAt = $lastDot !== false ? $lastDot : $lastComma;
            $separator = $digits[$decimalAt];

            $isThousands = substr_count($digits, $separator) > 1
                || (strlen($digits) - $decimalAt - 1) === 3;

            if ($isThousands) {
                $decimalAt = null;
            }
        } else {
            $decimalAt = null;
        }

        if ($decimalAt === null) {
            $value = (float) preg_replace('/[.,]/', '', $digits);
        } else {
            $integerPart  = preg_replace('/[.,]/', '', substr($digits, 0, $decimalAt));
            $fractionPart = preg_replace('/[.,]/', '', substr($digits, $decimalAt + 1));
            $value        = (float) ($integerPart . '.' . ($fractionPart !== '' ? $fractionPart : '0'));
        }

        return $negative ? -$value : $value;
    }

    /** @since 2.8.0 */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /** @since 2.8.0 */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * The amount rendered with the site's price formatting rules.
     *
     * @since 2.8.0
     */
    public function getFormatted(): string
    {
        return (new CurrencyService())->formatPrice($this->amount, $this->currency);
    }

    /**
     * @since 2.8.0
     *
     * @return array{amount: float, currency: string, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'amount'    => $this->amount,
            'currency'  => $this->currency,
            'formatted' => $this->getFormatted(),
        ];
    }

    /** @since 2.8.0 */
    public function __toString(): string
    {
        return $this->getFormatted();
    }
}
