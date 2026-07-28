<?php

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Border;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Box_Shadow;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Complete Divi controls shared by widgets that narrate property text.
 */
trait PropertyAudioPlayerControlsTrait
{
    protected function registerAudioPlayerContentControls(): void
    {
        $this->add_control('show_audio_player', [
            'label' => __('Mostrar reproductor de audio', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'no',
        ]);
        $this->add_control('audio_player_heading', [
            'label' => __('Título (línea 1)', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Escucha', 'homlity-real-estate'),
            'condition' => ['show_audio_player' => 'yes'],
        ]);
        $this->add_control('audio_player_label', [
            'label' => __('Subtítulo (línea 2)', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('este inmueble', 'homlity-real-estate'),
            'condition' => ['show_audio_player' => 'yes'],
        ]);
        $this->add_control('audio_default_rate', [
            'label' => __('Velocidad inicial', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => '1',
            'options' => [
                '0.75' => '0.75x',
                '1' => '1x',
                '1.25' => '1.25x',
                '1.5' => '1.5x',
                '1.75' => '1.75x',
                '2' => '2x',
            ],
            'condition' => ['show_audio_player' => 'yes'],
        ]);
        $this->add_control('audio_voice', [
            'label' => __('Voz del narrador', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'auto',
            'options' => [
                'auto' => __('Automática (recomendada)', 'homlity-real-estate'),
                'female' => __('Femenina', 'homlity-real-estate'),
                'male' => __('Masculina', 'homlity-real-estate'),
                'es-co' => __('Español (Colombia)', 'homlity-real-estate'),
                'es-es' => __('Español (España)', 'homlity-real-estate'),
                'es-mx' => __('Español (México)', 'homlity-real-estate'),
            ],
            'condition' => ['show_audio_player' => 'yes'],
        ]);
    }

    protected function registerAudioPlayerStyleControls(): void
    {
        $this->start_controls_section('style_audio_player', [
            'label' => __('Estilos reproductor', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_audio_player' => 'yes'],
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'audio_player_typography',
            'selector' => '{{WRAPPER}} .property-content-audio-bar, {{WRAPPER}} .property-content-audio-bar__heading, {{WRAPPER}} .property-content-audio-bar__sublabel, {{WRAPPER}} .property-content-audio-bar__time, {{WRAPPER}} .property-content-audio-bar__rate',
        ]);
        $this->add_responsive_control('audio_player_padding', [
            'label' => __('Padding', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem'],
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('audio_player_radius', [
            'label' => __('Radio de borde', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'audio_player_border',
            'selector' => '{{WRAPPER}} .property-content-audio-bar',
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'audio_player_shadow',
            'selector' => '{{WRAPPER}} .property-content-audio-bar',
        ]);
        $this->add_control('audio_player_background', [
            'label' => __('Fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_text_color', [
            'label' => __('Color texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar, {{WRAPPER}} .property-content-audio-bar__heading, {{WRAPPER}} .property-content-audio-bar__sublabel, {{WRAPPER}} .property-content-audio-bar__time, {{WRAPPER}} .property-content-audio-bar__rate' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_heading_color', [
            'label' => __('Color título/subtítulo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__heading, {{WRAPPER}} .property-content-audio-bar__sublabel' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_time_color', [
            'label' => __('Color tiempo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__time' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_rate_color', [
            'label' => __('Color selector velocidad', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__rate, {{WRAPPER}} .property-content-audio-bar__chevron' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_rate_bg', [
            'label' => __('Fondo selector velocidad', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__rate' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_rate_border', [
            'label' => __('Borde selector velocidad', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__rate' => 'border-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_button_heading', [
            'label' => __('Botón reproducir/pausa', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->start_controls_tabs('audio_player_button_tabs');
        $this->start_controls_tab('audio_player_button_normal', [
            'label' => __('Normal', 'homlity-real-estate'),
        ]);
        $this->add_control('audio_player_button_color', [
            'label' => __('Color icono', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__play-btn' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_button_bg', [
            'label' => __('Fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__play-btn' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_button_border', [
            'label' => __('Borde', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__play-btn' => 'border-color: {{VALUE}};',
            ],
        ]);
        $this->end_controls_tab();
        $this->start_controls_tab('audio_player_button_hover', [
            'label' => __('Hover/Activo', 'homlity-real-estate'),
        ]);
        $this->add_control('audio_player_button_color_hover', [
            'label' => __('Color icono', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__play-btn:hover, {{WRAPPER}} .property-content-audio-bar__play-btn.is-playing' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_button_bg_hover', [
            'label' => __('Fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__play-btn:hover, {{WRAPPER}} .property-content-audio-bar__play-btn.is-playing' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_button_border_hover', [
            'label' => __('Borde', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__play-btn:hover, {{WRAPPER}} .property-content-audio-bar__play-btn.is-playing' => 'border-color: {{VALUE}};',
            ],
        ]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('audio_player_progress_heading', [
            'label' => __('Barra de progreso', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_control('audio_player_track_color', [
            'label' => __('Color pista', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__track' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_progress_color', [
            'label' => __('Color progreso', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__progress' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('audio_player_thumb_color', [
            'label' => __('Color indicador', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-content-audio-bar__thumb' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->end_controls_section();
    }

    /** @return array<string, mixed> */
    protected function audioPlayerTemplateArgs(array $settings): array
    {
        return [
            'show_audio_player' => ($settings['show_audio_player'] ?? 'no') === 'yes',
            'audio_player_heading' => (string) ($settings['audio_player_heading'] ?? __('Escucha', 'homlity-real-estate')),
            'audio_player_label' => (string) ($settings['audio_player_label'] ?? __('este inmueble', 'homlity-real-estate')),
            'audio_default_rate' => (float) ($settings['audio_default_rate'] ?? 1),
            'audio_voice' => (string) ($settings['audio_voice'] ?? 'auto'),
        ];
    }

    protected function enqueueAudioPlayerAssets(bool $enabled): void
    {
        if (!$enabled) {
            return;
        }

        wp_enqueue_script(
            'homlity-real-estate-property-content-audio',
            HOMLITY_PLUGIN_URL . 'assets/js/property-content-audio.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );
        wp_enqueue_style(
            'homlity-real-estate-property-content-audio',
            HOMLITY_PLUGIN_URL . 'assets/css/property-content-audio.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );
    }
}
