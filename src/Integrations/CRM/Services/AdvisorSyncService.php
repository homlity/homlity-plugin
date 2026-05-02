<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Services;

if (!defined('ABSPATH')) {
    exit;
}

class AdvisorSyncService
{
    /**
     * @param array<string,mixed> $advisor
     * @return array<string,mixed>
     */
    public function resolveAdvisor(string $sourceKey, array $advisor): array
    {
        $sourceKey = sanitize_key($sourceKey);
        $externalId = sanitize_text_field((string) ($advisor['external_id'] ?? ''));
        $email = sanitize_email((string) ($advisor['email'] ?? ''));

        return [
            'source_key' => $sourceKey,
            'external_id' => $externalId,
            'email' => $email,
            'user_id' => $this->findExistingUserId($sourceKey, $externalId, $email),
            'action' => 'resolve_only',
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
                    ['key' => '_homlity_source_key', 'value' => $sourceKey],
                    ['key' => '_homlity_external_advisor_id', 'value' => $externalId],
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
}
