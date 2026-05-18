<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php
/**
 * Property gallery component with tabs.
 * Tabs: Fotos, Videos, Fotos 360, Recorridos 360.
 * Each tab is shown only when it has content.
 * Overridable at homlity-plugin/parts/property-gallery.php
 */

if (!function_exists('homlity_youtube_embed_url')) {
    function homlity_youtube_embed_url(string $url): string
    {
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|v\/))([\w-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0';
        }
        return '';
    }
}

if (!function_exists('homlity_is_image_url')) {
    function homlity_is_image_url(string $url): bool
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', $path);
    }
}

if (!function_exists('homlity_media_extract_urls')) {
    /**
     * @param mixed $value
     * @return array<int,string>
     */
    function homlity_media_extract_urls($value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            if ($trimmed[0] === '[' || $trimmed[0] === '{') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return homlity_media_extract_urls($decoded);
                }
            }
            $parts = preg_split('/[\r\n,;|]+/', $trimmed) ?: [];
            $urls = [];
            foreach ($parts as $part) {
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

if (!function_exists('homlity_media_extract_single_url')) {
    /**
     * @param mixed $value
     */
    function homlity_media_extract_single_url($value): string
    {
        if (is_array($value) && isset($value['url'])) {
            return esc_url_raw((string) $value['url']);
        }
        $urls = homlity_media_extract_urls($value);
        return $urls[0] ?? '';
    }
}

if (!isset($post_id)) {
    $post_id = get_the_ID();
}
if (!isset($layout)) {
    $layout = 'light_gallery';
}
$layout = in_array($layout, ['slider_show', 'masonry', 'light_gallery'], true) ? $layout : 'light_gallery';
if (!isset($photos_enable_lightbox)) {
    $photos_enable_lightbox = false;
}
$photos_enable_lightbox = (bool) $photos_enable_lightbox;
if (!isset($style_preset)) {
    $style_preset = 'minimal';
}
$style_preset = in_array($style_preset, ['minimal', 'rounded', 'shadow_card'], true) ? $style_preset : 'minimal';

// ── Photos ────────────────────────────────────────────────────────────────────
// _property_gallery puede tener tres formatos según su origen:
//   1. Array de URLs (plugin-homlity-sync sin import, o CRM de homlity-plugin)
//   2. Array de attachment IDs enteros (plugin-homlity-sync con import activado)
//   3. String de IDs separados por coma, formato legado del CPT
$images      = [];
$galleryIds  = [];
$metaGallery = get_post_meta($post_id, '_property_gallery', true);

if (is_array($metaGallery) && !empty($metaGallery)) {
    $firstItem = reset($metaGallery);
    if (filter_var((string) $firstItem, FILTER_VALIDATE_URL)) {
        // Formato 1: array de URLs externas
        $altBase = get_the_title($post_id);
        foreach ($metaGallery as $url) {
            $url = esc_url_raw((string) $url);
            if ($url && !isset($images[$url])) {
                $images[$url] = ['full' => $url, 'thumb' => $url, 'alt' => $altBase];
            }
        }
    } elseif (is_array($firstItem)) {
        // Formato 4: array de objetos/arrays con clave url.
        $altBase = get_the_title($post_id);
        foreach (homlity_media_extract_urls($metaGallery) as $url) {
            if (!isset($images[$url])) {
                $images[$url] = ['full' => $url, 'thumb' => $url, 'alt' => $altBase];
            }
        }
    } else {
        // Formato 2: array de attachment IDs
        $galleryIds = array_filter(array_map('absint', $metaGallery));
    }
} elseif (is_string($metaGallery) && $metaGallery !== '') {
    // Formato 3: "1234,5678,9012" (legado) o JSON con URLs/objetos.
    $decodedGallery = json_decode($metaGallery, true);
    if (is_array($decodedGallery)) {
        $altBase = get_the_title($post_id);
        foreach (homlity_media_extract_urls($decodedGallery) as $url) {
            if (!isset($images[$url])) {
                $images[$url] = ['full' => $url, 'thumb' => $url, 'alt' => $altBase];
            }
        }
        if (!$images) {
            $galleryIds = array_filter(array_map('absint', $decodedGallery));
        }
    } else {
        $galleryIds = array_filter(array_map('absint', explode(',', $metaGallery)));
    }
}

if ($galleryIds) {
    foreach ($galleryIds as $attachmentId) {
        $full  = wp_get_attachment_image_url($attachmentId, 'large');
        $thumb = wp_get_attachment_image_url($attachmentId, 'medium_large') ?: $full;
        $alt   = get_post_meta($attachmentId, '_wp_attachment_image_alt', true) ?: get_the_title($post_id);
        if ($full && !isset($images[$full])) {
            $images[$full] = ['full' => $full, 'thumb' => $thumb, 'alt' => $alt];
        }
    }
}

// Fallback 1: URL de imagen destacada guardada por el plugin de sync
if (!$images) {
    $featuredUrl = get_post_meta($post_id, '_property_featured_image_url', true);
    if ($featuredUrl && filter_var($featuredUrl, FILTER_VALIDATE_URL)) {
        $images[$featuredUrl] = [
            'full'  => esc_url_raw($featuredUrl),
            'thumb' => esc_url_raw($featuredUrl),
            'alt'   => get_the_title($post_id),
        ];
    }
}

// Fallback 2: imagen destacada de WordPress (set_post_thumbnail)
if (!$images && has_post_thumbnail($post_id)) {
    $thumbId = get_post_thumbnail_id($post_id);
    $full    = get_the_post_thumbnail_url($post_id, 'large');
    if ($full) {
        $images[$full] = [
            'full'  => $full,
            'thumb' => get_the_post_thumbnail_url($post_id, 'large'),
            'alt'   => get_post_meta($thumbId, '_wp_attachment_image_alt', true) ?: get_the_title($post_id),
        ];
    }
}

// Fallback 3: adjuntos hijos del post (solo si no hay galería definida)
if (!$images && empty($galleryIds)) {
    $attachments = get_posts([
        'post_type'      => 'attachment',
        'posts_per_page' => 8,
        'post_status'    => 'inherit',
        'post_parent'    => $post_id,
        'post_mime_type' => 'image',
        'orderby'        => 'menu_order',
        'fields'         => 'ids',
    ]);
    foreach ($attachments as $attachmentId) {
        $full  = wp_get_attachment_image_url($attachmentId, 'large');
        $thumb = wp_get_attachment_image_url($attachmentId, 'medium_large') ?: $full;
        $alt   = get_post_meta($attachmentId, '_wp_attachment_image_alt', true) ?: get_the_title($post_id);
        if ($full && !isset($images[$full])) {
            $images[$full] = ['full' => $full, 'thumb' => $thumb, 'alt' => $alt];
        }
    }
}
$images = array_values($images);

// ── YouTube videos ────────────────────────────────────────────────────────────
$rawVideos = get_post_meta($post_id, '_property_videos', true) ?: [];
$rawVideos = homlity_media_extract_urls($rawVideos);
$videos    = [];
foreach ((array) $rawVideos as $url) {
    $embed = homlity_youtube_embed_url((string) $url);
    if ($embed) {
        $videos[] = $embed;
    }
}

// ── Fotos 360 ─────────────────────────────────────────────────────────────────
$photos360 = get_post_meta($post_id, '_property_photos_360', true) ?: [];
$photos360 = homlity_media_extract_urls($photos360);

// ── Recorridos 360 ────────────────────────────────────────────────────────────
$tours360 = get_post_meta($post_id, '_property_tour_360', true) ?: [];
$tours360 = homlity_media_extract_urls($tours360);

// Fallback desde metas dedicadas del sync y payload crudo cuando los campos
// principales no fueron persistidos en formato esperado por el frontend.
if (!$videos) {
    $syncVideos = get_post_meta($post_id, '_homlity_sync_media_videos', true);
    foreach (homlity_media_extract_urls($syncVideos) as $url) {
        $embed = homlity_youtube_embed_url((string) $url);
        if ($embed) {
            $videos[] = $embed;
        }
    }
}
if (!$photos360) {
    $photos360 = homlity_media_extract_urls(get_post_meta($post_id, '_homlity_sync_media_photos_360', true));
}
if (!$tours360) {
    $tours360 = homlity_media_extract_urls(get_post_meta($post_id, '_homlity_sync_media_tour_360', true));
}
if (!$photos360 || !$tours360 || !$videos) {
    $rawPayload = get_post_meta($post_id, '_property_sync_payload', true);
    if (!is_string($rawPayload) || trim($rawPayload) === '') {
        $rawPayload = get_post_meta($post_id, '_homlity_sync_payload', true);
    }
    if (is_string($rawPayload) && trim($rawPayload) !== '') {
        $payload = json_decode($rawPayload, true);
        if (is_array($payload)) {
            $property = is_array($payload['property'] ?? null) ? $payload['property'] : $payload;
            $media = is_array($property['media'] ?? null) ? $property['media'] : [];
            if (!$videos) {
                $videoList = homlity_media_extract_urls($media['videos'] ?? ($property['videos'] ?? null));
                foreach ($videoList as $url) {
                    $embed = homlity_youtube_embed_url((string) $url);
                    if ($embed) {
                        $videos[] = $embed;
                    }
                }
            }
            if (!$photos360) {
                $photos360 = homlity_media_extract_urls($media['photos_360'] ?? null);
            }
            if (!$tours360) {
                $tours360 = homlity_media_extract_urls($media['tour_360'] ?? null);
            }
        }
    }
}

// ── Build active tabs ─────────────────────────────────────────────────────────
$tabs = [];
if ($images)    $tabs[] = ['id' => 'photos',    'label' => __('Fotos', 'homlity-plugin'),          'icon' => 'eicon-image'];
if ($videos)    $tabs[] = ['id' => 'videos',    'label' => __('Videos', 'homlity-plugin'),         'icon' => 'eicon-youtube'];
if ($photos360) $tabs[] = ['id' => 'photos360', 'label' => __('Fotos 360°', 'homlity-plugin'),     'icon' => 'eicon-globe'];
if ($tours360)  $tabs[] = ['id' => 'tours360',  'label' => __('Recorrido 360°', 'homlity-plugin'), 'icon' => 'eicon-eye'];

if (!$tabs) {
    return;
}

$useTabs = count($tabs) > 1;
$preferredFirstTab = isset($preferred_first_tab) ? sanitize_key((string) $preferred_first_tab) : '';
$availableTabIds = array_values(array_map(static fn ($tab) => (string) ($tab['id'] ?? ''), $tabs));
$firstId = in_array($preferredFirstTab, $availableTabIds, true) ? $preferredFirstTab : $tabs[0]['id'];
?>
<div class="property-gallery-tabs<?php echo $useTabs ? '' : ' property-gallery-tabs--single'; ?>">

    <?php if ($useTabs) : ?>
    <nav class="property-gallery-tabs__nav" role="tablist" aria-label="<?php esc_attr_e('Contenido multimedia', 'homlity-plugin'); ?>">
        <?php foreach ($tabs as $tab) : $isFirst = ($tab['id'] === $firstId); ?>
            <button class="property-gallery-tabs__btn<?php echo $isFirst ? ' is-active' : ''; ?>"
                    data-tab="<?php echo esc_attr($tab['id']); ?>"
                    role="tab"
                    aria-selected="<?php echo $isFirst ? 'true' : 'false'; ?>">
                <?php echo esc_html($tab['label']); ?>
            </button>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php
    // ── Panel: Fotos ──────────────────────────────────────────────────────────
    if ($images) :
        $panelActive = ($firstId === 'photos') ? ' is-active' : '';
    ?>
    <div class="property-gallery-tabs__panel<?php echo esc_attr( $panelActive ); ?>" data-panel="photos">

        <?php if (in_array($layout, ['slider_show', 'masonry'], true)) : ?>
        <div class="property-gallery property-gallery--swiper <?php echo $layout === 'masonry' ? 'property-gallery--swiper-masonry' : ''; ?> property-gallery--preset-<?php echo esc_attr($style_preset); ?>"
             data-homlity-swiper-gallery="1"
             <?php if ($photos_enable_lightbox) : ?>data-homlity-gallery="light"<?php endif; ?>
             data-layout="<?php echo esc_attr($layout === 'slider_show' ? 'slider' : $layout); ?>"
             data-autoplay="<?php echo !empty($autoplay) ? '1' : '0'; ?>"
             data-slides-desktop="<?php echo esc_attr((int) ($slides_desktop ?? 3)); ?>"
             data-slides-tablet="<?php echo esc_attr((int) ($slides_tablet ?? 2)); ?>"
             data-slides-mobile="<?php echo esc_attr((int) ($slides_mobile ?? 1)); ?>"
             data-loop="<?php echo !empty($loop) ? '1' : '0'; ?>"
             data-show-arrows="<?php echo !empty($show_arrows) ? '1' : '0'; ?>"
             data-show-pagination="<?php echo !empty($show_pagination) ? '1' : '0'; ?>"
             data-speed="<?php echo esc_attr((int) ($speed ?? 520)); ?>">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($images as $idx => $image) : ?>
                        <figure class="property-gallery__slide swiper-slide <?php echo esc_attr( $layout === 'masonry' ? 'is-masonry-' . ($idx % 6 + 1) : '' ); ?>">
                            <?php if ($photos_enable_lightbox) : ?>
                                <a class="property-gallery__slide-link"
                                   href="<?php echo esc_url($image['full']); ?>">
                                    <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                                </a>
                            <?php else : ?>
                                <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
                <?php if ($layout === 'slider_show' && !empty($show_pagination)) : ?>
                    <div class="swiper-pagination"></div>
                    <?php if (!empty($show_arrows)) : ?>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if ($layout === 'slider_show' && !empty($show_thumbs)) : ?>
                <div class="swiper property-gallery__thumbs">
                    <div class="swiper-wrapper">
                        <?php foreach ($images as $image) : ?>
                            <div class="swiper-slide">
                                <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($layout === 'light_gallery') : ?>
        <div class="property-gallery property-gallery--light property-gallery--preset-<?php echo esc_attr($style_preset); ?>" data-homlity-gallery="light">
            <?php foreach ($images as $image) : ?>
                <a class="property-gallery__item property-gallery__item--light"
                   href="<?php echo esc_url($image['full']); ?>">
                    <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <?php
    // ── Panel: Videos YouTube ─────────────────────────────────────────────────
    if ($videos) :
        $panelActive = ($firstId === 'videos') ? ' is-active' : '';
    ?>
    <div class="property-gallery-tabs__panel<?php echo esc_attr( $panelActive ); ?>" data-panel="videos">
        <div class="property-gallery-tabs__videos">
            <?php foreach ($videos as $embedUrl) : ?>
                <div class="property-gallery-tabs__video-wrap">
                    <iframe src="<?php echo esc_url($embedUrl); ?>"
                            title="<?php esc_attr_e('Video del inmueble', 'homlity-plugin'); ?>"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                            loading="lazy"></iframe>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ── Panel: Fotos 360 ──────────────────────────────────────────────────────
    if ($photos360) :
        $panelActive = ($firstId === 'photos360') ? ' is-active' : '';
    ?>
    <div class="property-gallery-tabs__panel<?php echo esc_attr( $panelActive ); ?>" data-panel="photos360">
        <div class="property-gallery property-gallery--swiper property-gallery--preset-<?php echo esc_attr($style_preset); ?>"
             data-homlity-swiper-gallery="1"
             data-layout="slider"
             data-autoplay="0"
             data-slides-desktop="<?php echo esc_attr((int) ($slides_desktop ?? 3)); ?>"
             data-slides-tablet="<?php echo esc_attr((int) ($slides_tablet ?? 2)); ?>"
             data-slides-mobile="<?php echo esc_attr((int) ($slides_mobile ?? 1)); ?>"
             data-loop="0"
             data-show-arrows="1"
             data-show-pagination="1"
             data-speed="<?php echo esc_attr((int) ($speed ?? 520)); ?>">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($photos360 as $url) : ?>
                        <div class="property-gallery-tabs__360-item swiper-slide">
                            <?php if (homlity_is_image_url((string) $url)) : ?>
                                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo esc_url($url); ?>"
                                         alt="<?php esc_attr_e('Foto 360°', 'homlity-plugin'); ?>"
                                         loading="lazy">
                                    <span class="property-gallery-tabs__360-badge">360°</span>
                                </a>
                            <?php else : ?>
                                <iframe src="<?php echo esc_url($url); ?>"
                                        title="<?php esc_attr_e('Foto 360°', 'homlity-plugin'); ?>"
                                        allowfullscreen
                                        loading="lazy"></iframe>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ── Panel: Recorridos 360 ─────────────────────────────────────────────────
    if ($tours360) :
        $panelActive = ($firstId === 'tours360') ? ' is-active' : '';
    ?>
    <div class="property-gallery-tabs__panel<?php echo esc_attr( $panelActive ); ?>" data-panel="tours360">
        <div class="property-gallery property-gallery--swiper property-gallery--preset-<?php echo esc_attr($style_preset); ?>"
             data-homlity-swiper-gallery="1"
             data-layout="slider"
             data-autoplay="0"
             data-slides-desktop="1"
             data-slides-tablet="1"
             data-slides-mobile="1"
             data-loop="0"
             data-show-arrows="1"
             data-show-pagination="1"
             data-speed="<?php echo esc_attr((int) ($speed ?? 520)); ?>">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($tours360 as $url) : ?>
                        <div class="property-gallery-tabs__tour-wrap swiper-slide">
                            <iframe src="<?php echo esc_url($url); ?>"
                                    title="<?php esc_attr_e('Recorrido 360°', 'homlity-plugin'); ?>"
                                    allowfullscreen
                                    allow="xr-spatial-tracking; gyroscope; accelerometer"
                                    loading="lazy"></iframe>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
