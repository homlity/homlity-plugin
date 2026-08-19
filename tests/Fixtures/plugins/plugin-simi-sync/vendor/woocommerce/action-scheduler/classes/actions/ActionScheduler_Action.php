<?php

declare(strict_types=1);

/**
 * Réplica del punto exacto donde Action Scheduler 3.9.3 lanza la excepción
 * cuando una acción programada no tiene callbacks registrados.
 *
 * @see vendor/woocommerce/action-scheduler/classes/actions/ActionScheduler_Action.php::execute()
 *
 * El archivo vive bajo tests/Fixtures/plugins/plugin-simi-sync/vendor/... a
 * propósito: Exception::getFile() es final y no se puede simular, así que la
 * única forma fiel de reproducir la atribución por ruta es que la excepción
 * nazca realmente dentro del árbol vendor/ de un plugin Homlity.
 */
function homlity_fixture_action_scheduler_execute(string $hook, ?callable $callback = null): void
{
    if ($callback === null) {
        throw new Exception(
            sprintf('Scheduled action for %1$s will not be executed as no callbacks are registered.', $hook)
        );
    }

    $callback();
}
