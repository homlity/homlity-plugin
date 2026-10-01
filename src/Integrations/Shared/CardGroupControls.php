<?php

namespace Homlity\PluginInmobiliario\Integrations\Shared;

final class CardGroupControls
{
    public static function options(string $type, array $args): array
    {
        $utility = match ($args['name'] ?? '') {
            'card_border' => '{{WRAPPER}} .property-card-bs.border-0',
            'card_shadow' => '{{WRAPPER}} .property-card-bs.shadow-sm',
            'card_title_typography' => '{{WRAPPER}} .property-card-bs .card-title.fs-6',
            'card_price_typography' => '{{WRAPPER}} .property-card-bs .property-card__price.fs-5',
            default => '',
        };
        if ($utility === '') {
            return $args;
        }
        $args['utility_selector'] = $utility;
        $fields = match ($type) {
            'border' => [
                'border' => 'border-style: {{VALUE}};',
                'width' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                'color' => 'border-color: {{VALUE}};',
            ],
            'box_shadow' => ['box_shadow' => 'box-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{SPREAD}}px {{COLOR}} {{box_shadow_position.VALUE}};'],
            'typography' => [
                'font_family' => 'font-family: {{VALUE}};',
                'font_size' => 'font-size: {{SIZE}}{{UNIT}};',
                'font_weight' => 'font-weight: {{VALUE}};',
                'text_transform' => 'text-transform: {{VALUE}};',
                'font_style' => 'font-style: {{VALUE}};',
                'text_decoration' => 'text-decoration: {{VALUE}};',
                'line_height' => 'line-height: {{SIZE}}{{UNIT}};',
                'letter_spacing' => 'letter-spacing: {{SIZE}}{{UNIT}};',
                'word_spacing' => 'word-spacing: {{SIZE}}{{UNIT}};',
            ],
            default => [],
        };
        foreach ($fields as $name => $declaration) {
            $args['fields_options'][$name]['selectors'] = [
                '{{SELECTOR}}' => $declaration,
                $utility => str_replace(';', ' !important;', $declaration),
            ];
        }
        return $args;
    }

    public static function appendUtilityCss(array &$rules, array $control, string $wrapper, string $selector): void
    {
        $utility = str_replace('{{WRAPPER}}', $wrapper, (string) ($control['utility_selector'] ?? ''));
        if ($utility === '') {
            return;
        }
        $prefix = match ($control['group_type'] ?? '') {
            'border' => '/^border-/',
            'box_shadow' => '/^box-shadow:/',
            default => '/^(?:font-|text-|line-height:|letter-spacing:|word-spacing:)/',
        };
        foreach ($rules[$selector] ?? [] as $declaration) {
            if (!preg_match($prefix, $declaration)) {
                continue;
            }
            $rules[$utility][] = str_replace(';', ' !important;', $declaration);
        }
        foreach (['tablet', 'phone'] as $device) {
            foreach ($rules['@' . $device][$selector] ?? [] as $declaration) {
                if (!preg_match($prefix, $declaration)) {
                    continue;
                }
                $rules['@' . $device][$utility][] = str_replace(';', ' !important;', $declaration);
            }
        }
    }
}
