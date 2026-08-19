<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if (!defined('ABSPATH')) {
    exit;
}

/** Foto del asesor, con la misma preferencia que usa el resto del plugin. */
class AgentImageTag extends Data_Tag
{
    use ResolvesAgent;

    public function get_name(): string
    {
        return 'homlity-agent-photo';
    }

    public function get_title(): string
    {
        return __('Asesor: foto', 'homlity-real-estate');
    }

    public function get_categories(): array
    {
        return [TagsModule::IMAGE_CATEGORY];
    }

    protected function register_controls(): void
    {
        $this->register_agent_control();
    }

    /**
     * @param array<string,mixed> $options
     * @return array{id:int, url:string}
     */
    protected function get_value(array $options = []): array
    {
        return AgentFields::image($this->resolved_agent());
    }
}
