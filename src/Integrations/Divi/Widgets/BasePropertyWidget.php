<?php
// Los superglobales que se leen en este archivo sirven sólo para saber en qué
// contexto del maquetador se está pintando (vista previa, pestaña activa,
// petición AJAX del editor). No procesan formularios: van saneados con
// absint()/sanitize_key() y toda rama que cambia estado exige current_user_can(),
// así que un nonce no aplica.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
/**
 * Base widget helpers.
 */

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BasePropertyWidget extends Widget_Base
{
    protected function register_property_control(): void
    {
        $this->add_control(
            'use_current_property',
            [
                'label' => __('Usar inmueble actual', 'homlity-real-estate'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Sí', 'homlity-real-estate'),
                'label_off' => __('No', 'homlity-real-estate'),
                'default' => 'yes',
                'description' => __('Detecta automáticamente el inmueble según la consulta actual.', 'homlity-real-estate'),
            ]
        );

        $this->add_control(
            'property_id',
            [
                'label' => __('ID de la propiedad', 'homlity-real-estate'),
                'type' => Controls_Manager::NUMBER,
                'default' => get_queried_object_id(),
                'condition' => [
                    'use_current_property!' => 'yes',
                ],
            ]
        );
    }

    protected function current_property_id(): int
    {
        $settings = $this->get_settings_for_display();
        $useCurrent = ($settings['use_current_property'] ?? 'yes') === 'yes';
        if (!$useCurrent) {
            $id = isset($settings['property_id']) ? (int) $settings['property_id'] : 0;
            if ($id > 0) {
                return $id;
            }
        }

        $previewId = $this->diviPreviewPropertyId();
        if ($previewId > 0) {
            return $previewId;
        }

        $queriedId = (int) get_queried_object_id();
        if ($queriedId > 0) {
            return $queriedId;
        }

        $postId = (int) get_the_ID();
        if ($postId > 0) {
            return $postId;
        }

        global $post;
        if ($post instanceof \WP_Post) {
            return (int) $post->ID;
        }

        return 0;
    }

    private function diviPreviewPropertyId(): int
    {
        if (!is_user_logged_in()) {
            return 0;
        }

        $templateId = (int) get_option('homlity_plugin_single_template_id', 0);
        if ($templateId <= 0 || !current_user_can('edit_post', $templateId)) {
            return 0;
        }

        $previewId = isset($_REQUEST['homlity_property_preview'])
            ? absint(wp_unslash($_REQUEST['homlity_property_preview']))
            : 0;
        $queriedId = (int) get_queried_object_id();
        $action = isset($_REQUEST['action'])
            ? sanitize_key(wp_unslash((string) $_REQUEST['action']))
            : '';
        $isDiviAjax = wp_doing_ajax()
            && (str_starts_with($action, 'et_fb_') || str_starts_with($action, 'et_builder_'));

        if ($previewId > 0 && ($queriedId === $templateId || $isDiviAjax)) {
            if (get_post_type($previewId) !== 'property') {
                return 0;
            }
            set_transient('homlity_divi_property_preview_' . get_current_user_id(), $previewId, HOUR_IN_SECONDS);
            return $previewId;
        }

        if ($isDiviAjax) {
            $storedId = (int) get_transient('homlity_divi_property_preview_' . get_current_user_id());
            if ($storedId > 0 && get_post_type($storedId) === 'property') {
                return $storedId;
            }
        }

        return 0;
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }
}
