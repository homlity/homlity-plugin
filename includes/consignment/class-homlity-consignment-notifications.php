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
        $edit_link  = admin_url('post.php?post=' . $post_id . '&action=edit');
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
        ]);

        $body = apply_filters('homlity_consignment_admin_email_body', $body, $post_id, $form, $payload);

        wp_mail(
            $recipients,
            $subject,
            $body,
            ['Content-Type: text/html; charset=UTF-8']
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
  <tr><td style="padding:6px 0;color:#6b7280;">Post ID</td><td style="padding:6px 0;">' . (int) $d['post_id'] . '</td></tr>
</table>
<p style="margin-top:24px;">
  <a href="' . esc_url($d['edit_link']) . '" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-size:14px;">Revisar inmueble en WordPress</a>
</p>
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
}
