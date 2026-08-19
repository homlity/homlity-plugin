<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyAgentsAvailableWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'property_agents_available';
    }

    public function get_title(): string
    {
        return __('Asesores con inmuebles disponibles', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-person';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);

        $this->add_control('limit', [
            'label' => __('Cantidad de asesores', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'default' => 12,
            'min' => 1,
            'max' => 200,
        ]);

        $this->add_responsive_control('columns', [
            'label' => __('Columnas', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => [''],
            'range' => ['' => ['min' => 1, 'max' => 6]],
            'default' => ['size' => 3],
            'selectors' => [
                '{{WRAPPER}} .hml-agents-available' => 'display:grid;grid-template-columns:repeat({{SIZE}}, minmax(0, 1fr));',
            ],
        ]);

        $this->add_control('show_phone', [
            'label' => __('Mostrar teléfono', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_email', [
            'label' => __('Mostrar correo', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_profile_button', [
            'label' => __('Botón perfil', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('profile_label', [
            'label' => __('Texto botón perfil', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Ver perfil', 'homlity-real-estate'),
            'condition' => ['show_profile_button' => 'yes'],
        ]);

        $this->add_control('icon_phone', [
            'label' => __('Ícono teléfono', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => [
                'value' => 'fas fa-phone',
                'library' => 'fa-solid',
            ],
            'condition' => ['show_phone' => 'yes'],
        ]);

        $this->add_control('icon_email', [
            'label' => __('Ícono correo', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => [
                'value' => 'fas fa-envelope',
                'library' => 'fa-solid',
            ],
            'condition' => ['show_email' => 'yes'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style', [
            'label' => __('Estilos', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('grid_gap', [
            'label' => __('Separación', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'default' => ['size' => 18, 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .hml-agents-available' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('card_bg', [
            'label' => __('Fondo tarjeta', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hml-agents-available__card' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('card_padding', [
            'label' => __('Padding tarjeta', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .hml-agents-available__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_radius', [
            'label' => __('Radio borde', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .hml-agents-available__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'card_border',
            'selector' => '{{WRAPPER}} .hml-agents-available__card',
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'card_shadow',
            'selector' => '{{WRAPPER}} .hml-agents-available__card',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'name_typography',
            'selector' => '{{WRAPPER}} .hml-agents-available__name',
        ]);

        $this->add_control('name_color', [
            'label' => __('Color nombre', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hml-agents-available__name' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'meta_typography',
            'selector' => '{{WRAPPER}} .hml-agents-available__meta, {{WRAPPER}} .hml-agents-available__meta a',
        ]);

        $this->add_control('meta_color', [
            'label' => __('Color datos', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-agents-available__meta, {{WRAPPER}} .hml-agents-available__meta a' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'button_typography',
            'selector' => '{{WRAPPER}} .hml-agents-available__cta',
        ]);

        $this->add_control('button_color', [
            'label' => __('Color botón', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hml-agents-available__cta' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('button_bg', [
            'label' => __('Fondo botón', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .hml-agents-available__cta' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('icon_color', [
            'label' => __('Color íconos', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-agents-available__meta i, {{WRAPPER}} .hml-agents-available__meta svg' => 'color: {{VALUE}}; fill: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $rows = $this->queryAgentsWithAvailableProperties(max(1, (int) ($settings['limit'] ?? 12)));

        if (empty($rows)) {
            return;
        }

        $showPhone = (($settings['show_phone'] ?? 'yes') === 'yes');
        $showEmail = (($settings['show_email'] ?? 'yes') === 'yes');
        $showButton = (($settings['show_profile_button'] ?? 'yes') === 'yes');
        $profileLabel = trim((string) ($settings['profile_label'] ?? __('Ver perfil', 'homlity-real-estate')));
        $phoneIcon = $settings['icon_phone'] ?? [];
        $emailIcon = $settings['icon_email'] ?? [];

        echo '<div class="hml-agents-available">';
        foreach ($rows as $row) {
            $user = $row['user'];
            $count = $row['count'];
            $phone = $this->resolveUserPhone($user->ID);
            $email = (string) $user->user_email;
            $profileUrl = AgentProfileService::profileUrl($user);

            echo '<article class="hml-agents-available__card">';
            echo '<div class="hml-agents-available__avatar">' . get_avatar($user->ID, 96) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<h3 class="hml-agents-available__name"><a href="' . esc_url($profileUrl) . '">' . esc_html($user->display_name) . '</a></h3>';
            /* translators: %d: number of available properties */
            echo '<p class="hml-agents-available__count">' . esc_html(sprintf(_n('%d inmueble disponible', '%d inmuebles disponibles', $count, 'homlity-real-estate'), $count)) . '</p>';

            if ($showPhone && $phone !== '') {
                echo '<p class="hml-agents-available__meta">';
                echo '<a href="tel:' . esc_attr((string) preg_replace('/\D+/', '', $phone)) . '">';
                if (!empty($phoneIcon['value']) && class_exists('\Elementor\Icons_Manager')) {
                    Icons_Manager::render_icon($phoneIcon, ['aria-hidden' => 'true']);
                }
                echo '<span>' . esc_html($phone) . '</span></a></p>';
            }

            if ($showEmail && $email !== '') {
                echo '<p class="hml-agents-available__meta">';
                echo '<a href="mailto:' . esc_attr($email) . '">';
                if (!empty($emailIcon['value']) && class_exists('\Elementor\Icons_Manager')) {
                    Icons_Manager::render_icon($emailIcon, ['aria-hidden' => 'true']);
                }
                echo '<span>' . esc_html($email) . '</span></a></p>';
            }

            if ($showButton && $profileLabel !== '') {
                echo '<a class="hml-agents-available__cta" href="' . esc_url($profileUrl) . '">' . esc_html($profileLabel) . '</a>';
            }
            echo '</article>';
        }
        echo '</div>';
    }

    /**
     * @return array<int,array{user:\WP_User,count:int}>
     */
    private function queryAgentsWithAvailableProperties(int $limit): array
    {
        global $wpdb;

        $records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT CAST(pm_agent.meta_value AS UNSIGNED) AS agent_id, COUNT(DISTINCT p.ID) AS total
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_agent ON pm_agent.post_id = p.ID AND pm_agent.meta_key = '_property_agent_id'
             LEFT JOIN {$wpdb->postmeta} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = '_property_status'
             LEFT JOIN {$wpdb->postmeta} pm_available ON pm_available.post_id = p.ID AND pm_available.meta_key = '_property_available'
             WHERE p.post_type = %s
               AND p.post_status = 'publish'
               AND pm_agent.meta_value REGEXP '^[0-9]+$'
               AND CAST(pm_agent.meta_value AS UNSIGNED) > 0
               AND (pm_status.meta_id IS NULL OR LOWER(pm_status.meta_value) = 'active')
               AND (pm_available.meta_id IS NULL OR LOWER(pm_available.meta_value) IN ('1','true','yes','active'))
             GROUP BY agent_id
             ORDER BY total DESC, agent_id ASC
             LIMIT %d",
                PropertyPostType::POST_TYPE,
                $limit
            ),
            ARRAY_A
        );
        if (!is_array($records) || empty($records)) {
            return [];
        }

        $counts = [];
        $agentIds = [];
        foreach ($records as $record) {
            $agentId = (int) ($record['agent_id'] ?? 0);
            $total = (int) ($record['total'] ?? 0);
            if ($agentId <= 0 || $total <= 0) {
                continue;
            }
            $counts[$agentId] = $total;
            $agentIds[] = $agentId;
        }

        if (empty($agentIds)) {
            return [];
        }

        $users = get_users([
            'include' => array_values(array_unique($agentIds)),
            'fields' => 'all',
        ]);

        if (empty($users)) {
            return [];
        }

        $result = [];
        foreach ($users as $user) {
            if (!isset($counts[$user->ID])) {
                continue;
            }
            if ((int) ($user->user_status ?? 0) !== 0) {
                continue;
            }
            $result[] = [
                'user' => $user,
                'count' => (int) $counts[$user->ID],
            ];
        }

        usort($result, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'];
        });

        return $result;
    }

    private function resolveUserPhone(int $userId): string
    {
        $phone = (string) get_user_meta($userId, 'phone', true);
        if ($phone !== '') {
            return $phone;
        }

        return (string) get_user_meta($userId, 'billing_phone', true);
    }
}
