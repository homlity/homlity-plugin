<?php
/**
 * Technical sheet widget. Drop it on the page configured in
 * Configuración → Plantillas ("Página de ficha técnica") and the sheet of
 * /ficha-tecnica/{inmueble}/ is rendered with the builder's own spacing,
 * margins and colours around it.
 */

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Box_Shadow;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Typography;
use Homlity\PluginInmobiliario\Services\TechnicalSheetService;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyTechnicalSheetWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_technical_sheet';
    }

    public function get_title(): string
    {
        return __('Ficha técnica del inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-document-file';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();

        $this->add_control('sections_heading', [
            'label' => __('Secciones', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_control('show_hero', [
            'label' => __('Encabezado', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('show_address', [
            'label' => __('Mostrar dirección', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'description' => __('Oculta por defecto: la dirección exacta no se publica en la ficha.', 'homlity-real-estate'),
        ]);
        $this->add_control('show_actions', [
            'label' => __('Botones volver / imprimir', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => ['show_hero' => 'yes'],
        ]);
        $this->add_control('show_advisor', [
            'label' => __('Información del asesor', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('show_finance', [
            'label' => __('Finanzas', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('show_info', [
            'label' => __('Información general', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('show_dimensions', [
            'label' => __('Dimensiones y ambientes', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('show_description', [
            'label' => __('Descripción completa', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('show_features', [
            'label' => __('Características', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('show_media', [
            'label' => __('Catálogo multimedia', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('show_legal', [
            'label' => __('Aviso legal', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_layout', [
            'label' => __('Diseño', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_responsive_control('sheet_max_width', [
            'label' => __('Ancho máximo', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => ['px' => ['min' => 480, 'max' => 1600], '%' => ['min' => 40, 'max' => 100]],
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-max-width: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('sheet_padding', [
            'label' => __('Márgenes internos', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('sheet_gap', [
            'label' => __('Separación entre bloques', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 80]],
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-gap: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('sheet_grid_min', [
            'label' => __('Ancho mínimo de columna', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 120, 'max' => 480]],
            'description' => __('Reduce este valor si los datos se ven apretados o cortados.', 'homlity-real-estate'),
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-grid-min: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('sheet_gallery_min', [
            'label' => __('Ancho mínimo de foto', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 80, 'max' => 400]],
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-gallery-min: {{SIZE}}{{UNIT}};'],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_colors', [
            'label' => __('Colores', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_control('sheet_primary', [
            'label' => __('Color principal', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'description' => __('Vacío usa el color configurado en el plugin.', 'homlity-real-estate'),
        ]);
        $this->add_control('heading_color', [
            'label' => __('Color de títulos', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-heading-color: {{VALUE}};'],
        ]);
        $this->add_control('text_color', [
            'label' => __('Color de texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-text-color: {{VALUE}};'],
        ]);
        $this->add_control('label_color', [
            'label' => __('Color de etiquetas', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-label-color: {{VALUE}};'],
        ]);
        $this->add_control('card_bg', [
            'label' => __('Fondo de las tarjetas', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-card-bg: {{VALUE}};'],
        ]);
        $this->add_control('hero_bg', [
            'label' => __('Fondo del encabezado', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-hero-bg: {{VALUE}};'],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_cards', [
            'label' => __('Tarjetas', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_control('card_border_color', [
            'label' => __('Color del borde', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-card-border-color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('card_radius', [
            'label' => __('Radio', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-card-radius: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('card_padding', [
            'label' => __('Espaciado interno', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors' => ['{{WRAPPER}} .homlity-tech-sheet' => '--sheet-card-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'card_shadow',
            'selector' => '{{WRAPPER}} .homlity-tech-sheet__card',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'heading_typography',
            'selector' => '{{WRAPPER}} .homlity-tech-sheet__card h2',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'body_typography',
            'selector' => '{{WRAPPER}} .homlity-tech-sheet',
        ]);
        $this->end_controls_section();
    }

    /**
     * On /ficha-tecnica/{inmueble}/ the queried object is the builder page, so
     * the property has to come from the route before falling back to the
     * generic resolution of the base widget.
     */
    protected function sheet_property_id(): int
    {
        $settings = $this->get_settings_for_display();
        $explicit = ($settings['use_current_property'] ?? 'yes') === 'yes'
            ? 0
            : (int) ($settings['property_id'] ?? 0);

        $postId = TechnicalSheetService::resolvePropertyId($explicit);
        if ($postId > 0) {
            return $postId;
        }

        // Designing the page: show a real property instead of an empty widget.
        return current_user_can('edit_posts') ? TechnicalSheetService::previewPropertyId() : 0;
    }

    protected function render(): void
    {
        $postId = $this->sheet_property_id();
        if ($postId <= 0) {
            return;
        }

        TemplateService::includeComponent('property-technical-sheet.php', [
            'post_id' => $postId,
            'settings' => $this->get_settings_for_display(),
        ]);
    }
}
