<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM;

if (!defined('ABSPATH')) {
    exit;
}

class CrmConfig
{
    public const OPTION_INTEGRATIONS = 'homlity_plugin_crm_integrations';
    public const OPTION_QUEUE = 'homlity_plugin_crm_sync_queue';
    public const OPTION_LOGS = 'homlity_plugin_crm_sync_logs';
    public const CRON_HOOK = 'homlity_plugin_crm_process_queue';
}
