<?php
/**
 * Static context registry for the "unavailable property" page.
 *
 * PropertyUnavailableService populates this before rendering any template.
 * The shortcodes [homlity_unavailable_notice],
 * [homlity_unavailable_similar_properties] and
 * [homlity_unavailable_search_context] read from here, so any Elementor page
 * configured as the "unavailable template" can access the retired property's
 * data without relying on loose globals.
 *
 * Usage – service side (called automatically):
 *   UnavailablePropertyContext::setPropertyId($propertyId);
 *   UnavailablePropertyContext::setRecoveryContext($ctx); // recovery mode only
 *
 * Usage – shortcode / template side:
 *   $id  = UnavailablePropertyContext::getPropertyId();
 *   $ctx = UnavailablePropertyContext::getRecoveryContext();
 *   if (UnavailablePropertyContext::isActive()) { ... }
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

class UnavailablePropertyContext
{
    /** @var int  Post ID of the retired/unavailable property (0 when not set). */
    private static int $propertyId = 0;

    /** @var bool Whether the unavailable-property context is active for the request. */
    private static bool $active = false;

    /**
     * Full recovery context produced by RetiredPropertyRecoveryService.
     * Empty array when recovery mode is disabled or context not yet set.
     *
     * @var array<string,mixed>
     */
    private static array $recoveryContext = [];

    // ── Setters ───────────────────────────────────────────────────────────────

    public static function setPropertyId(int $id): void
    {
        self::$propertyId = $id;
        self::$active = true;
    }

    public static function activate(): void
    {
        self::$active = true;
    }

    /**
     * @param array<string,mixed> $ctx
     */
    public static function setRecoveryContext(array $ctx): void
    {
        self::$recoveryContext = $ctx;
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public static function getPropertyId(): int
    {
        return self::$propertyId;
    }

    /**
     * @return array<string,mixed>
     */
    public static function getRecoveryContext(): array
    {
        return self::$recoveryContext;
    }

    /**
     * Returns TRUE when a context has been set for the current request.
     */
    public static function isActive(): bool
    {
        return self::$active;
    }

    /**
     * Reset all state. Intended for unit-test teardowns.
     */
    public static function reset(): void
    {
        self::$propertyId      = 0;
        self::$recoveryContext = [];
        self::$active          = false;
    }
}
