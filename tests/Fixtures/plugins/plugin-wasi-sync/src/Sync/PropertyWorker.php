<?php

declare(strict_types=1);

/**
 * Código propio (fuera de vendor/) de otro plugin Homlity: representa el fallo
 * real de un worker de sincronización WASI ejecutado por la cola.
 */
function homlity_fixture_wasi_worker_failure(string $message = 'WASI upstream timeout'): void
{
    throw new RuntimeException($message);
}
