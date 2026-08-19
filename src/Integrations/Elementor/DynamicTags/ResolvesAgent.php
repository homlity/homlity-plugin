<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Controls_Manager;
use Homlity\PluginInmobiliario\Services\AgentProfileService;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lo común a las tres etiquetas del asesor: el grupo del desplegable y el
 * control que permite fijar un asesor concreto.
 */
trait ResolvesAgent
{
    public function get_group(): string
    {
        return 'homlity-real-estate';
    }

    /**
     * El editor de Elementor no está en la página de perfil ni en la ficha de
     * un inmueble: sin este control, montar la plantilla sería a ciegas.
     */
    protected function register_agent_control(): void
    {
        $this->add_control('agent_id', [
            'label' => __('Asesor', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'options' => AgentProfileService::agentChoices(),
            'default' => '',
            'description' => __(
                'Vacío: toma el asesor del perfil o del inmueble que se esté viendo. Elige uno para fijarlo o para previsualizar en el editor.',
                'homlity-real-estate'
            ),
        ]);
    }

    protected function resolved_agent(): ?WP_User
    {
        return AgentFields::resolveAgent($this->get_settings('agent_id'));
    }
}
