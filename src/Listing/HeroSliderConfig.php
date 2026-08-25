<?php
/**
 * Builder-agnostic configuration for the hero slider.
 *
 * Mirrors what ListingConfig does for the listing: the page-builder widget
 * hands over its raw settings, this object normalises them, and everything
 * downstream — the template and the query — only sees sane values. Keeping it
 * out of the widget is what makes the behaviour testable, since a widget class
 * cannot be instantiated without the page builder loaded.
 */

namespace Homlity\PluginInmobiliario\Listing;

use Homlity\PluginInmobiliario\Services\PropertySearchService;

if (!defined('ABSPATH')) {
    exit;
}

final class HeroSliderConfig
{
    /** Layouts that show one property image at a time. */
    public const IMAGE_LAYOUTS = ['hero', 'split'];

    private const LAYOUTS = ['hero', 'split', 'cards'];
    private const EFFECTS = ['slide', 'fade'];
    private const PAGINATION_TYPES = ['bullets', 'fraction', 'progressbar'];

    /** Slides the query may return; a hero is not a catalogue. */
    private const MAX_SLIDES = 30;

    /** @var array<string,mixed> */
    private array $settings;

    /** @param array<string,mixed> $settings */
    private function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param array<string,mixed> $settings Raw widget settings.
     */
    public static function fromBuilderSettings(array $settings): self
    {
        return new self($settings);
    }

    /** @param array<string,mixed> $settings */
    public static function fromElementor(array $settings): self
    {
        return self::fromBuilderSettings($settings);
    }

    // ── Layout ────────────────────────────────────────────────────────────

    public function layout(): string
    {
        $layout = (string) ($this->settings['layout'] ?? 'hero');

        return in_array($layout, self::LAYOUTS, true) ? $layout : 'hero';
    }

    /** Whether the layout renders the plugin's property card as the slide. */
    public function isCardsLayout(): bool
    {
        return $this->layout() === 'cards';
    }

    /**
     * Ken Burns pans the property photo, so it means nothing in the card
     * carousel, where the card owns its own media.
     */
    public function kenBurnsEnabled(): bool
    {
        return $this->flag('kenburns')
            && in_array($this->layout(), self::IMAGE_LAYOUTS, true);
    }

    // ── Template options ──────────────────────────────────────────────────

    /**
     * The options array the property-hero-slider.php template reads.
     *
     * @return array<string,mixed>
     */
    public function templateOptions(): array
    {
        $cards = $this->isCardsLayout();

        return [
            'layout'            => $this->layout(),
            // Only the card carousel shows several properties at once; the
            // image layouts are always one slide wide whatever is stored.
            'slides_desktop'    => $cards ? $this->slides('slides_desktop', 3) : 1,
            'slides_tablet'     => $cards ? $this->slides('slides_tablet', 2) : 1,
            'slides_mobile'     => $cards ? $this->slides('slides_mobile', 1) : 1,
            'autoplay'          => $this->flag('autoplay', true),
            'autoplay_delay'    => max(1000, $this->int('autoplay_delay', 5000)),
            'pause_on_hover'    => $this->flag('pause_on_hover', true),
            'loop'              => $this->flag('loop', true),
            'effect'            => $this->choice('effect', self::EFFECTS, 'slide'),
            'speed'             => max(100, $this->int('speed', 600)),
            'show_arrows'       => $this->flag('show_arrows', true),
            'show_pagination'   => $this->flag('show_pagination', true),
            'pagination_type'   => $this->choice('pagination_type', self::PAGINATION_TYPES, 'bullets'),
            'kenburns'          => $this->kenBurnsEnabled(),
            'show_operation'    => $this->flag('show_operation', true),
            'show_title'        => $this->flag('show_title', true),
            'show_location'     => $this->flag('show_location', true),
            'show_price'        => $this->flag('show_price', true),
            'show_features'     => $this->flag('show_features', true),
            'show_excerpt'      => $this->flag('show_excerpt'),
            'show_code'         => $this->flag('show_code'),
            'excerpt_words'     => max(1, $this->int('excerpt_words', 22)),
            'feature_area'      => $this->flag('feature_area', true),
            'feature_bedrooms'  => $this->flag('feature_bedrooms', true),
            'feature_bathrooms' => $this->flag('feature_bathrooms', true),
            'feature_parking'   => $this->flag('feature_parking', true),
            'feature_icon_area'      => $this->icon('feature_icon_area'),
            'feature_icon_bedrooms'  => $this->icon('feature_icon_bedrooms'),
            'feature_icon_bathrooms' => $this->icon('feature_icon_bathrooms'),
            'feature_icon_parking'   => $this->icon('feature_icon_parking'),
            'location_icon'     => $this->icon('location_icon'),
            'show_button'       => $this->flag('show_button', true),
            'button_label'      => $this->text('button_label'),
            'button_icon'       => $this->icon('button_icon'),
            'show_whatsapp'     => $this->flag('show_whatsapp'),
            'whatsapp_label'    => $this->text('whatsapp_label'),
            'whatsapp_icon'     => $this->icon('whatsapp_icon'),
            'link_whole_slide'  => $this->flag('link_whole_slide', true),
            'link_new_tab'      => $this->flag('link_new_tab'),
            'empty_message'     => $this->text('empty_message'),
        ];
    }

    // ── Query ─────────────────────────────────────────────────────────────

    public function orderby(): string
    {
        return (string) ($this->settings['orderby'] ?? 'date');
    }

    /**
     * Params for PropertySearchService::buildQueryArgs().
     *
     * @return array<string,mixed>
     */
    public function queryParams(): array
    {
        return [
            'per_page'         => max(1, min(self::MAX_SLIDES, $this->int('posts_per_page', 6))),
            'page'             => 1,
            'orderby'          => $this->orderby(),
            'query_mode'       => 'custom',
            'featured'         => $this->flag('featured_only'),
            'preset_operation' => $this->int('preset_operation', 0),
            'preset_type'      => $this->int('preset_type', 0),
            'preset_category'  => $this->int('preset_category', 0),
            'preset_city'      => $this->int('preset_city', 0),
            'preset_tag'       => $this->int('preset_tag', 0),
        ];
    }

    /**
     * The final WP_Query arguments.
     *
     * @return array<string,mixed>
     */
    public function queryArgs(?PropertySearchService $search = null): array
    {
        $args = ($search ?? new PropertySearchService())->buildQueryArgs($this->queryParams());

        // A hero only ever shows its first page and never paginates, so the
        // found-rows count is dead weight on every request.
        $args['no_found_rows'] = true;

        // 'rand' is not part of the shared sort vocabulary, so it is applied
        // once the service has finished building the arguments.
        if ($this->orderby() === 'rand') {
            $args['orderby'] = 'rand';
            unset($args['meta_key'], $args['order']);
        }

        return $args;
    }

    // ── Normalisation helpers ─────────────────────────────────────────────

    /**
     * Page builders store switchers as 'yes' or an empty string. A key that
     * was never saved falls back to the control's own default, which is what
     * a widget dropped on the page and left untouched must render as.
     */
    private function flag(string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $this->settings)) {
            return $default;
        }

        return $this->settings[$key] === 'yes';
    }

    private function int(string $key, int $default): int
    {
        if (!array_key_exists($key, $this->settings) || $this->settings[$key] === '') {
            return $default;
        }

        return (int) $this->settings[$key];
    }

    private function text(string $key): string
    {
        return is_scalar($this->settings[$key] ?? null)
            ? (string) $this->settings[$key]
            : '';
    }

    private function slides(string $key, int $default): int
    {
        return max(1, $this->int($key, $default));
    }

    /**
     * @param array<int,string> $allowed
     */
    private function choice(string $key, array $allowed, string $default): string
    {
        $value = (string) ($this->settings[$key] ?? $default);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Icon controls arrive as ['value' => ..., 'library' => ...]; anything
     * else is treated as "no icon" rather than passed on to the renderer.
     *
     * @return array<string,mixed>
     */
    private function icon(string $key): array
    {
        $icon = $this->settings[$key] ?? [];

        return is_array($icon) ? $icon : [];
    }
}
