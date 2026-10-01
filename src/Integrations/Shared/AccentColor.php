<?php

namespace Homlity\PluginInmobiliario\Integrations\Shared;

final class AccentColor
{
    public static function control(string $selector, string $additionalDeclarations = ''): array
    {
        return [
            'label' => __('Color de acento', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [$selector => '--homlity-primary-color: {{VALUE}};' . $additionalDeclarations],
        ];
    }
}
