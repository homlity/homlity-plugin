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
    public static function buildPropertyLink(int $postId, string $fallbackPhone = '', string $fallbackMessage = ''): string
    {
        $propertyUrl = (string) get_permalink($postId);
        $propertyTitle = (string) get_the_title($postId);
        $code = (string) get_post_meta($postId, (new PropertyPostType())->metaKeys()['code'], true);

        $defaultMessage = $fallbackMessage !== '' ? $fallbackMessage : ($propertyTitle . ' - ' . $propertyUrl);
        $ninja = self::resolveNinjaAccount();
        if (!empty($ninja['phone'])) {
            $message = self::hydrateTemplate((string) ($ninja['message'] ?? ''), $propertyTitle, $propertyUrl, $code);
            if ($message === '') {
                $message = $defaultMessage;
            }
            return self::buildApiUrl((string) $ninja['phone'], $message);
        }

        $digits = preg_replace('/\D+/', '', (string) $fallbackPhone);
        if ($digits === '') {
            return '';
        }

        return self::buildApiUrl($digits, $defaultMessage);
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

    private static function hydrateTemplate(string $template, string $title, string $url, string $code): string
    {
        $template = trim(wp_strip_all_tags($template));
        if ($template === '') {
            return '';
        }

        return strtr($template, [
            '{title}' => $title,
            '{url}' => $url,
            '{code}' => $code,
        ]);
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

