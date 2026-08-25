<?php
// Los superglobales que se leen en este archivo sirven sólo para saber en qué
// contexto del maquetador se está pintando (vista previa, pestaña activa,
// petición AJAX del editor). No procesan formularios: van saneados con
// absint()/sanitize_key() y toda rama que cambia estado exige current_user_can(),
// así que un nonce no aplica.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if (!defined('ABSPATH')) {
    exit;
}

/** Elementor dynamic tag that exposes the current property's Homlity code. */
class PropertyCodeTag extends Tag
{
    public function get_name(): string
    {
        return 'homlity-property-code';
    }

    public function get_title(): string
    {
        return __('Código de propiedad Homlity', 'homlity-real-estate');
    }

    public function get_group(): string
    {
        return 'homlity-real-estate';
    }

    public function get_categories(): array
    {
        return [TagsModule::TEXT_CATEGORY];
    }

    protected function render(): void
    {
        echo esc_html($this->resolvePropertyCode());
    }

    private function resolvePropertyCode(): string
    {
        $postId = get_queried_object_id();

        if ($postId <= 0 || get_post_type($postId) !== 'property') {
            $postId = (int) get_the_ID();
        }

        if (($postId <= 0 || get_post_type($postId) !== 'property') && isset($_GET['post'])) {
            $candidate = absint($_GET['post']);
            if ($candidate > 0 && get_post_type($candidate) === 'property') {
                $postId = $candidate;
            }
        }

        if ($postId <= 0 || get_post_type($postId) !== 'property') {
            return '';
        }

        return sanitize_text_field((string) get_post_meta($postId, '_property_code', true));
    }
}
