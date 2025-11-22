<?php
/**
 * Adds custom user meta fields (e.g., phone).
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

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
        <h2><?php esc_html_e('Información de contacto', 'plugin-inmobiliario'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="plugin_inmobiliario_phone"><?php esc_html_e('Teléfono móvil', 'plugin-inmobiliario'); ?></label></th>
                <td>
                    <input type="text" name="plugin_inmobiliario_phone" id="plugin_inmobiliario_phone"
                           value="<?php echo esc_attr($value); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Número de celular para contacto en inmuebles.', 'plugin-inmobiliario'); ?></p>
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
        $value = isset($_POST['plugin_inmobiliario_phone']) ? sanitize_text_field($_POST['plugin_inmobiliario_phone']) : '';
        update_user_meta($userId, $this->phoneMeta, $value);
    }
}
