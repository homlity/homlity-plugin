<?php
/**
 * Technical sheet full page template.
 */

use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

get_header();
TemplateService::includeComponent('property-technical-sheet.php', [
    'post_id' => get_the_ID(),
]);
get_footer();
