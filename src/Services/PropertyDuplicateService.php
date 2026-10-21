<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\Developer\Support\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Expone el diagnóstico de duplicados en administración y WP-CLI.
 *
 * @since 2.9.16
 */
class PropertyDuplicateService implements ServiceInterface
{
    public function register(): void
    {
        add_action('save_post_property', [$this, 'invalidate']);
        add_action('deleted_post', [$this, 'invalidateDeletedProperty'], 10, 2);
        // Estos eventos se emiten después de persistir metas y taxonomías, también desde el CRM.
        add_action(Hooks::PROPERTY_CREATED, [$this, 'invalidate']);
        add_action(Hooks::PROPERTY_UPDATED, [$this, 'invalidate']);
        add_filter('manage_property_posts_columns', [$this, 'columns']);
        add_action('manage_property_posts_custom_column', [$this, 'renderColumn'], 10, 2);
        add_action('pre_get_posts', [$this, 'filterSiblings']);
        if (class_exists('\\WP_CLI')) {
            \WP_CLI::add_command('homlity duplicates', [$this, 'duplicatesCommand']);
        }
    }

    /**
     * Descarta el diagnóstico al guardar o terminar de persistir una ficha.
     *
     * @since 2.9.16
     */
    public function invalidate(): void
    {
        delete_transient(PropertyDuplicateDetector::TRANSIENT);
    }

    /**
     * Usa la ficha recibida porque después del borrado ya no existe en la base de datos.
     *
     * @since 2.9.16
     */
    public function invalidateDeletedProperty(int $postId, \WP_Post $post): void
    {
        if ($post->post_type === PropertyPostType::POST_TYPE) {
            $this->invalidate();
        }
    }

    /**
     * Añade el diagnóstico junto a las demás columnas de inmuebles.
     *
     * @since 2.9.16
     */
    public function columns(array $columns): array
    {
        $columns['homlity_duplicates'] = __('Calidad del texto', 'homlity-real-estate');
        return $columns;
    }

    /**
     * Muestra avisos sin alterar las fichas ni proponer borrados automáticos.
     *
     * @since 2.9.16
     */
    public function renderColumn(string $column, int $postId): void
    {
        if ($column !== 'homlity_duplicates') {
            return;
        }
        $detector = new PropertyDuplicateDetector();
        $group = $detector->groupFor($postId);
        if ($group !== null) {
            $url = add_query_arg(['post_type' => PropertyPostType::POST_TYPE, 'homlity_duplicate_of' => $postId], admin_url('edit.php'));
            $hint = $group['classification'] === 'same_listing'
                ? __('Posible mismo inmueble: revisar consolidación en el CRM.', 'homlity-real-estate')
                : __('Texto copiado: diferenciar las descripciones en el CRM.', 'homlity-real-estate');
            echo '<a class="homlity-duplicate-badge" href="' . esc_url($url) . '" title="' . esc_attr($hint) . '">'
                . esc_html(sprintf(__('Descripción duplicada (%d)', 'homlity-real-estate'), count($group['ids']) - 1)) . '</a>';
        }
        $mismatch = $detector->typeMismatch($postId);
        if ($mismatch !== null) {
            echo ($group !== null ? '<br>' : '') . '<span class="homlity-type-mismatch" title="' . esc_attr($mismatch) . '">'
                . esc_html__('Tipo ≠ texto', 'homlity-real-estate') . '</span>';
        }
    }

    /**
     * Resuelve el enlace del badge a los hermanos publicados del grupo.
     *
     * @since 2.9.16
     */
    public function filterSiblings($query): void
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== PropertyPostType::POST_TYPE) {
            return;
        }
        $source = absint($_GET['homlity_duplicate_of'] ?? 0);
        if ($source === 0) {
            return;
        }
        $group = (new PropertyDuplicateDetector())->groupFor($source);
        $ids = $group !== null ? array_values(array_diff($group['ids'], [$source])) : [];
        // [0] evita que WordPress interprete un grupo obsoleto como «todos».
        $query->set('post__in', $ids ?: [0]);
    }

    /**
     * Lista descripciones duplicadas y conflictos entre tipo y texto.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Formato de salida: table, json o csv. Por defecto, table.
     *
     * @since 2.9.16
     */
    public function duplicatesCommand(array $args, array $assocArgs): void
    {
        $format = $assocArgs['format'] ?? 'table';
        if (!in_array($format, ['table', 'json', 'csv'], true)) {
            \WP_CLI::error('Formato no válido. Usa table, json o csv.');
            return;
        }
        $detector = new PropertyDuplicateDetector();
        $groups = [];
        foreach ($detector->findGroups() as $group) {
            foreach ($group['ids'] as $id) {
                $groups[$id] = $group;
            }
        }
        $ids = get_posts(['post_type' => PropertyPostType::POST_TYPE, 'post_status' => 'publish',
            'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
        $rows = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $group = $groups[$id] ?? null;
            $mismatch = $detector->typeMismatch($id);
            if ($group === null && $mismatch === null) {
                continue;
            }
            $rows[] = ['post_id' => $id, 'title' => get_the_title($id),
                'classification' => $group['classification'] ?? '',
                'siblings' => $group !== null ? implode(',', array_diff($group['ids'], [$id])) : '',
                'type_mismatch' => $mismatch ?? ''];
        }
        \WP_CLI\Utils\format_items($format, $rows, ['post_id', 'title', 'classification', 'siblings', 'type_mismatch']);
    }
}
