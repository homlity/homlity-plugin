<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyVideoWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_video';
    }

    public function get_title(): string
    {
        return __('Video del inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-youtube';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();
        $this->end_controls_section();

        $this->start_controls_section('style_video', [
            'label' => __('Estilos video', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_responsive_control('video_height', [
            'label' => __('Alto', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range' => [
                'px' => ['min' => 180, 'max' => 900],
                'vh' => ['min' => 20, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-property-video iframe, {{WRAPPER}} .homlity-property-video video' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'video_border',
            'selector' => '{{WRAPPER}} .homlity-property-video iframe, {{WRAPPER}} .homlity-property-video video',
        ]);
        $this->add_responsive_control('video_radius', [
            'label' => __('Radio', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .homlity-property-video iframe, {{WRAPPER}} .homlity-property-video video' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'video_shadow',
            'selector' => '{{WRAPPER}} .homlity-property-video iframe, {{WRAPPER}} .homlity-property-video video',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $postId = $this->current_property_id();
        if ($postId <= 0) {
            return;
        }

        $videos = $this->extractUrlList(get_post_meta($postId, '_property_videos', true));
        if (empty($videos)) {
            $videos = $this->extractUrlList(get_post_meta($postId, '_homlity_sync_media_videos', true));
        }
        if (empty($videos)) {
            $videos = $this->extractVideosFromPayload($postId);
        }
        if (empty($videos)) {
            return;
        }

        $url = (string) $videos[0];
        if ($url === '') {
            return;
        }

        $embed = $this->youtubeEmbedUrl($url);
        echo '<div class="homlity-property-video">';
        if ($embed !== '') {
            echo '<iframe src="' . esc_url($embed) . '" title="' . esc_attr__('Video del inmueble', 'homlity-real-estate') . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>';
        } elseif ($this->isDirectVideoUrl($url)) {
            echo '<video controls preload="metadata" src="' . esc_url($url) . '"></video>';
        } else {
            // URL no compatible para embebido seguro.
            echo '</div>';
            return;
        }
        echo '</div>';
    }

    /**
     * @return array<int,string>
     */
    private function extractUrlList(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            if ($trimmed[0] === '[' || $trimmed[0] === '{') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $this->extractUrlList($decoded);
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

    /**
     * @return array<int,string>
     */
    private function extractVideosFromPayload(int $postId): array
    {
        $rawPayload = get_post_meta($postId, '_property_sync_payload', true);
        if (!is_string($rawPayload) || trim($rawPayload) === '') {
            $rawPayload = get_post_meta($postId, '_homlity_sync_payload', true);
        }
        if (!is_string($rawPayload) || trim($rawPayload) === '') {
            return [];
        }

        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded)) {
            return [];
        }

        $property = is_array($decoded['property'] ?? null) ? $decoded['property'] : $decoded;
        $media = is_array($property['media'] ?? null) ? $property['media'] : [];
        return $this->extractUrlList($media['videos'] ?? ($property['videos'] ?? null));
    }

    private function youtubeEmbedUrl(string $url): string
    {
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|v\/))([\w-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0';
        }
        return '';
    }

    private function isDirectVideoUrl(string $url): bool
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        return (bool) preg_match('/\.(mp4|webm|ogg|mov)$/i', $path);
    }
}
