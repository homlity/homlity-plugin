<?php

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

class SocialShareMessageService
{
    /** @return array<string,string> */
    public static function defaults(): array
    {
        return [
            'whatsapp' => "Hola buen día, estoy interesado en el código de inmueble {code} [{title}]\n{url}",
            'facebook' => '{title} | Código: {code}',
            'x' => '{title} | Código: {code}',
            'linkedin' => '{title} | Código: {code}',
            'telegram' => '{title} | Código: {code}',
            'pinterest' => '{title} | Código: {code}',
            'reddit' => '{title} | Código: {code}',
            'email' => "Hola, quiero compartir este inmueble:\n{summary}\n{url}",
        ];
    }

    /** @return array<string,array{label:string,description:string}> */
    public static function fieldDefinitions(): array
    {
        return [
            'whatsapp' => [
                'label' => __('Mensaje de WhatsApp', 'homlity-real-estate'),
                'description' => __('Se usa al compartir y en los botones de contacto por WhatsApp.', 'homlity-real-estate'),
            ],
            'facebook' => [
                'label' => __('Mensaje de Facebook', 'homlity-real-estate'),
                'description' => __('Facebook puede decidir mostrar únicamente la vista previa de la URL.', 'homlity-real-estate'),
            ],
            'x' => [
                'label' => __('Mensaje de X', 'homlity-real-estate'),
                'description' => __('La URL se agrega una sola vez como enlace del inmueble.', 'homlity-real-estate'),
            ],
            'linkedin' => [
                'label' => __('Mensaje de LinkedIn', 'homlity-real-estate'),
                'description' => __('LinkedIn puede decidir mostrar únicamente los metadatos de la página.', 'homlity-real-estate'),
            ],
            'telegram' => [
                'label' => __('Mensaje de Telegram', 'homlity-real-estate'),
                'description' => __('La URL se agrega una sola vez como enlace del inmueble.', 'homlity-real-estate'),
            ],
            'pinterest' => [
                'label' => __('Descripción de Pinterest', 'homlity-real-estate'),
                'description' => __('Se usa como descripción del pin.', 'homlity-real-estate'),
            ],
            'reddit' => [
                'label' => __('Título de Reddit', 'homlity-real-estate'),
                'description' => __('Se utiliza como título de la publicación.', 'homlity-real-estate'),
            ],
            'email' => [
                'label' => __('Mensaje de correo', 'homlity-real-estate'),
                'description' => __('Se utiliza como cuerpo del correo electrónico.', 'homlity-real-estate'),
            ],
        ];
    }

    /** @return array<string,string> */
    public static function templates(): array
    {
        $settings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
        $stored = is_array($settings) && is_array($settings['share_messages'] ?? null)
            ? $settings['share_messages']
            : [];

        $templates = self::defaults();
        foreach ($templates as $platform => $default) {
            if (array_key_exists($platform, $stored)) {
                $templates[$platform] = (string) $stored[$platform];
            }
        }

        return apply_filters('homlity_social_share_message_templates', $templates);
    }

    /** @return array<string,string> */
    public static function propertyContext(int $postId): array
    {
        $title = html_entity_decode(
            wp_strip_all_tags((string) get_the_title($postId)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $url = (string) (get_permalink($postId) ?: '');
        $bedrooms = (string) get_post_meta($postId, '_property_bedrooms', true);
        $bathrooms = (string) get_post_meta($postId, '_property_bathrooms', true);
        $parking = (string) get_post_meta($postId, '_property_parking', true);
        $area = (string) get_post_meta($postId, '_property_area', true);
        $code = trim((string) get_post_meta($postId, '_property_code', true));
        if ($code === '') {
            $code = (string) $postId;
        }

        $priceSale = get_post_meta($postId, '_property_price_sale', true);
        $priceRent = get_post_meta($postId, '_property_price_rent', true);
        $price = self::formatPrice($priceSale);
        if ($price === '') {
            $price = self::formatPrice($priceRent);
        }

        $summaryParts = array_filter([
            $title,
            /* translators: %s: código del inmueble. */
            sprintf(__('código: %s', 'homlity-real-estate'), $code),
            /* translators: %s: número de alcobas. */
            $bedrooms !== '' ? sprintf(__('alcobas: %s', 'homlity-real-estate'), $bedrooms) : '',
            /* translators: %s: número de baños. */
            $bathrooms !== '' ? sprintf(__('baños: %s', 'homlity-real-estate'), $bathrooms) : '',
            /* translators: %s: número de parqueaderos. */
            $parking !== '' ? sprintf(__('parqueaderos: %s', 'homlity-real-estate'), $parking) : '',
            /* translators: %s: área construida en metros cuadrados. */
            $area !== '' ? sprintf(__('área: %sm2', 'homlity-real-estate'), $area) : '',
            /* translators: %s: precio ya formateado con su moneda. */
            $price !== '' ? sprintf(__('valor: %s', 'homlity-real-estate'), $price) : '',
        ], static fn(string $value): bool => $value !== '');

        return apply_filters('homlity_social_share_property_context', [
            'title' => $title,
            'url' => $url,
            'summary' => trim(implode(' | ', $summaryParts)),
            'code' => $code,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'parking' => $parking,
            'area' => $area,
            'price' => $price,
        ], $postId);
    }

    public static function messageFor(string $platform, int $postId, string $override = ''): string
    {
        $templates = self::templates();
        $template = $override !== '' ? $override : (string) ($templates[$platform] ?? '{summary}');
        $context = self::propertyContext($postId);
        $message = str_replace(
            ['{title}', '{url}', '{summary}', '{code}', '{bedrooms}', '{bathrooms}', '{parking}', '{area}', '{price}'],
            [$context['title'], $context['url'], $context['summary'], $context['code'], $context['bedrooms'], $context['bathrooms'], $context['parking'], $context['area'], $context['price']],
            $template
        );

        return apply_filters(
            'homlity_social_share_message',
            self::deduplicateUrl(trim($message), $context['url']),
            $platform,
            $postId,
            $context
        );
    }

    public static function messageWithoutUrl(string $message, string $url): string
    {
        if ($url === '') {
            return trim($message);
        }
        return trim((string) preg_replace('/\s*' . preg_quote($url, '/') . '\s*/u', ' ', $message));
    }

    private static function deduplicateUrl(string $message, string $url): string
    {
        if ($url === '' || substr_count($message, $url) <= 1) {
            return $message;
        }

        $seen = false;
        return trim((string) preg_replace_callback(
            '/' . preg_quote($url, '/') . '/u',
            static function (array $matches) use (&$seen): string {
                if ($seen) {
                    return '';
                }
                $seen = true;
                return $matches[0];
            },
            $message
        ));
    }

    private static function formatPrice($value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }
        $number = (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
        return $number > 0 ? '$ ' . number_format_i18n($number, 0) : '';
    }
}
