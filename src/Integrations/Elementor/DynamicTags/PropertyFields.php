<?php
/**
 * Los datos de un inmueble expuestos como etiquetas dinámicas de Elementor.
 *
 * Toda la resolución vive aquí y no dentro de las clases Tag por la misma
 * razón que en [AgentFields]: Tag y Data_Tag sólo existen con Elementor
 * cargado, así que cualquier lógica metida ahí queda fuera del alcance de las
 * pruebas.
 *
 * El catálogo es una sola tabla —clave, etiqueta, grupo y cómo se resuelve— en
 * vez de un desplegable por un lado y un `match` por otro: con dos listas,
 * añadir un campo y olvidarse de la mitad es cuestión de tiempo.
 */

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Homlity\PluginInmobiliario\Services\PropertyDescription;
use Homlity\PluginInmobiliario\Services\PropertyMedia;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TechnicalSheetData;
use Homlity\PluginInmobiliario\Services\WhatsAppLinkService;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

final class PropertyFields
{
    /** Valor del control «Formato» que devuelve el dato tal y como se guardó. */
    public const FORMAT_RAW = 'raw';

    /**
     * El catálogo se arma una vez por petición: se consulta un campo por cada
     * etiqueta pintada, y una ficha lleva decenas.
     *
     * @var array<string,array<string,mixed>>|null
     */
    private static ?array $definitions = null;

    /** @var array<string,string>|null */
    private static ?array $metaKeys = null;

    /**
     * Catálogo de campos de texto.
     *
     * `kind` decide cómo se lee y cómo se presenta:
     *  - meta:   texto guardado en post meta.
     *  - money:  precio + su moneda, con el mismo formateador que las tarjetas.
     *  - area:   metros cuadrados.
     *  - number: entero con separador de miles.
     *  - bool:   Sí/No.
     *  - terms:  nombres de los términos de una taxonomía.
     *  - post:   algo que vive en el propio post (título, fechas…).
     *  - agent:  dato del asesor asignado, con su cadena de respaldo.
     *  - special: cálculos propios (ubicación completa, recuentos…).
     *
     * @return array<string,array<string,mixed>>
     */
    public static function definitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $inmueble = __('Inmueble', 'homlity-real-estate');
        $precios = __('Precios', 'homlity-real-estate');
        $medidas = __('Dimensiones y distribución', 'homlity-real-estate');
        $ubicacion = __('Ubicación', 'homlity-real-estate');
        $caracteristicas = __('Características', 'homlity-real-estate');
        $asesor = __('Asesor', 'homlity-real-estate');
        $contacto = __('Contacto del inmueble', 'homlity-real-estate');
        $multimedia = __('Multimedia', 'homlity-real-estate');
        $fechas = __('Fechas', 'homlity-real-estate');

        self::$definitions = [
            // ── Inmueble ──────────────────────────────────────────────────
            'title'              => ['label' => __('Título', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'post', 'source' => 'title'],
            'code'               => ['label' => __('Código', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'meta', 'meta' => 'code'],
            'description'        => ['label' => __('Descripción', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'post', 'source' => 'description'],
            'excerpt'            => ['label' => __('Extracto', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'post', 'source' => 'excerpt'],
            'operation'          => ['label' => __('Gestión', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'terms', 'taxonomy' => PropertyTaxonomies::TAXONOMY_OPERATION],
            'type'               => ['label' => __('Tipo de inmueble', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'terms', 'taxonomy' => PropertyTaxonomies::TAXONOMY_TYPE],
            'category'           => ['label' => __('Categoría', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'terms', 'taxonomy' => PropertyTaxonomies::TAXONOMY_CATEGORY],
            'tags'               => ['label' => __('Etiquetas', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'terms', 'taxonomy' => PropertyTaxonomies::TAXONOMY_TAG],
            'condition'          => ['label' => __('Estado', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'terms', 'taxonomy' => PropertyTaxonomies::TAXONOMY_CONDITION, 'meta' => 'condition'],
            'featured'           => ['label' => __('Destacado', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'bool', 'meta' => 'featured'],
            'identification'     => ['label' => __('Matrícula inmobiliaria', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'meta', 'meta' => 'identification'],
            'commercial_note'    => ['label' => __('Nota comercial', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'meta', 'meta' => 'commercial_note'],
            'photo_note'         => ['label' => __('Nota de fotos', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'meta', 'meta' => 'photo_note'],
            'consignant_type'    => ['label' => __('Tipo de consignante', 'homlity-real-estate'), 'group' => $inmueble, 'kind' => 'meta', 'meta' => 'consignant_type'],

            // ── Precios ───────────────────────────────────────────────────
            'price_sale'         => ['label' => __('Precio de venta', 'homlity-real-estate'), 'group' => $precios, 'kind' => 'money', 'meta' => 'price_sale', 'currency' => 'currency_sale'],
            'price_rent'         => ['label' => __('Canon de arriendo', 'homlity-real-estate'), 'group' => $precios, 'kind' => 'money', 'meta' => 'price_rent', 'currency' => 'currency_rent'],
            'price_admin'        => ['label' => __('Administración', 'homlity-real-estate'), 'group' => $precios, 'kind' => 'money', 'meta' => 'price_admin', 'currency' => 'currency_admin'],
            'price'              => ['label' => __('Precio principal (venta o arriendo)', 'homlity-real-estate'), 'group' => $precios, 'kind' => 'special', 'source' => 'primary_price'],
            'currency_sale'      => ['label' => __('Moneda de venta', 'homlity-real-estate'), 'group' => $precios, 'kind' => 'meta', 'meta' => 'currency_sale'],
            'currency_rent'      => ['label' => __('Moneda de arriendo', 'homlity-real-estate'), 'group' => $precios, 'kind' => 'meta', 'meta' => 'currency_rent'],
            'currency_admin'     => ['label' => __('Moneda de administración', 'homlity-real-estate'), 'group' => $precios, 'kind' => 'meta', 'meta' => 'currency_admin'],
            'price_valid_until'  => ['label' => __('Precio vigente hasta', 'homlity-real-estate'), 'group' => $precios, 'kind' => 'meta', 'meta' => 'price_valid_until'],
            'admin_included'     => ['label' => __('Administración incluida', 'homlity-real-estate'), 'group' => $precios, 'kind' => 'bool', 'meta' => 'admin_included'],
            'negotiable'         => ['label' => __('Precio negociable', 'homlity-real-estate'), 'group' => $precios, 'kind' => 'bool', 'meta' => 'negotiable'],

            // ── Dimensiones y distribución ────────────────────────────────
            'area'               => ['label' => __('Área total', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'area', 'meta' => 'area'],
            'area_built'         => ['label' => __('Área construida', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'area', 'meta' => 'area_built'],
            'area_private'       => ['label' => __('Área privada', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'area', 'meta' => 'area_private'],
            'area_lot'           => ['label' => __('Área del lote', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'area', 'meta' => 'area_lot'],
            'bedrooms'           => ['label' => __('Habitaciones', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'number', 'meta' => 'bedrooms'],
            'bathrooms'          => ['label' => __('Baños', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'number', 'meta' => 'bathrooms'],
            'parking'            => ['label' => __('Parqueaderos', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'number', 'meta' => 'parking'],
            'stratum'            => ['label' => __('Estrato', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'number', 'meta' => 'stratum'],
            'floor'              => ['label' => __('Piso', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'number', 'meta' => 'floor'],
            'levels'             => ['label' => __('Niveles', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'number', 'meta' => 'levels'],
            'elevators'          => ['label' => __('Ascensores', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'number', 'meta' => 'elevators'],
            'age'                => ['label' => __('Año de construcción', 'homlity-real-estate'), 'group' => $medidas, 'kind' => 'meta', 'meta' => 'age'],

            // ── Ubicación ─────────────────────────────────────────────────
            'country'            => ['label' => __('País', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'terms', 'taxonomy' => PropertyTaxonomies::TAXONOMY_COUNTRY],
            'state'              => ['label' => __('Departamento / Provincia', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'terms', 'taxonomy' => PropertyTaxonomies::TAXONOMY_STATE],
            'city'               => ['label' => __('Ciudad', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'terms', 'taxonomy' => PropertyTaxonomies::TAXONOMY_CITY],
            'neighborhood'       => ['label' => __('Barrio', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'terms', 'taxonomy' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD],
            'nearby'             => ['label' => __('Lugares cercanos', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'terms', 'taxonomy' => PropertyTaxonomies::TAXONOMY_NEARBY],
            'location'           => ['label' => __('Ubicación completa (barrio, ciudad…)', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'special', 'source' => 'location'],
            'address'            => ['label' => __('Dirección', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'meta', 'meta' => 'address'],
            'address_complement' => ['label' => __('Complemento de la dirección', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'meta', 'meta' => 'address_complement'],
            'location_reference' => ['label' => __('Referencia de ubicación', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'meta', 'meta' => 'location_reference'],
            'latitude'           => ['label' => __('Latitud', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'meta', 'meta' => 'latitude'],
            'longitude'          => ['label' => __('Longitud', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'meta', 'meta' => 'longitude'],
            'show_exact_address' => ['label' => __('Muestra la dirección exacta', 'homlity-real-estate'), 'group' => $ubicacion, 'kind' => 'bool', 'meta' => 'show_exact_address'],

            // ── Características ───────────────────────────────────────────
            'features'           => ['label' => __('Características (lista)', 'homlity-real-estate'), 'group' => $caracteristicas, 'kind' => 'special', 'source' => 'features'],
            'features_count'     => ['label' => __('Número de características', 'homlity-real-estate'), 'group' => $caracteristicas, 'kind' => 'special', 'source' => 'features_count'],

            // ── Asesor ────────────────────────────────────────────────────
            'agent_name'         => ['label' => __('Nombre del asesor', 'homlity-real-estate'), 'group' => $asesor, 'kind' => 'agent', 'source' => 'name', 'meta' => 'agent_name'],
            'agent_role'         => ['label' => __('Cargo del asesor', 'homlity-real-estate'), 'group' => $asesor, 'kind' => 'agent', 'source' => 'role', 'meta' => 'agent_role'],
            'agent_phone'        => ['label' => __('Teléfono del asesor', 'homlity-real-estate'), 'group' => $asesor, 'kind' => 'agent', 'source' => 'phone', 'meta' => 'agent_phone'],
            'agent_email'        => ['label' => __('Correo del asesor', 'homlity-real-estate'), 'group' => $asesor, 'kind' => 'agent', 'source' => 'email', 'meta' => 'agent_email'],
            'agent_bio'          => ['label' => __('Biografía del asesor', 'homlity-real-estate'), 'group' => $asesor, 'kind' => 'agent', 'source' => 'bio'],

            // ── Contacto del inmueble ─────────────────────────────────────
            'contact_name'       => ['label' => __('Nombre de contacto', 'homlity-real-estate'), 'group' => $contacto, 'kind' => 'meta', 'meta' => 'contact_name'],
            'contact_phone'      => ['label' => __('Teléfono de contacto', 'homlity-real-estate'), 'group' => $contacto, 'kind' => 'meta', 'meta' => 'contact_phone'],
            'contact_email'      => ['label' => __('Correo de contacto', 'homlity-real-estate'), 'group' => $contacto, 'kind' => 'meta', 'meta' => 'contact_email'],
            'contact_whatsapp'   => ['label' => __('WhatsApp de contacto', 'homlity-real-estate'), 'group' => $contacto, 'kind' => 'meta', 'meta' => 'contact_whatsapp'],

            // ── Multimedia ────────────────────────────────────────────────
            'gallery_count'      => ['label' => __('Número de fotos', 'homlity-real-estate'), 'group' => $multimedia, 'kind' => 'special', 'source' => 'gallery_count'],
            'videos_count'       => ['label' => __('Número de vídeos', 'homlity-real-estate'), 'group' => $multimedia, 'kind' => 'special', 'source' => 'videos_count'],
            'photos_360_count'   => ['label' => __('Número de fotos 360°', 'homlity-real-estate'), 'group' => $multimedia, 'kind' => 'special', 'source' => 'photos_360_count'],
            'has_tour_360'       => ['label' => __('Tiene recorrido 360°', 'homlity-real-estate'), 'group' => $multimedia, 'kind' => 'special', 'source' => 'has_tour_360'],
            'has_brochure'       => ['label' => __('Tiene folleto', 'homlity-real-estate'), 'group' => $multimedia, 'kind' => 'special', 'source' => 'has_brochure'],

            // ── Fechas ────────────────────────────────────────────────────
            'published_at'       => ['label' => __('Fecha de publicación', 'homlity-real-estate'), 'group' => $fechas, 'kind' => 'post', 'source' => 'published_at'],
            'updated_at'         => ['label' => __('Fecha de actualización', 'homlity-real-estate'), 'group' => $fechas, 'kind' => 'post', 'source' => 'updated_at'],
        ];

        return self::$definitions;
    }

    /**
     * Opciones del desplegable, agrupadas como las espera el control `select`
     * de Elementor: [ ['label' => grupo, 'options' => [clave => etiqueta]] ].
     *
     * @return array<int,array{label:string, options:array<string,string>}>
     */
    public static function textGroups(): array
    {
        $groups = [];
        foreach (self::definitions() as $key => $definition) {
            $group = (string) $definition['group'];
            $groups[$group]['label'] = $group;
            $groups[$group]['options'][$key] = (string) $definition['label'];
        }

        return array_values($groups);
    }

    /** @return array<string,string> clave => etiqueta visible */
    public static function textChoices(): array
    {
        return array_map(
            static fn(array $definition): string => (string) $definition['label'],
            self::definitions()
        );
    }

    /** @return array<string,string> clave => etiqueta visible */
    public static function urlChoices(): array
    {
        return [
            'permalink'        => __('Ficha del inmueble', 'homlity-real-estate'),
            'whatsapp'         => __('WhatsApp del inmueble', 'homlity-real-estate'),
            'maps'             => __('Ubicación en Google Maps', 'homlity-real-estate'),
            'brochure'         => __('Folleto (PDF)', 'homlity-real-estate'),
            'tour_360'         => __('Recorrido 360°', 'homlity-real-estate'),
            'video'            => __('Vídeo', 'homlity-real-estate'),
            'agent_profile'    => __('Perfil del asesor', 'homlity-real-estate'),
            'agent_phone'      => __('Llamar al asesor (tel:)', 'homlity-real-estate'),
            'agent_email'      => __('Escribir al asesor (mailto:)', 'homlity-real-estate'),
            'contact_phone'    => __('Llamar al contacto (tel:)', 'homlity-real-estate'),
            'contact_email'    => __('Escribir al contacto (mailto:)', 'homlity-real-estate'),
        ];
    }

    /** @return array<string,string> clave => etiqueta visible */
    public static function imageChoices(): array
    {
        return [
            'featured'      => __('Imagen principal', 'homlity-real-estate'),
            'gallery_first' => __('Primera foto de la galería', 'homlity-real-estate'),
            'gallery_last'  => __('Última foto de la galería', 'homlity-real-estate'),
            'agent_photo'   => __('Foto del asesor', 'homlity-real-estate'),
        ];
    }

    // ── A qué inmueble se refiere la etiqueta ─────────────────────────────

    /**
     * El inmueble del que se leen los datos.
     *
     * Por orden: el que se fije en el control —la única forma de ver algo en
     * el editor, donde no hay ficha que consultar—, el de la consulta, y el
     * del bucle. Ese último paso es lo que hace que las mismas etiquetas
     * funcionen dentro de un Loop Grid sin cambiar nada.
     *
     * @param mixed $candidate Id fijado en el control; vacío para deducirlo.
     */
    public static function resolvePropertyId($candidate = null): int
    {
        $fixed = is_scalar($candidate) ? absint($candidate) : 0;
        if ($fixed > 0 && self::isProperty($fixed)) {
            return $fixed;
        }

        foreach ([(int) get_queried_object_id(), (int) get_the_ID()] as $candidateId) {
            if ($candidateId > 0 && self::isProperty($candidateId)) {
                return $candidateId;
            }
        }

        return 0;
    }

    private static function isProperty(int $postId): bool
    {
        return $postId > 0 && get_post_type($postId) === PropertyPostType::POST_TYPE;
    }

    // ── Resolución de valores ─────────────────────────────────────────────

    /**
     * Valor de texto de un campo.
     *
     * Un campo desconocido devuelve cadena vacía: una etiqueta guardada en una
     * plantilla sigue existiendo aunque el campo desaparezca del catálogo, y
     * debe quedarse en blanco, no reventar.
     */
    public static function text(int $postId, string $field, string $format = 'formatted'): string
    {
        $definition = self::definitions()[$field] ?? null;
        if ($definition === null || !self::isProperty($postId)) {
            return '';
        }

        $raw = $format === self::FORMAT_RAW;

        return match ((string) $definition['kind']) {
            'meta' => self::meta($postId, (string) $definition['meta']),
            'money' => self::money($postId, $definition, $raw),
            'area' => self::area($postId, (string) $definition['meta'], $raw),
            'number' => self::number($postId, (string) $definition['meta'], $raw),
            'bool' => self::boolean(self::meta($postId, (string) $definition['meta']) !== '', $raw),
            'terms' => self::terms($postId, $definition, $raw),
            'post' => self::postField($postId, (string) $definition['source']),
            'agent' => self::agent($postId, $definition),
            'special' => self::special($postId, (string) $definition['source'], $raw),
            default => '',
        };
    }

    /**
     * URL de un enlace. Sin el dato de origen devuelve cadena vacía, que es lo
     * que Elementor entiende como «no pintes el enlace»: un `tel:` a secas
     * sería un botón que no lleva a ninguna parte.
     */
    public static function url(int $postId, string $link): string
    {
        if (!self::isProperty($postId)) {
            return '';
        }

        return match ($link) {
            'permalink' => (string) get_permalink($postId),
            'whatsapp' => WhatsAppLinkService::buildListingPropertyLink($postId),
            'maps' => self::mapsUrl($postId),
            'brochure' => self::firstUrl(self::meta($postId, 'brochure')),
            'tour_360' => self::firstUrl(self::rawMeta($postId, 'tour_360')),
            'video' => self::firstUrl(self::rawMeta($postId, 'videos')),
            'agent_profile' => self::agentProfileUrl($postId),
            'agent_phone' => self::telLink(self::text($postId, 'agent_phone')),
            'agent_email' => self::mailtoLink(self::text($postId, 'agent_email')),
            'contact_phone' => self::telLink(self::meta($postId, 'contact_phone')),
            'contact_email' => self::mailtoLink(self::meta($postId, 'contact_email')),
            default => '',
        };
    }

    /**
     * Imagen para el widget de imagen.
     *
     * Elementor espera id y URL: con el id monta los tamaños intermedios y el
     * srcset, y cae a la URL cuando la foto viene del CRM y no está en la
     * biblioteca de medios.
     *
     * @return array{id:int, url:string}
     */
    public static function image(int $postId, string $which): array
    {
        if (!self::isProperty($postId)) {
            return ['id' => 0, 'url' => ''];
        }

        if ($which === 'agent_photo') {
            return AgentFields::image(self::agentUser($postId));
        }

        if ($which === 'featured') {
            return PropertyMedia::mainImage($postId);
        }

        $gallery = self::gallery($postId);
        if ($gallery === []) {
            return ['id' => 0, 'url' => ''];
        }

        return $which === 'gallery_last' ? end($gallery) : $gallery[0];
    }

    /**
     * Todas las fotos del inmueble, en el formato que espera la galería de
     * Elementor.
     *
     * @return array<int,array{id:int, url:string}>
     */
    public static function gallery(int $postId): array
    {
        if (!self::isProperty($postId)) {
            return [];
        }

        return PropertyMedia::galleryImages($postId);
    }

    // ── Lectores por tipo de campo ────────────────────────────────────────

    /** @param mixed $key Alias del catálogo de metadatos del CPT. */
    private static function meta(int $postId, string $key): string
    {
        $value = self::rawMeta($postId, $key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @return mixed */
    private static function rawMeta(int $postId, string $key)
    {
        self::$metaKeys ??= (new PropertyPostType())->metaKeys();
        if (!isset(self::$metaKeys[$key])) {
            return '';
        }

        return get_post_meta($postId, self::$metaKeys[$key], true);
    }

    /** @param array<string,mixed> $definition */
    private static function money(int $postId, array $definition, bool $raw): string
    {
        $amount = (float) self::meta($postId, (string) $definition['meta']);
        if ($amount <= 0) {
            return '';
        }

        if ($raw) {
            return (string) $amount;
        }

        $currency = self::meta($postId, (string) $definition['currency']);

        // El mismo formateador que pintan las tarjetas, para que un precio no
        // salga de dos maneras en la misma página. Si nadie lo engancha —una
        // instalación con CurrencyService apagado— el filtro devuelve el
        // número tal cual, y un precio pelado no es un precio.
        $formatted = \homlity_plugin_apply_filters(
            'homlity_plugin_format_price',
            null,
            $amount,
            $currency !== '' ? $currency : null
        );

        return is_string($formatted) && $formatted !== '' && $formatted !== (string) $amount
            ? $formatted
            : TechnicalSheetData::formatMoney($amount, $currency);
    }

    private static function area(int $postId, string $key, bool $raw): string
    {
        $value = self::meta($postId, $key);
        if ($value === '' || (float) $value <= 0) {
            return '';
        }

        return $raw ? $value : number_format_i18n((float) $value, 0) . ' m²';
    }

    private static function number(int $postId, string $key, bool $raw): string
    {
        $value = self::meta($postId, $key);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        return $raw ? $value : number_format_i18n((float) $value, 0);
    }

    private static function boolean(bool $value, bool $raw): string
    {
        if ($raw) {
            return $value ? '1' : '0';
        }

        return $value ? __('Sí', 'homlity-real-estate') : __('No', 'homlity-real-estate');
    }

    /** @param array<string,mixed> $definition */
    private static function terms(int $postId, array $definition, bool $raw): string
    {
        $terms = get_the_terms($postId, (string) $definition['taxonomy']);
        if (is_array($terms) && $terms !== []) {
            return implode(', ', array_map(
                static fn($term): string => (string) ($raw ? $term->slug : $term->name),
                $terms
            ));
        }

        // El estado se guardó como metadato antes de tener taxonomía propia y
        // hay catálogos sincronizados que siguen así.
        return isset($definition['meta']) ? self::meta($postId, (string) $definition['meta']) : '';
    }

    private static function postField(int $postId, string $source): string
    {
        return match ($source) {
            'title' => (string) get_the_title($postId),
            'description' => PropertyDescription::text($postId),
            'excerpt' => trim((string) get_the_excerpt($postId)),
            'published_at' => (string) get_the_date('', $postId),
            'updated_at' => (string) get_the_modified_date('', $postId),
            default => '',
        };
    }

    /** @param array<string,mixed> $definition */
    private static function agent(int $postId, array $definition): string
    {
        $source = (string) $definition['source'];
        $value = AgentFields::text(self::agentUser($postId), $source);
        if ($value !== '') {
            return $value;
        }

        // Los inmuebles importados traen los datos del asesor en el propio
        // inmueble aunque ese asesor no tenga usuario en el sitio.
        return isset($definition['meta']) ? self::meta($postId, (string) $definition['meta']) : '';
    }

    private static function agentUser(int $postId): ?WP_User
    {
        $agentId = (int) self::rawMeta($postId, 'agent_id');
        if ($agentId <= 0) {
            return null;
        }

        $user = get_user_by('id', $agentId);

        return $user instanceof WP_User ? $user : null;
    }

    private static function agentProfileUrl(int $postId): string
    {
        $user = self::agentUser($postId);

        return $user instanceof WP_User ? AgentFields::url($user, 'profile') : '';
    }

    private static function special(int $postId, string $source, bool $raw): string
    {
        return match ($source) {
            'primary_price' => self::primaryPrice($postId, $raw),
            'location' => self::location($postId),
            'features' => implode(', ', self::featureNames($postId)),
            'features_count' => (string) count(self::featureNames($postId)),
            'gallery_count' => (string) count(self::gallery($postId)),
            'videos_count' => (string) count(TechnicalSheetData::urls(self::rawMeta($postId, 'videos'))),
            'photos_360_count' => (string) count(TechnicalSheetData::urls(self::rawMeta($postId, 'photos_360'))),
            'has_tour_360' => self::boolean(self::firstUrl(self::rawMeta($postId, 'tour_360')) !== '', $raw),
            'has_brochure' => self::boolean(self::meta($postId, 'brochure') !== '', $raw),
            default => '',
        };
    }

    /** El precio que se enseña en la tarjeta: venta si lo hay, si no arriendo. */
    private static function primaryPrice(int $postId, bool $raw): string
    {
        foreach (['price_sale', 'price_rent', 'price_admin'] as $key) {
            $value = self::text($postId, $key, $raw ? self::FORMAT_RAW : 'formatted');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function location(int $postId): string
    {
        $parts = [];
        foreach (['neighborhood', 'city', 'state', 'country'] as $field) {
            $value = self::text($postId, $field);
            if ($value !== '' && !in_array($value, $parts, true)) {
                $parts[] = $value;
            }
        }

        return implode(', ', $parts);
    }

    /** @return string[] */
    private static function featureNames(int $postId): array
    {
        return array_map(
            static fn($term): string => (string) $term->name,
            PropertyTaxonomies::getVisibleFeatureTermsForPost($postId)
        );
    }

    private static function mapsUrl(int $postId): string
    {
        $manual = self::meta($postId, 'maps_url');
        if ($manual !== '' && filter_var($manual, FILTER_VALIDATE_URL)) {
            return $manual;
        }

        $lat = self::meta($postId, 'latitude');
        $lng = self::meta($postId, 'longitude');
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return '';
        }

        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);
    }

    /**
     * La primera URL de un metadato que puede traer una lista.
     *
     * @param mixed $value
     */
    private static function firstUrl($value): string
    {
        foreach (TechnicalSheetData::urls($value) as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        return '';
    }

    private static function telLink(string $phone): string
    {
        // Se conserva el prefijo internacional y se tira todo lo demás:
        // espacios y paréntesis rompen el marcado en algunos teléfonos.
        $digits = preg_replace('/(?!^\+)\D+/', '', trim($phone));

        return is_string($digits) && $digits !== '' && $digits !== '+' ? 'tel:' . $digits : '';
    }

    private static function mailtoLink(string $email): string
    {
        $email = sanitize_email($email);

        return $email !== '' && is_email($email) ? 'mailto:' . $email : '';
    }
}
