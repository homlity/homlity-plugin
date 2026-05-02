<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Services;

if (!defined('ABSPATH')) {
    exit;
}

class MediaSyncService
{
    /**
     * Phase 3 core contract only. External PUSH modules can call this service once
     * their adapter has normalized image records. PULL modules should store URLs.
     *
     * @param array<int,array<string,mixed>> $images
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function planSync(int $postId, array $images, array $options = []): array
    {
        return [
            'post_id' => $postId,
            'strategy' => sanitize_key((string) ($options['strategy'] ?? 'mark_and_sweep')),
            'download_allowed' => !empty($options['download_allowed']),
            'incoming_count' => count($images),
            'steps' => [
                'download_new',
                'validate_downloads',
                'update_gallery',
                'delete_obsolete_owned_media',
            ],
        ];
    }
}
