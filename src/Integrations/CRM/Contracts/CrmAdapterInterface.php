<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

interface CrmAdapterInterface
{
    public function key(): string;

    public function label(): string;

    /**
     * @return string[]
     */
    public function capabilities(): array;

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>
     */
    public function mapRecordToNormalized(array $payload, array $context = []): array;
}
