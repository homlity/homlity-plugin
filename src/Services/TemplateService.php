<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
/**
 * Front templates for properties, taxonomies, search and agent profiles.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateService implements ServiceInterface
{
    private bool $isArchivePage = false;

    public function register(): void
    {
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_filter('request', [$this, 'normalizeSeoArchiveRequest'], 1);
        add_action('init', [$this, 'addRewriteRules']);
        add_action('init', [$this, 'registerShortcodes']);
        add_filter('template_include', [$this, 'maybeLoadTemplate']);
        add_action('pre_get_posts', [$this, 'filterArchiveQuery']);
        add_action('template_redirect', [$this, 'maybeRenderTechnicalSheetPdf'], 1);
        add_action('template_redirect', [$this, 'redirectLegacyEnglishUrls']);
        add_action('wp_enqueue_scripts', [$this, 'enqueuePublicAssets']);
        add_filter('elementor/frontend/admin_bar/settings', [$this, 'injectElementorAdminBarLinks'], 600);
    }

    public function registerShortcodes(): void
    {
        add_shortcode('homlity_agent_profile', [$this, 'renderAgentProfileShortcode']);
    }

    public function renderAgentProfileShortcode(): string
    {
        ob_start();
        self::includeComponent('agent-profile-content.php');
        return (string) ob_get_clean();
    }

    public function maybeRenderTechnicalSheetPdf(): void
    {
        if (is_admin() || $this->isElementorEditorRequest()) {
            return;
        }

        if (!is_singular(PropertyPostType::POST_TYPE)) {
            return;
        }

        $sheetFlag = (string) ($_GET['homlity_sheet'] ?? get_query_var('homlity_sheet', ''));
        if ($sheetFlag !== '1') {
            return;
        }

        if (!class_exists('\Dompdf\Dompdf')) {
            return;
        }

        $postId = (int) get_queried_object_id();
        if ($postId <= 0) {
            return;
        }

        $cssFile = HOMLITY_PLUGIN_PATH . 'assets/css/front-components.css';
        $css = file_exists($cssFile) ? (string) file_get_contents($cssFile) : '';
        $css .= '
            @page { margin: 18px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111827; }
            .homlity-tech-sheet { max-width: none !important; padding: 0 !important; }
            .homlity-tech-sheet__actions { display: none !important; }
            .homlity-tech-sheet__card { page-break-inside: avoid; }
            .homlity-tech-sheet__gallery { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
        ';

        ob_start();
        self::includeComponent('property-technical-sheet.php', ['post_id' => $postId]);
        $content = (string) ob_get_clean();

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>' . $content . '</body></html>';

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $filename = sanitize_title(get_the_title($postId)) ?: 'ficha-tecnica';
        $forceDownload = (string) ($_GET['download'] ?? '') === '1';
        if ($forceDownload) {
            PropertyTechnicalSheetDownloadTrackingService::trackDownload($postId);
        }
        $dompdf->stream($filename . '.pdf', ['Attachment' => $forceDownload]);
        exit;
    }

    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'property_agent';
        $vars[] = 'property_type';
        $vars[] = 'property_category';
        $vars[] = 'property_operation';
        $vars[] = 'property_city';
        $vars[] = 'property_neighborhood';
        $vars[] = 'property_nearby';
        $vars[] = 'property_tag';
        $vars[] = 'gestion';
        $vars[] = 'tipo';
        $vars[] = 'ciudad';
        $vars[] = 'barrios';
        $vars[] = 'price_min';
        $vars[] = 'price_max';
        $vars[] = 'area_min';
        $vars[] = 'area_max';
        $vars[] = 'date_from';
        $vars[] = 'date_to';
        $vars[] = 'homlity_property_archive';
        $vars[] = 'homlity_sheet';
        return $vars;
    }

    public function normalizeSeoArchiveRequest(array $queryVars): array
    {
        if (is_admin()) {
            return $queryVars;
        }

        if ($this->isElementorEditorRequest()) {
            return $queryVars;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = trim((string) wp_parse_url($requestUri, PHP_URL_PATH), '/');
        if ($path === '') {
            return $queryVars;
        }

        $parts = explode('/', $path);
        if (($parts[0] ?? '') !== 'inmuebles') {
            return $queryVars;
        }

        $queryVars['homlity_property_archive'] = '1';
        $hasArchivePageContext = !empty($queryVars['page_id']) || !empty($queryVars['pagename']);
        if (!$hasArchivePageContext && (!isset($queryVars['post_type']) || $queryVars['post_type'] === '')) {
            $queryVars['post_type'] = PropertyPostType::POST_TYPE;
            unset($queryVars['name'], $queryVars['pagename'], $queryVars['page_id'], $queryVars['attachment']);
        }

        if (count($parts) < 2) {
            return $queryVars;
        }

        for ($i = 1; $i < count($parts); $i += 2) {
            $key = sanitize_key((string) ($parts[$i] ?? ''));
            $rawValue = (string) ($parts[$i + 1] ?? '');
            if ($key === '' || $rawValue === '') {
                continue;
            }

            $value = sanitize_title(rawurldecode($rawValue));
            if ($value === '') {
                continue;
            }

            if ($key === 'gestion') {
                $queryVars['gestion'] = $value;
            } elseif ($key === 'tipo') {
                $queryVars['tipo'] = $value;
            } elseif ($key === 'ciudad') {
                $queryVars['ciudad'] = $value;
            } elseif ($key === 'barrios') {
                $queryVars['barrios'] = $value;
            }
        }

        return $queryVars;
    }

    public function addRewriteRules(): void
    {
        $archivePageId = (int) get_option('homlity_plugin_archive_page_id', 0);

        add_rewrite_rule(
            '^property-agent/([^/]+)/?$',
            'index.php?property_agent=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^properties/([^/]+)/?$',
            'index.php?' . PropertyPostType::POST_TYPE . '=$matches[1]',
            'top'
        );

        if ($archivePageId > 0) {
            add_rewrite_rule(
                '^inmuebles(?:/gestion/([^/]+))?(?:/tipo/([^/]+))?(?:/ciudad/([^/]+))?(?:/barrios/([^/]+))?/?$',
                'index.php?page_id=' . $archivePageId . '&homlity_property_archive=1&gestion=$matches[1]&tipo=$matches[2]&ciudad=$matches[3]&barrios=$matches[4]',
                'top'
            );
        } else {
            add_rewrite_rule(
                '^inmuebles(?:/gestion/([^/]+))?(?:/tipo/([^/]+))?(?:/ciudad/([^/]+))?(?:/barrios/([^/]+))?/?$',
                'index.php?post_type=' . PropertyPostType::POST_TYPE . '&homlity_property_archive=1&gestion=$matches[1]&tipo=$matches[2]&ciudad=$matches[3]&barrios=$matches[4]',
                'top'
            );
        }
    }

    public function redirectLegacyEnglishUrls(): void
    {
        if (is_admin()) {
            return;
        }

        $requestPath = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

        if (in_array($requestPath, ['properties', 'propiedades'], true)) {
            $archivePageId = (int) get_option('homlity_plugin_archive_page_id', 0);
            $target = $archivePageId ? get_permalink($archivePageId) : home_url('/inmuebles/');
            wp_safe_redirect($target, 301);
            exit;
        }

        if (is_singular(PropertyPostType::POST_TYPE) && strpos($requestPath, 'properties/') === 0) {
            wp_safe_redirect(get_permalink(get_queried_object_id()), 301);
            exit;
        }
    }

    public function filterArchiveQuery($query): void
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($this->isElementorEditorRequest()) {
            return;
        }

        $archivePageId = (int) get_option('homlity_plugin_archive_page_id', 0);
        $isSeoArchiveRequest = ((string) get_query_var('homlity_property_archive', '') === '1');
        $isArchivePage = $archivePageId > 0 && $query->is_page($archivePageId);

        // Keep Elementor page rendering for /inmuebles/ and SEO-friendly filtered routes
        // like /inmuebles/gestion/... when an archive page is configured.
        if ($archivePageId > 0 && ($isArchivePage || $isSeoArchiveRequest)) {
            $this->isArchivePage = true;
            return;
        }

        if ($isArchivePage || $isSeoArchiveRequest) {
            if ($isArchivePage && get_post_meta($archivePageId, '_elementor_edit_mode', true) === 'builder') {
                return;
            }

            $this->isArchivePage = true;
            $query->set('post_type', PropertyPostType::POST_TYPE);
            $query->set('page_id', '');
            $query->set('pagename', '');
            $query->is_page     = false;
            $query->is_singular = false;
            $query->is_archive  = true;
            $query->is_post_type_archive = [PropertyPostType::POST_TYPE];
        }

        if ($query->is_post_type_archive(PropertyPostType::POST_TYPE) || $isArchivePage || $isSeoArchiveRequest) {
            $taxQuery = [];
            $metaQuery = ['relation' => 'AND'];
            $settings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, ['archive_per_page' => 12, 'archive_order' => 'created_desc']);
            $perPage = isset($settings['archive_per_page']) ? (int) $settings['archive_per_page'] : 12;
            if ($perPage > 0) {
                $query->set('posts_per_page', $perPage);
            }

            $taxMap = [
                'property_category' => PropertyTaxonomies::TAXONOMY_CATEGORY,
                'property_type' => PropertyTaxonomies::TAXONOMY_TYPE,
                'property_operation' => PropertyTaxonomies::TAXONOMY_OPERATION,
                'property_city' => PropertyTaxonomies::TAXONOMY_CITY,
                'property_neighborhood' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
                'property_nearby' => PropertyTaxonomies::TAXONOMY_NEARBY,
                'property_tag' => PropertyTaxonomies::TAXONOMY_TAG,
            ];

            foreach ($taxMap as $param => $taxonomy) {
                if (!empty($_GET[$param])) {
                    $taxQuery[] = [
                        'taxonomy' => $taxonomy,
                        'field' => 'slug',
                        'terms' => array_map('sanitize_text_field', (array) $_GET[$param]),
                    ];
                }
            }

            $seoTaxMap = [
                'gestion' => PropertyTaxonomies::TAXONOMY_OPERATION,
                'tipo' => PropertyTaxonomies::TAXONOMY_TYPE,
                'ciudad' => PropertyTaxonomies::TAXONOMY_CITY,
                'barrios' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            ];
            foreach ($seoTaxMap as $qv => $taxonomy) {
                $qvValue = (string) get_query_var($qv, '');
                if ($qvValue === '') {
                    continue;
                }
                $taxQuery[] = [
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => [sanitize_title($qvValue)],
                ];
            }

            $priceMin = isset($_GET['price_min']) ? (float) $_GET['price_min'] : null;
            $priceMax = isset($_GET['price_max']) ? (float) $_GET['price_max'] : null;
            if ($priceMin || $priceMax) {
                $priceFields = ['_property_price_sale', '_property_price_rent', '_property_price_admin'];
                $priceGroup = ['relation' => 'OR'];
                foreach ($priceFields as $field) {
                    $condition = [
                        'key' => $field,
                        'type' => 'NUMERIC',
                        'compare' => 'EXISTS',
                    ];
                    if ($priceMin && $priceMax) {
                        $condition['value'] = [$priceMin, $priceMax];
                        $condition['compare'] = 'BETWEEN';
                    } elseif ($priceMin) {
                        $condition['value'] = $priceMin;
                        $condition['compare'] = '>=';
                    } elseif ($priceMax) {
                        $condition['value'] = $priceMax;
                        $condition['compare'] = '<=';
                    }
                    $priceGroup[] = $condition;
                }
                $metaQuery[] = $priceGroup;
            }

            $areaMin = isset($_GET['area_min']) ? (float) $_GET['area_min'] : null;
            $areaMax = isset($_GET['area_max']) ? (float) $_GET['area_max'] : null;
            if ($areaMin || $areaMax) {
                $areaCondition = [
                    'key' => '_property_area',
                    'type' => 'NUMERIC',
                ];
                if ($areaMin && $areaMax) {
                    $areaCondition['value'] = [$areaMin, $areaMax];
                    $areaCondition['compare'] = 'BETWEEN';
                } elseif ($areaMin) {
                    $areaCondition['value'] = $areaMin;
                    $areaCondition['compare'] = '>=';
                } else {
                    $areaCondition['value'] = $areaMax;
                    $areaCondition['compare'] = '<=';
                }
                $metaQuery[] = $areaCondition;
            }

            if (count($metaQuery) > 1) {
                $query->set('meta_query', $metaQuery);
            }

            if ($taxQuery) {
                $query->set('tax_query', $taxQuery);
            }

            // Keyword search (?q= from the filter form, or ?s= from WP native search)
            $keyword = '';
            if (!empty($_GET['q'])) {
                $keyword = sanitize_text_field(wp_unslash($_GET['q']));
            } elseif (!empty($_GET['s'])) {
                $keyword = sanitize_text_field(wp_unslash($_GET['s']));
            }
            if ($keyword !== '') {
                $query->set('homlity_keyword_search', $keyword);
            }

            $dateFrom = !empty($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : null;
            $dateTo = !empty($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : null;
            if ($dateFrom || $dateTo) {
                $dateQuery = [];
                if ($dateFrom) {
                    $dateQuery['after'] = $dateFrom;
                }
                if ($dateTo) {
                    $dateQuery['before'] = $dateTo;
                }
                $query->set('date_query', [$dateQuery]);
            }

            $order = sanitize_key((string) ($settings['archive_order'] ?? 'created_desc'));
            if ($order === 'date_desc') {
                $order = 'created_desc';
            } elseif ($order === 'date_asc') {
                $order = 'created_asc';
            }

            if ($order === 'price_desc' || $order === 'price_asc') {
                $query->set('meta_key', '_property_price_sale');
                $query->set('orderby', 'meta_value_num');
                $query->set('order', $order === 'price_asc' ? 'ASC' : 'DESC');
            } elseif ($order === 'modified_desc' || $order === 'modified_asc') {
                $query->set('orderby', 'modified');
                $query->set('order', $order === 'modified_asc' ? 'ASC' : 'DESC');
            } else {
                $query->set('orderby', 'date');
                $query->set('order', $order === 'created_asc' ? 'ASC' : 'DESC');
            }
        }
    }

    public function maybeLoadTemplate(string $template): string
    {
        if ($this->isElementorEditorRequest()) {
            return $template;
        }

        if (get_query_var('property_agent')) {
            return self::locateTemplate('property-agent.php', $template);
        }

        if (is_singular(PropertyPostType::POST_TYPE)) {
            if ((string) ($_GET['homlity_sheet'] ?? '') === '1' || (string) get_query_var('homlity_sheet', '') === '1') {
                return self::locateTemplate('property-technical-sheet.php', $template);
            }
            return self::locateTemplate('single-property.php', $template);
        }

        // For SEO archive routes, keep the configured archive page template (Elementor),
        // do not override it with archive-property.php.
        // Some filtered URLs can lose `is_page()` context depending on rewrite/order,
        // but should still render through the configured archive page.
        $archivePageId = (int) get_option('homlity_plugin_archive_page_id', 0);
        $isSeoArchiveRequest = ((string) get_query_var('homlity_property_archive', '') === '1');
        if ($archivePageId > 0 && $isSeoArchiveRequest) {
            return $template;
        }

        if (
            is_post_type_archive(PropertyPostType::POST_TYPE)
            || $this->isArchivePage
            || $isSeoArchiveRequest
        ) {
            // If the admin chose a custom page template in the editor, respect it.
            if ($archivePageId > 0 && $this->isArchivePage) {
                $customSlug = get_page_template_slug($archivePageId);
                if ($customSlug && $customSlug !== 'default') {
                    $themeFile = get_theme_file_path($customSlug);
                    if (file_exists($themeFile)) {
                        return $themeFile;
                    }
                }
            }

            return self::locateTemplate('archive-property.php', $template);
        }

        if (is_tax([
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_LOCATION,
            PropertyTaxonomies::TAXONOMY_CATEGORY,
            PropertyTaxonomies::TAXONOMY_TAG,
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_COUNTRY,
            PropertyTaxonomies::TAXONOMY_STATE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            PropertyTaxonomies::TAXONOMY_NEARBY,
        ])) {
            return self::locateTemplate('taxonomy-property.php', $template);
        }

        return $template;
    }

    public function enqueuePublicAssets(): void
    {
        if (!$this->shouldEnqueuePublicAssets()) {
            return;
        }

        $settings = $this->publicSettings();

        wp_enqueue_style(
            'homlity-real-estate-front-components',
            HOMLITY_PLUGIN_URL . 'assets/css/front-components.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );

        $inlineCss = $this->buildPrimaryColorCss($settings['primary_color']);
        if ($inlineCss !== '') {
            wp_add_inline_style('homlity-real-estate-front-components', $inlineCss);
        }

        wp_enqueue_script(
            'homlity-real-estate-contact-tracking',
            HOMLITY_PLUGIN_URL . 'assets/js/property-contact-tracking.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );
        wp_localize_script('homlity-real-estate-contact-tracking', 'homlityContactTracking', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('homlity_contact_click_nonce'),
        ]);

        if (!is_singular(PropertyPostType::POST_TYPE)) {
            return;
        }

        if ($settings['default_map_provider'] === 'leaflet_map') {
            wp_enqueue_style(
                'homlity-real-estate-leaflet-front',
                HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.min.css',
                [],
                '1.9.4'
            );

            wp_enqueue_script(
                'homlity-real-estate-leaflet-front',
                HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.min.js',
                [],
                '1.9.4',
                true
            );

            wp_enqueue_script(
                'homlity-real-estate-front-map',
                HOMLITY_PLUGIN_URL . 'assets/js/front-map.js',
                ['homlity-real-estate-leaflet-front'],
                HOMLITY_PLUGIN_VERSION,
                true
            );
            wp_localize_script('homlity-real-estate-front-map', 'homlityLeafletAssets', [
                'iconUrl' => file_exists(HOMLITY_PLUGIN_PATH . 'assets/vendor/leaflet/images/marker-icon.png')
                    ? HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/images/marker-icon.png'
                    : '',
                'iconRetinaUrl' => file_exists(HOMLITY_PLUGIN_PATH . 'assets/vendor/leaflet/images/marker-icon-2x.png')
                    ? HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/images/marker-icon-2x.png'
                    : '',
                'shadowUrl' => file_exists(HOMLITY_PLUGIN_PATH . 'assets/vendor/leaflet/images/marker-shadow.png')
                    ? HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/images/marker-shadow.png'
                    : '',
            ]);
        }

        // Required by legacy "Tabs multimedia inmueble" photos slider.
        wp_enqueue_style(
            'homlity-real-estate-swiper',
            HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css',
            [],
            '11.0.0'
        );
        wp_enqueue_script(
            'homlity-real-estate-swiper',
            HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js',
            [],
            '11.0.0',
            true
        );

        $galleryDeps = [];
        if ($settings['detail_gallery_mode'] === 'owl_gallery') {
            wp_enqueue_style(
                'homlity-real-estate-owl-carousel',
                HOMLITY_PLUGIN_URL . 'assets/vendor/owlcarousel/owl.carousel.min.css',
                [],
                '2.3.4'
            );

            wp_enqueue_style(
                'homlity-real-estate-owl-carousel-theme',
                HOMLITY_PLUGIN_URL . 'assets/vendor/owlcarousel/owl.theme.default.min.css',
                ['homlity-real-estate-owl-carousel'],
                '2.3.4'
            );

            wp_enqueue_script(
                'homlity-real-estate-owl-carousel',
                HOMLITY_PLUGIN_URL . 'assets/vendor/owlcarousel/owl.carousel.min.js',
                ['jquery'],
                '2.3.4',
                true
            );

            $galleryDeps = ['jquery', 'homlity-real-estate-owl-carousel'];
        } else {
            wp_enqueue_style(
                'homlity-real-estate-lightgallery',
                HOMLITY_PLUGIN_URL . 'assets/vendor/lightgallery/lightgallery-bundle.min.css',
                [],
                '2.8.3'
            );

            wp_enqueue_script(
                'homlity-real-estate-lightgallery',
                HOMLITY_PLUGIN_URL . 'assets/vendor/lightgallery/lightgallery.min.js',
                [],
                '2.8.3',
                true
            );

            $galleryDeps = ['homlity-real-estate-lightgallery'];
        }

        wp_enqueue_script(
            'homlity-real-estate-front-gallery',
            HOMLITY_PLUGIN_URL . 'assets/js/front-gallery.js',
            $galleryDeps,
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_localize_script('homlity-real-estate-front-gallery', 'homlityPluginFrontGallery', [
            'mode' => $settings['detail_gallery_mode'],
        ]);
    }

    public function injectElementorAdminBarLinks(array $settings): array
    {
        if (!is_admin_bar_showing() || !isset($settings['elementor_edit_page'])) {
            return $settings;
        }

        $isSeoArchiveRequest = ((string) get_query_var('homlity_property_archive', '') === '1');
        if ($isSeoArchiveRequest || is_post_type_archive(PropertyPostType::POST_TYPE)) {
            $archivePageId = (int) get_option('homlity_plugin_archive_page_id', 0);
            if ($archivePageId > 0 && current_user_can('edit_post', $archivePageId)) {
                $settings['elementor_edit_page']['href'] = admin_url('post.php?post=' . $archivePageId . '&action=elementor');
            }
            return $settings;
        }

        if (!is_singular(PropertyPostType::POST_TYPE)) {
            return $settings;
        }

        $postId = get_queried_object_id();
        if (!$postId || !current_user_can('edit_post', $postId)) {
            return $settings;
        }

        $templateEditUrl = $this->findPropertySingleTemplateEditUrl($settings['elementor_edit_page']['children'] ?? []);
        $themeBuilderUrl = admin_url('edit.php?post_type=elementor_library&tabs_group=theme');
        $targetUrl = $templateEditUrl ?: $themeBuilderUrl;

        $settings['elementor_edit_page']['href'] = $targetUrl;

        if (!isset($settings['elementor_edit_page']['children']) || !is_array($settings['elementor_edit_page']['children'])) {
            $settings['elementor_edit_page']['children'] = [];
        }

        $settings['elementor_edit_page']['children'][] = [
            'id' => 'homlity_edit_property_single_template',
            'title' => __('Editar plantilla detalle inmueble', 'homlity-real-estate'),
            'sub_title' => __('Template global', 'homlity-real-estate'),
            'href' => $targetUrl,
        ];

        if (current_user_can('edit_theme_options')) {
            $settings['elementor_edit_page']['children'][] = [
                'id' => 'homlity_edit_property_single_templates',
                'title' => __('Plantillas detalle inmuebles', 'homlity-real-estate'),
                'sub_title' => __('Theme Builder', 'homlity-real-estate'),
                'href' => admin_url('edit.php?post_type=elementor_library&tabs_group=theme'),
            ];
        }

        return $settings;
    }

    private function findPropertySingleTemplateEditUrl(array $adminBarChildren): string
    {
        $templateId = (int) get_option('homlity_plugin_single_template_id', 0);
        if ($templateId > 0 && get_post_status($templateId)) {
            return admin_url('post.php?post=' . $templateId . '&action=elementor');
        }

        foreach ($adminBarChildren as $child) {
            $id = isset($child['id']) ? (string) $child['id'] : '';
            $title = isset($child['title']) ? strtolower((string) $child['title']) : '';
            $href = isset($child['href']) ? (string) $child['href'] : '';

            if ($href === '' || strpos($id, 'elementor_edit_doc_') !== 0) {
                continue;
            }

            if (in_array($title, ['header', 'footer'], true)) {
                continue;
            }

            return $href;
        }

        $templates = get_posts([
            'post_type' => 'elementor_library',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_elementor_template_type',
                    'value' => ['single', 'single-post'],
                    'compare' => 'IN',
                ],
            ],
            'suppress_filters' => false,
        ]);

        if (!$templates) {
            return '';
        }

        return admin_url('post.php?post=' . (int) $templates[0]->ID . '&action=elementor');
    }

    public static function locateTemplate(string $filename, string $fallback = ''): string
    {
        $candidates = [
            'homlity-real-estate/' . $filename,
            'plugin-inmobiliario/' . $filename,
            $filename,
        ];

        $themeFile = locate_template($candidates);
        if ($themeFile) {
            return $themeFile;
        }

        $pluginFile = HOMLITY_PLUGIN_PATH . 'templates/' . $filename;
        if (file_exists($pluginFile)) {
            return $pluginFile;
        }

        return $fallback ?: $filename;
    }

    public static function includeComponent(string $component, array $args = []): void
    {
        $filename = 'parts/' . $component;
        $path = self::locateTemplate($filename, HOMLITY_PLUGIN_PATH . 'templates/' . $filename);

        if (!file_exists($path)) {
            return;
        }

        if ($args) {
            extract($args, EXTR_SKIP);
        }

        include $path;
    }

    private function shouldEnqueuePublicAssets(): bool
    {
        if (is_singular(PropertyPostType::POST_TYPE) || is_post_type_archive(PropertyPostType::POST_TYPE) || $this->isArchivePage) {
            return true;
        }

        $archivePageId = (int) get_option('homlity_plugin_archive_page_id', 0);
        if ($archivePageId > 0 && is_page($archivePageId)) {
            return true;
        }

        if (get_query_var('property_agent')) {
            return true;
        }

        return is_tax([
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_LOCATION,
            PropertyTaxonomies::TAXONOMY_CATEGORY,
            PropertyTaxonomies::TAXONOMY_TAG,
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_COUNTRY,
            PropertyTaxonomies::TAXONOMY_STATE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            PropertyTaxonomies::TAXONOMY_NEARBY,
        ]);
    }

    private function publicSettings(): array
    {
        $defaults = [
            'primary_color' => '#ff6752',
            'default_map_provider' => 'leaflet_map',
            'detail_gallery_mode' => 'light_gallery',
        ];

        $settings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $settings = array_merge($defaults, $settings);
        if (($settings['primary_color'] ?? '') === '#2490ff') {
            $settings['primary_color'] = '#ff6752';
        }
        $settings['primary_color'] = sanitize_hex_color($settings['primary_color'] ?? '') ?: $defaults['primary_color'];
        $settings['default_map_provider'] = in_array($settings['default_map_provider'], ['leaflet_map', 'google_map'], true)
            ? $settings['default_map_provider']
            : $defaults['default_map_provider'];
        $settings['detail_gallery_mode'] = in_array($settings['detail_gallery_mode'], ['light_gallery', 'owl_gallery'], true)
            ? $settings['detail_gallery_mode']
            : $defaults['detail_gallery_mode'];

        return $settings;
    }

    private function buildPrimaryColorCss(string $color): string
    {
        $rgb = $this->hexToRgb($color);
        if ($rgb === null) {
            return '';
        }

        return sprintf(
            'body{--homlity-primary-color:%1$s;--homlity-primary-color-rgb:%2$d,%3$d,%4$d;--homlity-primary-color-soft:rgba(%2$d,%3$d,%4$d,0.12);--homlity-primary-color-strong:rgba(%2$d,%3$d,%4$d,0.18);}',
            $color,
            $rgb[0],
            $rgb[1],
            $rgb[2]
        );
    }

    private function hexToRgb(string $color): ?array
    {
        $normalized = ltrim($color, '#');
        if (strlen($normalized) === 3) {
            $normalized = preg_replace('/(.)/', '$1$1', $normalized);
        }

        if (strlen($normalized) !== 6 || !ctype_xdigit($normalized)) {
            return null;
        }

        return [
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        ];
    }

    private function isElementorEditorRequest(): bool
    {
        if (is_admin()) {
            return true;
        }

        if (isset($_GET['action']) && sanitize_key((string) $_GET['action']) === 'elementor') {
            return true;
        }

        if (isset($_GET['elementor-preview']) || isset($_GET['elementor_library'])) {
            return true;
        }

        if (isset($_GET['preview']) && (string) $_GET['preview'] === 'true') {
            return true;
        }

        return false;
    }
}
