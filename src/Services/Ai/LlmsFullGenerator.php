<?php
/**
 * Generates the Markdown content for /llms-full.txt.
 *
 * Focused on property indexing and AI discoverability of listings.
 * Each published property is rendered as a self-contained structured record
 * so that AI assistants can answer queries like "apartamentos en Medellín
 * con 3 habitaciones en venta" with direct citations and links.
 *
 * All output is plain-text Markdown. No HTML, no sensitive data.
 */

namespace Homlity\PluginInmobiliario\Services\Ai;

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class LlmsFullGenerator
{
    public function generate(): string
    {
        $postType = apply_filters('homlity_llms_full_post_type', PropertyPostType::POST_TYPE);

        // Fetch all published properties in two queries (posts + bulk meta/terms).
        $posts = $this->fetchProperties($postType);

        $sections = [
            $this->sectionHeader(),
            $this->sectionSearchGuide($postType),
            $this->sectionInventorySummary($posts, $postType),
            $this->sectionIndexByType(),
            $this->sectionIndexByOperation(),
            $this->sectionIndexByCity(),
            $this->sectionCatalog($posts),
            $this->sectionContactInfo(),
            $this->sectionAiNote(),
        ];

        return implode("\n\n", array_filter($sections, static fn($s) => $s !== ''));
    }

    // ── Sections ──────────────────────────────────────────────────────────────

    private function sectionHeader(): string
    {
        $siteName    = wp_strip_all_tags(get_bloginfo('name'));
        $description = $this->getText('homlity_llms_full_business_description');

        $lines = ['# ' . $siteName . ' — Catálogo de Inmuebles'];

        if ($description !== '') {
            $lines[] = '';
            $lines[] = '> ' . $description;
        }

        $lines[] = '';
        $lines[] = 'Este archivo contiene el catálogo completo de inmuebles disponibles, estructurado para motores de inteligencia artificial. Cada inmueble incluye tipo, ubicación, precio, características y enlace directo al sitio web.';

        return implode("\n", $lines);
    }

    private function sectionSearchGuide(string $postType): string
    {
        $catalogUrl = $this->resolvedCatalogUrl($postType);
        $city       = $this->getText('homlity_llms_full_city');
        $whatsapp   = $this->getText('homlity_llms_full_whatsapp');
        $email      = $this->getText('homlity_llms_full_email');

        $lines = ['## Cómo encontrar inmuebles', ''];

        if ($catalogUrl !== '') {
            $lines[] = '- Catálogo con filtros avanzados: ' . $catalogUrl;
            $lines[] = '- Buscar por código de referencia: ' . $catalogUrl . '?q=[CÓDIGO]';
        }

        $lines[] = '- Filtros disponibles: tipo de inmueble, ciudad, zona/barrio, gestión (venta / arriendo), precio mínimo y máximo, habitaciones, baños y características.';
        $lines[] = '';
        $lines[] = '**Para agendar visita o solicitar información:**';

        if ($whatsapp !== '') {
            $lines[] = '- WhatsApp: ' . $whatsapp;
        }
        if ($email !== '') {
            $lines[] = '- Correo: ' . $email;
        }
        if ($city !== '') {
            $lines[] = '- Ciudad principal: ' . $city;
        }

        $lines[] = '- Sitio web: ' . esc_url_raw(home_url('/'));

        return implode("\n", $lines);
    }

    /**
     * @param \WP_Post[] $posts
     */
    private function sectionInventorySummary(array $posts, string $postType): string
    {
        $total      = count($posts);
        $catalogUrl = $this->resolvedCatalogUrl($postType);

        $lines = ['## Resumen del inventario', ''];
        $lines[] = '- **Total de inmuebles disponibles: ' . $total . '**';

        if ($catalogUrl !== '') {
            $lines[] = '- Ver catálogo completo: ' . $catalogUrl;
        }

        $lines[] = '- Actualización: en tiempo real (generado dinámicamente)';
        $lines[] = '- Última generación: ' . (string) wp_date('Y-m-d H:i');

        return implode("\n", $lines);
    }

    private function sectionIndexByType(): string
    {
        return $this->buildTaxonomyIndex(
            '## Inmuebles por tipo',
            apply_filters('homlity_llms_full_property_type_taxonomy', PropertyTaxonomies::TAXONOMY_TYPE)
        );
    }

    private function sectionIndexByOperation(): string
    {
        return $this->buildTaxonomyIndex(
            '## Inmuebles por gestión',
            PropertyTaxonomies::TAXONOMY_OPERATION
        );
    }

    private function sectionIndexByCity(): string
    {
        return $this->buildTaxonomyIndex(
            '## Inmuebles por ciudad',
            apply_filters('homlity_llms_full_location_taxonomy', PropertyTaxonomies::TAXONOMY_CITY)
        );
    }

    /**
     * @param \WP_Post[] $posts
     */
    private function sectionCatalog(array $posts): string
    {
        if (empty($posts)) {
            return implode("\n", [
                '## Catálogo de inmuebles',
                '',
                'No hay inmuebles publicados en este momento.',
            ]);
        }

        $total = count($posts);
        $lines = [
            '## Catálogo de inmuebles',
            '',
            '*' . $total . ' inmueble' . ($total !== 1 ? 's' : '') . ' disponible' . ($total !== 1 ? 's' : '') . ', ordenados por fecha de publicación.*',
        ];

        foreach ($posts as $post) {
            $lines[] = '';
            $lines[] = '---';
            $lines[] = '';
            $lines[] = $this->renderProperty($post);
        }

        $lines[] = '';
        $lines[] = '---';

        return implode("\n", $lines);
    }

    private function renderProperty(\WP_Post $post): string
    {
        $id = $post->ID;

        // ── Taxonomies (served from term cache loaded by WP_Query) ─────────
        $type         = $this->firstTermName($id, PropertyTaxonomies::TAXONOMY_TYPE);
        $operation    = $this->firstTermName($id, PropertyTaxonomies::TAXONOMY_OPERATION);
        $country      = $this->firstTermName($id, PropertyTaxonomies::TAXONOMY_COUNTRY);
        $state        = $this->firstTermName($id, PropertyTaxonomies::TAXONOMY_STATE);
        $city         = $this->firstTermName($id, PropertyTaxonomies::TAXONOMY_CITY);
        $neighborhood = $this->firstTermName($id, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD);
        $location     = $this->firstTermName($id, PropertyTaxonomies::TAXONOMY_LOCATION);
        $features     = $this->allTermNames($id, PropertyTaxonomies::TAXONOMY_FEATURE);
        $nearby       = $this->allTermNames($id, PropertyTaxonomies::TAXONOMY_NEARBY);

        // ── Meta (served from meta cache loaded by WP_Query) ───────────────
        $code         = (string) get_post_meta($id, '_property_code', true);
        $priceSale    = (string) get_post_meta($id, '_property_price_sale', true);
        $currSale     = (string) get_post_meta($id, '_property_currency_sale', true);
        $priceRent    = (string) get_post_meta($id, '_property_price_rent', true);
        $currRent     = (string) get_post_meta($id, '_property_currency_rent', true);
        $priceAdmin   = (string) get_post_meta($id, '_property_price_admin', true);
        $currAdmin    = (string) get_post_meta($id, '_property_currency_admin', true);
        $adminIncl    = (bool) get_post_meta($id, '_property_admin_included', true);
        $area         = (string) get_post_meta($id, '_property_area', true);
        $areaBuilt    = (string) get_post_meta($id, '_property_area_built', true);
        $areaPrivate  = (string) get_post_meta($id, '_property_area_private', true);
        $areaLot      = (string) get_post_meta($id, '_property_area_lot', true);
        $bedrooms     = (string) get_post_meta($id, '_property_bedrooms', true);
        $bathrooms    = (string) get_post_meta($id, '_property_bathrooms', true);
        $parking      = (string) get_post_meta($id, '_property_parking', true);
        $condition    = (string) get_post_meta($id, '_property_condition', true);
        $address      = (string) get_post_meta($id, '_property_address', true);
        $featured     = (bool) get_post_meta($id, '_property_featured', true);

        // ── Heading ───────────────────────────────────────────────────────
        $title   = wp_strip_all_tags($post->post_title);
        $heading = '### ' . $title;
        if ($code !== '') {
            $heading .= ' — Ref. ' . $code;
        }
        if ($featured) {
            $heading .= ' [DESTACADO]';
        }

        $lines = [$heading, ''];

        // ── Classification ────────────────────────────────────────────────
        if ($type !== '') {
            $lines[] = '- **Tipo:** ' . $type;
        }
        if ($operation !== '') {
            $lines[] = '- **Gestión:** ' . $operation;
        }

        // ── Location ──────────────────────────────────────────────────────
        $locationParts = array_filter([$neighborhood, $location, $city, $state, $country]);
        if ($locationParts !== []) {
            $lines[] = '- **Ubicación:** ' . implode(', ', $locationParts);
        }
        if ($address !== '') {
            $lines[] = '- **Dirección:** ' . sanitize_text_field($address);
        }

        // ── Prices ────────────────────────────────────────────────────────
        if ($priceSale !== '' && (float) $priceSale > 0) {
            $lines[] = '- **Precio venta:** ' . $this->formatPrice($priceSale, $currSale);
        }
        if ($priceRent !== '' && (float) $priceRent > 0) {
            $adminNote = $adminIncl ? ' (administración incluida)' : '';
            $lines[] = '- **Precio arriendo:** ' . $this->formatPrice($priceRent, $currRent) . $adminNote;
        }
        if ($priceAdmin !== '' && (float) $priceAdmin > 0 && !$adminIncl) {
            $lines[] = '- **Administración:** ' . $this->formatPrice($priceAdmin, $currAdmin);
        }

        // ── Physical specs ────────────────────────────────────────────────
        if ($bedrooms !== '' && $bedrooms !== '0') {
            $lines[] = '- **Habitaciones:** ' . $bedrooms;
        }
        if ($bathrooms !== '' && $bathrooms !== '0') {
            $lines[] = '- **Baños:** ' . $bathrooms;
        }
        if ($parking !== '' && $parking !== '0') {
            $lines[] = '- **Parqueaderos:** ' . $parking;
        }

        // Areas — show the most informative combination without duplicating
        $primaryArea = $area ?: $areaBuilt ?: $areaPrivate;
        if ($primaryArea !== '') {
            $lines[] = '- **Área total:** ' . $primaryArea . ' m²';
        }
        if ($areaBuilt !== '' && $areaBuilt !== $primaryArea) {
            $lines[] = '- **Área construida:** ' . $areaBuilt . ' m²';
        }
        if ($areaPrivate !== '' && $areaPrivate !== $primaryArea && $areaPrivate !== $areaBuilt) {
            $lines[] = '- **Área privada:** ' . $areaPrivate . ' m²';
        }
        if ($areaLot !== '' && $areaLot !== $primaryArea) {
            $lines[] = '- **Área de lote:** ' . $areaLot . ' m²';
        }

        if ($condition !== '') {
            $lines[] = '- **Estado:** ' . sanitize_text_field($condition);
        }

        // ── Features ──────────────────────────────────────────────────────
        if ($features !== []) {
            $lines[] = '- **Características:** ' . implode(', ', $features);
        }
        if ($nearby !== []) {
            $lines[] = '- **Lugares cercanos:** ' . implode(', ', $nearby);
        }

        // ── Description ───────────────────────────────────────────────────
        $excerpt = trim(wp_strip_all_tags($post->post_excerpt));
        if ($excerpt !== '') {
            $lines[] = '- **Descripción:** ' . $excerpt;
        }

        // ── Link (critical for AI citations) ─────────────────────────────
        $permalink = get_permalink($post);
        if ($permalink) {
            $lines[] = '- **Enlace:** ' . esc_url_raw($permalink);
        }

        return implode("\n", $lines);
    }

    private function sectionContactInfo(): string
    {
        $whatsapp   = $this->getText('homlity_llms_full_whatsapp');
        $email      = $this->getText('homlity_llms_full_email');
        $contactUrl = $this->getUrl('homlity_llms_full_contact_url', home_url('/contacto/'));
        $serviceUrl = $this->getUrl('homlity_llms_full_services_url', '');
        $blogUrl    = $this->getUrl('homlity_llms_full_blog_url', '');
        $extra      = wp_strip_all_tags(trim((string) get_option('homlity_llms_full_extra_context', '')));

        $services = apply_filters(
            'homlity_llms_full_services',
            $this->getList('homlity_llms_full_services')
        );

        $lines = ['## Información de contacto y enlaces', ''];
        $lines[] = '- Inicio: ' . esc_url_raw(home_url('/'));

        if ($contactUrl !== '') {
            $lines[] = '- Contacto: ' . $contactUrl;
        }
        if ($serviceUrl !== '') {
            $lines[] = '- Servicios: ' . $serviceUrl;
        }
        if ($blogUrl !== '') {
            $lines[] = '- Blog: ' . $blogUrl;
        }
        if ($whatsapp !== '') {
            $lines[] = '- WhatsApp: ' . $whatsapp;
        }
        if ($email !== '') {
            $lines[] = '- Correo: ' . $email;
        }

        if ($services !== []) {
            $lines[] = '';
            $lines[] = '**Servicios ofrecidos:**';
            foreach ($services as $s) {
                $s = trim(wp_strip_all_tags((string) $s));
                if ($s !== '') {
                    $lines[] = '- ' . $s;
                }
            }
        }

        if ($extra !== '') {
            $lines[] = '';
            $lines[] = $extra;
        }

        return implode("\n", $lines);
    }

    private function sectionAiNote(): string
    {
        return implode("\n", [
            '## Nota para modelos de IA',
            '',
            'Este catálogo contiene únicamente información pública de inmuebles disponibles. No incluye datos privados de propietarios, leads, contratos, credenciales, tokens de API ni información confidencial. Los precios e inventario se actualizan en tiempo real.',
        ]);
    }

    // ── Data fetching ─────────────────────────────────────────────────────────

    /**
     * Fetches published properties using WP_Query.
     *
     * WP_Query with update_post_meta_cache and update_post_term_cache (both true
     * by default) loads all meta and all taxonomy terms for the result set in
     * two extra queries — regardless of how many posts are returned.
     * This means per-property calls to get_post_meta() and get_the_terms() inside
     * renderProperty() are O(1) cache hits, not database queries.
     *
     * @return \WP_Post[]
     */
    private function fetchProperties(string $postType): array
    {
        $limit = (int) apply_filters('homlity_llms_full_property_limit', 500);

        $query = new \WP_Query([
            'post_type'              => $postType,
            'post_status'            => 'publish',
            'posts_per_page'         => $limit,
            'orderby'                => ['date' => 'DESC'],
            'no_found_rows'          => true,  // skip COUNT(*) — we don't need pagination info
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
        ]);

        $posts = $query->posts;
        wp_reset_postdata();

        return $posts;
    }

    // ── Taxonomy index builder ────────────────────────────────────────────────

    private function buildTaxonomyIndex(string $heading, string $taxonomy): string
    {
        if (!taxonomy_exists($taxonomy)) {
            return '';
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'number'     => 200,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $lines = [$heading, ''];
        foreach ($terms as $term) {
            $count = (int) $term->count;
            $label = sanitize_text_field($term->name);
            $entry = '- ' . $label . ' (' . $count . ' inmueble' . ($count !== 1 ? 's' : '') . ')';

            $link = get_term_link($term);
            if (!is_wp_error($link)) {
                $entry .= ': ' . esc_url_raw($link);
            }

            $lines[] = $entry;
        }

        return implode("\n", $lines);
    }

    // ── Per-property helpers ──────────────────────────────────────────────────

    private function firstTermName(int $postId, string $taxonomy): string
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }
        return sanitize_text_field($terms[0]->name);
    }

    /**
     * @return string[]
     */
    private function allTermNames(int $postId, string $taxonomy): array
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (empty($terms) || is_wp_error($terms)) {
            return [];
        }
        return array_map(static fn($t) => sanitize_text_field($t->name), $terms);
    }

    private function formatPrice(string $value, string $currency): string
    {
        $num = (float) $value;
        if ($num <= 0) {
            return '';
        }
        $curr = $currency !== '' ? ' ' . strtoupper($currency) : '';
        return '$' . number_format($num, 0, ',', '.') . $curr;
    }

    // ── Generic option helpers ────────────────────────────────────────────────

    private function resolvedCatalogUrl(string $postType): string
    {
        return apply_filters(
            'homlity_llms_full_catalog_url',
            $this->getUrl(
                'homlity_llms_full_catalog_url',
                (string) (get_post_type_archive_link($postType) ?: '')
            )
        );
    }

    private function getText(string $key): string
    {
        return sanitize_text_field((string) get_option($key, ''));
    }

    private function getUrl(string $key, string $fallback = ''): string
    {
        $value = trim((string) get_option($key, ''));
        return $value !== '' ? esc_url_raw($value) : ($fallback !== '' ? esc_url_raw($fallback) : '');
    }

    /**
     * Returns a list option as a clean string array.
     * Accepts both a stored array and a newline-delimited string.
     *
     * @return string[]
     */
    private function getList(string $key): array
    {
        $raw = get_option($key, '');

        $items = is_array($raw)
            ? $raw
            : explode("\n", (string) $raw);

        return array_values(
            array_filter(
                array_map(static fn($item) => sanitize_text_field($item), $items)
            )
        );
    }
}
