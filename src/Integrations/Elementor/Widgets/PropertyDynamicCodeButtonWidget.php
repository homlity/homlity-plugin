<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyDynamicCodeButtonWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_dynamic_code_button';
    }

    public function get_title(): string
    {
        return __('Botón dinámico con código', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-button';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();

        $this->add_control('button_text', [
            'label' => __('Texto del botón', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Agendar inmueble', 'homlity-real-estate'),
        ]);

        $this->add_control('base_url', [
            'label' => __('URL base', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => 'https://portal.epicainmobiliaria.com/agendamiento/[Codigo de inmueble]',
            'placeholder' => 'https://portal.epicainmobiliaria.com/agendamiento/[Codigo de inmueble]',
            'description' => __('Usa [Codigo de inmueble], [codigo], [code] o {code} para insertar el código del inmueble.', 'homlity-real-estate'),
        ]);

        $this->add_control('fallback_url', [
            'label' => __('URL fallback', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
            'placeholder' => 'https://royalpropiedadraiz.capitalana.com/inmuebles/',
            'description' => __('Se usa cuando el inmueble actual no tiene código disponible.', 'homlity-real-estate'),
        ]);

        $this->add_control('button_icon', [
            'label' => __('Ícono', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-calendar-check', 'library' => 'fa-solid'],
        ]);

        $this->add_control('icon_align', [
            'label' => __('Posición del ícono', 'homlity-real-estate'),
            'type' => Controls_Manager::CHOOSE,
            'default' => 'left',
            'options' => [
                'left' => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-h-align-left'],
                'right' => ['title' => __('Derecha', 'homlity-real-estate'), 'icon' => 'eicon-h-align-right'],
            ],
        ]);

        $this->add_control('open_in_new_tab', [
            'label' => __('Abrir en nueva pestaña', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style', [
            'label' => __('Estilos', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('button_align', [
            'label' => __('Alineación', 'homlity-real-estate'),
            'type' => Controls_Manager::CHOOSE,
            'default' => 'left',
            'options' => [
                'left' => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'), 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => __('Derecha', 'homlity-real-estate'), 'icon' => 'eicon-text-align-right'],
                'justify' => ['title' => __('Justificado', 'homlity-real-estate'), 'icon' => 'eicon-text-align-justify'],
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button-wrap' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_width_mode', [
            'label' => __('Ancho del botón', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'inline-flex',
            'options' => [
                'inline-flex' => __('Automático', 'homlity-real-estate'),
                'flex' => __('100% ancho', 'homlity-real-estate'),
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button' => 'display: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('button_custom_width', [
            'label' => __('Ancho personalizado', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => [
                'px' => ['min' => 40, 'max' => 1200],
                '%' => ['min' => 1, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button' => 'width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_min_height', [
            'label' => __('Altura mínima', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 300],
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button' => 'min-height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'button_typography',
            'selector' => '{{WRAPPER}} .homlity-dynamic-code-button',
        ]);

        $this->add_responsive_control('button_radius', [
            'label' => __('Radio del borde', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_padding', [
            'label' => __('Padding', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_justify_content', [
            'label' => __('Alineación interna', 'homlity-real-estate'),
            'type' => Controls_Manager::CHOOSE,
            'default' => 'center',
            'options' => [
                'flex-start' => ['title' => __('Inicio', 'homlity-real-estate'), 'icon' => 'eicon-h-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'), 'icon' => 'eicon-h-align-center'],
                'flex-end' => ['title' => __('Fin', 'homlity-real-estate'), 'icon' => 'eicon-h-align-right'],
                'space-between' => ['title' => __('Separado', 'homlity-real-estate'), 'icon' => 'eicon-justify-space-between-h'],
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button' => 'justify-content: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('button_gap', [
            'label' => __('Espacio icono-texto', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 8],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('icon_heading', [
            'label' => __('Ícono', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('icon_size', [
            'label' => __('Tamaño del ícono', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range' => [
                'px' => ['min' => 0, 'max' => 120],
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button__icon' => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .homlity-dynamic-code-button__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->start_controls_tabs('button_style_tabs');

        $this->start_controls_tab('button_style_tab_normal', [
            'label' => __('Normal', 'homlity-real-estate'),
        ]);

        $this->add_control('button_text_color', [
            'label' => __('Color texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_bg_color', [
            'label' => __('Color fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_icon_color', [
            'label' => __('Color ícono', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button__icon' => 'color: {{VALUE}};',
                '{{WRAPPER}} .homlity-dynamic-code-button__icon svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'button_border',
            'selector' => '{{WRAPPER}} .homlity-dynamic-code-button',
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'button_shadow',
            'selector' => '{{WRAPPER}} .homlity-dynamic-code-button',
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('button_style_tab_hover', [
            'label' => __('Hover', 'homlity-real-estate'),
        ]);

        $this->add_control('button_text_color_hover', [
            'label' => __('Color texto hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_bg_color_hover', [
            'label' => __('Color fondo hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_icon_color_hover', [
            'label' => __('Color ícono hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button:hover .homlity-dynamic-code-button__icon' => 'color: {{VALUE}};',
                '{{WRAPPER}} .homlity-dynamic-code-button:hover .homlity-dynamic-code-button__icon svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_border_color_hover', [
            'label' => __('Color borde hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button:hover' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'button_shadow_hover',
            'selector' => '{{WRAPPER}} .homlity-dynamic-code-button:hover',
        ]);

        $this->add_control('button_hover_animation_duration', [
            'label' => __('Duración transición (ms)', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['ms'],
            'range' => [
                'ms' => ['min' => 0, 'max' => 3000],
            ],
            'default' => ['unit' => 'ms', 'size' => 250],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button, {{WRAPPER}} .homlity-dynamic-code-button__icon, {{WRAPPER}} .homlity-dynamic-code-button__icon svg' => 'transition-duration: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_hover_translate_y', [
            'label' => __('Desplazamiento hover', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => -40, 'max' => 40],
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-dynamic-code-button:hover' => 'transform: translateY({{SIZE}}{{UNIT}});',
            ],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $postId = $this->current_property_id();
        $propertyCode = $postId > 0 ? (string) get_post_meta($postId, '_property_code', true) : '';
        $url = $this->buildUrl((string) ($settings['base_url'] ?? ''), $propertyCode);

        if ($url === '') {
            $url = esc_url_raw((string) ($settings['fallback_url'] ?? ''));
        }

        if ($url === '') {
            return;
        }

        $buttonText = trim((string) ($settings['button_text'] ?? ''));
        if ($buttonText === '') {
            $buttonText = __('Abrir enlace', 'homlity-real-estate');
        }

        $icon = $settings['button_icon'] ?? [];
        $openInNewTab = ($settings['open_in_new_tab'] ?? 'yes') === 'yes';
        $iconAlign = ($settings['icon_align'] ?? 'left') === 'right' ? 'right' : 'left';

        echo '<div class="homlity-dynamic-code-button-wrap">';
        echo '<a class="homlity-dynamic-code-button" href="' . esc_url($url) . '"' . ($openInNewTab ? ' target="_blank" rel="noopener noreferrer"' : '') . ' style="display:inline-flex;align-items:center;text-decoration:none;transition-property:background-color,color,border-color,box-shadow,transform;">';

        if ($iconAlign === 'left') {
            $this->renderIcon($icon);
        }

        echo '<span class="homlity-dynamic-code-button__text">' . esc_html($buttonText) . '</span>';

        if ($iconAlign === 'right') {
            $this->renderIcon($icon);
        }

        echo '</a>';
        echo '</div>';
    }

    private function buildUrl(string $pattern, string $propertyCode): string
    {
        $pattern = trim($pattern);
        $propertyCode = trim($propertyCode);

        if ($pattern === '') {
            return '';
        }

        if ($propertyCode === '') {
            return '';
        }

        $replacements = [
            '[Codigo de inmueble]' => rawurlencode($propertyCode),
            '[Código de inmueble]' => rawurlencode($propertyCode),
            '[Codigo del inmueble]' => rawurlencode($propertyCode),
            '[Código del inmueble]' => rawurlencode($propertyCode),
            '[codigo de inmueble]' => rawurlencode($propertyCode),
            '[código de inmueble]' => rawurlencode($propertyCode),
            '[codigo del inmueble]' => rawurlencode($propertyCode),
            '[código del inmueble]' => rawurlencode($propertyCode),
            '[codigo]' => rawurlencode($propertyCode),
            '[código]' => rawurlencode($propertyCode),
            '[code]' => rawurlencode($propertyCode),
            '{code}' => rawurlencode($propertyCode),
        ];

        $resolved = strtr($pattern, $replacements);
        if ($resolved === $pattern) {
            $resolved = trailingslashit($pattern) . rawurlencode($propertyCode);
        }

        return esc_url_raw($resolved);
    }

    private function renderIcon(array $icon): void
    {
        if (empty($icon['value']) || !class_exists(Icons_Manager::class)) {
            return;
        }

        echo '<span class="homlity-dynamic-code-button__icon" aria-hidden="true">';
        Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);
        echo '</span>';
    }
}
