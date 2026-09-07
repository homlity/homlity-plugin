<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if (!defined('ABSPATH')) {
    exit;
}

/** Una foto suelta del inmueble para el widget de imagen. */
class PropertyImageTag extends Data_Tag
{
    use ResolvesProperty;

    public function get_name(): string
    {
        return 'homlity-property-image';
    }

    public function get_title(): string
    {
        return __('Inmueble: imagen', 'homlity-real-estate');
    }

    public function get_categories(): array
    {
        return [TagsModule::IMAGE_CATEGORY];
    }

    protected function register_controls(): void
    {
        $this->add_control('image', [
            'label' => __('Imagen', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'options' => PropertyFields::imageChoices(),
            'default' => 'featured',
        ]);

        $this->register_property_control();
    }

    /**
     * @param array<string,mixed> $options
     * @return array{id:int, url:string}
     */
    protected function get_value(array $options = []): array
    {
        return PropertyFields::image($this->resolved_property_id(), (string) $this->get_settings('image'));
    }
}
