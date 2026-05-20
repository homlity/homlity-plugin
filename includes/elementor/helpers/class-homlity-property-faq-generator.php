<?php
if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Property_FAQ_Generator
{
    private int $post_id;
    private ?array $data_cache = null;

    public function __construct(int $post_id)
    {
        $this->post_id = $post_id;
    }

    public function get_meta_keys(): array
    {
        return apply_filters('homlity_faq_property_meta_keys', [
            'price_sale'       => '_property_price_sale',
            'currency_sale'    => '_property_currency_sale',
            'price_rent'       => '_property_price_rent',
            'currency_rent'    => '_property_currency_rent',
            'price_admin'      => '_property_price_admin',
            'currency_admin'   => '_property_currency_admin',
            'admin_included'   => '_property_admin_included',
            'area'             => '_property_area',
            'area_built'       => '_property_area_built',
            'area_private'     => '_property_area_private',
            'area_lot'         => '_property_area_lot',
            'bedrooms'         => '_property_bedrooms',
            'bathrooms'        => '_property_bathrooms',
            'parking'          => '_property_parking',
            'stratum'          => '_property_stratum',
            'age'              => '_property_age',
            'condition'        => '_property_condition',
            'code'             => '_property_code',
            'address'          => '_property_address',
            'agent_phone'      => '_property_agent_phone',
        ]);
    }

    public function get_taxonomy_slugs(): array
    {
        return [
            'type'         => apply_filters('homlity_faq_property_type_taxonomy',      'property_type'),
            'operation'    => apply_filters('homlity_faq_operation_taxonomy',           'property_operation'),
            'city'         => apply_filters('homlity_faq_city_taxonomy',               'property_city'),
            'neighborhood' => apply_filters('homlity_faq_zone_taxonomy',               'property_neighborhood'),
            'feature'      => apply_filters('homlity_faq_features_taxonomy',           'property_feature'),
        ];
    }

    public function get_property_data(): array
    {
        if ($this->data_cache !== null) {
            return $this->data_cache;
        }

        if (!$this->post_id || get_post_type($this->post_id) !== apply_filters('homlity_faq_property_post_type', 'property')) {
            return $this->data_cache = [];
        }

        $keys = $this->get_meta_keys();
        $data = [];

        foreach ($keys as $name => $meta_key) {
            $data[$name] = (string) get_post_meta($this->post_id, $meta_key, true);
        }

        $taxes = $this->get_taxonomy_slugs();
        foreach ($taxes as $name => $slug) {
            $data['tax_' . $name] = $this->get_taxonomy_terms_string($slug);
        }

        $data['title']    = (string) get_the_title($this->post_id);
        $data['permalink'] = (string) get_permalink($this->post_id);

        return $this->data_cache = apply_filters('homlity_faq_property_data', $data, $this->post_id);
    }

    private function get_taxonomy_terms_string(string $taxonomy): string
    {
        if (!$this->post_id) {
            return '';
        }
        $terms = get_the_terms($this->post_id, $taxonomy);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }
        return implode(', ', wp_list_pluck($terms, 'name'));
    }

    private function get_display_price(): string
    {
        $data = $this->get_property_data();

        $price    = (float) preg_replace('/[^0-9.]/', '', $data['price_sale'] ?? '');
        $currency = sanitize_text_field($data['currency_sale'] ?? '');

        if ($price <= 0) {
            $price    = (float) preg_replace('/[^0-9.]/', '', $data['price_rent'] ?? '');
            $currency = sanitize_text_field($data['currency_rent'] ?? '');
        }

        if ($price <= 0) {
            return '';
        }

        $formatted = apply_filters(
            'homlity_faq_price_format',
            '$' . number_format_i18n($price, 0),
            $price,
            $currency
        );

        return $currency ? $formatted . ' ' . $currency : $formatted;
    }

    private function pluralize(int $n, string $singular, string $plural): string
    {
        return $n === 1 ? $n . ' ' . $singular : $n . ' ' . $plural;
    }

    private function format_areas(array $data): string
    {
        $parts = [];
        if ((float) $data['area'] > 0) {
            /* translators: %s: formatted total area in square meters. */
            $parts[] = sprintf(__('área total: %s m²', 'homlity-real-estate'), number_format_i18n((float) $data['area'], 0));
        }
        if ((float) $data['area_built'] > 0) {
            /* translators: %s: formatted built area in square meters. */
            $parts[] = sprintf(__('construida: %s m²', 'homlity-real-estate'), number_format_i18n((float) $data['area_built'], 0));
        }
        if ((float) $data['area_private'] > 0) {
            /* translators: %s: formatted private area in square meters. */
            $parts[] = sprintf(__('privada: %s m²', 'homlity-real-estate'), number_format_i18n((float) $data['area_private'], 0));
        }
        if ((float) $data['area_lot'] > 0) {
            /* translators: %s: formatted lot area in square meters. */
            $parts[] = sprintf(__('lote: %s m²', 'homlity-real-estate'), number_format_i18n((float) $data['area_lot'], 0));
        }
        return implode(', ', $parts);
    }

    private function build_location(array $data): string
    {
        $parts = array_filter([
            $data['tax_neighborhood'] ?? '',
            $data['tax_city'] ?? '',
        ]);
        return implode(', ', $parts);
    }

    public function generate_auto_faqs(array $settings): array
    {
        // phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment,WordPress.WP.I18n.UnorderedPlaceholdersText
        $data = $this->get_property_data();
        if (empty($data)) {
            return [];
        }

        $faqs = [];

        // Price
        if (($settings['show_price'] ?? 'yes') === 'yes') {
            $price = $this->get_display_price();
            if ($price !== '') {
                $faqs[] = [
                    'key'      => 'price',
                    'question' => __('¿Cuál es el precio de este inmueble?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('El precio de este inmueble es %s. Este valor puede estar sujeto a cambios, por lo que se recomienda confirmar la información antes de iniciar el proceso.', 'homlity-real-estate'),
                        '<strong>' . esc_html($price) . '</strong>'
                    ),
                ];
            }
        }

        // Operation
        if (($settings['show_operation'] ?? 'yes') === 'yes') {
            $operation = $data['tax_operation'] ?? '';
            if ($operation !== '') {
                $faqs[] = [
                    'key'      => 'operation',
                    'question' => __('¿Este inmueble está disponible para venta o arriendo?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('Este inmueble está disponible para %s. Puedes revisar la información publicada y contactar a la inmobiliaria para validar disponibilidad actual.', 'homlity-real-estate'),
                        '<strong>' . esc_html($operation) . '</strong>'
                    ),
                ];
            }
        }

        // Location
        if (($settings['show_location'] ?? 'yes') === 'yes') {
            $location = $this->build_location($data);
            if ($location !== '') {
                $faqs[] = [
                    'key'      => 'location',
                    'question' => __('¿Dónde está ubicado este inmueble?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('Este inmueble está ubicado en %s. La ubicación publicada corresponde a la información disponible en la ficha del inmueble.', 'homlity-real-estate'),
                        '<strong>' . esc_html($location) . '</strong>'
                    ),
                ];
            }
        }

        // Type
        if (($settings['show_type'] ?? 'yes') === 'yes') {
            $type = $data['tax_type'] ?? '';
            if ($type !== '') {
                $faqs[] = [
                    'key'      => 'type',
                    'question' => __('¿Qué tipo de inmueble es?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('Este inmueble corresponde a un %s, publicado dentro del inventario inmobiliario disponible.', 'homlity-real-estate'),
                        '<strong>' . esc_html($type) . '</strong>'
                    ),
                ];
            }
        }

        // Area
        if (($settings['show_area'] ?? 'yes') === 'yes') {
            $areas = $this->format_areas($data);
            if ($areas !== '') {
                $faqs[] = [
                    'key'      => 'area',
                    'question' => __('¿Cuál es el área del inmueble?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('El inmueble cuenta con las siguientes áreas: %s.', 'homlity-real-estate'),
                        $areas
                    ),
                ];
            }
        }

        // Bedrooms
        if (($settings['show_bedrooms'] ?? 'yes') === 'yes') {
            $bedrooms = (int) $data['bedrooms'];
            if ($bedrooms > 0) {
                $faqs[] = [
                    'key'      => 'bedrooms',
                    'question' => __('¿Cuántas habitaciones tiene este inmueble?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('Este inmueble cuenta con %s.', 'homlity-real-estate'),
                        '<strong>' . esc_html($this->pluralize($bedrooms, __('habitación', 'homlity-real-estate'), __('habitaciones', 'homlity-real-estate'))) . '</strong>'
                    ),
                ];
            }
        }

        // Bathrooms
        if (($settings['show_bathrooms'] ?? 'yes') === 'yes') {
            $bathrooms = (int) $data['bathrooms'];
            if ($bathrooms > 0) {
                $faqs[] = [
                    'key'      => 'bathrooms',
                    'question' => __('¿Cuántos baños tiene este inmueble?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('Este inmueble cuenta con %s.', 'homlity-real-estate'),
                        '<strong>' . esc_html($this->pluralize($bathrooms, __('baño', 'homlity-real-estate'), __('baños', 'homlity-real-estate'))) . '</strong>'
                    ),
                ];
            }
        }

        // Parking
        if (($settings['show_parking'] ?? 'yes') === 'yes') {
            $parking = (int) $data['parking'];
            if ($parking > 0) {
                $faqs[] = [
                    'key'      => 'parking',
                    'question' => __('¿El inmueble tiene parqueadero?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('Sí, este inmueble cuenta con %s.', 'homlity-real-estate'),
                        '<strong>' . esc_html($this->pluralize($parking, __('parqueadero', 'homlity-real-estate'), __('parqueaderos', 'homlity-real-estate'))) . '</strong>'
                    ),
                ];
            }
        }

        // Admin fee
        if (($settings['show_admin'] ?? 'yes') === 'yes') {
            $admin_price = (float) preg_replace('/[^0-9.]/', '', $data['price_admin'] ?? '');
            if ($admin_price > 0) {
                $currency  = sanitize_text_field($data['currency_admin'] ?? $data['currency_sale'] ?? '');
                $formatted = '$' . number_format_i18n($admin_price, 0);
                if ($currency) {
                    $formatted .= ' ' . $currency;
                }
                $included = (bool) $data['admin_included'];
                $suffix   = $included ? ' ' . __('(incluida en el precio).', 'homlity-real-estate') : '.';
                $faqs[]   = [
                    'key'      => 'admin',
                    'question' => __('¿Cuál es el valor de la administración?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('El valor de administración publicado para este inmueble es de %1$s%2$s', 'homlity-real-estate'),
                        '<strong>' . esc_html($formatted) . '</strong>',
                        $suffix
                    ),
                ];
            }
        }

        // Stratum
        if (($settings['show_stratum'] ?? 'yes') === 'yes') {
            $stratum = (int) $data['stratum'];
            if ($stratum > 0) {
                $faqs[] = [
                    'key'      => 'stratum',
                    'question' => __('¿De qué estrato es este inmueble?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('Este inmueble corresponde al estrato %s, según la información publicada.', 'homlity-real-estate'),
                        '<strong>' . esc_html((string) $stratum) . '</strong>'
                    ),
                ];
            }
        }

        // Features
        if (($settings['show_features'] ?? 'yes') === 'yes') {
            $features = $data['tax_feature'] ?? '';
            if ($features !== '') {
                $faqs[] = [
                    'key'      => 'features',
                    'question' => __('¿Qué características tiene este inmueble?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('Este inmueble cuenta con: %s. La disponibilidad de cada característica debe validarse con la inmobiliaria.', 'homlity-real-estate'),
                        '<strong>' . esc_html($features) . '</strong>'
                    ),
                ];
            }
        }

        // Code
        if (($settings['show_code'] ?? 'yes') === 'yes') {
            $code = $data['code'] ?? '';
            if ($code !== '') {
                $faqs[] = [
                    'key'      => 'code',
                    'question' => __('¿Cuál es el código de este inmueble?', 'homlity-real-estate'),
                    'answer'   => sprintf(
                        __('El código de referencia de este inmueble es %s. Puedes usarlo al contactar a la inmobiliaria para solicitar información más rápida.', 'homlity-real-estate'),
                        '<strong>' . esc_html($code) . '</strong>'
                    ),
                ];
            }
        }

        // Contact
        if (($settings['show_contact'] ?? 'yes') === 'yes') {
            $faqs[] = [
                'key'      => 'contact',
                'question' => __('¿Cómo puedo solicitar más información o agendar una visita?', 'homlity-real-estate'),
                'answer'   => __('Puedes contactar a la inmobiliaria a través de los canales disponibles en esta página para solicitar más información, confirmar disponibilidad o agendar una visita.', 'homlity-real-estate'),
            ];
        }

        // phpcs:enable
        return apply_filters('homlity_faq_auto_questions', $faqs, $this->post_id, $settings);
    }

    public function get_manual_faqs(array $repeater_items): array
    {
        $faqs = [];
        foreach ($repeater_items as $item) {
            if (($item['faq_visible'] ?? 'yes') !== 'yes') {
                continue;
            }
            $q = sanitize_text_field($item['faq_question'] ?? '');
            $a = wp_kses_post($item['faq_answer'] ?? '');
            if ($q === '' || $a === '') {
                continue;
            }
            $faqs[] = [
                'key'      => 'manual_' . sanitize_key($q),
                'question' => $q,
                'answer'   => $a,
                'position' => ($item['faq_position'] ?? 'after'),
            ];
        }
        return apply_filters('homlity_faq_manual_questions', $faqs, $this->post_id);
    }

    public function combine_faqs(array $auto, array $manual): array
    {
        $before = array_filter($manual, static fn($m) => ($m['position'] ?? 'after') === 'before');
        $after  = array_filter($manual, static fn($m) => ($m['position'] ?? 'after') !== 'before');

        $combined = array_values(array_merge(array_values($before), $auto, array_values($after)));
        return $this->deduplicate_faqs($combined);
    }

    public function deduplicate_faqs(array $faqs): array
    {
        $seen   = [];
        $result = [];
        foreach ($faqs as $faq) {
            $key = $this->normalize_for_comparison($faq['question']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[]   = $faq;
            }
        }
        return $result;
    }

    private function normalize_for_comparison(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', '¿', '?', '¡', '!'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n', '', '', '', ''],
            $text
        );
        return preg_replace('/\s+/', ' ', trim($text));
    }
}
