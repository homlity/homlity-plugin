<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!defined('ABSPATH')) {
    exit;
}

abstract class PropertyFeaturedTermsBaseWidget extends Widget_Base
{
    protected function register_controls(): void
    {
        $defaults = $this->get_featured_terms_config();

        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);

        $this->add_control('widget_title', [
            'label' => __('Título', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => $defaults['title'],
        ]);

        $this->add_control('items_limit', [
            'label' => __('Cantidad de items', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 50,
            'default' => $defaults['limit'],
        ]);

        $this->add_control('item_text_template', [
            'label' => __('Texto de cada item', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => $defaults['item_text'],
            'description' => __('Usa {{term}} para el término y {{count}} para el total.', 'homlity-real-estate'),
        ]);

        $this->add_control('show_count', [
            'label' => __('Mostrar contador', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('list_icon', [
            'label' => __('Ícono', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => $defaults['icon'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_title', [
            'label' => __('Título', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'selector' => '{{WRAPPER}} .hml-featured-terms-widget__title',
        ]);

        $this->add_control('title_color', [
            'label' => __('Color', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-featured-terms-widget__title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('title_spacing', [
            'label' => __('Espacio inferior', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 80]],
            'selectors' => [
                '{{WRAPPER}} .hml-featured-terms-widget__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_items', [
            'label' => __('Items', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'item_typography',
            'selector' => '{{WRAPPER}} .hml-featured-terms-widget__link, {{WRAPPER}} .hml-featured-terms-widget__count',
        ]);

        $this->add_control('item_color', [
            'label' => __('Color texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-featured-terms-widget__link' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('count_color', [
            'label' => __('Color contador', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-featured-terms-widget__count' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('items_gap', [
            'label' => __('Espacio entre items', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 80]],
            'selectors' => [
                '{{WRAPPER}} .hml-featured-terms-widget__list' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_icon', [
            'label' => __('Ícono', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('icon_color', [
            'label' => __('Color', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-featured-terms-widget__icon' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('icon_size', [
            'label' => __('Tamaño', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 64]],
            'selectors' => [
                '{{WRAPPER}} .hml-featured-terms-widget__icon i' => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .hml-featured-terms-widget__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('icon_gap', [
            'label' => __('Espacio con texto', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'selectors' => [
                '{{WRAPPER}} .hml-featured-terms-widget__item' => 'column-gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $config = $this->get_featured_terms_config();
        $items = $this->top_terms($config['taxonomy'], max(1, (int) ($settings['items_limit'] ?? $config['limit'])));

        if ($items === []) {
            return;
        }

        $title = trim((string) ($settings['widget_title'] ?? $config['title']));
        $template = trim((string) ($settings['item_text_template'] ?? $config['item_text']));
        $showCount = ($settings['show_count'] ?? 'yes') === 'yes';
        $icon = is_array($settings['list_icon'] ?? null) ? $settings['list_icon'] : [];

        echo '<section class="hml-featured-terms-widget">';

        if ($title !== '') {
            echo '<h3 class="hml-featured-terms-widget__title">' . esc_html($title) . '</h3>';
        }

        echo '<ul class="hml-featured-terms-widget__list" style="list-style:none;margin:0;padding:0;display:grid;">';

        foreach ($items as $item) {
            $url = home_url('/inmuebles/' . $config['segment'] . '/' . $item['slug'] . '/');
            $label = $this->build_item_label($template, $item['name'], $item['total']);
            $hasIcon = !empty($icon['value']) && class_exists(Icons_Manager::class);
            $itemClass = 'hml-featured-terms-widget__item' . ($hasIcon ? '' : ' hml-featured-terms-widget__item--no-icon');

            echo '<li class="' . esc_attr($itemClass) . '" style="display:grid;grid-template-columns:auto 1fr;align-items:center;">';

            if ($hasIcon) {
                echo '<span class="hml-featured-terms-widget__icon" aria-hidden="true">';
                Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);
                echo '</span>';
            }

            echo '<span class="hml-featured-terms-widget__content">';
            echo '<a class="hml-featured-terms-widget__link" href="' . esc_url($url) . '">';
            echo esc_html($label);
            echo '</a>';

            if ($showCount) {
                echo ' <span class="hml-featured-terms-widget__count">(' . esc_html((string) $item['total']) . ')</span>';
            }

            echo '</span>';
            echo '</li>';
        }

        echo '</ul>';
        echo '</section>';
    }

    /**
     * @return array{title:string,limit:int,taxonomy:string,segment:string,item_text:string,icon:array<string,string>}
     */
    abstract protected function get_featured_terms_config(): array;

    private function build_item_label(string $template, string $term, int $count): string
    {
        if ($template === '') {
            $template = '{{term}}';
        }

        return strtr($template, [
            '{{term}}' => $term,
            '{{count}}' => (string) $count,
        ]);
    }

    /**
     * @return array<int,array{name:string,slug:string,total:int}>
     */
    private function top_terms(string $taxonomy, int $limit): array
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
