<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if (!defined('ABSPATH')) {
    exit;
}

/** Enlaces del asesor: perfil, WhatsApp, llamada, correo y sitio web. */
class AgentUrlTag extends Data_Tag
{
    use ResolvesAgent;

    public function get_name(): string
    {
        return 'homlity-agent-url';
    }

    public function get_title(): string
    {
        return __('Asesor: enlace', 'homlity-real-estate');
    }

    public function get_categories(): array
    {
        return [TagsModule::URL_CATEGORY];
    }

    protected function register_controls(): void
    {
        $this->add_control('link', [
            'label' => __('Enlace', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'options' => AgentFields::urlChoices(),
            'default' => 'profile',
        ]);

        $this->register_agent_control();
    }

    /** @param array<string,mixed> $options */
    protected function get_value(array $options = []): string
    {
        return AgentFields::url($this->resolved_agent(), (string) $this->get_settings('link'));
    }
}
