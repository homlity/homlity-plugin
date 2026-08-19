<?php

declare(strict_types=1);

/**
 * Réplica del envoltorio de ActionScheduler_Abstract_QueueRunner::process_action().
 *
 * Action Scheduler 3.9.3 re-lanza SIEMPRE el Throwable original dentro de una
 * Exception nueva creada en este archivo:
 *
 *     } catch ( Throwable $e ) {
 *         throw new Exception( $e->getMessage(), $e->getCode(), $e );
 *     }
 *
 * Ese detalle es el origen de la atribución errónea: la excepción que recibe
 * el hook action_scheduler_failed_execution siempre apunta a este archivo.
 */
function homlity_fixture_action_scheduler_process_action(callable $execute): Exception
{
    try {
        $execute();
    } catch (Throwable $error) {
        return new Exception($error->getMessage(), (int) $error->getCode(), $error);
    }

    throw new LogicException('El fixture esperaba una excepción del callback.');
}
