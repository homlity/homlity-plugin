<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFeaturedTermsWidget extends Widget_Base
{
    private const FRONT_COMPONENTS_STYLE_HANDLE = 'homlity-real-estate-front-components';
    private const LISTING_STYLE_HANDLE = 'homlity-real-estate-listing';

    public function get_name(): string
    {
        return 'property_featured_terms';
    }

    public function get_title(): string
    {
        return __('Destacados por ubicación y tipo', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-icon-box';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    /**
     * La hoja que maqueta los grupos de términos.
     *
     * Sin esto el widget salía sin estilos en la web pública y con ellos en el
     * editor: enqueuePreviewAssets() mete las dos hojas dentro del iframe de
     * previsualización, pero fuera de ahí Elementor solo carga lo que cada
     * widget declara. Los grupos se apilaban en vez de repartirse en dos
     * columnas y las listas salían con las viñetas del navegador.
     *
     * Su hermano por taxonomía —PropertyFeaturedTermsBaseWidget— no necesita
     * nada de esto: pinta otras clases, que no tienen hoja, y lleva su
     * maquetación en atributos `style`.
     *
     * @return string[]
     */
    public function get_style_depends(): array
    {
        wp_register_style(
            self::FRONT_COMPONENTS_STYLE_HANDLE,
            HOMLITY_PLUGIN_URL . 'assets/css/front-components.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );
        wp_register_style(
            self::LISTING_STYLE_HANDLE,
            HOMLITY_PLUGIN_URL . 'assets/css/property-listing.css',
            [self::FRONT_COMPONENTS_STYLE_HANDLE],
            HOMLITY_PLUGIN_VERSION
        );

        return [self::LISTING_STYLE_HANDLE];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);

        $this->add_control('show_city', [
            'label' => __('Mostrar ciudades destacadas', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('title_city', [
            'label' => __('Título ciudades', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Ciudades destacadas', 'homlity-real-estate'),
            'condition' => ['show_city' => 'yes'],
        ]);
        $this->add_control('limit_city', [
            'label' => __('Cantidad ciudades', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 50,
            'default' => 8,
            'condition' => ['show_city' => 'yes'],
        ]);

        $this->add_control('show_neighborhood', [
            'label' => __('Mostrar barrios destacados', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('title_neighborhood', [
            'label' => __('Título barrios', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Barrios destacados', 'homlity-real-estate'),
            'condition' => ['show_neighborhood' => 'yes'],
        ]);
        $this->add_control('limit_neighborhood', [
            'label' => __('Cantidad barrios', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 50,
            'default' => 8,
            'condition' => ['show_neighborhood' => 'yes'],
        ]);

        $this->add_control('show_operation', [
            'label' => __('Mostrar gestión destacada', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('title_operation', [
            'label' => __('Título gestión', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Gestión destacada', 'homlity-real-estate'),
            'condition' => ['show_operation' => 'yes'],
        ]);
        $this->add_control('limit_operation', [
            'label' => __('Cantidad gestión', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 20,
            'default' => 6,
            'condition' => ['show_operation' => 'yes'],
        ]);

        $this->add_control('show_type', [
            'label' => __('Mostrar tipo destacado', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('title_type', [
            'label' => __('Título tipo', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Tipos de inmueble destacados', 'homlity-real-estate'),
            'condition' => ['show_type' => 'yes'],
        ]);
        $this->add_control('limit_type', [
            'label' => __('Cantidad tipo', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 20,
            'default' => 8,
            'condition' => ['show_type' => 'yes'],
        ]);

        $this->add_control('list_icon', [
            'label' => __('Ícono del listado', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-location-dot', 'library' => 'fa-solid'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style', [
            'label' => __('Estilo', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('columns', [
            'label' => __('Columnas', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => '2',
            'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
            'selectors' => [
                '{{WRAPPER}} .hml-featured-terms' => 'display:grid;grid-template-columns:repeat({{VALUE}}, minmax(0,1fr));',
            ],
        ]);

        $this->add_responsive_control('grid_gap', [
            'label' => __('Espacio entre bloques', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'selectors' => ['{{WRAPPER}} .hml-featured-terms' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'selector' => '{{WRAPPER}} .hml-featured-terms__title',
        ]);
        $this->add_control('title_color', [
            'label' => __('Color título', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hml-featured-terms__title' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'item_typography',
            'selector' => '{{WRAPPER}} .hml-featured-terms__link',
        ]);
        $this->add_control('item_color', [
            'label' => __('Color ítem', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hml-featured-terms__link' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('count_color', [
            'label' => __('Color contador', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hml-featured-terms__count' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('icon_color', [
            'label' => __('Color ícono', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hml-featured-terms__icon' => 'color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('icon_size', [
            'label' => __('Tamaño ícono', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 40]],
            'selectors' => [
                '{{WRAPPER}} .hml-featured-terms__icon i' => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .hml-featured-terms__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $groups = [
            [
                'enabled' => !empty($settings['show_city']),
                'title' => (string) ($settings['title_city'] ?? __('Ciudades destacadas', 'homlity-real-estate')),
                'limit' => max(1, (int) ($settings['limit_city'] ?? 8)),
                'taxonomy' => PropertyTaxonomies::TAXONOMY_CITY,
                'segment' => 'ciudad',
            ],
            [
                'enabled' => !empty($settings['show_neighborhood']),
                'title' => (string) ($settings['title_neighborhood'] ?? __('Barrios destacados', 'homlity-real-estate')),
                'limit' => max(1, (int) ($settings['limit_neighborhood'] ?? 8)),
                'taxonomy' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
                'segment' => 'barrios',
            ],
            [
                'enabled' => !empty($settings['show_operation']),
                'title' => (string) ($settings['title_operation'] ?? __('Gestión destacada', 'homlity-real-estate')),
                'limit' => max(1, (int) ($settings['limit_operation'] ?? 6)),
                'taxonomy' => PropertyTaxonomies::TAXONOMY_OPERATION,
                'segment' => 'gestion',
            ],
            [
                'enabled' => !empty($settings['show_type']),
                'title' => (string) ($settings['title_type'] ?? __('Tipos de inmueble destacados', 'homlity-real-estate')),
                'limit' => max(1, (int) ($settings['limit_type'] ?? 8)),
                'taxonomy' => PropertyTaxonomies::TAXONOMY_TYPE,
                'segment' => 'tipo',
            ],
        ];

        $icon = is_array($settings['list_icon'] ?? null) ? $settings['list_icon'] : [];
        echo '<div class="hml-featured-terms">';
        foreach ($groups as $group) {
            if (!$group['enabled']) {
                continue;
            }
            $terms = $this->topTerms((string) $group['taxonomy'], (int) $group['limit']);
            if (!$terms) {
                continue;
            }

            echo '<section class="hml-featured-terms__group">';
            echo '<h3 class="hml-featured-terms__title">' . esc_html($group['title']) . '</h3>';
            echo '<ul class="hml-featured-terms__list">';
            foreach ($terms as $item) {
                $url = home_url('/inmuebles/' . $group['segment'] . '/' . $item['slug'] . '/');
                echo '<li class="hml-featured-terms__item">';
                if (!empty($icon['value']) && class_exists('\Elementor\Icons_Manager')) {
                    echo '<span class="hml-featured-terms__icon" aria-hidden="true">';
                    \Elementor\Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);
                    echo '</span>';
                }
                echo '<a class="hml-featured-terms__link" href="' . esc_url($url) . '">';
                echo esc_html($item['name']);
                echo '</a> ';
                echo '<span class="hml-featured-terms__count">(' . esc_html((string) $item['total']) . ')</span>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</section>';
        }
        echo '</div>';
    }

    /**
     * @return array<int,array{name:string,slug:string,total:int}>
     */
    private function topTerms(string $taxonomy, int $limit): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.name, t.slug, COUNT(DISTINCT p.ID) AS total
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = %s
             INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             LEFT JOIN {$wpdb->postmeta} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = '_property_status'
             LEFT JOIN {$wpdb->postmeta} pm_available ON pm_available.post_id = p.ID AND pm_available.meta_key = '_property_available'
             WHERE p.post_type = %s
               AND p.post_status = 'publish'
               AND (pm_status.meta_id IS NULL OR LOWER(pm_status.meta_value) = 'active')
               AND (pm_available.meta_id IS NULL OR LOWER(pm_available.meta_value) IN ('1','true','yes','active'))
             GROUP BY t.term_id, t.name, t.slug
             ORDER BY total DESC, t.name ASC
             LIMIT %d",
                $taxonomy,
                PropertyPostType::POST_TYPE,
                $limit
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $result;
    }
}
