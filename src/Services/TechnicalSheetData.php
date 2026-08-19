<?php
/**
 * Datos de la ficha técnica de un inmueble.
 *
 * Vive aparte de las plantillas porque hay dos que necesitan lo mismo: la
 * ficha de pantalla —parts/property-technical-sheet.php— y la del PDF
 * —parts/property-technical-sheet-pdf.php—. Con la preparación dentro del
 * HTML, cambiar de dónde sale un dato obligaba a tocar las dos y a que no se
 * quedaran desincronizadas por su cuenta.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class TechnicalSheetData
{
    /** Fotos que caben en el catálogo del PDF: tres filas de tres. */
    public const PDF_GALLERY_LIMIT = 9;

    /**
     * Todo lo que necesitan las plantillas de la ficha.
     *
     * @param array<string,mixed> $settings Ajustes del widget «Ficha técnica».
     * @return array<string,mixed>
     */
    public static function forProperty(int $postId, array $settings = []): array
    {
        $metaKeys = (new PropertyPostType())->metaKeys();
        $plugin = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
        $plugin = is_array($plugin) ? $plugin : [];

        $show = self::visibility($settings);
        $title = (string) get_the_title($postId);

        return [
            'post_id' => $postId,
            'show' => $show,
            'title' => $title,
            'permalink' => (string) get_permalink($postId),
            'description' => PropertyDescription::text($postId),
            'primary_color' => self::primaryColor($settings, $plugin),
            'company' => self::company($plugin),
            'agent' => self::agent($postId, $metaKeys, $title),
            'finance' => self::finance($postId, $metaKeys, $plugin),
            'info' => self::info($postId, $metaKeys, $show['address']),
            'dimensions' => self::dimensions($postId, $metaKeys),
            'features' => self::features($postId),
            'media' => self::media($postId, $metaKeys),
            'published_at' => (string) get_the_date('Y-m-d', $postId),
            'updated_at' => (string) get_the_modified_date('Y-m-d', $postId),
            'address' => (string) get_post_meta($postId, $metaKeys['address'], true),
        ];
    }

    /**
     * Qué secciones se pintan.
     *
     * Los interruptores de los constructores llegan como 'yes'/''; Divi y
     * WPBakery normalizan al mismo par. Lo que no venga conserva su valor por
     * defecto, para que la plantilla del plugin y el PDF salgan completos.
     *
     * @param array<string,mixed> $settings
     * @return array<string,bool>
     */
    public static function visibility(array $settings): array
    {
        $defaults = [
            // La dirección es información comercial sensible: va apagada
            // salvo que se encienda a propósito.
            'address' => false,
            'hero' => true,
            'actions' => true,
            'advisor' => true,
            'finance' => true,
            'info' => true,
            'dimensions' => true,
            'description' => true,
            'features' => true,
            'media' => true,
            'legal' => true,
        ];

        $visibility = [];
        foreach ($defaults as $key => $default) {
            $settingKey = 'show_' . $key;
            if (!array_key_exists($settingKey, $settings)) {
                $visibility[$key] = $default;
                continue;
            }
            $value = $settings[$settingKey];
            $visibility[$key] = is_bool($value)
                ? $value
                : in_array(strtolower(trim((string) $value)), ['yes', 'on', 'true', '1'], true);
        }

        return $visibility;
    }

    /**
     * El color del widget manda sobre el global del plugin.
     *
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $plugin
     */
    public static function primaryColor(array $settings, array $plugin): string
    {
        return sanitize_hex_color((string) ($settings['sheet_primary'] ?? ''))
            ?: (sanitize_hex_color((string) ($plugin['primary_color'] ?? '')) ?: '#ff6752');
    }

    /**
     * Datos públicos de la inmobiliaria, del panel SEO & GEO.
     *
     * @param array<string,mixed> $plugin
     * @return array<string,string>
     */
    private static function company(array $plugin): array
    {
        $seo = static fn(string $key): string => trim((string) SeoGeoSettingsService::get($key, ''));

        $name = $seo('company_name')
            ?: (string) ($plugin['company_name'] ?? '')
            ?: (string) get_bloginfo('name');

        return [
            'name' => $name,
            'legal_name' => $seo('company_legal_name'),
            'document' => $seo('company_nit'),
            'email' => $seo('contact_email'),
            'phone' => $seo('contact_phone') ?: $seo('contact_mobile'),
            'address' => $seo('contact_address') ?: $seo('geo_address'),
            'neighborhood' => $seo('geo_neighborhood'),
            'city' => $seo('geo_city'),
            'state' => $seo('geo_state'),
            'website' => $seo('contact_website') ?: (string) home_url('/'),
            // El logo de SEO & GEO es el de la marca; el icono del sitio es el
            // respaldo, que es lo único que hay en una instalación recién hecha.
            'logo' => $seo('company_logo') ?: ((string) get_site_icon_url(192)),
        ];
    }

    /**
     * @param array<string,string> $metaKeys
     * @return array<string,string>
     */
    private static function agent(int $postId, array $metaKeys, string $title): array
    {
        $name = '';
        $phone = '';
        $email = '';
        $photo = '';

        $agentId = (int) get_post_meta($postId, $metaKeys['agent_id'], true);
        if ($agentId > 0) {
            $user = get_user_by('id', $agentId);
            if ($user) {
                $name = (string) $user->display_name;
                $phone = (string) (
                    get_user_meta($agentId, 'homlity_plugin_phone', true)
                    ?: get_user_meta($agentId, '_homlity_advisor_phone', true)
                    ?: get_user_meta($agentId, 'phone', true)
                    ?: get_user_meta($agentId, 'telefono', true)
                    ?: get_user_meta($agentId, 'mobile_phone', true)
                    ?: get_user_meta($agentId, 'celular', true)
                    ?: get_user_meta($agentId, 'billing_phone', true)
                );
                $email = (string) $user->user_email;
                $photo = (string) (
                    get_user_meta($agentId, '_homlity_advisor_photo', true)
                    ?: get_avatar_url($agentId, ['size' => 120])
                    ?: ''
                );
            }
        }

        $name = $name !== '' ? $name : (string) get_post_meta($postId, $metaKeys['agent_name'], true);
        $phone = $phone !== '' ? $phone : (string) get_post_meta($postId, $metaKeys['agent_phone'], true);
        $email = $email !== '' ? $email : (string) get_post_meta($postId, $metaKeys['agent_email'], true);
        $photo = $photo !== '' ? $photo : (string) get_post_meta($postId, $metaKeys['agent_photo'], true);

        return [
            'id' => (string) $agentId,
            'name' => $name,
            'role' => (string) get_post_meta($postId, $metaKeys['agent_role'], true),
            'phone' => $phone,
            'email' => $email,
            'photo' => $photo,
            'whatsapp' => WhatsAppLinkService::buildPropertyLink(
                $postId,
                $phone,
                'Buen día, estoy interesado en ' . $title
            ),
        ];
    }

    /**
     * Enlace de WhatsApp al asesor con un mensaje propio.
     *
     * Los botones del pie del PDF —contactar, agendar visita, hacer oferta—
     * son el mismo número con distinta intención.
     *
     * @param array<string,string> $agent
     */
    public static function whatsAppWithMessage(array $agent, string $message): string
    {
        return WhatsAppLinkService::buildAgentLink((string) ($agent['phone'] ?? ''), $message);
    }

    /**
     * @param array<string,string> $metaKeys
     * @param array<string,mixed>  $plugin
     * @return array<int,array{label:string, value:string}>
     */
    private static function finance(int $postId, array $metaKeys, array $plugin): array
    {
        $baseCurrency = strtoupper((string) ($plugin['base_currency'] ?? 'COP'));

        $money = static function (string $priceKey, string $currencyKey) use ($postId, $metaKeys, $baseCurrency): string {
            $currency = (string) get_post_meta($postId, $metaKeys[$currencyKey], true);

            return self::formatMoney(
                (float) get_post_meta($postId, $metaKeys[$priceKey], true),
                $currency !== '' ? $currency : $baseCurrency
            );
        };

        return [
            ['label' => 'Valor de venta', 'value' => $money('price_sale', 'currency_sale')],
            ['label' => 'Canon de arriendo', 'value' => $money('price_rent', 'currency_rent')],
            ['label' => 'Administración', 'value' => $money('price_admin', 'currency_admin')],
        ];
    }

    public static function formatMoney(float $value, string $currency): string
    {
        if ($value <= 0) {
            return 'Sin dato';
        }

        $symbols = [
            'COP' => '$', 'USD' => '$', 'MXN' => '$', 'CLP' => '$', 'ARS' => '$',
            'EUR' => '€', 'GBP' => '£', 'BRL' => 'R$', 'PEN' => 'S/', 'CRC' => '¢',
            'CAD' => 'C$', 'AUD' => 'A$', 'NZD' => 'NZ$', 'CHF' => 'CHF',
            'JPY' => '¥', 'CNY' => '¥',
        ];
        $currency = strtoupper($currency);
        $prefix = ($symbols[$currency] ?? ($currency !== '' ? $currency : '$')) . ' ';

        return $prefix . number_format_i18n($value, 0);
    }

    /** Un valor vacío se dice, no se deja en blanco. */
    public static function text($value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : 'Sin dato';
    }

    /**
     * @param array<string,string> $metaKeys
     * @return array<int,array{label:string, value:string}>
     */
    private static function info(int $postId, array $metaKeys, bool $withAddress): array
    {
        $terms = static function (string $taxonomy) use ($postId): string {
            $found = get_the_terms($postId, $taxonomy);
            if (is_wp_error($found) || !$found) {
                return 'Sin dato';
            }

            return implode(', ', array_map(static fn($term): string => (string) $term->name, $found));
        };

        $items = [
            ['label' => 'Código', 'value' => self::text(get_post_meta($postId, $metaKeys['code'], true))],
            ['label' => 'Gestión', 'value' => $terms(PropertyTaxonomies::TAXONOMY_OPERATION)],
            ['label' => 'Tipo', 'value' => $terms(PropertyTaxonomies::TAXONOMY_TYPE)],
            ['label' => 'Categoría', 'value' => $terms(PropertyTaxonomies::TAXONOMY_CATEGORY)],
            ['label' => 'Etiqueta', 'value' => $terms(PropertyTaxonomies::TAXONOMY_TAG)],
            ['label' => 'País', 'value' => $terms(PropertyTaxonomies::TAXONOMY_COUNTRY)],
            ['label' => 'Departamento/Provincia', 'value' => $terms(PropertyTaxonomies::TAXONOMY_STATE)],
            ['label' => 'Ciudad', 'value' => $terms(PropertyTaxonomies::TAXONOMY_CITY)],
            ['label' => 'Barrio', 'value' => $terms(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD)],
            ['label' => 'Lugares cercanos', 'value' => $terms(PropertyTaxonomies::TAXONOMY_NEARBY)],
            ['label' => 'Publicado', 'value' => self::text(get_the_date('Y-m-d', $postId))],
            ['label' => 'Actualizado', 'value' => self::text(get_the_modified_date('Y-m-d', $postId))],
        ];

        if ($withAddress) {
            array_splice($items, 1, 0, [[
                'label' => 'Dirección',
                'value' => self::text(get_post_meta($postId, $metaKeys['address'], true)),
            ]]);
        }

        return $items;
    }

    /**
     * @param array<string,string> $metaKeys
     * @return array<int,array{label:string, value:string}>
     */
    private static function dimensions(int $postId, array $metaKeys): array
    {
        $rows = [
            ['Área total', 'area', 'm²'],
            ['Área lote', 'area_lot', 'm²'],
            ['Área privada', 'area_private', 'm²'],
            ['Área construida', 'area_built', 'm²'],
            ['Habitaciones', 'bedrooms', ''],
            ['Baños', 'bathrooms', ''],
            ['Garajes', 'parking', ''],
            ['Estado físico', 'condition', ''],
            ['Edad del inmueble', 'age', ''],
        ];

        $items = [];
        foreach ($rows as [$label, $metaKey, $unit]) {
            $raw = get_post_meta($postId, $metaKeys[$metaKey], true);
            $value = self::text($raw);
            // La unidad solo tiene sentido detrás de un número: «Sin dato m²»
            // no dice nada.
            $items[] = [
                'label' => $label,
                'value' => $unit !== '' && trim((string) $raw) !== '' ? $value . ' ' . $unit : $value,
            ];
        }

        return $items;
    }

    /** @return string[] */
    private static function features(int $postId): array
    {
        $terms = PropertyTaxonomies::getVisibleFeatureTermsForPost($postId);
        if (!$terms) {
            return [];
        }

        return array_values(array_map(static fn($term): string => (string) $term->name, $terms));
    }

    /**
     * @param array<string,string> $metaKeys
     * @return array{images:string[], videos:string[], tours:string[], photos_360:string[], brochure:string}
     */
    private static function media(int $postId, array $metaKeys): array
    {
        $images = self::urls(get_post_meta($postId, $metaKeys['gallery'], true));
        if (!$images) {
            $featured = (string) get_the_post_thumbnail_url($postId, 'large');
            if ($featured !== '') {
                $images[] = $featured;
            }
        }

        $brochure = (string) get_post_meta($postId, $metaKeys['brochure'], true);
        if ($brochure !== '' && !filter_var($brochure, FILTER_VALIDATE_URL)) {
            $brochure = '';
        }

        return [
            'images' => $images,
            'videos' => self::urls(get_post_meta($postId, $metaKeys['videos'], true)),
            'tours' => self::urls(get_post_meta($postId, $metaKeys['tour_360'], true)),
            'photos_360' => self::urls(get_post_meta($postId, $metaKeys['photos_360'], true)),
            'brochure' => $brochure,
        ];
    }

    /**
     * Saca URLs de un metadato que el CRM puede haber guardado como JSON, como
     * lista separada por comas o saltos de línea, o como array de arrays.
     *
     * @param mixed $value
     * @return string[]
     */
    public static function urls($value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            if ($trimmed[0] === '[' || $trimmed[0] === '{') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return self::urls($decoded);
                }
            }

            $urls = [];
            foreach (preg_split('/[\r\n,;|]+/', $trimmed) ?: [] as $part) {
                $url = esc_url_raw(trim((string) $part));
                if ($url !== '') {
                    $urls[] = $url;
                }
            }

            return array_values(array_unique($urls));
        }

        if (!is_array($value)) {
            return [];
        }

        $urls = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $url = esc_url_raw(trim($item));
                if ($url !== '') {
                    $urls[] = $url;
                }
                continue;
            }
            if (is_array($item)) {
                $url = esc_url_raw(trim((string) ($item['url'] ?? '')));
                if ($url !== '') {
                    $urls[] = $url;
                }
            }
        }

        return array_values(array_unique($urls));
    }
}
