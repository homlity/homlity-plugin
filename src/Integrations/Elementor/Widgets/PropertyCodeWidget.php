<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\PropertyCodeResolver;
use Homlity\PluginInmobiliario\Services\SocialShareMessageService;
use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Services\WhatsAppLinkService;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * El código del inmueble como elemento propio de la ficha.
 *
 * A diferencia de la etiqueta dinámica del mismo dato, aquí el texto de antes
 * y el de después son partes con estilo propio y el conjunto puede llevar
 * enlace: el caso habitual es escribir a WhatsApp citando ese código.
 */
class PropertyCodeWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_code';
    }

    public function get_title(): string
    {
        return __('Código del inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-barcode';
    }

    public function get_keywords(): array
    {
        return ['codigo', 'código', 'code', 'referencia', 'whatsapp', 'inmueble'];
    }

    protected function register_controls(): void
    {
        // ── Contenido ────────────────────────────────────────────────────────
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();

        $this->add_control('before_text', [
            'label'       => __('Texto antes', 'homlity-real-estate'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('Código: ', 'homlity-real-estate'),
            'placeholder' => __('Código: ', 'homlity-real-estate'),
        ]);

        $this->add_control('after_text', [
            'label'       => __('Texto después', 'homlity-real-estate'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'placeholder' => __('· Consúltalo con un asesor', 'homlity-real-estate'),
        ]);

        $this->add_control('keep_spaces', [
            'label'       => __('Respetar los espacios del texto', 'homlity-real-estate'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'description' => __('Mantiene el espacio final de «Código: » tal como se escribió. Desactivado, las partes se separan con un espacio simple.', 'homlity-real-estate'),
        ]);

        $this->add_control('html_tag', [
            'label'   => __('Etiqueta HTML', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'div',
            'options' => [
                'div'  => 'DIV',
                'p'    => 'P',
                'span' => 'SPAN',
                'h2'   => 'H2',
                'h3'   => 'H3',
                'h4'   => 'H4',
                'h5'   => 'H5',
                'h6'   => 'H6',
            ],
        ]);

        $this->add_control('fallback_text', [
            'label'       => __('Texto si el inmueble no tiene código', 'homlity-real-estate'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'description' => __('Vacío: el widget no se pinta.', 'homlity-real-estate'),
        ]);

        $this->end_controls_section();

        // ── Enlace ───────────────────────────────────────────────────────────
        $this->start_controls_section('link', ['label' => __('Enlace', 'homlity-real-estate')]);

        $this->add_control('link_type', [
            'label'   => __('Enlazar a', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => [
                ''         => __('Sin enlace', 'homlity-real-estate'),
                'whatsapp' => __('WhatsApp', 'homlity-real-estate'),
                'custom'   => __('URL personalizada', 'homlity-real-estate'),
            ],
        ]);

        $this->add_control('whatsapp_source', [
            'label'     => __('Número de WhatsApp', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'auto',
            'options'   => [
                'auto'   => __('El del inmueble (agencia o asesor)', 'homlity-real-estate'),
                'manual' => __('Uno fijo para este widget', 'homlity-real-estate'),
            ],
            'condition' => ['link_type' => 'whatsapp'],
        ]);

        $this->add_control('whatsapp_phone', [
            'label'       => __('Número', 'homlity-real-estate'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'placeholder' => '573001234567',
            'description' => __('Con indicativo del país y sin signos.', 'homlity-real-estate'),
            'condition'   => [
                'link_type'       => 'whatsapp',
                'whatsapp_source' => 'manual',
            ],
        ]);

        $this->add_control('whatsapp_message', [
            'label'       => __('Mensaje', 'homlity-real-estate'),
            'type'        => Controls_Manager::TEXTAREA,
            'rows'        => 4,
            'default'     => '',
            'placeholder' => __('Hola, estoy interesado en el inmueble {code} — {url}', 'homlity-real-estate'),
            'description' => __('Marcadores: {code}, {title}, {url}, {price}, {bedrooms}, {bathrooms}, {parking}, {area}, {summary}. Vacío: se usa el mensaje configurado en los ajustes del plugin.', 'homlity-real-estate'),
            'condition'   => ['link_type' => 'whatsapp'],
        ]);

        $this->add_control('custom_url', [
            'label'         => __('URL', 'homlity-real-estate'),
            'type'          => Controls_Manager::URL,
            'options'       => false,
            'placeholder'   => 'https://',
            'description'   => __('Usa {code} para insertar el código del inmueble.', 'homlity-real-estate'),
            'condition'     => ['link_type' => 'custom'],
        ]);

        $this->add_control('link_scope', [
            'label'     => __('Qué se enlaza', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'all',
            'options'   => [
                'all'  => __('Todo el texto', 'homlity-real-estate'),
                'code' => __('Solo el código', 'homlity-real-estate'),
            ],
            'condition' => ['link_type!' => ''],
        ]);

        $this->add_control('open_in_new_tab', [
            'label'     => __('Abrir en nueva pestaña', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'condition' => ['link_type!' => ''],
        ]);

        $this->end_controls_section();

        // ── Estilos ──────────────────────────────────────────────────────────
        $this->start_controls_section('style_code', [
            'label' => __('Estilos', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('align', [
            'label'   => __('Alineación', 'homlity-real-estate'),
            'type'    => Controls_Manager::CHOOSE,
            'default' => 'left',
            'options' => [
                'left'   => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'),    'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Derecha', 'homlity-real-estate'),   'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => ['{{WRAPPER}} .homlity-property-code' => 'text-align: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'typography',
            'label'    => __('Tipografía', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .homlity-property-code',
        ]);

        $this->add_control('text_color', [
            'label'     => __('Color del texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-code' => 'color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .homlity-property-code' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('background', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-code' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'border',
            'selector' => '{{WRAPPER}} .homlity-property-code',
        ]);

        $this->add_responsive_control('border_radius', [
            'label'      => __('Radio del borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .homlity-property-code' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'box_shadow',
            'selector' => '{{WRAPPER}} .homlity-property-code',
        ]);

        // — Texto antes —
        $this->add_control('before_heading', [
            'label'     => __('Texto antes', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'before_typography',
            'selector' => '{{WRAPPER}} .homlity-property-code__before',
        ]);

        $this->add_control('before_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-code__before' => 'color: {{VALUE}};'],
        ]);

        // — Código —
        $this->add_control('value_heading', [
            'label'     => __('Código', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'value_typography',
            'selector' => '{{WRAPPER}} .homlity-property-code__value',
        ]);

        $this->add_control('value_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-code__value' => 'color: {{VALUE}};'],
        ]);

        // — Texto después —
        $this->add_control('after_heading', [
            'label'     => __('Texto después', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'after_typography',
            'selector' => '{{WRAPPER}} .homlity-property-code__after',
        ]);

        $this->add_control('after_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-code__after' => 'color: {{VALUE}};'],
        ]);

        // — Enlace —
        $this->add_control('link_heading', [
            'label'     => __('Enlace', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['link_type!' => ''],
        ]);

        $this->add_control('link_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['link_type!' => ''],
            'selectors' => ['{{WRAPPER}} .homlity-property-code__link' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('link_color_hover', [
            'label'     => __('Color (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['link_type!' => ''],
            'selectors' => ['{{WRAPPER}} .homlity-property-code__link:hover' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('link_decoration', [
            'label'     => __('Subrayado', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'none',
            'options'   => [
                'none'      => __('Sin subrayado', 'homlity-real-estate'),
                'underline' => __('Subrayado', 'homlity-real-estate'),
            ],
            'condition' => ['link_type!' => ''],
            'selectors' => ['{{WRAPPER}} .homlity-property-code__link' => 'text-decoration: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $postId   = $this->current_property_id();
        $code     = $postId > 0 ? PropertyCodeResolver::forDisplay($postId) : '';

        TemplateService::includeComponent('property-code.php', [
            'post_id'         => $postId,
            'code'            => $code,
            'before_text'     => (string) ($settings['before_text'] ?? ''),
            'after_text'      => (string) ($settings['after_text'] ?? ''),
            'keep_spaces'     => ($settings['keep_spaces'] ?? 'yes') === 'yes',
            'html_tag'        => (string) ($settings['html_tag'] ?? 'div'),
            'fallback_text'   => (string) ($settings['fallback_text'] ?? ''),
            'link_url'        => $this->resolveLinkUrl($settings, $postId, $code),
            'link_scope'      => (string) ($settings['link_scope'] ?? 'all'),
            'open_in_new_tab' => ($settings['open_in_new_tab'] ?? 'yes') === 'yes',
        ]);
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function resolveLinkUrl(array $settings, int $postId, string $code): string
    {
        $type = (string) ($settings['link_type'] ?? '');

        if ($type === 'custom') {
            $url = (string) ($settings['custom_url']['url'] ?? '');
            $url = str_replace(['{code}', '{codigo}'], rawurlencode($code), $url);

            return esc_url_raw($url);
        }

        if ($type !== 'whatsapp' || $postId <= 0) {
            return '';
        }

        $message = (string) ($settings['whatsapp_message'] ?? '');

        if ((string) ($settings['whatsapp_source'] ?? 'auto') === 'manual') {
            $phone = (string) ($settings['whatsapp_phone'] ?? '');

            return $phone === ''
                ? ''
                : WhatsAppLinkService::buildAgentLink(
                    $phone,
                    SocialShareMessageService::messageFor('whatsapp', $postId, $message)
                );
        }

        return WhatsAppLinkService::buildPropertyLinkWithTemplate(
            $postId,
            WhatsAppLinkService::advisorPhoneForProperty($postId),
            $message
        );
    }
}
