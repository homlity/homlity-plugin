<?php

namespace Homlity\PluginInmobiliario\Integrations\Shared;

final class ListingControlSections
{
    public static function forCompatibilityPanel(array $controls): array
    {
        if (!isset($controls['query_mode'], $controls['columns'])) {
            return $controls;
        }
        $heading = '';
        $label = '';
        foreach ($controls as $name => &$control) {
            if (($control['section'] ?? '') !== 'query') {
                $heading = '';
                continue;
            }
            if (($control['type'] ?? '') === 'heading') {
                $heading = $name;
                $label = (string) ($control['label'] ?? '');
            }
            if ($heading !== '') {
                $control['section'] = $heading;
                $control['section_label'] = __('Consulta', 'homlity-real-estate') . ' · ' . $label;
            }
        }
        unset($control);
        return $controls;
    }
}
