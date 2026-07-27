<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves the public property reference shown to visitors.
 */
final class PropertyCodeResolver
{
    public static function forDisplay(int $postId): string
    {
        if ($postId <= 0) {
            return '';
        }

        // SIMI distinguishes its public property code from the WordPress post
        // ID/internal reference stored in the canonical property meta.
        $simiCode = trim((string) get_post_meta($postId, '_simi_sync_code', true));
        if ($simiCode !== '') {
            return $simiCode;
        }

        // Compatibility with older SIMI imports that saved only their
        // external identifier before _simi_sync_code was introduced.
        $simiId = trim((string) get_post_meta($postId, '_simi_sync_id', true));
        if ($simiId !== '') {
            return $simiId;
        }

        return trim((string) get_post_meta($postId, '_property_code', true));
    }
}
