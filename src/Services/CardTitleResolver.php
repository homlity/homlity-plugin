<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

/** Property-specific card titles, independent of archive title filters. */
final class CardTitleResolver
{
    public static function forDisplay(int $postId, array $options, string $code, $operations): string
    {
        $title = trim((string) get_post_field('post_title', $postId, 'raw'));
        if ($title === '') {
            $title = __('Inmueble', 'homlity-real-estate');
        }
        $parts = [$title];
        if (!empty($options['title_operation']) && is_array($operations)) {
            $labels = [];
            foreach ($operations as $operation) {
                $operation = trim((string) $operation);
                if ($operation !== '' && stripos($title, $operation) === false) {
                    $labels[] = $operation;
                }
            }
            if ($labels !== []) {
                $parts[] = implode(' / ', array_unique($labels));
            }
        }
        if (!empty($options['title_code']) && trim($code) !== '') {
            $parts[] = sprintf(__('Código %s', 'homlity-real-estate'), trim($code));
        }
        return implode(' · ', $parts);
    }
}
