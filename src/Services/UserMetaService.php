<?php
// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
/**
 * Adds custom user meta fields (e.g., phone).
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class UserMetaService implements ServiceInterface
{
    private string $phoneMeta = 'phone';

    public function register(): void
    {
        add_action('user_new_form', [$this, 'renderPhoneField']);
        add_action('show_user_profile', [$this, 'renderPhoneField']);
        add_action('edit_user_profile', [$this, 'renderPhoneField']);

        add_action('user_register', [$this, 'savePhone']);
        add_action('personal_options_update', [$this, 'savePhone']);
        add_action('edit_user_profile_update', [$this, 'savePhone']);
    }

    public function renderPhoneField($user): void
    {
        $value = '';
        if ($user instanceof \WP_User) {
            $value = get_user_meta($user->ID, $this->phoneMeta, true);
        }
        ?>
        <h2><?php esc_html_e('Información de contacto', 'homlity-real-estate'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="homlity_plugin_phone"><?php esc_html_e('Teléfono móvil', 'homlity-real-estate'); ?></label></th>
                <td>
                    <input type="text" name="homlity_plugin_phone" id="homlity_plugin_phone"
                           value="<?php echo esc_attr($value); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Número de celular para contacto en inmuebles.', 'homlity-real-estate'); ?></p>
                </td>
            </tr>
        </table>
        <?php
        $this->renderPublicListingField($user);
    }

    /**
     * El interruptor que decide si el asesor sale en los listados del sitio.
     *
     * Solo se pinta para quien es asesor: en el perfil de un suscriptor o de
     * un redactor no significa nada, y una casilla que no hace nada en la
     * mayoría de los perfiles del sitio es ruido.
     *
     * @param mixed $user
     */
    private function renderPublicListingField($user): void
    {
        if (!$user instanceof \WP_User || !AgentProfileService::qualifiesAsAgent($user)) {
            return;
        }

        $listed = AgentProfileService::isPubliclyListed($user);
        ?>
        <h2><?php esc_html_e('Asesor', 'homlity-real-estate'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Visibilidad', 'homlity-real-estate'); ?></th>
                <td>
                    <?php // El campo oculto es lo que permite desmarcar: una casilla sin marcar no se envía. ?>
                    <input type="hidden" name="homlity_agent_public_present" value="1" />
                    <label for="homlity_agent_public">
                        <input type="checkbox" name="homlity_agent_public" id="homlity_agent_public"
                               value="1" <?php checked($listed); ?> />
                        <?php esc_html_e('Mostrar en los listados de asesores del sitio', 'homlity-real-estate'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Al desmarcarlo el asesor deja de aparecer en el widget «Asesores con inmuebles disponibles». Sus inmuebles publicados no cambian, y sigue apareciendo como contacto en la ficha de los suyos.', 'homlity-real-estate'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function savePhone(int $userId): void
    {
        if (!current_user_can('edit_user', $userId)) {
            return;
        }
        $value = isset($_POST['homlity_plugin_phone'])
            ? sanitize_text_field($_POST['homlity_plugin_phone'])
            : '';
        update_user_meta($userId, $this->phoneMeta, $value);

        $this->savePublicListing($userId);
    }

    /**
     * El interruptor solo se guarda cuando su formulario venía en la petición.
     *
     * Sin el campo testigo, cualquier otro formulario que dispare estos mismos
     * ganchos —el alta de usuario, o un plugin con su propia pantalla de
     * perfil— llegaría aquí sin la casilla y ocultaría al asesor sin que nadie
     * lo hubiera pedido.
     */
    private function savePublicListing(int $userId): void
    {
        if (!isset($_POST['homlity_agent_public_present'])) {
            return;
        }

        update_user_meta(
            $userId,
            AgentProfileService::PUBLIC_META,
            isset($_POST['homlity_agent_public']) ? '1' : '0'
        );
    }
}
