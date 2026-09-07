<?php
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_query
/**
 * Resolves WhatsApp links for properties, prioritizing WP Chat App (Ninja Team).
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

class WhatsAppLinkService
{
    /**
     * @param string $fallbackMessage Ignorado: el mensaje sale siempre de los
     *                                ajustes del plugin. Para un mensaje propio
     *                                usa buildPropertyLinkWithTemplate().
     */
    public static function buildPropertyLink(int $postId, string $fallbackPhone = '', string $fallbackMessage = ''): string
    {
        return self::buildPropertyLinkWithTemplate($postId, $fallbackPhone, '');
    }

    /**
     * Mismo enlace que buildPropertyLink() —misma prioridad de número: la
     * cuenta de la agencia primero, el asesor como respaldo— pero con la
     * plantilla del mensaje que decida quien lo pinta.
     *
     * La plantilla admite los mismos marcadores que los mensajes para
     * compartir ({code}, {title}, {url}, {price}...); vacía, se usa la
     * configurada en los ajustes del plugin.
     */
    public static function buildPropertyLinkWithTemplate(int $postId, string $fallbackPhone = '', string $messageTemplate = ''): string
    {
        $phone = self::resolvePropertyPhone($postId, $fallbackPhone);
        if ($phone === '') {
            return '';
        }

        return self::buildApiUrl($phone, SocialShareMessageService::messageFor('whatsapp', $postId, $messageTemplate));
    }

    /**
     * Número que atiende a este inmueble: el de la cuenta de la agencia si
     * existe, y si no el que se le pase (normalmente el del asesor).
     */
    public static function resolvePropertyPhone(int $postId, string $fallbackPhone = ''): string
    {
        $ninja = self::resolveNinjaAccount();
        if (!empty($ninja['phone'])) {
            return (string) $ninja['phone'];
        }

        return (string) preg_replace('/\D+/', '', $fallbackPhone);
    }

    /**
     * Teléfono del asesor asignado al inmueble.
     *
     * La ficha guarda el teléfono al sincronizar, pero cuando el CRM no lo
     * envía queda solo el usuario del asesor, y ahí el número vive en su
     * perfil —cada integración lo ha guardado con un nombre distinto—.
     */
    public static function advisorPhoneForProperty(int $postId): string
    {
        if ($postId <= 0) {
            return '';
        }

        $phone = trim((string) get_post_meta($postId, '_property_agent_phone', true));
        if ($phone !== '') {
            return $phone;
        }

        $agentId = (int) get_post_meta($postId, '_property_agent_id', true);
        if ($agentId <= 0) {
            return '';
        }

        foreach (['homlity_plugin_phone', '_homlity_advisor_phone', 'phone', 'billing_phone'] as $metaKey) {
            $phone = trim((string) get_user_meta($agentId, $metaKey, true));
            if ($phone !== '') {
                return $phone;
            }
        }

        return '';
    }

    /**
     * Direct link to an advisor's own number. Unlike buildPropertyLink() this
     * never falls back to the agency-wide WhatsApp account: an advisor profile
     * must reach that advisor.
     */
    public static function buildAgentLink(string $phone, string $message = ''): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }

        return self::buildApiUrl($digits, $message);
    }

    /**
     * @return array{phone:string,message:string}|array{}
     */
    private static function resolveNinjaAccount(): array
    {
        if (!post_type_exists('whatsapp-accounts')) {
            return [];
        }

        $accounts = get_posts([
            'post_type' => 'whatsapp-accounts',
            'post_status' => 'publish',
            'numberposts' => 1,
            'meta_key' => 'nta_wa_widget_position',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'nta_wa_widget_show',
                    'value' => 'ON',
                    'compare' => '=',
                ],
            ],
        ]);

        if (!$accounts) {
            $accounts = get_posts([
                'post_type' => 'whatsapp-accounts',
                'post_status' => 'publish',
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
        }

        if (!$accounts || !isset($accounts[0]->ID)) {
            return [];
        }

        $accountInfo = get_post_meta((int) $accounts[0]->ID, 'nta_wa_account_info', true);
        if (!is_array($accountInfo)) {
            return [];
        }

        $phone = preg_replace('/\D+/', '', (string) ($accountInfo['number'] ?? ''));
        if ($phone === '') {
            return [];
        }

        return [
            'phone' => $phone,
            'message' => (string) ($accountInfo['predefinedText'] ?? ''),
        ];
    }

    private static function buildApiUrl(string $phoneDigits, string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'https://api.whatsapp.com/send?phone=' . rawurlencode($phoneDigits);
        }

        return 'https://api.whatsapp.com/send?phone=' . rawurlencode($phoneDigits) . '&text=' . rawurlencode($message);
    }

}
