<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if (!defined('ABSPATH')) {
    exit;
}

/** Datos de texto del asesor: nombre, cargo, teléfono, biografía… */
class AgentTextTag extends Tag
{
    use ResolvesAgent;

    public function get_name(): string
    {
        return 'homlity-agent-text';
    }

    public function get_title(): string
    {
        return __('Asesor: dato', 'homlity-real-estate');
    }

    public function get_categories(): array
    {
        // También en la categoría numérica para que el número de inmuebles se
        // pueda enchufar a un contador, no solo a un texto.
        return [TagsModule::TEXT_CATEGORY, TagsModule::NUMBER_CATEGORY];
    }

    protected function register_controls(): void
    {
        $this->add_control('field', [
            'label' => __('Dato', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'options' => AgentFields::textChoices(),
            'default' => 'name',
        ]);

        $this->register_agent_control();
    }

    protected function render(): void
    {
        echo esc_html(AgentFields::text($this->resolved_agent(), (string) $this->get_settings('field')));
    }
}
