<?php
/**
 * Homlity news widget for the WordPress dashboard.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class DashboardNewsService implements ServiceInterface
{
    private const API_URL = 'https://homi.homlity.com/api/v1/noticias/publicadas';
    private const CACHE_KEY = 'homlity_dashboard_news_v1';
    private const DEFAULT_LIMIT = 5;

    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'registerWidget'], 1);
        add_filter('get_user_option_meta-box-order_dashboard', [$this, 'moveWidgetToFirstPosition'], 10, 3);
    }

    /**
     * Keep the Homlity widget at the top of the primary dashboard column,
     * including for users who already have a saved dashboard layout.
     *
     * @param mixed $order
     * @return mixed
     */
    public function moveWidgetToFirstPosition($order, string $option, $user)
    {
        if (!is_array($order)) {
            return $order;
        }

        $widgetId = 'homlity_dashboard_news';

        foreach ($order as $column => $widgetIds) {
            if (!is_string($widgetIds)) {
                continue;
            }

            $ids = array_filter(explode(',', $widgetIds));
            $ids = array_values(array_diff($ids, [$widgetId]));
            $order[$column] = implode(',', $ids);
        }

        $primaryWidgets = isset($order['normal']) && is_string($order['normal'])
            ? array_filter(explode(',', $order['normal']))
            : [];
        array_unshift($primaryWidgets, $widgetId);
        $order['normal'] = implode(',', $primaryWidgets);

        return $order;
    }

    public function registerWidget(): void
    {
        wp_add_dashboard_widget(
            'homlity_dashboard_news',
            __('Homlity', 'homlity-real-estate'),
            [$this, 'renderWidget']
        );
    }

    public function renderWidget(): void
    {
        $result = $this->getNews();
        $items = $result['items'];

        echo '<div class="homlity-dashboard-news">';
        echo '<div class="homlity-dashboard-news__hero">';
        echo '<img class="homlity-dashboard-news__logo" src="' . esc_url(HOMLITY_RE_PLUGIN_URL . 'icono.png') . '" alt="' . esc_attr__('Homlity', 'homlity-real-estate') . '">';
        echo '<div><p class="homlity-dashboard-news__eyebrow">' . esc_html__('Novedades Homlity', 'homlity-real-estate') . '</p>';
        echo '<h2>' . esc_html__('Mantente al día', 'homlity-real-estate') . '</h2>';
        echo '<p>' . esc_html__('Actualizaciones, funcionalidades y noticias para tu inmobiliaria.', 'homlity-real-estate') . '</p></div>';
        echo '</div>';

        if ($result['error'] !== '') {
            echo '<p class="homlity-dashboard-news__message">' . esc_html($result['error']) . '</p>';
        } elseif ($items === []) {
            echo '<p class="homlity-dashboard-news__message">' . esc_html__('No hay noticias publicadas por el momento.', 'homlity-real-estate') . '</p>';
        } else {
            echo '<ul class="homlity-dashboard-news__list">';
            foreach ($items as $item) {
                $this->renderNewsItem($item);
            }
            echo '</ul>';
        }

        echo '<p class="homlity-dashboard-news__footer"><a href="' . esc_url('https://homi.homlity.com/') . '" target="_blank" rel="noopener noreferrer">'
            . esc_html__('Ver todas las novedades', 'homlity-real-estate') . ' <span aria-hidden="true">→</span>'
            . '<span class="screen-reader-text"> ' . esc_html__('(abre en una pestaña nueva)', 'homlity-real-estate') . '</span></a></p>';
        echo '</div>';

        $this->renderStyles();
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, error: string}
     */
    private function getNews(): array
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['items'], $cached['error'])) {
            return $cached;
        }

        $limit = (int) apply_filters('homlity_dashboard_news_limit', self::DEFAULT_LIMIT);
        $limit = max(1, min(100, $limit));
        $url = add_query_arg('limit', $limit, self::API_URL);
        $url = (string) apply_filters('homlity_dashboard_news_api_url', $url);

        $response = wp_remote_get($url, [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 8,
        ]);

        if (is_wp_error($response)) {
            return $this->cacheResult([], __('No fue posible consultar las noticias de Homlity.', 'homlity-real-estate'), 5 * MINUTE_IN_SECONDS);
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 || !is_array($body) || empty($body['success']) || !isset($body['data']) || !is_array($body['data'])) {
            return $this->cacheResult([], __('No fue posible consultar las noticias de Homlity.', 'homlity-real-estate'), 5 * MINUTE_IN_SECONDS);
        }

        $items = array_values(array_filter($body['data'], 'is_array'));
        $duration = (int) apply_filters('homlity_dashboard_news_cache_duration', 30 * MINUTE_IN_SECONDS);

        return $this->cacheResult(array_slice($items, 0, $limit), '', max(MINUTE_IN_SECONDS, $duration));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{items: array<int, array<string, mixed>>, error: string}
     */
    private function cacheResult(array $items, string $error, int $duration): array
    {
        $result = ['items' => $items, 'error' => $error];
        set_transient(self::CACHE_KEY, $result, $duration);

        return $result;
    }

    /** @param array<string, mixed> $item */
    private function renderNewsItem(array $item): void
    {
        $title = isset($item['titulo']) ? sanitize_text_field((string) $item['titulo']) : '';
        if ($title === '') {
            return;
        }

        $description = isset($item['descripcion']) ? wp_trim_words(wp_strip_all_tags((string) $item['descripcion']), 24) : '';
        $link = isset($item['link_destino']) ? esc_url((string) $item['link_destino']) : '';
        $category = '';
        $categorySlug = 'general';
        if (isset($item['categoria']['label']) && is_string($item['categoria']['label'])) {
            $category = sanitize_text_field($item['categoria']['label']);
        }
        if (isset($item['categoria']['slug']) && is_string($item['categoria']['slug'])) {
            $categorySlug = sanitize_html_class($item['categoria']['slug']);
        }

        $date = '';
        if (!empty($item['fecha_inicio']) && is_string($item['fecha_inicio'])) {
            $timestamp = strtotime($item['fecha_inicio']);
            if ($timestamp !== false) {
                $date = wp_date(get_option('date_format'), $timestamp);
            }
        }

        echo '<li class="homlity-dashboard-news__item">';
        echo '<div class="homlity-dashboard-news__meta">';
        if ($category !== '') {
            echo '<span class="homlity-dashboard-news__category homlity-dashboard-news__category--' . esc_attr($categorySlug) . '">' . esc_html($category) . '</span>';
        }
        if ($date !== '') {
            echo '<time>' . esc_html($date) . '</time>';
        }
        echo '</div>';

        echo '<h3 class="homlity-dashboard-news__title">';
        if ($link !== '') {
            echo '<a href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($title) . ' <span aria-hidden="true">↗</span></a>';
        } else {
            echo esc_html($title);
        }
        echo '</h3>';

        if ($description !== '') {
            echo '<p class="homlity-dashboard-news__description">' . esc_html($description) . '</p>';
        }
        echo '</li>';
    }

    private function renderStyles(): void
    {
        ?>
        <style>
            #homlity_dashboard_news { overflow: hidden; border: 0; border-radius: 12px; box-shadow: 0 4px 18px rgba(36, 39, 58, .10); }
            #homlity_dashboard_news .postbox-header { border-bottom-color: #ececf1; }
            #homlity_dashboard_news .inside { margin: 0; padding: 0; background: #f7f8fb; }
            .homlity-dashboard-news__hero { display: flex; gap: 14px; align-items: center; padding: 18px; color: #fff; background: linear-gradient(135deg, #282b3e 0%, #3d4059 62%, #ff6267 145%); }
            .homlity-dashboard-news__logo { width: 54px; height: 54px; flex: 0 0 54px; border: 3px solid rgba(255,255,255,.22); border-radius: 16px; box-shadow: 0 5px 14px rgba(0,0,0,.18); }
            .homlity-dashboard-news__hero h2 { margin: 1px 0 3px; color: #fff; font-size: 18px; line-height: 1.25; }
            .homlity-dashboard-news__hero p { margin: 0; color: rgba(255,255,255,.78); font-size: 12px; line-height: 1.45; }
            .homlity-dashboard-news__hero .homlity-dashboard-news__eyebrow { color: #ff8589; font-size: 10px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
            .homlity-dashboard-news__list { margin: 0; padding: 10px; }
            .homlity-dashboard-news__item { margin: 0 0 8px; padding: 13px 14px; border: 1px solid #e8e8ee; border-radius: 9px; background: #fff; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
            .homlity-dashboard-news__item:last-child { margin-bottom: 0; }
            .homlity-dashboard-news__item:hover { border-color: #ffc4c6; box-shadow: 0 4px 12px rgba(36,39,58,.07); transform: translateY(-1px); }
            .homlity-dashboard-news__meta { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; color: #777b89; font-size: 11px; }
            .homlity-dashboard-news__category { padding: 3px 8px; border-radius: 20px; background: #eef1f7; color: #4d5368; font-weight: 600; }
            .homlity-dashboard-news__category--funcionalidad { background: #e9f7ef; color: #18794e; }
            .homlity-dashboard-news__category--actualizacion { background: #fff0ed; color: #c43d42; }
            .homlity-dashboard-news__category--informacion { background: #eaf3ff; color: #1769aa; }
            .homlity-dashboard-news__category--soporte { background: #fff6dc; color: #8a6500; }
            .homlity-dashboard-news__title { margin: 0 0 5px; color: #292c3d; font-size: 14px; line-height: 1.4; }
            .homlity-dashboard-news__title a { color: #292c3d; text-decoration: none; }
            .homlity-dashboard-news__title a:hover { color: #df4f55; }
            .homlity-dashboard-news__description { margin: 0; color: #626675; font-size: 12px; line-height: 1.55; }
            .homlity-dashboard-news__message { margin: 10px; padding: 18px; border: 1px dashed #d7d8df; border-radius: 9px; color: #646970; text-align: center; background: #fff; }
            .homlity-dashboard-news__footer { margin: 0; padding: 12px 16px 14px; text-align: right; }
            .homlity-dashboard-news__footer a { color: #d9464d; font-size: 12px; font-weight: 600; text-decoration: none; }
            .homlity-dashboard-news__footer a:hover { color: #a92e34; }
        </style>
        <?php
    }
}
