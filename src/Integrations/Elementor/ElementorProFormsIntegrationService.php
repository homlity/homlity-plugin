<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags\AgentImageTag;
use Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags\AgentTextTag;
use Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags\AgentUrlTag;
use Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags\PropertyCodeTag;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Converts Elementor Pro submissions into a plugin-agnostic Homlity event.
 *
 * The real-estate plugin owns the page-builder integration; CRM plugins can
 * consume the normalized event without depending on Elementor classes.
 */
class ElementorProFormsIntegrationService implements ServiceInterface
{
    public function register(): void
    {
        add_action('elementor_pro/forms/new_record', [$this, 'captureSubmission'], 20, 2);
        add_action('elementor/dynamic_tags/register', [$this, 'registerDynamicTags']);
    }

    /** @param mixed $dynamicTags Elementor dynamic-tags manager. */
    public function registerDynamicTags($dynamicTags): void
    {
        if (
            !is_object($dynamicTags)
            || !method_exists($dynamicTags, 'register')
            || !class_exists('\Elementor\Core\DynamicTags\Tag')
        ) {
            return;
        }

        if (method_exists($dynamicTags, 'register_group')) {
            $dynamicTags->register_group('homlity-real-estate', [
                'title' => __('Homlity', 'homlity-real-estate'),
            ]);
        }

        $dynamicTags->register(new PropertyCodeTag());

        // Las etiquetas del asesor sirven tanto en su perfil —/author/{slug}/—
        // como en la ficha de un inmueble: resuelven al asesor de la página en
        // la que estén. Data_Tag solo existe en Elementor 3, de ahí la
        // comprobación aparte.
        if (class_exists('\Elementor\Core\DynamicTags\Data_Tag')) {
            $dynamicTags->register(new AgentTextTag());
            $dynamicTags->register(new AgentUrlTag());
            $dynamicTags->register(new AgentImageTag());
        }
    }

    /**
     * @param mixed $record  Elementor\Pro\Modules\Forms\Classes\Form_Record
     * @param mixed $handler Elementor form AJAX handler
     */
    public function captureSubmission($record, $handler): void
    {
        if (!is_object($record) || !method_exists($record, 'get')) {
            return;
        }

        $rawFields = $record->get('fields');
        if (!is_array($rawFields)) {
            return;
        }

        $fields = [];
        $labels = [];
        $types = [];

        foreach ($rawFields as $key => $field) {
            if (!is_array($field)) {
                continue;
            }

            $id = sanitize_key((string) ($field['id'] ?? $key));
            $type = sanitize_key((string) ($field['type'] ?? ''));
            if (
                $id === ''
                || in_array($type, ['password', 'recaptcha', 'recaptcha_v3', 'honeypot'], true)
                || in_array($id, ['password', 'pass', 'honeypot'], true)
            ) {
                continue;
            }

            $fields[$id] = $this->sanitizeValue($field['value'] ?? '');
            $labels[$id] = sanitize_text_field((string) ($field['title'] ?? $id));
            $types[$id] = $type;
        }

        $settings = $record->get('form_settings');
        $settings = is_array($settings) ? $settings : [];
        $sourceUrl = wp_get_referer() ?: home_url('/');

        $submission = [
            'source'       => 'elementor-pro',
            'form_id'      => sanitize_text_field((string) ($settings['id'] ?? $settings['form_id'] ?? '')),
            'form_name'    => sanitize_text_field((string) ($settings['form_name'] ?? __('Formulario Elementor', 'homlity-real-estate'))),
            'fields'       => $fields,
            'field_labels' => $labels,
            'field_types'  => $types,
            'source_url'   => esc_url_raw($sourceUrl),
            'submitted_at' => current_time('mysql'),
        ];

        /**
         * Fires after an Elementor Pro form passes validation.
         *
         * @param array<string,mixed> $submission Normalized form submission.
         * @param mixed               $record     Original Elementor record.
         * @param mixed               $handler    Original Elementor AJAX handler.
        */
        do_action('homlity_elementor_form_submitted', $submission, $record, $handler);
        do_action('homlity_form_submitted', $submission, [
            'record'  => $record,
            'handler' => $handler,
        ]);
    }

    private function sanitizeValue(mixed $value): string|array
    {
        if (is_array($value)) {
            return array_values(array_map(
                fn($item): string => sanitize_text_field((string) $item),
                $value
            ));
        }

        return sanitize_textarea_field((string) $value);
    }
}
