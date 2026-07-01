<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
/**
 * Homlity Consignment REST Controller.
 *
 * Endpoints (all under namespace homlity/v1):
 *   GET  /consignment/config
 *   POST /consignment/validate-step
 *   POST /consignment/submit
 *   POST /consignment/upload
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Consignment_Rest_Controller
{
    const NS   = 'homlity/v1';
    const BASE = 'consignment';

    // ── Route registration ────────────────────────────────────────────────

    public static function register_routes(): void
    {
        $self = self::class;

        register_rest_route(self::NS, '/' . self::BASE . '/config', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$self, 'getConfig'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/' . self::BASE . '/validate-step', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$self, 'validateStep'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/' . self::BASE . '/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$self, 'submit'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/' . self::BASE . '/upload', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$self, 'upload'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── GET /config ───────────────────────────────────────────────────────

    public static function getConfig(WP_REST_Request $request): WP_REST_Response
    {
        $opts = Homlity_Consignment_Manager::options();

        $config = [
            'provider'        => apply_filters('homlity_consignment_default_provider', $opts['provider']),
            'default_currency'=> $opts['default_currency'],
            'default_country' => $opts['default_country'],
            'default_city'    => $opts['default_city'],
            'operations'      => apply_filters(
                'homlity_consignment_allowed_operations',
                self::terms('property_operation') ?: ['Venta', 'Arriendo']
            ),
            'property_types'  => apply_filters('homlity_consignment_allowed_property_types', self::terms('property_type')),
            'categories'      => self::terms('property_category'),
            'features'        => apply_filters('homlity_consignment_allowed_features', self::terms('property_feature')),
            'countries'       => self::terms('property_country'),
            'states'          => self::terms('property_state'),
            'cities'          => self::terms('property_city'),
            'neighborhoods'   => self::terms('property_neighborhood'),
            'currencies'      => ['COP', 'USD', 'EUR', 'MXN', 'ARS', 'PEN', 'CLP', 'BRL'],
            'conditions'      => [
                'nuevo'        => 'Nuevo',
                'usado'        => 'Usado',
                'remodelado'   => 'Remodelado',
                'obra_gris'    => 'Obra gris',
                'obra_blanca'  => 'Obra blanca',
                'sobre_planos' => 'Sobre planos',
            ],
            'consignant_types'=> [
                'owner'      => 'Propietario',
                'advisor'    => 'Asesor inmobiliario',
                'agency'     => 'Inmobiliaria',
                'builder'    => 'Constructor / Proyecto',
                'authorized' => 'Persona autorizada',
            ],
            'upload_limits'   => apply_filters('homlity_consignment_upload_limits', [
                'max_files'       => (int) $opts['max_images'],
                'max_file_size'   => (int) $opts['max_image_size'] * 1024 * 1024,
                'max_brochure_size'=> (int) $opts['max_brochure_size'] * 1024 * 1024,
                'allowed_types'   => apply_filters(
                    'homlity_consignment_allowed_mime_types',
                    ['image/jpeg', 'image/png', 'image/webp']
                ),
            ]),
            'required_fields' => [
                'require_coordinates' => (bool) $opts['require_coordinates'],
                'require_image'       => (bool) $opts['require_image'],
                'require_gallery'     => (bool) $opts['require_gallery'],
                'allow_advisors'      => (bool) $opts['allow_advisors'],
                'allow_agencies'      => (bool) $opts['allow_agencies'],
                'allow_owners'        => (bool) $opts['allow_owners'],
            ],
            'texts'  => [
                'form_title'    => $opts['form_title'],
                'form_subtitle' => $opts['form_subtitle'],
                'welcome_text'  => $opts['form_welcome_text'],
                'btn_next'      => $opts['btn_next_text'],
                'btn_prev'      => $opts['btn_prev_text'],
                'btn_submit'    => $opts['btn_submit_text'],
                'success'       => apply_filters('homlity_consignment_success_message', $opts['success_message']),
                'error'         => apply_filters('homlity_consignment_error_message', $opts['error_message']),
                'data_policy'   => $opts['data_policy_text'],
                'authorization' => $opts['authorization_text'],
            ],
            'styles' => [
                'primary_color'   => $opts['primary_color'],
                'secondary_color' => $opts['secondary_color'],
                'bg_color'        => $opts['bg_color'],
                'text_color'      => $opts['text_color'],
                'border_radius'   => $opts['border_radius'],
            ],
            'nonce' => wp_create_nonce('wp_rest'),
        ];

        return new WP_REST_Response(
            apply_filters('homlity_consignment_form_config', $config),
            200
        );
    }

    // ── POST /validate-step ───────────────────────────────────────────────

    public static function validateStep(WP_REST_Request $request): WP_REST_Response
    {
        if (!self::verifyNonce($request)) {
            return new WP_REST_Response(['ok' => false, 'errors' => ['_nonce' => 'Sesión inválida. Recarga la página e intenta de nuevo.']], 403);
        }

        $body = (array) $request->get_json_params();
        $step = sanitize_key($body['step'] ?? '');
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        if ($step === '') {
            return new WP_REST_Response(['ok' => false, 'errors' => ['step' => 'Paso requerido.']], 400);
        }

        $errors = Homlity_Consignment_Validator::validateStep($step, $data);

        return new WP_REST_Response(['ok' => empty($errors), 'errors' => $errors], 200);
    }

    // ── POST /submit ──────────────────────────────────────────────────────

    public static function submit(WP_REST_Request $request): WP_REST_Response
    {
        if (!self::verifyNonce($request)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Sesión inválida. Recarga la página e intenta de nuevo.'], 403);
        }

        $body = (array) $request->get_json_params();
        if (empty($body)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Datos vacíos.'], 400);
        }

        $opts = Homlity_Consignment_Manager::options();

        // Honeypot
        if ($opts['enable_honeypot'] && !empty($body['_hp'])) {
            return new WP_REST_Response(['ok' => true, 'message' => $opts['success_message']], 200);
        }

        // Rate limit
        if ($opts['enable_rate_limit']) {
            $ip_key = 'homlity_cr_' . hash('sha256', sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? 'x'))));
            $count  = (int) get_transient($ip_key);
            $max    = apply_filters('homlity_consignment_rate_limit_allowed', (int) $opts['rate_limit_per_hour']);
            if ($count >= $max) {
                return new WP_REST_Response([
                    'ok'      => false,
                    'message' => 'Has excedido el límite de envíos por hora. Por favor intenta más tarde.',
                ], 429);
            }
        }

        do_action('homlity_consignment_before_validate', $body);

        // Full validation
        $errors = Homlity_Consignment_Validator::validateAll($body);

        do_action('homlity_consignment_after_validate', $body, $errors);

        if (!empty($errors)) {
            do_action('homlity_consignment_failed', $errors, $body);
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'Hay campos obligatorios pendientes o inválidos. Revisa el formulario e inténtalo de nuevo.',
                'errors'  => $errors,
            ], 422);
        }

        // Spam filter hook (reCAPTCHA, Akismet, etc.)
        if (apply_filters('homlity_consignment_spam_check', false, $body, $request)) {
            return new WP_REST_Response(['ok' => true, 'message' => $opts['success_message']], 200);
        }

        // Build payload
        $payload = Homlity_Consignment_Payload_Builder::build($body, $opts);
        $payload = apply_filters('homlity_consignment_payload', $payload, $body);
        $payload = apply_filters('homlity_consignment_payload_before_create', $payload, $body);

        do_action('homlity_consignment_before_create_property', $payload);

        // Create property using existing upsert service
        $result  = self::createProperty($payload, $opts);
        $post_id = is_int($result) ? $result : 0;

        if ($post_id <= 0) {
            return new WP_REST_Response(['ok' => false, 'message' => $opts['error_message']], 500);
        }

        $post_id = (int) apply_filters('homlity_consignment_created_post_id', $post_id, $payload);

        do_action('homlity_consignment_after_create_property', $post_id, $payload);

        // Increment rate limit
        if ($opts['enable_rate_limit']) {
            set_transient($ip_key, $count + 1, HOUR_IN_SECONDS);
        }

        // Log
        if ($opts['enable_logs']) {
            self::log($post_id, $body, $opts);
        }

        // Notifications
        if ($opts['notify_admin']) {
            Homlity_Consignment_Notifications::notifyAdmin($post_id, $body, $payload);
        }
        if ($opts['notify_consignant']) {
            Homlity_Consignment_Notifications::notifyConsignant($body, $opts);
        }

        return new WP_REST_Response([
            'ok'      => true,
            'post_id' => $post_id,
            'message' => $opts['success_message'],
        ], 200);
    }

    // ── POST /upload ──────────────────────────────────────────────────────

    public static function upload(WP_REST_Request $request): WP_REST_Response
    {
        if (!self::verifyNonce($request)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Sesión inválida.'], 403);
        }

        // Rate limit uploads separately
        $ip_key       = 'homlity_cu_' . hash('sha256', sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? 'x'))));
        $upload_count = (int) get_transient($ip_key);
        if ($upload_count >= 100) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Límite de carga excedido.'], 429);
        }

        $files     = $request->get_file_params();
        $file_type = in_array($request->get_param('type'), ['image', 'brochure'], true)
            ? $request->get_param('type')
            : 'image';

        if (empty($files['file'])) {
            return new WP_REST_Response(['ok' => false, 'message' => 'No se recibió ningún archivo.'], 400);
        }

        $result = Homlity_Consignment_Media_Handler::upload($files['file'], $file_type);

        set_transient($ip_key, $upload_count + 1, HOUR_IN_SECONDS);

        if (is_wp_error($result)) {
            return new WP_REST_Response(['ok' => false, 'message' => $result->get_error_message()], 400);
        }

        return new WP_REST_Response(['ok' => true, 'url' => $result['url'], 'id' => $result['id']], 200);
    }

    // ── Property creation ─────────────────────────────────────────────────

    private static function createProperty(array $payload, array $opts): int
    {
        // Force safe status — never publish from a public form unless explicitly allowed
        $allowed_statuses = ['draft', 'pending'];
        $status = in_array($opts['default_status'], $allowed_statuses, true)
            ? $opts['default_status']
            : 'pending';

        if (apply_filters('homlity_consignment_allow_publish', false)) {
            $allowed_statuses[] = 'publish';
        }

        $payload['post']['status'] = $status;

        // Try the existing PropertyUpsertService (PSR-4 autoloaded)
        $upsert_class = 'Homlity\\PluginInmobiliario\\Integrations\\CRM\\PropertyUpsertService';
        if (class_exists($upsert_class)) {
            try {
                $service = new $upsert_class();
                $result  = $service->upsert($payload);
                if (!empty($result['ok']) && !empty($result['post_id'])) {
                    // Stamp consignment meta
                    update_post_meta((int) $result['post_id'], '_consignment_source', 'public-form');
                    update_post_meta((int) $result['post_id'], '_consignment_submitted_at', current_time('mysql'));
                    return (int) $result['post_id'];
                }
            } catch (\Throwable $e) {
                // Fall through to direct creation
            }
        }

        return self::createPropertyDirect($payload, $status);
    }

    /** Fallback: direct wp_insert_post path following plugin meta conventions. */
    private static function createPropertyDirect(array $payload, string $status): int
    {
        $post_id = wp_insert_post([
            'post_type'    => 'property',
            'post_title'   => wp_strip_all_tags($payload['post']['title'] ?? ''),
            'post_content' => wp_kses_post($payload['post']['description'] ?? ''),
            'post_excerpt' => sanitize_text_field($payload['post']['short_description'] ?? ''),
            'post_status'  => $status,
        ], true);

        if (is_wp_error($post_id) || $post_id <= 0) {
            return 0;
        }

        // Scalar meta
        $loc     = $payload['location'] ?? [];
        $pricing = $payload['pricing'] ?? [];
        $metrics = $payload['metrics'] ?? [];
        $advisor = $payload['advisor'] ?? [];
        $media   = $payload['media'] ?? [];
        $ext     = $payload['external'] ?? [];

        $metas = [
            '_property_address'        => sanitize_text_field($loc['address'] ?? ''),
            '_property_latitude'       => (string) ($loc['latitude'] ?? ''),
            '_property_longitude'      => (string) ($loc['longitude'] ?? ''),
            '_property_price_sale'     => sanitize_text_field($pricing['sale_price'] ?? ''),
            '_property_currency_sale'  => sanitize_key($pricing['sale_currency'] ?? 'COP'),
            '_property_price_rent'     => sanitize_text_field($pricing['rent_price'] ?? ''),
            '_property_currency_rent'  => sanitize_key($pricing['rent_currency'] ?? 'COP'),
            '_property_price_admin'    => sanitize_text_field($pricing['admin_price'] ?? ''),
            '_property_currency_admin' => sanitize_key($pricing['admin_currency'] ?? 'COP'),
            '_property_admin_included' => empty($pricing['admin_included']) ? '0' : '1',
            '_property_area'           => (string) ($metrics['area'] ?? ''),
            '_property_area_lot'       => (string) ($metrics['area_lot'] ?? ''),
            '_property_area_private'   => (string) ($metrics['area_private'] ?? ''),
            '_property_area_built'     => (string) ($metrics['area_built'] ?? ''),
            '_property_bedrooms'       => (string) (int) ($metrics['bedrooms'] ?? 0),
            '_property_bathrooms'      => (string) (int) ($metrics['bathrooms'] ?? 0),
            '_property_parking'        => (string) (int) ($metrics['parking'] ?? 0),
            '_property_condition'      => sanitize_key($metrics['condition'] ?? ''),
            '_property_age'            => (string) (int) ($metrics['year_built'] ?? 0),
            '_property_code'           => sanitize_text_field($metrics['code'] ?? ''),
            '_property_featured'       => '0',
            '_property_videos'         => wp_json_encode((array) ($media['videos'] ?? [])),
            '_property_tour_360'       => wp_json_encode((array) ($media['tour_360'] ?? [])),
            '_property_photos_360'     => wp_json_encode((array) ($media['photos_360'] ?? [])),
            '_property_brochure'       => esc_url_raw($media['brochure'] ?? ''),
            '_property_agent_name'     => sanitize_text_field($advisor['name'] ?? ''),
            '_property_agent_email'    => sanitize_email($advisor['email'] ?? ''),
            '_property_agent_phone'    => sanitize_text_field($advisor['phone'] ?? ''),
            '_consignment_source'      => 'public-form',
            '_consignment_external_id' => sanitize_text_field($ext['id'] ?? ''),
            '_consignment_submitted_at'=> current_time('mysql'),
            '_property_external_source'=> sanitize_key($ext['source'] ?? ''),
            '_property_external_id'    => sanitize_text_field($ext['id'] ?? ''),
        ];

        foreach ($metas as $key => $val) {
            if ($val !== '' && $val !== '[]' && $val !== 'null') {
                update_post_meta($post_id, $key, $val);
            }
        }

        // Gallery + featured image
        $gallery = array_filter(array_map('esc_url_raw', (array) ($media['gallery'] ?? [])));
        if (!empty($gallery)) {
            update_post_meta($post_id, '_property_gallery', implode(',', $gallery));
            $featured_url = esc_url_raw($media['featured_image_url'] ?? $gallery[0]);
            if ($featured_url) {
                update_post_meta($post_id, '_property_featured_image_url', $featured_url);
                $att_id = attachment_url_to_postid($featured_url);
                if ($att_id) {
                    set_post_thumbnail($post_id, $att_id);
                }
            }
        }

        // Taxonomies (flat)
        $flat_taxes = [
            'property_operation' => (array) ($payload['taxonomy']['property_operation'] ?? []),
            'property_type'      => (array) ($payload['taxonomy']['property_type'] ?? []),
            'property_category'  => (array) ($payload['taxonomy']['property_category'] ?? []),
            'property_feature'   => (array) ($payload['taxonomy']['property_feature'] ?? []),
        ];
        foreach ($flat_taxes as $tax => $names) {
            self::setTermsByName($post_id, $tax, $names, false);
        }

        // Geographic hierarchy
        $geo = [
            'property_country'      => (array) ($payload['taxonomy']['property_country'] ?? []),
            'property_state'        => (array) ($payload['taxonomy']['property_state'] ?? []),
            'property_city'         => (array) ($payload['taxonomy']['property_city'] ?? []),
            'property_neighborhood' => (array) ($payload['taxonomy']['property_neighborhood'] ?? []),
        ];
        $parent = 0;
        foreach ($geo as $tax => $names) {
            $name = trim($names[0] ?? '');
            if ($name === '' || !taxonomy_exists($tax)) {
                continue;
            }
            $existing = get_terms(['taxonomy' => $tax, 'name' => $name, 'parent' => $parent, 'hide_empty' => false, 'number' => 1]);
            if (!empty($existing) && !is_wp_error($existing)) {
                $term = $existing[0];
            } else {
                $r = wp_insert_term($name, $tax, ['parent' => $parent]);
                if (is_wp_error($r)) {
                    continue;
                }
                $term = get_term($r['term_id'], $tax);
            }
            wp_set_object_terms($post_id, [(int) $term->term_id], $tax, true);
            $parent = (int) $term->term_id;
        }

        return $post_id;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private static function setTermsByName(int $post_id, string $taxonomy, array $names, bool $append): void
    {
        if (!taxonomy_exists($taxonomy)) {
            return;
        }
        $ids = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $term = get_term_by('name', $name, $taxonomy);
            if (!$term) {
                $r = wp_insert_term($name, $taxonomy);
                if (!is_wp_error($r)) {
                    $ids[] = (int) $r['term_id'];
                }
            } else {
                $ids[] = (int) $term->term_id;
            }
        }
        if (!empty($ids)) {
            wp_set_object_terms($post_id, $ids, $taxonomy, $append);
        }
    }

    private static function verifyNonce(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');
        return (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    private static function terms(string $taxonomy): array
    {
        if (!taxonomy_exists($taxonomy)) {
            return [];
        }
        $items = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 500]);
        if (is_wp_error($items) || empty($items)) {
            return [];
        }
        return wp_list_pluck($items, 'name');
    }

    private static function log(int $post_id, array $data, array $opts): void
    {
        $entry = [
            'date'            => current_time('mysql'),
            'post_id'         => $post_id,
            'source'          => $opts['provider'] ?? 'public-consignment',
            'consignant_type' => sanitize_key($data['contact']['consignant_type'] ?? ''),
            'email'           => sanitize_email($data['contact']['email'] ?? ''),
            'result'          => 'success',
            'ip_hash'         => hash('sha256', sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')))),
        ];

        $logs = (array) get_option('homlity_consignment_logs', []);
        $logs[] = $entry;
        if (count($logs) > 500) {
            $logs = array_slice($logs, -500);
        }
        update_option('homlity_consignment_logs', $logs, false);

        update_post_meta($post_id, '_consignment_audit', $entry);
    }
}
