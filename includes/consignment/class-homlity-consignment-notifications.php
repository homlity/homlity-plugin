<?php
/**
 * Homlity Consignment Notifications.
 *
 * Sends admin notification and consignant confirmation emails.
 * Uses wp_mail() — compatible with any SMTP plugin (WP Mail SMTP, FluentSMTP, etc.).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Consignment_Notifications
{
    /**
     * Notify the site administrator about a new consignment.
     *
     * @param int   $post_id  Created post ID.
     * @param array $form     Raw form data.
     * @param array $payload  Normalized Homlity payload.
     */
    public static function notifyAdmin(int $post_id, array $form, array $payload): void
    {
        $opts = Homlity_Consignment_Manager::options();

        $recipients = apply_filters(
            'homlity_consignment_notification_recipients',
            array_filter(array_map('trim', explode(',', $opts['notification_email'] ?? get_bloginfo('admin_email')))),
            $post_id,
            $form
        );

        if (empty($recipients)) {
            return;
        }

        $contact  = (array) ($form['contact'] ?? []);
        $details  = (array) ($form['property_details'] ?? []);
        $op_data  = (array) ($form['operation'] ?? []);
        $loc      = (array) ($form['location'] ?? []);
        $post     = $payload['post'] ?? [];
        $pricing  = $payload['pricing'] ?? [];
        $taxonomy = $payload['taxonomy'] ?? [];

        $site_name  = get_bloginfo('name');
        $edit_link  = $post_id > 0 ? admin_url('post.php?post=' . $post_id . '&action=edit') : '';
        $type_label = sanitize_text_field($details['property_type'] ?? ($taxonomy['property_type'][0] ?? '—'));
        $city_label = sanitize_text_field($loc['city'] ?? ($taxonomy['property_city'][0] ?? '—'));
        $ops_label  = implode(' / ', (array) ($taxonomy['property_operation'] ?? []));
        $price_parts = [];
        if (!empty($pricing['sale_price'])) {
            $price_parts[] = number_format((float) $pricing['sale_price'], 0, ',', '.') . ' ' . $pricing['sale_currency'];
        }
        if (!empty($pricing['rent_price'])) {
            $price_parts[] = 'Arriendo: ' . number_format((float) $pricing['rent_price'], 0, ',', '.') . ' ' . $pricing['rent_currency'];
        }
        $price_label = implode(' | ', $price_parts) ?: '—';

        $subject = apply_filters(
            'homlity_consignment_admin_email_subject',
            sprintf('[%s] Nuevo inmueble consignado: %s', $site_name, wp_strip_all_tags($post['title'] ?? 'Sin título')),
            $post_id
        );

        $body = self::adminEmailBody([
            'site_name'        => $site_name,
            'consignant_name'  => sanitize_text_field($contact['name'] ?? '—'),
            'consignant_type'  => self::typeLabel(sanitize_key($contact['consignant_type'] ?? '')),
            'consignant_phone' => sanitize_text_field($contact['phone'] ?? $contact['whatsapp'] ?? '—'),
            'consignant_email' => sanitize_email($contact['email'] ?? ''),
            'property_title'   => wp_strip_all_tags($post['title'] ?? '—'),
            'property_type'    => $type_label,
            'operation'        => $ops_label ?: sanitize_text_field($op_data['operation'] ?? '—'),
            'city'             => $city_label,
            'price'            => $price_label,
            'post_id'          => $post_id,
            'edit_link'        => $edit_link,
            'sections'         => self::submissionSections($form, $payload),
            'files'            => self::submissionFiles($payload),
        ]);

        $body = apply_filters('homlity_consignment_admin_email_body', $body, $post_id, $form, $payload);
        $attachments = apply_filters(
            'homlity_consignment_admin_email_attachments',
            self::attachmentPaths($payload),
            $post_id,
            $form,
            $payload
        );

        wp_mail(
            $recipients,
            $subject,
            $body,
            ['Content-Type: text/html; charset=UTF-8'],
            $attachments
        );
    }

    /**
     * Send a confirmation email to the person who submitted the consignment.
     *
     * @param array $form  Raw form data.
     * @param array $opts  Plugin options.
     */
    public static function notifyConsignant(array $form, array $opts): void
    {
        $contact = (array) ($form['contact'] ?? []);
        $email   = sanitize_email($contact['email'] ?? '');

        if (!is_email($email)) {
            return;
        }

        $name      = sanitize_text_field($contact['name'] ?? '');
        $site_name = get_bloginfo('name');
        $subject   = apply_filters(
            'homlity_consignment_consignant_email_subject',
            sprintf('[%s] Recibimos la información de tu inmueble', $site_name)
        );

        $body = self::consignantEmailBody([
            'name'      => $name,
            'site_name' => $site_name,
            'message'   => nl2br(esc_html($opts['success_message'] ?? '')),
        ]);

        $body = apply_filters('homlity_consignment_consignant_email_body', $body, $form, $opts);

        wp_mail(
            $email,
            $subject,
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    // ── Email bodies ──────────────────────────────────────────────────────

    private static function adminEmailBody(array $d): string
    {
        return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#1f2937;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#2563eb;border-bottom:2px solid #2563eb;padding-bottom:8px;">Nuevo inmueble consignado</h2>
<p>Se ha recibido una nueva consignación en <strong>' . esc_html($d['site_name']) . '</strong>.</p>
<h3 style="margin-top:24px;color:#374151;">Datos del consignante</h3>
<table style="width:100%;border-collapse:collapse;">
  <tr><td style="padding:6px 0;color:#6b7280;width:40%;">Nombre</td><td style="padding:6px 0;"><strong>' . esc_html($d['consignant_name']) . '</strong></td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Tipo</td><td style="padding:6px 0;">' . esc_html($d['consignant_type']) . '</td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Teléfono</td><td style="padding:6px 0;">' . esc_html($d['consignant_phone']) . '</td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Correo</td><td style="padding:6px 0;"><a href="mailto:' . esc_attr($d['consignant_email']) . '">' . esc_html($d['consignant_email']) . '</a></td></tr>
</table>
<h3 style="margin-top:24px;color:#374151;">Datos del inmueble</h3>
<table style="width:100%;border-collapse:collapse;">
  <tr><td style="padding:6px 0;color:#6b7280;width:40%;">Título</td><td style="padding:6px 0;"><strong>' . esc_html($d['property_title']) . '</strong></td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Tipo</td><td style="padding:6px 0;">' . esc_html($d['property_type']) . '</td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Operación</td><td style="padding:6px 0;">' . esc_html($d['operation']) . '</td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Ciudad</td><td style="padding:6px 0;">' . esc_html($d['city']) . '</td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Precio</td><td style="padding:6px 0;">' . esc_html($d['price']) . '</td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Post ID</td><td style="padding:6px 0;">' . ($d['post_id'] > 0 ? (int) $d['post_id'] : esc_html__('No creado', 'homlity-real-estate')) . '</td></tr>
</table>'
 . self::renderSections((array) ($d['sections'] ?? []))
 . self::renderFiles((array) ($d['files'] ?? []))
 . (!empty($d['edit_link']) ? '<p style="margin-top:24px;">
  <a href="' . esc_url($d['edit_link']) . '" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-size:14px;">Revisar inmueble en WordPress</a>
</p>' : '') . '
<hr style="margin-top:32px;border:none;border-top:1px solid #e5e7eb;">
<p style="color:#9ca3af;font-size:12px;">Este correo fue generado automáticamente por el plugin Homlity Real Estate.</p>
</body></html>';
    }

    private static function consignantEmailBody(array $d): string
    {
        return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#1f2937;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#2563eb;border-bottom:2px solid #2563eb;padding-bottom:8px;">¡Gracias por consignar tu inmueble!</h2>
<p>Hola <strong>' . esc_html($d['name']) . '</strong>,</p>
<p>' . $d['message'] . '</p>
<p>Nuestro equipo revisará la información suministrada y se pondrá en contacto contigo para validar los datos antes de la publicación.</p>
<p style="color:#6b7280;font-size:13px;margin-top:24px;">Por favor no respondas a este correo. Si tienes alguna pregunta, comunícate directamente con nosotros a través de los canales de contacto de nuestro sitio web.</p>
<hr style="margin-top:32px;border:none;border-top:1px solid #e5e7eb;">
<p style="color:#9ca3af;font-size:12px;">' . esc_html($d['site_name']) . ' — Plataforma inmobiliaria Homlity</p>
</body></html>';
    }

    private static function typeLabel(string $type): string
    {
        return [
            'owner'      => 'Propietario',
            'advisor'    => 'Asesor inmobiliario',
            'agency'     => 'Inmobiliaria',
            'builder'    => 'Constructor / Proyecto',
            'authorized' => 'Persona autorizada',
        ][$type] ?? ucfirst($type);
    }

    private static function submissionSections(array $form, array $payload): array
    {
        $contact = (array) ($form['contact'] ?? []);
        $operation = (array) ($form['operation'] ?? []);
        $location = (array) ($form['location'] ?? []);
        $details = (array) ($form['property_details'] ?? []);
        $areas = (array) ($form['areas'] ?? []);
        $features = (array) ($form['features'] ?? []);
        $advisor = (array) ($form['advisor'] ?? []);
        $review = (array) ($form['review'] ?? []);
        $post = (array) ($payload['post'] ?? []);
        $taxonomy = (array) ($payload['taxonomy'] ?? []);
        $pricing = (array) ($payload['pricing'] ?? []);
        $metrics = (array) ($payload['metrics'] ?? []);
        $payloadLocation = (array) ($payload['location'] ?? []);
        $payloadAdvisor = (array) ($payload['advisor'] ?? []);

        if (empty($contact) && !empty($form['propietario'])) {
            $owner = (array) $form['propietario'];
            $contact = [
                'consignant_type' => $owner['tipo'] ?? 'owner',
                'name' => $owner['nombre'] ?? '',
                'document' => $owner['identificacion'] ?? '',
                'phone' => $owner['telefono'] ?? '',
                'email' => $owner['email'] ?? '',
            ];
        }
        if (empty($advisor)) {
            $advisor = $payloadAdvisor;
        }

        return [
            __('Contacto', 'homlity-real-estate') => [
                __('Tipo de consignante', 'homlity-real-estate') => self::typeLabel(sanitize_key($contact['consignant_type'] ?? '')),
                __('Nombre', 'homlity-real-estate') => $contact['name'] ?? '',
                __('Identificación', 'homlity-real-estate') => $contact['document'] ?? '',
                __('Teléfono', 'homlity-real-estate') => $contact['phone'] ?? '',
                __('WhatsApp', 'homlity-real-estate') => $contact['whatsapp'] ?? '',
                __('Correo', 'homlity-real-estate') => $contact['email'] ?? '',
                __('Acepta política de datos', 'homlity-real-estate') => !empty($contact['data_consent']) ? __('Sí', 'homlity-real-estate') : __('No', 'homlity-real-estate'),
                __('Tiene autorización', 'homlity-real-estate') => !empty($contact['authorization_consent']) ? __('Sí', 'homlity-real-estate') : __('No', 'homlity-real-estate'),
            ],
            __('Operación y precio', 'homlity-real-estate') => [
                __('Gestión', 'homlity-real-estate') => implode(', ', (array) ($taxonomy['property_operation'] ?? [])),
                __('Precio de venta', 'homlity-real-estate') => self::money($operation['sale_price'] ?? ($pricing['sale_price'] ?? ''), $operation['sale_currency'] ?? ($pricing['sale_currency'] ?? '')),
                __('Canon de arriendo', 'homlity-real-estate') => self::money($operation['rent_price'] ?? ($pricing['rent_price'] ?? ''), $operation['rent_currency'] ?? ($pricing['rent_currency'] ?? '')),
                __('Administración', 'homlity-real-estate') => self::money($operation['admin_price'] ?? ($pricing['admin_price'] ?? ''), $operation['admin_currency'] ?? ($pricing['admin_currency'] ?? '')),
                __('Administración incluida', 'homlity-real-estate') => (!empty($operation['admin_included']) || !empty($pricing['admin_included'])) ? __('Sí', 'homlity-real-estate') : __('No', 'homlity-real-estate'),
                __('Precio negociable', 'homlity-real-estate') => (!empty($operation['negotiable']) || !empty($pricing['negotiable'])) ? __('Sí', 'homlity-real-estate') : __('No', 'homlity-real-estate'),
                __('Nota comercial', 'homlity-real-estate') => $operation['commercial_note'] ?? ($pricing['commercial_note'] ?? ''),
            ],
            __('Ubicación', 'homlity-real-estate') => [
                __('País', 'homlity-real-estate') => $location['country'] ?? implode(', ', (array) ($taxonomy['property_country'] ?? [])),
                __('Departamento / Estado', 'homlity-real-estate') => $location['state'] ?? implode(', ', (array) ($taxonomy['property_state'] ?? [])),
                __('Ciudad', 'homlity-real-estate') => $location['city'] ?? implode(', ', (array) ($taxonomy['property_city'] ?? [])),
                __('Barrio', 'homlity-real-estate') => $location['neighborhood'] ?? implode(', ', (array) ($taxonomy['property_neighborhood'] ?? [])),
                __('Dirección', 'homlity-real-estate') => $location['address'] ?? ($payloadLocation['address'] ?? ''),
                __('Complemento', 'homlity-real-estate') => $location['address_complement'] ?? ($payloadLocation['address_complement'] ?? ''),
                __('Mostrar dirección exacta', 'homlity-real-estate') => (!empty($location['show_exact_address']) || !empty($payloadLocation['show_exact_address'])) ? __('Sí', 'homlity-real-estate') : __('No', 'homlity-real-estate'),
                __('Latitud', 'homlity-real-estate') => $location['latitude'] ?? ($payloadLocation['latitude'] ?? ''),
                __('Longitud', 'homlity-real-estate') => $location['longitude'] ?? ($payloadLocation['longitude'] ?? ''),
                __('Referencia', 'homlity-real-estate') => $location['location_reference'] ?? ($payloadLocation['location_reference'] ?? ''),
                __('Google Maps', 'homlity-real-estate') => $location['maps_url'] ?? ($payloadLocation['maps_url'] ?? ''),
            ],
            __('Datos del inmueble', 'homlity-real-estate') => [
                __('Título', 'homlity-real-estate') => $post['title'] ?? '',
                __('Tipo', 'homlity-real-estate') => $details['property_type'] ?? implode(', ', (array) ($taxonomy['property_type'] ?? [])),
                __('Categoría', 'homlity-real-estate') => $details['category'] ?? implode(', ', (array) ($taxonomy['property_category'] ?? [])),
                __('Estado', 'homlity-real-estate') => $details['condition'] ?? '',
                __('Año de construcción', 'homlity-real-estate') => $details['year_built'] ?? '',
                __('Descripción', 'homlity-real-estate') => $post['description'] ?? ($details['description'] ?? ''),
            ],
            __('Áreas y distribución', 'homlity-real-estate') => [
                __('Área total', 'homlity-real-estate') => $areas['area'] ?? ($metrics['area'] ?? ''),
                __('Área construida', 'homlity-real-estate') => $areas['area_built'] ?? ($metrics['area_built'] ?? ''),
                __('Área privada', 'homlity-real-estate') => $areas['area_private'] ?? ($metrics['area_private'] ?? ''),
                __('Área lote', 'homlity-real-estate') => $areas['area_lot'] ?? ($metrics['area_lot'] ?? ''),
                __('Habitaciones', 'homlity-real-estate') => $areas['bedrooms'] ?? ($metrics['bedrooms'] ?? ''),
                __('Baños', 'homlity-real-estate') => $areas['bathrooms'] ?? ($metrics['bathrooms'] ?? ''),
                __('Parqueaderos', 'homlity-real-estate') => $areas['parking'] ?? ($metrics['parking'] ?? ''),
                __('Estrato', 'homlity-real-estate') => $areas['stratum'] ?? ($metrics['stratum'] ?? ''),
                __('Piso', 'homlity-real-estate') => $areas['floor'] ?? ($metrics['floor'] ?? ''),
                __('Niveles', 'homlity-real-estate') => $areas['levels'] ?? ($metrics['levels'] ?? ''),
                __('Ascensores', 'homlity-real-estate') => $areas['elevators'] ?? ($metrics['elevators'] ?? ''),
            ],
            __('Características', 'homlity-real-estate') => [
                __('Seleccionadas', 'homlity-real-estate') => implode(', ', array_filter((array) ($features['selected'] ?? []))),
                __('Personalizadas', 'homlity-real-estate') => implode(', ', array_filter((array) ($features['custom'] ?? []))),
            ],
            __('Asesor', 'homlity-real-estate') => [
                __('Nombre', 'homlity-real-estate') => $advisor['name'] ?? '',
                __('Correo', 'homlity-real-estate') => $advisor['email'] ?? '',
                __('Teléfono', 'homlity-real-estate') => $advisor['phone'] ?? '',
                __('Rol', 'homlity-real-estate') => $advisor['role'] ?? '',
                __('Foto', 'homlity-real-estate') => $advisor['photo'] ?? '',
            ],
            __('Declaraciones finales', 'homlity-real-estate') => [
                __('Información verdadera', 'homlity-real-estate') => !empty($review['truth_declaration']) ? __('Sí', 'homlity-real-estate') : __('No', 'homlity-real-estate'),
                __('Autoriza contacto', 'homlity-real-estate') => !empty($review['contact_consent']) ? __('Sí', 'homlity-real-estate') : __('No', 'homlity-real-estate'),
            ],
        ];
    }

    private static function renderSections(array $sections): string
    {
        $html = '';
        foreach ($sections as $title => $rows) {
            $html .= '<h3 style="margin-top:24px;color:#374151;">' . esc_html((string) $title) . '</h3>';
            $html .= '<table style="width:100%;border-collapse:collapse;">';
            foreach ((array) $rows as $label => $value) {
                $value = self::stringify($value);
                if ($value === '') {
                    continue;
                }
                $html .= '<tr><td style="padding:6px 0;color:#6b7280;width:40%;vertical-align:top;">' . esc_html((string) $label) . '</td><td style="padding:6px 0;vertical-align:top;">' . self::formatValue($value) . '</td></tr>';
            }
            $html .= '</table>';
        }
        return $html;
    }

    private static function renderFiles(array $files): string
    {
        if (empty($files)) {
            return '';
        }
        $html = '<h3 style="margin-top:24px;color:#374151;">Archivos adjuntos</h3><ul style="padding-left:18px;">';
        foreach ($files as $file) {
            $label = sanitize_text_field((string) ($file['label'] ?? __('Archivo', 'homlity-real-estate')));
            $url = esc_url_raw((string) ($file['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $html .= '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
        }
        return $html . '</ul>';
    }

    private static function submissionFiles(array $payload): array
    {
        $media = (array) ($payload['media'] ?? []);
        $files = [];
        foreach ((array) ($media['gallery'] ?? []) as $index => $url) {
            $files[] = [
                'label' => sprintf(__('Foto %d', 'homlity-real-estate'), ((int) $index) + 1),
                'url' => esc_url_raw((string) $url),
            ];
        }
        foreach (['featured_image_url' => __('Foto principal', 'homlity-real-estate'), 'brochure' => __('Documento / brochure', 'homlity-real-estate')] as $key => $label) {
            if (!empty($media[$key])) {
                $files[] = ['label' => $label, 'url' => esc_url_raw((string) $media[$key])];
            }
        }
        foreach ((array) ($media['documentos'] ?? []) as $index => $url) {
            $files[] = [
                'label' => sprintf(__('Documento %d', 'homlity-real-estate'), ((int) $index) + 1),
                'url' => esc_url_raw((string) $url),
            ];
        }

        $seen = [];
        return array_values(array_filter($files, static function (array $file) use (&$seen): bool {
            $url = (string) ($file['url'] ?? '');
            if ($url === '' || isset($seen[$url])) {
                return false;
            }
            $seen[$url] = true;
            return true;
        }));
    }

    private static function attachmentPaths(array $payload): array
    {
        $paths = [];
        foreach (self::submissionFiles($payload) as $file) {
            $path = self::pathForUrl((string) ($file['url'] ?? ''));
            if ($path !== '') {
                $paths[] = $path;
            }
        }
        return array_values(array_unique($paths));
    }

    private static function pathForUrl(string $url): string
    {
        $url = esc_url_raw($url);
        if ($url === '') {
            return '';
        }

        $attachmentId = attachment_url_to_postid($url);
        if ($attachmentId > 0) {
            $path = get_attached_file($attachmentId);
            if (is_string($path) && $path !== '' && file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        $uploads = wp_get_upload_dir();
        $baseUrl = (string) ($uploads['baseurl'] ?? '');
        $baseDir = (string) ($uploads['basedir'] ?? '');
        if ($baseUrl !== '' && $baseDir !== '' && str_starts_with($url, $baseUrl)) {
            $relative = ltrim(substr($url, strlen($baseUrl)), '/');
            $path = trailingslashit($baseDir) . $relative;
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return '';
    }

    private static function money($amount, $currency): string
    {
        $amount = trim((string) $amount);
        if ($amount === '') {
            return '';
        }
        $number = preg_replace('/[^0-9.]/', '', $amount);
        $formatted = $number !== '' ? number_format((float) $number, 0, ',', '.') : $amount;
        return trim($formatted . ' ' . sanitize_text_field((string) $currency));
    }

    private static function stringify($value): string
    {
        if (is_bool($value)) {
            return $value ? __('Sí', 'homlity-real-estate') : __('No', 'homlity-real-estate');
        }
        if (is_array($value)) {
            return implode(', ', array_filter(array_map([self::class, 'stringify'], $value)));
        }
        return trim(wp_strip_all_tags((string) $value));
    }

    private static function formatValue(string $value): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return '<a href="' . esc_url($value) . '">' . esc_html($value) . '</a>';
        }
        return nl2br(esc_html($value));
    }
}
