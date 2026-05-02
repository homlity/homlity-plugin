<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM;

use Homlity\PluginInmobiliario\Integrations\CRM\Adapters\Demo\DemoCrmAdapter;
use Homlity\PluginInmobiliario\Integrations\CRM\Contracts\CrmAdapterInterface;

if (!defined('ABSPATH')) {
    exit;
}

class CrmIntegrationManager
{
    /** @var array<string,CrmAdapterInterface> */
    private array $adapters = [];

    public function __construct()
    {
        // Demo stays in core so package authors can validate the flow without a real CRM.
        $this->registerAdapter(new DemoCrmAdapter());
        do_action('homlity_crm_register_adapters', $this);

        $filtered = apply_filters('homlity_crm_adapters', $this->adapters);
        if (is_array($filtered)) {
            $this->adapters = array_filter($filtered, static fn($adapter): bool => $adapter instanceof CrmAdapterInterface);
        }
    }

    public function registerAdapter(CrmAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->key()] = $adapter;
    }

    public function getAdapter(string $provider): ?CrmAdapterInterface
    {
        $provider = sanitize_key($provider);
        return $this->adapters[$provider] ?? null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listProviders(): array
    {
        $providers = [];
        foreach ($this->adapters as $adapter) {
            $providers[] = [
                'key' => $adapter->key(),
                'label' => $adapter->label(),
                'capabilities' => $adapter->capabilities(),
            ];
        }

        return $providers;
    }
}
