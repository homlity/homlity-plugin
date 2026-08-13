<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
namespace Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets;

use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Group_Control_Text_Shadow;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Group_Control_Typography;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Widget_Base;
use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\LocalityPostType;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyResultsTitleWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'property_results_title';
    }

    public function get_title(): string
    {
        return __('Título resultados inmuebles', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-archive-title';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);

        $this->add_control('title_tag', [
            'label' => __('Etiqueta HTML', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'h1',
            'options' => [
                'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6',
                'p' => 'P', 'span' => 'SPAN', 'div' => 'DIV',
            ],
        ]);

        $this->add_control('base_text', [
            'label' => __('Texto base', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Inmuebles', 'homlity-real-estate'),
        ]);

        $this->add_control('show_total', [
            'label' => __('Mostrar total de resultados', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('fallback_title', [
            'label' => __('Título cuando no hay filtros', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Todos los inmuebles disponibles', 'homlity-real-estate'),
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_title', [
            'label' => __('Estilos', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_responsive_control('title_align', [
            'label' => __('Alineación', 'homlity-real-estate'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'left' => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'), 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => __('Derecha', 'homlity-real-estate'), 'icon' => 'eicon-text-align-right'],
                'justify' => ['title' => __('Justificado', 'homlity-real-estate'), 'icon' => 'eicon-text-align-justify'],
            ],
            'selectors' => ['{{WRAPPER}} .property-results-title-widget' => 'text-align: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'selector' => '{{WRAPPER}} .property-results-title-widget',
        ]);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name' => 'title_text_shadow',
            'selector' => '{{WRAPPER}} .property-results-title-widget',
        ]);
        $this->add_control('title_stroke_width', [
            'label' => __('Trazo ancho (px)', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 6]],
            'selectors' => ['{{WRAPPER}} .property-results-title-widget' => '-webkit-text-stroke-width: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_control('title_stroke_color', [
            'label' => __('Trazo color', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-results-title-widget' => '-webkit-text-stroke-color: {{VALUE}};'],
        ]);
        $this->start_controls_tabs('title_states');
        $this->start_controls_tab('title_normal', ['label' => __('Normal', 'homlity-real-estate')]);
        $this->add_control('title_color', [
            'label' => __('Color texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-results-title-widget' => 'color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();
        $this->start_controls_tab('title_hover', ['label' => __('Hover', 'homlity-real-estate')]);
        $this->add_control('title_color_hover', [
            'label' => __('Color texto (hover)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-results-title-widget:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('title_stroke_color_hover', [
            'label' => __('Trazo color (hover)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-results-title-widget:hover' => '-webkit-text-stroke-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $tag = in_array(($settings['title_tag'] ?? 'h1'), ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div'], true)
            ? $settings['title_tag']
            : 'h1';

        $baseText = trim((string) ($settings['base_text'] ?? __('Inmuebles', 'homlity-real-estate')));
        if ($baseText === '') {
            $baseText = __('Inmuebles', 'homlity-real-estate');
        }

        $title = $this->buildSeoTitle($baseText, (string) ($settings['fallback_title'] ?? ''));
        $suffix = '';

        if (($settings['show_total'] ?? 'yes') === 'yes') {
            $search = new PropertySearchService();
            $args   = $search->buildQueryArgs($search->currentQueryParams());
            $args['fields']         = 'ids';
            $args['no_found_rows']  = false;
            $args['posts_per_page'] = 1;
            $countQuery = new \WP_Query($args);
            wp_reset_postdata();
            $total  = (int) $countQuery->found_posts;
            $suffix = ' (' . number_format_i18n($total) . ')';
        }

        printf(
            '<%1$s class="property-results-title-widget">%2$s%3$s</%1$s>',
            esc_attr($tag),
            esc_html($title),
            esc_html($suffix)
        );
    }

    private function buildSeoTitle(string $baseText, string $fallbackTitle): string
    {
        $gestion = $this->resolveSingleTermName($this->readParam('gestion', 'property_operation'), PropertyTaxonomies::TAXONOMY_OPERATION);
        $tipo = $this->resolveMultiTermNames($this->readParam('tipo', 'property_type'), PropertyTaxonomies::TAXONOMY_TYPE);
        $ciudades = $this->resolveMultiTermNames($this->readParam('ciudad', 'property_city'), PropertyTaxonomies::TAXONOMY_CITY);
        $localidades = array_values(array_filter(array_map(
            static fn(int $id): string => (string) get_the_title($id),
            LocalityPostType::resolvePublishedIds($this->readParam('localidades', 'property_locality'))
        )));
        $barrios = $this->resolveMultiTermNames($this->readParam('barrios', 'property_neighborhood'), PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD);
        $etiquetas = $this->resolveMultiTermNames($this->readParam('etiquetas', 'property_tag'), PropertyTaxonomies::TAXONOMY_TAG);
        $keyword = $this->readScalar('q', 's');

        $parts = [];

        $header = $baseText;
        if ($gestion !== '') {
            $header .= ' en ' . $gestion;
        }
        if (!empty($tipo)) {
            $header .= ' de ' . implode(', ', $tipo);
        }
        $parts[] = $header;

        if (!empty($barrios)) {
            $parts[] = 'en barrios ' . implode(', ', $barrios);
        } elseif (!empty($localidades)) {
            $parts[] = 'en localidades ' . implode(', ', $localidades);
        } elseif (!empty($ciudades)) {
            $parts[] = 'en ' . implode(', ', $ciudades);
        }

        if (!empty($etiquetas)) {
            $parts[] = 'con etiquetas ' . implode(', ', $etiquetas);
        }

        if ($keyword !== '') {
            $parts[] = 'con palabra clave "' . $keyword . '"';
        }

        $title = trim(implode(' ', $parts));
        if ($title === '' && trim($fallbackTitle) !== '') {
            return trim($fallbackTitle);
        }

        if ($title === '') {
            return __('Todos los inmuebles disponibles', 'homlity-real-estate');
        }

        return $title;
    }

    private function readParam(string ...$keys): array
    {
        foreach ($keys as $key) {
            $qv = get_query_var($key, null);
            if ($qv !== null && $qv !== '') {
                return $this->normalizeValues($qv);
            }

            if (isset($_GET[$key])) {
                return $this->normalizeValues(sanitize_text_field(wp_unslash((string) $_GET[$key])));
            }
        }

        return [];
    }

    private function readScalar(string ...$keys): string
    {
        $values = $this->readParam(...$keys);
        return $values[0] ?? '';
    }

    /**
     * @param mixed $raw
     */
    private function normalizeValues($raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = explode(',', (string) $raw);
        }

        $out = [];
        foreach ($items as $item) {
            $value = sanitize_title((string) $item);
            if ($value === '') {
                continue;
            }
            $out[] = $value;
        }

        return array_values(array_unique($out));
    }

    private function resolveSingleTermName(array $slugs, string $taxonomy): string
    {
        if (empty($slugs)) {
            return '';
        }

        $term = get_term_by('slug', $slugs[0], $taxonomy);
        if ($term && !is_wp_error($term)) {
            return (string) $term->name;
        }

        return str_replace('-', ' ', $slugs[0]);
    }

    private function resolveMultiTermNames(array $slugs, string $taxonomy): array
    {
        if (empty($slugs)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'slug' => $slugs,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || !$terms) {
            return array_map(static fn(string $slug): string => str_replace('-', ' ', $slug), $slugs);
        }

        $nameMap = [];
        foreach ($terms as $term) {
            $nameMap[$term->slug] = $term->name;
        }

        $names = [];
        foreach ($slugs as $slug) {
            $names[] = $nameMap[$slug] ?? str_replace('-', ' ', $slug);
        }

        return $names;
    }
}
