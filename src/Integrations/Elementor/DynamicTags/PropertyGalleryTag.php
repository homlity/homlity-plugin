<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if (!defined('ABSPATH')) {
    exit;
}

/** Toda la galería del inmueble para los widgets de galería y carrusel. */
class PropertyGalleryTag extends Data_Tag
{
    use ResolvesProperty;

    public function get_name(): string
    {
        return 'homlity-property-gallery';
    }

    public function get_title(): string
    {
        return __('Inmueble: galería', 'homlity-real-estate');
    }

    public function get_categories(): array
    {
        return [TagsModule::GALLERY_CATEGORY];
    }

    protected function register_controls(): void
    {
        $this->register_property_control();
    }

    /**
     * @param array<string,mixed> $options
     * @return array<int,array{id:int, url:string}>
     */
    protected function get_value(array $options = []): array
    {
        return PropertyFields::gallery($this->resolved_property_id());
    }
}
