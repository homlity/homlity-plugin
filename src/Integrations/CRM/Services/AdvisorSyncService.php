<?php
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
namespace Homlity\PluginInmobiliario\Integrations\CRM\Services;

if (!defined('ABSPATH')) {
    exit;
}

class AdvisorSyncService
{
    /**
     * Find an existing WordPress user for the advisor or create one.
     * Saves all advisor metas on the user and returns the WP user ID.
     *
     * Resolution order:
     *  1. By _homlity_source_key + _homlity_external_advisor_id meta
     *  2. By email
     *  3. Create new subscriber user
     *
     * @param array<string,mixed> $advisor  Normalized advisor data
     */
    public function resolveOrCreate(string $sourceKey, array $advisor): int
    {
        $sourceKey   = sanitize_key($sourceKey);
        $externalId  = sanitize_text_field((string) ($advisor['external_id'] ?? ''));
        $email       = sanitize_email((string) ($advisor['email'] ?? ''));
        $name        = sanitize_text_field((string) ($advisor['name'] ?? ''));
        $phone       = sanitize_text_field((string) ($advisor['phone'] ?? ''));
        $photo       = esc_url_raw((string) ($advisor['photo'] ?? ''));
        $role        = sanitize_text_field((string) ($advisor['role'] ?? ''));

        $userId = $this->findExistingUserId($sourceKey, $externalId, $email);

        if ($userId === 0 && $email !== '') {
            $userId = $this->createUser($email, $name);
        }

        if ($userId > 0) {
            $this->updateUserMeta($userId, $sourceKey, $externalId, $phone, $photo, $role);
        }

        return $userId;
    }

    /**
     * Legacy method — returns resolved data without creating users.
     *
     * @param array<string,mixed> $advisor
     * @return array<string,mixed>
     */
    public function resolveAdvisor(string $sourceKey, array $advisor): array
    {
        $sourceKey  = sanitize_key($sourceKey);
        $externalId = sanitize_text_field((string) ($advisor['external_id'] ?? ''));
        $email      = sanitize_email((string) ($advisor['email'] ?? ''));

        return [
            'source_key'  => $sourceKey,
            'external_id' => $externalId,
            'email'       => $email,
            'user_id'     => $this->findExistingUserId($sourceKey, $externalId, $email),
            'action'      => 'resolve_only',
        ];
    }

    private function findExistingUserId(string $sourceKey, string $externalId, string $email): int
    {
        if ($sourceKey !== '' && $externalId !== '') {
            $users = get_users([
                'number' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => '_homlity_source_key',           'value' => $sourceKey],
                    ['key' => '_homlity_external_advisor_id',  'value' => $externalId],
                ],
            ]);
            if (!empty($users)) {
                return (int) $users[0];
            }
        }

        if ($email === '') {
            return 0;
        }

        $user = get_user_by('email', $email);
        return $user ? (int) $user->ID : 0;
    }

    private function createUser(string $email, string $name): int
    {
        $username = sanitize_user(strstr($email, '@', true) ?: $email, true);
        $username = $username ?: 'advisor_' . substr(md5($email), 0, 8);

        if (username_exists($username)) {
            $username .= '_' . substr(md5($email . time()), 0, 6);
        }

        $parts = explode(' ', $name, 2);

        $result = wp_insert_user([
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password(24, true, true),
            'display_name' => $name ?: $username,
            'first_name'   => $parts[0] ?? '',
            'last_name'    => $parts[1] ?? '',
            'role'         => 'subscriber',
        ]);

        if (is_wp_error($result)) {
            return 0;
        }

        return (int) $result;
    }

    private function updateUserMeta(
        int    $userId,
        string $sourceKey,
        string $externalId,
        string $phone,
        string $photo,
        string $role,
    ): void {
        if ($sourceKey !== '') {
            update_user_meta($userId, '_homlity_source_key', $sourceKey);
        }
        if ($externalId !== '') {
            update_user_meta($userId, '_homlity_external_advisor_id', $externalId);
            update_user_meta($userId, '_homlity_sync_advisor_id', $externalId);
        }
        if ($phone !== '') {
            update_user_meta($userId, '_homlity_advisor_phone', $phone);
        }
        if ($photo !== '') {
            update_user_meta($userId, '_homlity_advisor_photo', $photo);
        }
        if ($role !== '') {
            update_user_meta($userId, '_homlity_advisor_role', $role);
        }
    }
}
