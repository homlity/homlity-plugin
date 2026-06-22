<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if (!function_exists('homlity_media_extract_urls')) {
    /** @return array<int,string> */
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

if (!function_exists('homlity_media_extract_from_payload')) {
    /** @return array{videos: array<int,string>, photos360: array<int,string>, tours360: array<int,string>} */
    function homlity_media_extract_from_payload(int $postId): array
    {
        $rawPayload = get_post_meta($postId, '_property_sync_payload', true);
        if (!is_string($rawPayload) || trim($rawPayload) === '') {
            $rawPayload = get_post_meta($postId, '_homlity_sync_payload', true);
        }
        if (!is_string($rawPayload) || trim($rawPayload) === '') {
            return ['videos' => [], 'photos360' => [], 'tours360' => []];
        }

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            return ['videos' => [], 'photos360' => [], 'tours360' => []];
        }

        $property = is_array($payload['property'] ?? null) ? $payload['property'] : $payload;
        $media = is_array($property['media'] ?? null) ? $property['media'] : [];

        return [
            'videos' => homlity_media_extract_urls($media['videos'] ?? ($property['videos'] ?? null)),
            'photos360' => homlity_media_extract_urls($media['photos_360'] ?? null),
            'tours360' => homlity_media_extract_urls($media['tour_360'] ?? null),
        ];
    }
}

if (!function_exists('homlity_normalize_youtube_embed_url')) {
    function homlity_normalize_youtube_embed_url(string $url, bool $privacyMode = true): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $query = [];
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

        $videoId = '';
        if (strpos($host, 'youtu.be') !== false) {
            $videoId = trim($path, '/');
        } elseif (strpos($host, 'youtube.com') !== false || strpos($host, 'youtube-nocookie.com') !== false) {
            if (!empty($query['v']) && is_string($query['v'])) {
                $videoId = $query['v'];
            } elseif (preg_match('#/(embed|shorts|v)/([^/?&]+)#', $path, $m)) {
                $videoId = $m[2];
            }
        }

        $videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $videoId);
        if (!$videoId || strlen($videoId) < 6) {
            return '';
        }

        $base = $privacyMode ? 'https://www.youtube-nocookie.com/embed/' : 'https://www.youtube.com/embed/';
        return $base . $videoId;
    }
}

if (!function_exists('homlity_normalize_vimeo_embed_url')) {
    function homlity_normalize_vimeo_embed_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#vimeo\.com/(?:video/)?([0-9]+)#i', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return '';
    }
}

if (!function_exists('homlity_is_direct_video_url')) {
    function homlity_is_direct_video_url(string $url): bool
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        return (bool) preg_match('/\.(mp4|webm|ogg|mov)$/i', $path);
    }
}

if (!function_exists('homlity_allowed_embed_domains')) {
    /** @return array<int,string> */
    function homlity_allowed_embed_domains(): array
    {
        $default = [
            'youtube.com',
            'youtube-nocookie.com',
            'youtu.be',
            'vimeo.com',
            'player.vimeo.com',
            'matterport.com',
            'my.matterport.com',
            'kuula.co',
            'cloudpano.com',
            'ricoh360.com',
            'view.ricoh360.com',
            'eyespy360.vr-360-tour.com',
        ];

        $domains = apply_filters('homlity_allowed_media_embed_domains', $default);
        if (!is_array($domains)) {
            return $default;
        }

        $clean = [];
        foreach ($domains as $domain) {
            $domain = strtolower(trim((string) $domain));
            if ($domain !== '') {
                $clean[] = $domain;
            }
        }
        return array_values(array_unique($clean));
    }
}

if (!function_exists('homlity_url_is_allowed_embed_domain')) {
    function homlity_url_is_allowed_embed_domain(string $url): bool
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach (homlity_allowed_embed_domains() as $allowed) {
            if ($host === $allowed || substr($host, -strlen('.' . $allowed)) === '.' . $allowed) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('homlity_normalize_embed_html')) {
    function homlity_normalize_embed_html(string $rawHtml): string
    {
        $allowed = [
            'iframe' => [
                'src' => true,
                'title' => true,
                'width' => true,
                'height' => true,
                'allow' => true,
                'allowfullscreen' => true,
                'loading' => true,
                'referrerpolicy' => true,
            ],
        ];

        return wp_kses($rawHtml, $allowed);
    }
}

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$post_id = (int) $post_id;
if ($post_id <= 0) {
    return;
}

$layout = isset($layout) && in_array($layout, ['slider_show', 'masonry', 'light_gallery'], true) ? $layout : 'light_gallery';
$photos_enable_lightbox = !empty($photos_enable_lightbox);
$style_preset = isset($style_preset) && in_array($style_preset, ['minimal', 'rounded', 'shadow_card'], true) ? $style_preset : 'minimal';

$show_tab_photos = !isset($show_tab_photos) || (bool) $show_tab_photos;
$show_tab_videos = !isset($show_tab_videos) || (bool) $show_tab_videos;
$show_tab_photos360 = !isset($show_tab_photos360) || (bool) $show_tab_photos360;
$show_tab_tours360 = !isset($show_tab_tours360) || (bool) $show_tab_tours360;
$hide_empty_tabs = !isset($hide_empty_tabs) || (bool) $hide_empty_tabs;
$show_empty_message = !isset($show_empty_message) || (bool) $show_empty_message;
$show_empty_icon = !isset($show_empty_icon) || (bool) $show_empty_icon;

$empty_text_photos = (string) ($empty_text_photos ?? __('No hay fotos disponibles.', 'homlity-real-estate'));
$empty_text_videos = (string) ($empty_text_videos ?? __('No hay videos disponibles.', 'homlity-real-estate'));
$empty_text_photos360 = (string) ($empty_text_photos360 ?? __('No hay fotos 360° disponibles.', 'homlity-real-estate'));
$empty_text_tours360 = (string) ($empty_text_tours360 ?? __('No hay recorridos 360° disponibles.', 'homlity-real-estate'));

$video_privacy_mode = !isset($video_privacy_mode) || (bool) $video_privacy_mode;
$video_autoplay = !empty($video_autoplay);
$video_muted = !empty($video_muted);
$video_controls = !isset($video_controls) || (bool) $video_controls;
$video_rel = !empty($video_rel);
$video_lazy = !isset($video_lazy) || (bool) $video_lazy;

$photos360_enable_viewer = !isset($photos360_enable_viewer) || (bool) $photos360_enable_viewer;
$photos360_viewer_autoload = !empty($photos360_viewer_autoload);
$photos360_show_zoom = !isset($photos360_show_zoom) || (bool) $photos360_show_zoom;
$photos360_show_fullscreen = !isset($photos360_show_fullscreen) || (bool) $photos360_show_fullscreen;
$photos360_yaw = (float) ($photos360_yaw ?? 0);
$photos360_pitch = (float) ($photos360_pitch ?? 0);
$photos360_hfov = (float) ($photos360_hfov ?? 100);

$tour_embed_mode = (string) ($tour_embed_mode ?? 'embed'); // embed|button
$tour_button_label = (string) ($tour_button_label ?? __('Abrir recorrido virtual', 'homlity-real-estate'));
$tour_new_tab = !isset($tour_new_tab) || (bool) $tour_new_tab;
$tour_lazy = !isset($tour_lazy) || (bool) $tour_lazy;

$widget_uid = sanitize_key((string) ($widget_uid ?? 'hml-' . $post_id . '-' . wp_unique_id()));

// Images
$images = [];
$galleryIds = [];
$metaGallery = get_post_meta($post_id, '_property_gallery', true);

if (is_array($metaGallery) && !empty($metaGallery)) {
    $firstItem = reset($metaGallery);
    if (filter_var((string) $firstItem, FILTER_VALIDATE_URL)) {
        $altBase = get_the_title($post_id);
        foreach ($metaGallery as $url) {
            $url = esc_url_raw((string) $url);
            if ($url && !isset($images[$url])) {
                $images[$url] = ['full' => $url, 'thumb' => $url, 'alt' => $altBase];
            }
        }
    } elseif (is_array($firstItem)) {
        $altBase = get_the_title($post_id);
        foreach (homlity_media_extract_urls($metaGallery) as $url) {
            if (!isset($images[$url])) {
                $images[$url] = ['full' => $url, 'thumb' => $url, 'alt' => $altBase];
            }
        }
    } else {
        $galleryIds = array_filter(array_map('absint', $metaGallery));
    }
} elseif (is_string($metaGallery) && $metaGallery !== '') {
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
        $full = wp_get_attachment_image_url($attachmentId, 'large');
        $thumb = wp_get_attachment_image_url($attachmentId, 'medium_large') ?: $full;
        $alt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true) ?: get_the_title($post_id);
        if ($full && !isset($images[$full])) {
            $images[$full] = ['full' => $full, 'thumb' => $thumb, 'alt' => $alt];
        }
    }
}

if (!$images) {
    $featuredUrl = get_post_meta($post_id, '_property_featured_image_url', true);
    if ($featuredUrl && filter_var($featuredUrl, FILTER_VALIDATE_URL)) {
        $images[$featuredUrl] = ['full' => esc_url_raw($featuredUrl), 'thumb' => esc_url_raw($featuredUrl), 'alt' => get_the_title($post_id)];
    }
}

if (!$images && has_post_thumbnail($post_id)) {
    $thumbId = get_post_thumbnail_id($post_id);
    $full = get_the_post_thumbnail_url($post_id, 'large');
    if ($full) {
        $images[$full] = [
            'full' => $full,
            'thumb' => get_the_post_thumbnail_url($post_id, 'large'),
            'alt' => get_post_meta($thumbId, '_wp_attachment_image_alt', true) ?: get_the_title($post_id),
        ];
    }
}
$images = array_values($images);

// Multimedia sources.
$videosRaw = homlity_media_extract_urls(get_post_meta($post_id, '_property_videos', true));
$photos360 = homlity_media_extract_urls(get_post_meta($post_id, '_property_photos_360', true));
$tours360 = homlity_media_extract_urls(get_post_meta($post_id, '_property_tour_360', true));

if (!$videosRaw) {
    $videosRaw = homlity_media_extract_urls(get_post_meta($post_id, '_homlity_sync_media_videos', true));
}
if (!$photos360) {
    $photos360 = homlity_media_extract_urls(get_post_meta($post_id, '_homlity_sync_media_photos_360', true));
}
if (!$tours360) {
    $tours360 = homlity_media_extract_urls(get_post_meta($post_id, '_homlity_sync_media_tour_360', true));
}
if (!$videosRaw || !$photos360 || !$tours360) {
    $payloadMedia = homlity_media_extract_from_payload($post_id);
    if (!$videosRaw) $videosRaw = $payloadMedia['videos'];
    if (!$photos360) $photos360 = $payloadMedia['photos360'];
    if (!$tours360) $tours360 = $payloadMedia['tours360'];
}

$videos = [];
foreach ($videosRaw as $url) {
    $youtube = homlity_normalize_youtube_embed_url($url, $video_privacy_mode);
    if ($youtube !== '') {
        $params = [
            'autoplay' => $video_autoplay ? '1' : '0',
            'mute' => $video_muted ? '1' : '0',
            'controls' => $video_controls ? '1' : '0',
            'rel' => $video_rel ? '1' : '0',
            'playsinline' => '1',
        ];
        $videos[] = ['type' => 'iframe', 'src' => add_query_arg($params, $youtube), 'provider' => 'youtube'];
        continue;
    }

    $vimeo = homlity_normalize_vimeo_embed_url($url);
    if ($vimeo !== '') {
        $videos[] = ['type' => 'iframe', 'src' => $vimeo, 'provider' => 'vimeo'];
        continue;
    }

    if (homlity_is_direct_video_url($url)) {
        $videos[] = ['type' => 'video', 'src' => $url, 'provider' => 'file'];
        continue;
    }

    if (homlity_url_is_allowed_embed_domain($url)) {
        $videos[] = ['type' => 'iframe', 'src' => $url, 'provider' => 'external'];
    }
}

$tabsConfig = [
    'photos' => ['enabled' => $show_tab_photos, 'has' => !empty($images), 'label' => (string) ($tab_label_photos ?? __('Fotos', 'homlity-real-estate')), 'icon' => (string) ($tab_icon_photos ?? 'eicon-image')],
    'videos' => ['enabled' => $show_tab_videos, 'has' => !empty($videos), 'label' => (string) ($tab_label_videos ?? __('Videos', 'homlity-real-estate')), 'icon' => (string) ($tab_icon_videos ?? 'eicon-youtube')],
    'photos360' => ['enabled' => $show_tab_photos360, 'has' => !empty($photos360), 'label' => (string) ($tab_label_photos360 ?? __('Fotos 360°', 'homlity-real-estate')), 'icon' => (string) ($tab_icon_photos360 ?? 'eicon-globe')],
    'tours360' => ['enabled' => $show_tab_tours360, 'has' => !empty($tours360), 'label' => (string) ($tab_label_tours360 ?? __('Recorrido 360°', 'homlity-real-estate')), 'icon' => (string) ($tab_icon_tours360 ?? 'eicon-eye')],
];

$tabs = [];
foreach ($tabsConfig as $id => $tab) {
    if (!$tab['enabled']) {
        continue;
    }
    if ($hide_empty_tabs && !$tab['has']) {
        continue;
    }
    $tabs[] = ['id' => $id, 'label' => $tab['label'], 'icon' => $tab['icon'], 'count' => $id === 'photos' ? count($images) : ($id === 'videos' ? count($videos) : ($id === 'photos360' ? count($photos360) : count($tours360)))];
}

if (!$tabs && !$show_empty_message) {
    return;
}

$preferredFirstTab = isset($preferred_first_tab) ? sanitize_key((string) $preferred_first_tab) : 'first_available';
$initialFallback = isset($initial_tab_fallback) ? sanitize_key((string) $initial_tab_fallback) : 'first_available';
$availableTabIds = array_values(array_map(static fn($tab) => $tab['id'], $tabs));

$firstId = $availableTabIds[0] ?? '';
if ($preferredFirstTab !== 'first_available' && in_array($preferredFirstTab, $availableTabIds, true)) {
    $firstId = $preferredFirstTab;
} elseif ($preferredFirstTab !== 'first_available' && $initialFallback === 'show_empty') {
    $firstId = $preferredFirstTab;
}

$useTabs = count($tabs) > 1;

$tabLayout = sanitize_key((string) ($tabs_layout ?? 'horizontal'));
$tabAlign = sanitize_key((string) ($tabs_align ?? 'left'));
$show_tab_icons = !empty($show_tab_icons);
$show_tab_count = !empty($show_tab_count);

$emptyClass = $show_empty_icon ? ' property-gallery-tabs__empty--icon' : '';

$render_empty = static function (string $text) use ($show_empty_message, $emptyClass): void {
    if (!$show_empty_message) {
        return;
    }
    echo '<div class="property-gallery-tabs__empty' . esc_attr($emptyClass) . '"><span>' . esc_html($text) . '</span></div>';
};
?>
<div class="property-gallery-tabs property-gallery-tabs--<?php echo esc_attr($tabLayout); ?> property-gallery-tabs--align-<?php echo esc_attr($tabAlign); ?><?php echo $useTabs ? '' : ' property-gallery-tabs--single'; ?>" data-widget-id="<?php echo esc_attr($widget_uid); ?>">
    <?php if ($useTabs) : ?>
        <nav class="property-gallery-tabs__nav" role="tablist" aria-label="<?php esc_attr_e('Contenido multimedia', 'homlity-real-estate'); ?>">
            <?php foreach ($tabs as $tab) :
                $isFirst = ($tab['id'] === $firstId);
                $tabId = $widget_uid . '-tab-' . $tab['id'];
                $panelId = $widget_uid . '-panel-' . $tab['id'];
                ?>
                <button type="button"
                        id="<?php echo esc_attr($tabId); ?>"
                        class="property-gallery-tabs__btn<?php echo $isFirst ? ' is-active' : ''; ?>"
                        data-tab="<?php echo esc_attr($tab['id']); ?>"
                        role="tab"
                        aria-controls="<?php echo esc_attr($panelId); ?>"
                        aria-selected="<?php echo $isFirst ? 'true' : 'false'; ?>"
                        tabindex="<?php echo $isFirst ? '0' : '-1'; ?>">
                    <?php if ($show_tab_icons && $tab['icon'] !== '') : ?><i class="<?php echo esc_attr($tab['icon']); ?>" aria-hidden="true"></i><?php endif; ?>
                    <span><?php echo esc_html($tab['label']); ?></span>
                    <?php if ($show_tab_count) : ?><small class="property-gallery-tabs__count"><?php echo esc_html((string) $tab['count']); ?></small><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php
    $panelIds = [
        'photos' => $widget_uid . '-panel-photos',
        'videos' => $widget_uid . '-panel-videos',
        'photos360' => $widget_uid . '-panel-photos360',
        'tours360' => $widget_uid . '-panel-tours360',
    ];
    ?>

    <section id="<?php echo esc_attr($panelIds['photos']); ?>" class="property-gallery-tabs__panel<?php echo $firstId === 'photos' ? ' is-active' : ''; ?>" data-panel="photos" role="tabpanel" <?php echo $firstId === 'photos' ? '' : 'hidden'; ?>>
        <?php if ($images) : ?>
            <?php if (in_array($layout, ['slider_show', 'masonry'], true)) : ?>
                <div class="property-gallery property-gallery--swiper <?php echo $layout === 'masonry' ? 'property-gallery--swiper-masonry' : ''; ?> property-gallery--preset-<?php echo esc_attr($style_preset); ?>"
                    data-homlity-swiper-gallery="1"
                    <?php if ($photos_enable_lightbox) : ?>data-homlity-gallery="light"<?php endif; ?>
                    data-layout="<?php echo esc_attr($layout === 'slider_show' ? 'slider' : $layout); ?>"
                    data-autoplay="<?php echo !empty($autoplay) ? '1' : '0'; ?>"
                    data-slides-desktop="<?php echo esc_attr((int) ($slides_desktop ?? 1)); ?>"
                    data-slides-tablet="<?php echo esc_attr((int) ($slides_tablet ?? 1)); ?>"
                    data-slides-mobile="<?php echo esc_attr((int) ($slides_mobile ?? 1)); ?>"
                    data-thumbs-per-view="<?php echo esc_attr((int) ($thumbs_per_view ?? 4)); ?>"
                    data-loop="<?php echo !empty($loop) ? '1' : '0'; ?>"
                    data-show-arrows="<?php echo !empty($show_arrows) ? '1' : '0'; ?>"
                    data-show-pagination="<?php echo !empty($show_pagination) ? '1' : '0'; ?>"
                    data-speed="<?php echo esc_attr((int) ($speed ?? 520)); ?>">
                    <div class="swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($images as $idx => $image) : ?>
                                <figure class="property-gallery__slide swiper-slide <?php echo esc_attr($layout === 'masonry' ? 'is-masonry-' . ($idx % 6 + 1) : ''); ?>">
                                    <?php if ($photos_enable_lightbox) : ?>
                                        <a class="property-gallery__slide-link" href="<?php echo esc_url($image['full']); ?>">
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
                            <?php if (!empty($show_arrows)) : ?><div class="swiper-button-prev"></div><div class="swiper-button-next"></div><?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($layout === 'slider_show' && !empty($show_thumbs)) : ?>
                        <div class="swiper property-gallery__thumbs">
                            <div class="swiper-wrapper">
                                <?php foreach ($images as $image) : ?>
                                    <div class="swiper-slide"><img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="property-gallery property-gallery--light property-gallery--preset-<?php echo esc_attr($style_preset); ?>" data-homlity-gallery="light">
                    <?php foreach ($images as $image) : ?>
                        <a class="property-gallery__item property-gallery__item--light" href="<?php echo esc_url($image['full']); ?>">
                            <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else :
            $render_empty($empty_text_photos);
        endif; ?>
    </section>

    <section id="<?php echo esc_attr($panelIds['videos']); ?>" class="property-gallery-tabs__panel<?php echo $firstId === 'videos' ? ' is-active' : ''; ?>" data-panel="videos" role="tabpanel" <?php echo $firstId === 'videos' ? '' : 'hidden'; ?>>
        <?php if ($videos) : ?>
            <div class="property-gallery-tabs__videos">
                <?php foreach ($videos as $video) : ?>
                    <div class="property-gallery-tabs__video-wrap">
                        <?php if ($video['type'] === 'video') : ?>
                            <video controls preload="metadata" src="<?php echo esc_url($video['src']); ?>"></video>
                        <?php else :
                            $srcAttr = $video_lazy ? 'data-src' : 'src';
                            ?>
                            <iframe <?php echo esc_attr($srcAttr); ?>="<?php echo esc_url($video['src']); ?>"
                                    title="<?php esc_attr_e('Video del inmueble', 'homlity-real-estate'); ?>"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen
                                    loading="lazy"
                                    referrerpolicy="strict-origin-when-cross-origin"></iframe>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else :
            $render_empty($empty_text_videos);
        endif; ?>
    </section>

    <section id="<?php echo esc_attr($panelIds['photos360']); ?>" class="property-gallery-tabs__panel<?php echo $firstId === 'photos360' ? ' is-active' : ''; ?>" data-panel="photos360" role="tabpanel" <?php echo $firstId === 'photos360' ? '' : 'hidden'; ?>>
        <?php if ($photos360) : ?>
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
                        <?php foreach ($photos360 as $url) :
                            $isImage = (bool) preg_match('/\.(jpe?g|png|webp)$/i', (string) wp_parse_url($url, PHP_URL_PATH));
                            // Embeddable: explicit allowed domain OR any http/https non-image URL
                            // (admin-stored 360-tour HTML pages like S3-hosted tours are always safe to iframe)
                            $canEmbed360 = homlity_url_is_allowed_embed_domain($url)
                                || (!$isImage && in_array(strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true));
                            ?>
                            <div class="property-gallery-tabs__360-item swiper-slide">
                                <?php if ($photos360_enable_viewer && $isImage) : ?>
                                    <div class="property-gallery-tabs__panorama"
                                        data-homlity-panorama="1"
                                        data-panorama-url="<?php echo esc_url($url); ?>"
                                        data-autoload="<?php echo $photos360_viewer_autoload ? '1' : '0'; ?>"
                                        data-zoom-ctrl="<?php echo $photos360_show_zoom ? '1' : '0'; ?>"
                                        data-fullscreen-ctrl="<?php echo $photos360_show_fullscreen ? '1' : '0'; ?>"
                                        data-yaw="<?php echo esc_attr((string) $photos360_yaw); ?>"
                                        data-pitch="<?php echo esc_attr((string) $photos360_pitch); ?>"
                                        data-hfov="<?php echo esc_attr((string) $photos360_hfov); ?>"></div>
                                <?php elseif ($canEmbed360) : ?>
                                    <iframe data-src="<?php echo esc_url($url); ?>" title="<?php esc_attr_e('Foto 360°', 'homlity-real-estate'); ?>" allowfullscreen loading="lazy"></iframe>
                                <?php else : ?>
                                    <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver foto 360°', 'homlity-real-estate'); ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            </div>
        <?php else :
            $render_empty($empty_text_photos360);
        endif; ?>
    </section>

    <section id="<?php echo esc_attr($panelIds['tours360']); ?>" class="property-gallery-tabs__panel<?php echo $firstId === 'tours360' ? ' is-active' : ''; ?>" data-panel="tours360" role="tabpanel" <?php echo $firstId === 'tours360' ? '' : 'hidden'; ?>>
        <?php if ($tours360) : ?>
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
                        <?php foreach ($tours360 as $url) :
                            // Embeddable: explicit allowed domain OR any http/https URL in embed mode
                            // (admin-stored tour URLs are safe to iframe regardless of hosting domain)
                            $canEmbedTour = $tour_embed_mode === 'embed' && (
                                homlity_url_is_allowed_embed_domain($url)
                                || in_array(strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
                            );
                            ?>
                            <div class="property-gallery-tabs__tour-wrap swiper-slide">
                                <?php if ($canEmbedTour) : ?>
                                    <iframe <?php echo $tour_lazy ? 'data-src' : 'src'; ?>="<?php echo esc_url($url); ?>"
                                            title="<?php esc_attr_e('Recorrido 360°', 'homlity-real-estate'); ?>"
                                            allowfullscreen
                                            allow="xr-spatial-tracking; gyroscope; accelerometer"
                                            loading="lazy"></iframe>
                                <?php else : ?>
                                    <a class="property-gallery-tabs__tour-link" href="<?php echo esc_url($url); ?>" <?php echo $tour_new_tab ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html($tour_button_label); ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            </div>
        <?php else :
            $render_empty($empty_text_tours360);
        endif; ?>
    </section>
</div>
