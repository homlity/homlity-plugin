<?php
/**
 * Handles role capabilities for the property post type.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class CapabilityService implements ServiceInterface
{
    public const ROLE_ASSESSOR = 'asesor_comercial';

    public const CAPS = [
        'edit_post' => 'edit_property',
        'read_post' => 'read_property',
        'delete_post' => 'delete_property',
        'edit_posts' => 'edit_properties',
        'edit_others_posts' => 'edit_others_properties',
        'publish_posts' => 'publish_properties',
        'read_private_posts' => 'read_private_properties',
        'read' => 'read_property',
        'delete_posts' => 'delete_properties',
        'delete_private_posts' => 'delete_private_properties',
        'delete_published_posts' => 'delete_published_properties',
        'delete_others_posts' => 'delete_others_properties',
        'edit_private_posts' => 'edit_private_properties',
        'edit_published_posts' => 'edit_published_properties',
        'create_posts' => 'edit_properties',
    ];

    public function register(): void
    {
        add_action('init', [$this, 'ensureCaps']);
    }

    public function ensureCaps(): void
    {
        $this->ensureRole();

        $roles = ['administrator', 'editor'];
        foreach ($roles as $roleName) {
            $role = get_role($roleName);
            if (!$role) {
                continue;
            }
            foreach (self::CAPS as $cap) {
                if (!$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }
        }

        $assessor = get_role(self::ROLE_ASSESSOR);
        if (!$assessor) {
            $assessor = add_role(self::ROLE_ASSESSOR, __('Asesor Comercial', 'plugin-inmobiliario'));
        }
        if ($assessor) {
            $assessorCaps = [
                'read',
                self::CAPS['read_post'],
                self::CAPS['edit_post'],
                self::CAPS['edit_posts'],
                self::CAPS['publish_posts'],
                self::CAPS['delete_post'],
                self::CAPS['delete_posts'],
                self::CAPS['edit_published_posts'],
                self::CAPS['delete_published_posts'],
                'upload_files',
            ];
            foreach ($assessorCaps as $cap) {
                if (!$assessor->has_cap($cap)) {
                    $assessor->add_cap($cap);
                }
            }
        }
    }

    private function ensureRole(): void
    {
        if (!get_role(self::ROLE_ASSESSOR)) {
            add_role(self::ROLE_ASSESSOR, __('Asesor Comercial', 'plugin-inmobiliario'));
        }
    }
}
