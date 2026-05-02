<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Support;

if (!defined('ABSPATH')) {
    exit;
}

class ArrayPath
{
    /**
     * @param array<string,mixed> $source
     * @return mixed|null
     */
    public static function get(array $source, string $path)
    {
        $cursor = $source;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
