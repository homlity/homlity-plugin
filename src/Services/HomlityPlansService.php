<?php

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class HomlityPlansService implements ServiceInterface
{
    private const API_URL = 'https://homi.homlity.com/api/v1/plugins/planes';

    public function register(): void {}

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'homlity-real-estate'));
        }

        [$data, $error] = $this->fetchPlans();
        $this->renderHtml($data, $error);
    }

    /** @return array{0: array|null, 1: string} */
    private function fetchPlans(): array
    {
        $response = wp_remote_get(self::API_URL, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return [null, $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 || !is_array($data)) {
            $message = is_array($data) ? (string) ($data['message'] ?? "HTTP $code") : "HTTP $code";
            return [null, $message];
        }

        return [$data, ''];
    }

    private function renderHtml(?array $data, string $error): void
    {
        $groups = is_array($data['data'] ?? null) ? $data['data'] : [];
        $meta   = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        echo '<div class="wrap">';
        echo '<style>
            .homlity-admin-banners { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin:12px 0 16px; width:100%; }
            .homlity-admin-banners a { display:block; overflow:hidden; border-radius:10px; line-height:0; }
            .homlity-admin-banners img { display:block; width:100%; height:auto; border:0; border-radius:10px; }
            .homlity-plans-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:24px; }
            .homlity-plan-card { background:#fff; border:1px solid #dcdcde; border-radius:8px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.07); transition:box-shadow .15s; }
            .homlity-plan-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.12); }
            .homlity-plan-card__body { padding:18px 20px; display:flex; flex-direction:column; gap:10px; flex:1; }
            .homlity-plan-desc { color:#3c434a; font-size:13px; line-height:1.6; flex:1; }
            .homlity-plan-desc p { margin:0 0 6px; }
            .homlity-plan-desc p:last-child { margin-bottom:0; }
            .homlity-plan-desc ul { margin:0; padding-left:16px; }
            .homlity-plugin-badge { display:inline-block; background:#fff3f0; color:#c0392b; border:1px solid #ff6752; border-radius:4px; font-size:11px; font-weight:600; padding:2px 8px; text-transform:uppercase; letter-spacing:.4px; text-decoration:none; }
            .homlity-plan-price { padding:12px 0; border-top:1px solid #f0f0f1; }
            .homlity-plan-price__main { font-size:22px; font-weight:700; color:#1d2327; }
            .homlity-plan-price__unit { font-size:13px; color:#646970; font-weight:400; margin-left:4px; }
            .homlity-plan-price__annual { font-size:12px; color:#646970; margin-top:2px; }
            .homlity-plan-actions { display:flex; gap:8px; }
            .homlity-plan-actions .button { flex:1; text-align:center; justify-content:center; }
            @media (max-width:782px) { .homlity-admin-banners { grid-template-columns:1fr; } }
        </style>';

        $this->renderBanners();

        echo '<h1 style="margin-bottom:6px;">' . esc_html__('Planes de integración', 'homlity-real-estate') . '</h1>';

        if (!empty($meta)) {
            $totalPlugins = (int) ($meta['total_plugins'] ?? 0);
            $totalPlans   = (int) ($meta['total_plans'] ?? 0);
            echo '<p style="color:#646970;margin-top:0;margin-bottom:28px;">';
            echo esc_html(sprintf(
                /* translators: 1: plugin count, 2: plan count */
                _n('%2$d plan disponible en %1$d plugin.', '%2$d planes disponibles en %1$d plugins.', $totalPlugins, 'homlity-real-estate'),
                $totalPlugins,
                $totalPlans
            ));
            echo '</p>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__('Error al obtener planes:', 'homlity-real-estate') . '</strong> ';
            echo esc_html($error);
            echo '</p></div>';
        }

        if (empty($groups)) {
            if ($error === '') {
                echo '<p>' . esc_html__('No hay planes de integración disponibles en este momento.', 'homlity-real-estate') . '</p>';
            }
            echo '</div>';
            return;
        }

        echo '<div class="homlity-plans-grid">';
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $plugin = is_array($group['plugin'] ?? null) ? $group['plugin'] : [];
            $plans  = is_array($group['plans'] ?? null) ? $group['plans'] : [];
            foreach ($plans as $plan) {
                if (!is_array($plan)) {
                    continue;
                }
                $this->renderPlanCard($plan, $plugin);
            }
        }
        echo '</div>';

        echo '</div>';
    }

    private function renderBanners(): void
    {
        ?>
        <div class="homlity-admin-banners">
            <a href="https://homi.homlity.com/registro-inmobiliaria-gratis" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Registrarse gratis en Homlity', 'homlity-real-estate'); ?>">
                <img src="<?php echo esc_url(HOMLITY_PLUGIN_URL . 'assets/img/homlity.png'); ?>" alt="<?php esc_attr_e('Impulsa tu inmobiliaria con Homlity', 'homlity-real-estate'); ?>">
            </a>
            <a href="https://homi.homlity.com/register" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Registrarse gratis en el Directorio Inmobiliario de Homlity', 'homlity-real-estate'); ?>">
                <img src="<?php echo esc_url(HOMLITY_PLUGIN_URL . 'assets/img/directorio-inmobiliario.png'); ?>" alt="<?php esc_attr_e('Únete al Directorio Inmobiliario de Homlity', 'homlity-real-estate'); ?>">
            </a>
        </div>
        <?php
    }

    private function renderPlanCard(array $plan, array $plugin = []): void
    {
        $name        = (string) ($plan['nombre'] ?? '');
        $description = (string) ($plan['descripcion'] ?? '');
        $precio = (float) ($plan['precio_mensual'] ?? $plan['precio_anual'] ?? $plan['precio_unico'] ?? $plan['valor'] ?? 0);

        $periodoRaw = strtolower((string) ($plan['periodo'] ?? $plan['tipo_pago'] ?? $plan['frecuencia'] ?? ''));
        if ($periodoRaw === '') {
            if (!empty($plan['precio_mensual']) && (float) $plan['precio_mensual'] > 0) {
                $periodoRaw = 'mensual';
            } elseif (!empty($plan['precio_anual']) && (float) $plan['precio_anual'] > 0) {
                $periodoRaw = 'anual';
            } elseif (!empty($plan['precio_unico']) && (float) $plan['precio_unico'] > 0) {
                $periodoRaw = 'unico';
            }
        }

        $periodoLabel = match (true) {
            in_array($periodoRaw, ['mensual', 'monthly', 'mes'], true)                              => '/ ' . __('mes', 'homlity-real-estate'),
            in_array($periodoRaw, ['anual', 'annual', 'año', 'ano', 'yearly'], true)               => '/ ' . __('año', 'homlity-real-estate'),
            in_array($periodoRaw, ['unico', 'único', 'one_time', 'one-time', 'pago_unico'], true)  => __('pago único', 'homlity-real-estate'),
            in_array($periodoRaw, ['ilimitado', 'unlimited', 'lifetime', 'vitalicio'], true)       => __('ilimitado', 'homlity-real-estate'),
            default                                                                                 => '',
        };
        $imageUrl    = (string) ($plan['imagen_url'] ?? $plan['image_url'] ?? $plan['imagen'] ?? '');
        $purchaseUrl = (string) ($plan['public_purchase_url'] ?? '');

        $pluginName = (string) ($plugin['nombre'] ?? '');
        $pluginUrl  = (string) ($plugin['pagina_inicio'] ?? '');

        echo '<div class="homlity-plan-card">';

        // Image / gradient header
        if ($imageUrl !== '') {
            echo '<div style="height:160px;overflow:hidden;background:#f0f0f1;">';
            echo '<img src="' . esc_url($imageUrl) . '" alt="' . esc_attr($name) . '" style="width:100%;height:100%;object-fit:cover;" loading="lazy" />';
            echo '</div>';
        } else {
            $initials = mb_strtoupper(mb_substr(wp_strip_all_tags($name), 0, 2));
            echo '<div style="height:80px;background:linear-gradient(135deg,#ff6752 0%,#ff9085 100%);display:flex;align-items:center;justify-content:center;">';
            echo '<span style="color:#fff;font-size:26px;font-weight:700;opacity:.9;">' . esc_html($initials) . '</span>';
            echo '</div>';
        }

        echo '<div class="homlity-plan-card__body">';

        // Plugin badge
        if ($pluginName !== '') {
            if ($pluginUrl !== '') {
                echo '<a href="' . esc_url($pluginUrl) . '" target="_blank" rel="noopener noreferrer" class="homlity-plugin-badge">' . esc_html($pluginName) . '</a>';
            } else {
                echo '<span class="homlity-plugin-badge">' . esc_html($pluginName) . '</span>';
            }
        }

        // Plan name
        echo '<h3 style="margin:0;font-size:15px;line-height:1.3;color:#1d2327;">' . esc_html($name) . '</h3>';

        // Description — rendered as HTML (API sends HTML content)
        if ($description !== '') {
            echo '<div class="homlity-plan-desc">' . wp_kses_post($description) . '</div>';
        }

        // Price
        echo '<div class="homlity-plan-price">';
        if ($precio > 0) {
            echo '<div class="homlity-plan-price__main">';
            echo '$' . esc_html(number_format($precio, 0, ',', '.'));
            if ($periodoLabel !== '') {
                echo '<span class="homlity-plan-price__unit">' . esc_html($periodoLabel) . '</span>';
            }
            echo '</div>';
        } else {
            echo '<div class="homlity-plan-price__main" style="color:#00a32a;">' . esc_html__('Gratis', 'homlity-real-estate') . '</div>';
        }
        echo '</div>';

        // CTA buttons
        echo '<div class="homlity-plan-actions">';
        if ($purchaseUrl !== '') {
            echo '<a href="' . esc_url($purchaseUrl) . '" target="_blank" rel="noopener noreferrer" class="button button-primary">';
            echo esc_html__('Ver detalles', 'homlity-real-estate');
            echo '</a>';
        }
        echo '</div>';

        echo '</div>'; // __body
        echo '</div>'; // card
    }
}
