<?php
/**
 * Template editor — removed.
 *
 * WordPress.org guidelines prohibit plugins from allowing arbitrary PHP/CSS/JS
 * editing from within the admin (see: Plugin Review Guidelines §11).
 * This class is kept as an empty stub for backwards compatibility but no
 * longer registers any hooks or menus.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateEditorService implements ServiceInterface
{
    public function register(): void
    {
        // Intentionally empty: feature removed per WordPress.org review guidelines.
    }
}
