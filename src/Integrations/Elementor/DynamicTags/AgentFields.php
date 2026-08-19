<?php
/**
 * Datos del asesor expuestos como etiquetas dinámicas de Elementor.
 *
 * Toda la resolución vive aquí y no en las etiquetas para que se pueda probar:
 * las clases Tag/Data_Tag solo existen cuando Elementor está cargado, así que
 * cualquier lógica dentro de ellas queda fuera del alcance de las pruebas.
 */

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\WhatsAppLinkService;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

final class AgentFields
{
    /**
     * Campos de texto ofrecidos en el desplegable de la etiqueta.
     *
     * @return array<string,string> clave => etiqueta visible
     */
    public static function textChoices(): array
    {
        return [
            'name' => __('Nombre', 'homlity-real-estate'),
            'first_name' => __('Nombre de pila', 'homlity-real-estate'),
            'last_name' => __('Apellidos', 'homlity-real-estate'),
            'role' => __('Cargo', 'homlity-real-estate'),
            'phone' => __('Teléfono', 'homlity-real-estate'),
            'email' => __('Correo electrónico', 'homlity-real-estate'),
            'bio' => __('Biografía', 'homlity-real-estate'),
            'website' => __('Sitio web', 'homlity-real-estate'),
            'property_count' => __('Número de inmuebles', 'homlity-real-estate'),
        ];
    }

    /** @return array<string,string> clave => etiqueta visible */
    public static function urlChoices(): array
    {
        return [
            'profile' => __('Perfil del asesor', 'homlity-real-estate'),
            'whatsapp' => __('WhatsApp', 'homlity-real-estate'),
            'phone' => __('Llamada (tel:)', 'homlity-real-estate'),
            'email' => __('Correo (mailto:)', 'homlity-real-estate'),
            'website' => __('Sitio web', 'homlity-real-estate'),
        ];
    }

    /**
     * El asesor al que se refiere la etiqueta.
     *
     * Por orden: el que se haya fijado en el control —lo que permite
     * previsualizar en el editor, donde no hay perfil que consultar—, el del
     * perfil que se está viendo, y por último el asignado al inmueble de la
     * página. Ese tercer paso es lo que deja usar las mismas etiquetas en la
     * plantilla de inmueble sin duplicarlas.
     *
     * @param mixed $candidate WP_User, id o nicename; vacío para deducirlo.
     */
    public static function resolveAgent($candidate = null): ?WP_User
    {
        $agent = AgentProfileService::resolveAgent($candidate);

        return $agent instanceof WP_User ? $agent : self::propertyAgent();
    }

    /** El asesor asignado al inmueble que se está mostrando. */
    private static function propertyAgent(): ?WP_User
    {
        $postId = (int) get_the_ID();
        if ($postId <= 0 || get_post_type($postId) !== PropertyPostType::POST_TYPE) {
            return null;
        }

        $metaKeys = (new PropertyPostType())->metaKeys();
        $agentId = (int) get_post_meta($postId, $metaKeys['agent_id'], true);
        if ($agentId <= 0) {
            return null;
        }

        $user = get_user_by('id', $agentId);

        return $user instanceof WP_User ? $user : null;
    }

    /**
     * Valor de texto de un campo. Un campo desconocido devuelve cadena vacía:
     * una etiqueta guardada en una página sigue existiendo aunque el campo
     * desaparezca de la lista, y debe quedarse en blanco, no reventar.
     */
    public static function text(?WP_User $agent, string $field): string
    {
        if (!$agent instanceof WP_User) {
            return '';
        }

        return match ($field) {
            'name' => (string) $agent->display_name,
            'first_name' => (string) get_user_meta($agent->ID, 'first_name', true),
            'last_name' => (string) get_user_meta($agent->ID, 'last_name', true),
            'role' => AgentProfileService::agentRole($agent),
            'phone' => AgentProfileService::agentPhone($agent),
            'email' => (string) $agent->user_email,
            'bio' => (string) get_user_meta($agent->ID, 'description', true),
            'website' => (string) $agent->user_url,
            'property_count' => (string) AgentProfileService::propertyCount((int) $agent->ID),
            default => '',
        };
    }

    /**
     * URL de un enlace. Sin el dato de origen devuelve cadena vacía, que es lo
     * que Elementor entiende como «no pintes el enlace»: un `tel:` o un
     * `mailto:` a secas sería un botón que no lleva a ninguna parte.
     */
    public static function url(?WP_User $agent, string $link): string
    {
        if (!$agent instanceof WP_User) {
            return '';
        }

        return match ($link) {
            'profile' => AgentProfileService::profileUrl($agent),
            'whatsapp' => WhatsAppLinkService::buildAgentLink(AgentProfileService::agentPhone($agent)),
            'phone' => self::telLink(AgentProfileService::agentPhone($agent)),
            'email' => self::mailtoLink((string) $agent->user_email),
            'website' => (string) $agent->user_url,
            default => '',
        };
    }

    /**
     * Foto del asesor para el widget de imagen.
     *
     * Elementor espera id y URL: con el id monta los tamaños intermedios y el
     * srcset, y cae a la URL cuando la foto del CRM no está en la biblioteca.
     *
     * @return array{id:int, url:string}
     */
    public static function image(?WP_User $agent): array
    {
        if (!$agent instanceof WP_User) {
            return ['id' => 0, 'url' => ''];
        }

        $source = AgentProfileService::avatarSource($agent);

        return ['id' => $source['id'], 'url' => $source['url']];
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
