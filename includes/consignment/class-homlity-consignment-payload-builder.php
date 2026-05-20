<?php
/**
 * Homlity Consignment Payload Builder.
 *
 * Converts the multi-step form data array into a normalized Homlity payload
 * compatible with PropertyUpsertService::upsert() and the REST sync schema.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Consignment_Payload_Builder
{
    /**
     * @param array $form  The full form data from the React app (keyed by step name).
     * @param array $opts  Plugin options from Homlity_Consignment_Manager::options().
     * @return array       Normalized Homlity payload.
     */
    public static function build(array $form, array $opts): array
    {
        $contact  = (array) ($form['contact']          ?? []);
        $op       = (array) ($form['operation']        ?? []);
        $loc      = (array) ($form['location']         ?? []);
        $details  = (array) ($form['property_details'] ?? []);
        $areas    = (array) ($form['areas']            ?? []);
        $features = (array) ($form['features']         ?? []);
        $media    = (array) ($form['media']            ?? []);
        $advisor  = (array) ($form['advisor']          ?? []);

        $provider = sanitize_key(
            apply_filters('homlity_consignment_default_provider', $opts['provider'] ?? 'public-consignment')
        );

        // Generate a unique, collision-resistant external ID
        $external_id = 'consignment-' . time() . '-' . wp_generate_password(10, false, false);

        // ── Operation ──────────────────────────────────────────────────────
        $operation_raw = trim($op['operation'] ?? '');
        $operations    = self::resolveOperations($operation_raw);

        // ── Features ──────────────────────────────────────────────────────
        $selected_features = array_values(array_filter(array_map('trim', (array) ($features['selected'] ?? []))));
        $custom_features   = array_values(array_filter(array_map('sanitize_text_field', (array) ($features['custom'] ?? []))));
        $all_features      = array_unique(array_merge($selected_features, $custom_features));

        // ── Media ──────────────────────────────────────────────────────────
        $gallery       = array_values(array_filter(array_map('esc_url_raw', (array) ($media['gallery'] ?? []))));
        $featured_url  = esc_url_raw($media['featured_image'] ?? ($gallery[0] ?? ''));
        $videos        = array_values(array_filter(array_map('esc_url_raw', (array) ($media['videos'] ?? []))));
        $tours         = array_values(array_filter(array_map('esc_url_raw', (array) ($media['tour_360'] ?? []))));
        $brochure      = esc_url_raw($media['brochure'] ?? '');

        // ── Advisor vs Owner as contact ────────────────────────────────────
        $consignant_type = sanitize_key($contact['consignant_type'] ?? 'owner');
        $needs_advisor   = in_array($consignant_type, ['advisor', 'agency', 'builder'], true);

        if ($needs_advisor && !empty($advisor['name'])) {
            $advisor_payload = [
                'external_id' => 'adv-' . sanitize_key($advisor['email'] ?? (string) time()),
                'name'        => sanitize_text_field($advisor['name'] ?? ''),
                'email'       => sanitize_email($advisor['email'] ?? ''),
                'phone'       => sanitize_text_field($advisor['phone'] ?? ''),
                'photo'       => esc_url_raw($advisor['photo'] ?? ''),
                'role'        => sanitize_text_field($advisor['role'] ?? ''),
            ];
        } else {
            // Use consignant data as contact
            $advisor_payload = [
                'external_id' => 'owner-' . sanitize_key($contact['email'] ?? (string) time()),
                'name'        => sanitize_text_field($contact['name'] ?? ''),
                'email'       => sanitize_email($contact['email'] ?? ''),
                'phone'       => sanitize_text_field($contact['phone'] ?? $contact['whatsapp'] ?? ''),
                'photo'       => '',
                'role'        => self::consignantTypeLabel($consignant_type),
            ];
        }

        // ── Geographic taxonomy ────────────────────────────────────────────
        $country      = trim($loc['country'] ?? '');
        $state        = trim($loc['state'] ?? '');
        $city         = trim($loc['city'] ?? '');
        $neighborhood = trim($loc['neighborhood'] ?? '');

        // ── Title auto-fill if empty ───────────────────────────────────────
        $title = trim($details['title'] ?? '');
        if ($title === '' && !empty($details['property_type'])) {
            $op_label  = !empty($operations) ? mb_strtolower($operations[0]) : '';
            $geo_label = $neighborhood ?: $city;
            $title     = trim(implode(' en ', array_filter([$details['property_type'], $op_label, $geo_label])));
        }

        // ── Parking advanced meta (stored as extra meta, not in base schema) ─
        $parking_advanced = [
            'private'    => (int) ($areas['parking'] ?? 0),
            'communal'   => !empty($areas['communal_parking']),
            'visitors'   => !empty($areas['visitor_parking']),
            'motorcycle' => !empty($areas['motorcycle_parking']),
            'car'        => !empty($areas['car_parking']),
        ];

        // ── Assemble payload ───────────────────────────────────────────────
        $payload = [
            'external' => [
                'source'     => $provider,
                'id'         => $external_id,
                'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'raw'        => [
                    'consignant_type' => $consignant_type,
                    'parking_advanced'=> $parking_advanced,
                    'contact_phone'   => sanitize_text_field($contact['phone'] ?? ''),
                    'contact_whatsapp'=> sanitize_text_field($contact['whatsapp'] ?? ''),
                    'contact_name'    => sanitize_text_field($contact['name'] ?? ''),
                    'contact_email'   => sanitize_email($contact['email'] ?? ''),
                    'show_exact_address' => !empty($loc['show_exact_address']),
                    'location_reference' => sanitize_text_field($loc['location_reference'] ?? ''),
                    'commercial_note'    => sanitize_textarea_field($op['commercial_note'] ?? ''),
                    'photo_note'         => sanitize_textarea_field($media['photo_note'] ?? ''),
                ],
            ],
            'post' => [
                'title'             => wp_strip_all_tags($title),
                'description'       => wp_kses_post($details['description'] ?? ''),
                'short_description' => sanitize_text_field($details['short_description'] ?? ''),
                'status'            => 'pending', // always overridden by controller
            ],
            'location' => [
                'address'   => sanitize_text_field($loc['address'] ?? ''),
                'latitude'  => (float) ($loc['latitude'] ?? 0),
                'longitude' => (float) ($loc['longitude'] ?? 0),
            ],
            'pricing' => [
                'sale_price'    => self::cleanPrice($op['sale_price'] ?? ''),
                'sale_currency' => sanitize_key($op['sale_currency'] ?? $opts['default_currency'] ?? 'COP'),
                'rent_price'    => self::cleanPrice($op['rent_price'] ?? ''),
                'rent_currency' => sanitize_key($op['rent_currency'] ?? $opts['default_currency'] ?? 'COP'),
                'admin_price'   => self::cleanPrice($op['admin_price'] ?? ''),
                'admin_currency'=> sanitize_key($op['admin_currency'] ?? $opts['default_currency'] ?? 'COP'),
                'admin_included'=> !empty($op['admin_included']),
            ],
            'metrics' => [
                'area'         => (string) self::cleanDecimal($areas['area'] ?? ''),
                'area_lot'     => (string) self::cleanDecimal($areas['area_lot'] ?? ''),
                'area_private' => (string) self::cleanDecimal($areas['area_private'] ?? ''),
                'area_built'   => (string) self::cleanDecimal($areas['area_built'] ?? ''),
                'bedrooms'     => (int) ($areas['bedrooms'] ?? 0),
                'bathrooms'    => (int) ($areas['bathrooms'] ?? 0),
                'parking'      => (int) ($areas['parking'] ?? 0),
                'condition'    => sanitize_key($details['condition'] ?? ''),
                'year_built'   => (int) ($details['year_built'] ?? 0),
                'code'         => sanitize_text_field($details['code'] ?? ''),
                'featured'     => false,
            ],
            'taxonomy' => [
                'property_operation'    => $operations,
                'property_type'         => array_filter([sanitize_text_field($details['property_type'] ?? '')]),
                'property_category'     => array_filter([sanitize_text_field($details['category'] ?? '')]),
                'property_feature'      => array_map('sanitize_text_field', $all_features),
                'property_country'      => array_filter([$country]),
                'property_state'        => array_filter([$state]),
                'property_city'         => array_filter([$city]),
                'property_neighborhood' => array_filter([$neighborhood]),
            ],
            'media' => [
                'gallery'            => $gallery,
                'featured_image_url' => $featured_url,
                'videos'             => $videos,
                'tour_360'           => $tours,
                'photos_360'         => [],
                'brochure'           => $brochure,
            ],
            'advisor' => $advisor_payload,
        ];

        return apply_filters('homlity_consignment_payload_after_build', $payload, $form, $opts);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private static function resolveOperations(string $raw): array
    {
        $ops = [];
        if (stripos($raw, 'Venta') !== false || $raw === 'Venta') {
            $ops[] = 'Venta';
        }
        if (stripos($raw, 'Arriendo') !== false || $raw === 'Arriendo') {
            $ops[] = 'Arriendo';
        }
        if (empty($ops) && $raw !== '') {
            $ops[] = sanitize_text_field($raw);
        }
        return $ops;
    }

    private static function cleanPrice(string $raw): string
    {
        $clean = preg_replace('/[^0-9.]/', '', $raw);
        return $clean !== '' ? $clean : '';
    }

    private static function cleanDecimal($val): float
    {
        $clean = preg_replace('/[^0-9.]/', '', (string) $val);
        return $clean !== '' ? (float) $clean : 0.0;
    }

    private static function consignantTypeLabel(string $type): string
    {
        return [
            'owner'      => 'Propietario',
            'advisor'    => 'Asesor inmobiliario',
            'agency'     => 'Inmobiliaria',
            'builder'    => 'Constructor',
            'authorized' => 'Persona autorizada',
        ][$type] ?? 'Consignante';
    }
}
