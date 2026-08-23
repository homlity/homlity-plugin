<?php

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Contracts;

use Homlity\PluginInmobiliario\Integrations\CRM\Contracts\CrmAdapterInterface as InternalCrmAdapterInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translates one CRM's records into Homlity's canonical property shape.
 *
 * An adapter does exactly one job: take whatever JSON the CRM sends —
 * through a webhook, a pull job or a manual sync — and return the canonical
 * array documented in docs/developers/models/property.md. Writing the post,
 * resolving taxonomies, homologating features and downloading media are the
 * plugin's responsibility, not the adapter's.
 *
 * Register the adapter during `homlity_crm_register_adapters`:
 *
 *     add_action('homlity_crm_register_adapters', function ($manager) {
 *         $manager->registerAdapter(new MyCrmAdapter());
 *     });
 *
 * @since 2.8.0
 */
interface CrmAdapterInterface extends InternalCrmAdapterInterface
{
}
