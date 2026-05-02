<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Contracts\V2;

use Homlity\PluginInmobiliario\Integrations\CRM\DTO\CrmPageResult;
use Homlity\PluginInmobiliario\Integrations\CRM\DTO\IntegrationTestResult;
use Homlity\PluginInmobiliario\Integrations\CRM\DTO\NormalizedProperty;

if (!defined('ABSPATH')) {
    exit;
}

interface HomlityCrmAdapterInterface
{
    public function getSourceKey(): string;

    public function getSourceName(): string;

    public function supportsPush(): bool;

    public function supportsPull(): bool;

    /**
     * @param array<string,mixed> $raw
     */
    public function normalizeProperty(array $raw): NormalizedProperty;

    /**
     * @param array<string,mixed> $cursor
     */
    public function fetchPropertiesPage(array $cursor = []): CrmPageResult;

    /**
     * @return array<string,mixed>|null
     */
    public function fetchPropertyById(string $externalId): ?array;

    public function validateCredentials(): IntegrationTestResult;
}
