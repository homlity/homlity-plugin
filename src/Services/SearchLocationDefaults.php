<?php
/**
 * Resolves the agency's base location into the values the search form
 * pre-selects.
 *
 * The location itself is the one already configured under Ajustes → Ubicación
 * base (the same one the property editor pre-fills with). This class only
 * decides whether the search form should adopt it, and translates the stored
 * term IDs into the slugs the filter selects are keyed by.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class SearchLocationDefaults
{
    /** Settings flag that turns the pre-selection on. */
    public const SETTING_KEY = 'preselect_location_in_search';

    /**
     * Location levels, in cascade order, mapped to their taxonomy and to the
     * settings key holding the configured term.
     *
     * @var array<string,array{0:string,1:string}>
     */
    private const LEVELS = [
        'country'      => [PropertyTaxonomies::TAXONOMY_COUNTRY, 'default_country'],
        'state'        => [PropertyTaxonomies::TAXONOMY_STATE, 'default_state'],
        'city'         => [PropertyTaxonomies::TAXONOMY_CITY, 'default_city'],
        'neighborhood' => [PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, 'default_neighborhood'],
    ];

    /**
     * Whether the administrator asked for the base location to be pre-selected
     * in the search form. Off unless explicitly enabled, so sites that already
     * use the base location for the property editor keep their current search.
     */
    public static function isEnabled(): bool
    {
        $settings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);

        return is_array($settings) && !empty($settings[self::SETTING_KEY]);
    }

    /**
     * The configured location as term slugs, keyed by level. Levels with no
     * configured term — or whose term no longer exists — are left out, so a
     * deleted city simply stops being pre-selected instead of selecting
     * nothing and looking broken.
     *
     * @return array<string,string>
     */
    public static function slugs(): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        $settings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            return [];
        }

        $slugs = [];
        foreach (self::LEVELS as $level => [$taxonomy, $settingKey]) {
            $termId = absint($settings[$settingKey] ?? 0);
            if ($termId <= 0) {
                continue;
            }

            $term = get_term($termId, $taxonomy);
            if (!$term instanceof \WP_Term) {
                continue;
            }

            $slugs[$level] = (string) $term->slug;
        }

        return $slugs;
    }

    /**
     * Decides what a location field starts with.
     *
     * The configured default is a suggestion, never an override: it applies
     * only to a request that does not mention the field. Submitting the search
     * form sends every enabled field — empty ones included — so clearing the
     * city and searching keeps it cleared instead of snapping back.
     *
     * @param array<string,string> $slugs           Result of self::slugs().
     * @param string               $level           country|state|city|neighborhood
     * @param bool                 $requestProvided Whether the request carries this field.
     * @param mixed                $currentValue    What the request resolved to.
     * @return mixed
     */
    public static function pick(array $slugs, string $level, bool $requestProvided, $currentValue)
    {
        if ($requestProvided || !isset($slugs[$level])) {
            return $currentValue;
        }

        return $slugs[$level];
    }
}
