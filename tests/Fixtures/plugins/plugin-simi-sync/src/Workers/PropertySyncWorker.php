<?php

declare(strict_types=1);

/**
 * Código propio (fuera de vendor/) de homlity-simi: representa el fallo real de
 * un worker de sincronización que SIMI relanza hacia Action Scheduler.
 */
function homlity_fixture_simi_worker_failure(string $message = 'SIMI upstream 502'): void
{
    throw new RuntimeException($message);
}
