<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Los asesores que el sitio enseña, con cuántos inmuebles disponibles tiene
 * cada uno.
 */

namespace Homlity\PluginInmobiliario\Services;

use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quién sale en el widget «Asesores con inmuebles disponibles».
 *
 * Vivía copiada tres veces —una por constructor— dentro de los widgets, que no
 * se pueden instanciar sin Elementor, Divi o WPBakery delante; es decir, no
 * había forma de comprobar nada de esto. Aquí está una sola vez y sí se puede.
 *
 * El recuento y el reparto van separados a propósito: la consulta es un JOIN
 * contra las metas de los inmuebles que ningún doble de base de datos sabe
 * ejecutar, mientras que decidir a quién se enseña y en qué orden es donde
 * están las reglas que importan. Partirlo deja lo segundo bajo prueba sin
 * tener que fingir lo primero.
 */
class AvailableAgentsService
{
    /**
     * Asesores visibles, de más a menos inmuebles.
     *
     * @return array<int,array{user:WP_User,count:int}>
     */
    public static function agents(int $limit): array
    {
        $limit = max(1, $limit);

        // Se piden más de los que se van a enseñar: la consulta no sabe quién
        // está oculto —el interruptor es una meta del usuario, no del
        // inmueble—, así que pedir justo el límite dejaría el widget corto en
        // cuanto hubiese un asesor apagado entre los primeros.
        $counts = self::propertyCountsByAgent(min(200, $limit * 3));

        return array_slice(self::listable($counts), 0, $limit);
    }

    /**
     * Inmuebles publicados y disponibles por asesor, del que más tiene al que
     * menos.
     *
     * @return array<int,int> Id del asesor => número de inmuebles.
     */
    public static function propertyCountsByAgent(int $limit): array
    {
        global $wpdb;

        $records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT CAST(pm_agent.meta_value AS UNSIGNED) AS agent_id, COUNT(DISTINCT p.ID) AS total
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_agent ON pm_agent.post_id = p.ID AND pm_agent.meta_key = '_property_agent_id'
             LEFT JOIN {$wpdb->postmeta} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = '_property_status'
             LEFT JOIN {$wpdb->postmeta} pm_available ON pm_available.post_id = p.ID AND pm_available.meta_key = '_property_available'
             WHERE p.post_type = %s
               AND p.post_status = 'publish'
               AND pm_agent.meta_value REGEXP '^[0-9]+$'
               AND CAST(pm_agent.meta_value AS UNSIGNED) > 0
               AND (pm_status.meta_id IS NULL OR LOWER(pm_status.meta_value) = 'active')
               AND (pm_available.meta_id IS NULL OR LOWER(pm_available.meta_value) IN ('1','true','yes','active'))
             GROUP BY agent_id
             ORDER BY total DESC, agent_id ASC
             LIMIT %d",
                PropertyPostType::POST_TYPE,
                max(1, $limit)
            ),
            ARRAY_A
        );

        if (!is_array($records)) {
            return [];
        }

        $counts = [];
        foreach ($records as $record) {
            $agentId = (int) ($record['agent_id'] ?? 0);
            $total = (int) ($record['total'] ?? 0);
            if ($agentId <= 0 || $total <= 0) {
                continue;
            }
            $counts[$agentId] = $total;
        }

        return $counts;
    }

    /**
     * De un recuento por id, los asesores que el sitio enseña.
     *
     * @param array<int,int> $counts Id del asesor => número de inmuebles.
     * @return array<int,array{user:WP_User,count:int}>
     */
    public static function listable(array $counts): array
    {
        $agentIds = array_values(array_unique(array_map('intval', array_keys($counts))));
        $agentIds = array_values(array_filter($agentIds, static fn(int $id): bool => $id > 0));
        if ($agentIds === []) {
            return [];
        }

        $users = get_users([
            'include' => $agentIds,
            'fields' => 'all',
        ]);

        $result = [];
        foreach ((array) $users as $user) {
            // El recuento tendría que estar siempre —`include` acota
            // get_users() a estos ids—, pero la consulta de usuarios pasa por
            // `pre_get_users` y un plugin del sitio puede ensancharla. Sin
            // esta comprobación, un usuario colado ahí saldría en el listado
            // con «0 inmuebles disponibles» y un aviso de índice indefinido.
            if (!$user instanceof WP_User || !isset($counts[$user->ID])) {
                continue;
            }
            // Un usuario dado de baja por WordPress no se enseña aunque siga
            // teniendo inmuebles a su nombre.
            if ((int) ($user->user_status ?? 0) !== 0) {
                continue;
            }
            if (!AgentProfileService::isPubliclyListed($user)) {
                continue;
            }

            $result[] = ['user' => $user, 'count' => (int) $counts[$user->ID]];
        }

        // El desempate por nombre es lo que hace que dos asesores con los
        // mismos inmuebles no se intercambien de sitio entre una visita y la
        // siguiente: get_users() no promete orden y la consulta lo perdió al
        // pasar por él.
        usort($result, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count']
                ?: strcasecmp((string) $a['user']->display_name, (string) $b['user']->display_name);
        });

        return $result;
    }
}
