<?php
/**
 * Overrides WP Chat App (Ninja Team) floating widget account on single property pages.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class NinjaWhatsAppPropertyOverrideService implements ServiceInterface
{
    public function register(): void
    {
        add_action('wp_print_footer_scripts', [$this, 'injectSinglePropertyAccountOverride'], 1);
    }

    public function injectSinglePropertyAccountOverride(): void
    {
        if (!is_singular(PropertyPostType::POST_TYPE)) {
            return;
        }

        if (!wp_script_is('nta-js-popup', 'enqueued')) {
            return;
        }

        $postId = (int) get_queried_object_id();
        if ($postId <= 0) {
            return;
        }

        $meta = (new PropertyPostType())->metaKeys();
        $agentPhone = (string) get_post_meta($postId, $meta['agent_phone'], true);
        $agentId = (int) get_post_meta($postId, $meta['agent_id'], true);
        if ($agentPhone === '' && $agentId > 0) {
            $agentPhone = (string) get_user_meta($agentId, 'homlity_plugin_phone', true);
            if ($agentPhone === '') {
                $agentPhone = (string) get_user_meta($agentId, 'billing_phone', true);
            }
        }

        $digits = preg_replace('/\D+/', '', $agentPhone);
        if ($digits === '') {
            return;
        }

        $title = (string) get_the_title($postId);
        $url = (string) get_permalink($postId);
        $code = (string) get_post_meta($postId, $meta['code'], true);
        $advisorName = '';
        if ($agentId > 0) {
            $advisor = get_user_by('id', $agentId);
            if ($advisor && !empty($advisor->display_name)) {
                $advisorName = (string) $advisor->display_name;
            }
        }
        if ($advisorName === '') {
            $advisorName = __('Asesor', 'homlity-plugin');
        }

        $message = sprintf(
            'Estoy interesado en este inmueble. Código: %s. %s - %s',
            $code !== '' ? $code : 'N/A',
            $title,
            $url
        );

        $payload = [
            'number' => $digits,
            'message' => $message,
            'advisorName' => $advisorName,
            'postId' => $postId,
        ];

        $inline = '(function(){' .
            'var d=' . wp_json_encode($payload) . ';' .
            'if(!window.njt_wa||!Array.isArray(window.njt_wa.accounts)||!window.njt_wa.accounts.length){return;}' .
            'var base=window.njt_wa.accounts[0]||{};' .
            'var acc={};' .
            'for(var k in base){if(Object.prototype.hasOwnProperty.call(base,k)){acc[k]=base[k];}}' .
            'acc.number=d.number;' .
            'acc.predefinedText=d.message;' .
            'acc.accountName=d.advisorName;' .
            'if(!acc.title){acc.title="Asesor";}' .
            'window.njt_wa.accounts=[acc];' .
            '})();';

        wp_add_inline_script('nta-js-popup', $inline, 'before');
    }
}

