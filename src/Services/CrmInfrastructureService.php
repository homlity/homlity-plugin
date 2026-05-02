<?php

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Integrations\CRM\Repository\PullSyncJobRepository;
use Homlity\PluginInmobiliario\Integrations\CRM\Repository\SyncIndexRepository;
use Homlity\PluginInmobiliario\Integrations\CRM\Repository\WebhookEventRepository;

if (!defined('ABSPATH')) {
    exit;
}

class CrmInfrastructureService implements ServiceInterface
{
    private const OPTION_SCHEMA_VERSION = 'homlity_plugin_crm_schema_version';
    private const SCHEMA_VERSION = '3';

    private SyncIndexRepository $indexRepository;
    private WebhookEventRepository $eventRepository;
    private PullSyncJobRepository $jobRepository;

    public function __construct()
    {
        $this->indexRepository = new SyncIndexRepository();
        $this->eventRepository = new WebhookEventRepository();
        $this->jobRepository = new PullSyncJobRepository();
    }

    public function register(): void
    {
        add_action('init', [$this, 'maybeUpgradeSchema'], 30);
    }

    public function maybeUpgradeSchema(): void
    {
        $current = (string) get_option(self::OPTION_SCHEMA_VERSION, '0');
        if ($current === self::SCHEMA_VERSION) {
            return;
        }

        $this->indexRepository->ensureTable();
        $this->eventRepository->ensureTable();
        $this->jobRepository->ensureTable();
        update_option(self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false);
    }
}
