# Reporte centralizado de errores Homlity

`homlity-real-estate` es el colector de incidencias de los plugins oficiales. El sistema no instala un manejador global de excepciones ni sustituye los manejadores de WordPress. Registra únicamente un callback de apagado para errores PHP fatales y consume hooks explícitos de sincronización.

## Alcance

Orígenes canónicos reconocidos:

| Producto | Directorios reconocidos |
|---|---|
| `homlity-real-estate` | `homlity-real-estate` |
| `homlity-sync` | `homlity-sync` |
| `homlity-wasi` | `plugin-wasi-sync`, `homlity-wasi` |
| `homlity-simi` | `plugin-simi-sync`, `homlity-simi` |
| `homlity-softinm` | `plugin-softinm-sync`, `homlity-softinm` |

Se aceptan `E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR`, `E_USER_ERROR` y `E_RECOVERABLE_ERROR` cuando el archivo pertenece a uno de esos directorios. Warnings, notices, deprecated, errores del tema, core o terceros se descartan. También se descartan validaciones esperadas, 4xx funcionales, sincronizaciones vacías correctas, bloqueos normales y solicitudes que coinciden inequívocamente con scanners o rutas de ataque.

## Reportar un fallo final de sincronización

Los plugins oficiales pueden usar la fachada sin conocer la implementación del colector:

```php
Homlity_Error_Reporter::report_sync_error(
    'homlity-wasi',
    $exception,
    [
        'operation' => 'full_reconciliation',
        'run_id' => $runId,
        'status' => 'failed',
        'failed' => $failedCount,
    ]
);
```

También está disponible el hook equivalente:

```php
do_action('homlity_report_sync_error', 'homlity-wasi', $exception, $safeContext);
```

Debe invocarse al terminar definitivamente una operación, no en cada intento. Para trabajos por inmueble, `homlity_sync_job_failed` debe entregar un contexto mínimo con `status=dead`, `attempt` y `max_attempts`; los estados `retry_scheduled` se ignoran. No deben incluirse payloads del proveedor, contactos, credenciales ni datos personales. El colector aplica una segunda lista blanca antes de persistir.

## Entrega y autenticación

Cada evento se envía a:

```text
POST {HOMI_API_URL}/api/v1/plugin-installations/{site_id}/error-events
```

La licencia se obtiene del almacenamiento propio del plugin al momento de enviar y se agrega exclusivamente como `X-Plugin-License`. Nunca forma parte del evento ni de la cola. `site_id` es el UUID guardado durante la activación/validación de esa misma licencia.

- `200` y `202`: se elimina el evento.
- `401` y `403`: el evento queda bloqueado y se marca que la licencia requiere revalidación.
- `422`: el evento inválido se descarta y solo se guarda el código diagnóstico local.
- `429`, `5xx` o fallo de transporte: se reintenta con backoff exponencial y jitter, conservando el mismo `event_id`.
- Otros `4xx`: se descartan como no recuperables.

La cola usa opciones de WordPress, almacena como máximo 100 eventos ya saneados y elimina entradas de más de siete días. Las ocurrencias equivalentes pendientes se agregan en un solo evento. Un lock atómico evita entregas concurrentes. WP-Cron procesa la cola cada cinco minutos y las visitas administrativas habilitan un procesamiento pequeño de respaldo.

## Privacidad

Antes de persistir o transmitir se eliminan o enmascaran:

- licencias, authorization headers, tokens, JWT, cookies, passwords, API keys y secretos;
- credenciales de WASI, SIMI y Softinm;
- correos, teléfonos, documentos y secuencias numéricas sensibles;
- query strings, request bodies, payloads completos del proveedor y rutas absolutas del servidor.

Solo se conserva método y path de la solicitud, contexto operacional incluido en lista blanca, hasta 50 breadcrumbs saneados, y mensajes/trazas truncados a 65.535 bytes. El evento completo se mantiene por debajo de 256 KB.

## Diagnóstico administrativo

En **Homlity → Ajustes → Incidencias** se muestran la cola, bloqueos, último envío/error, próximo reintento, cron y plugins detectados. Licencia y `site_id` siempre aparecen enmascarados. “Validar conexión” comprueba localmente que haya licencia activa y `site_id`; no fabrica ni envía una incidencia falsa. Al validar credenciales recuperadas, habilita nuevamente los eventos que habían quedado bloqueados por `401/403`.

## Extensibilidad segura

- `homlity_error_reporter_official_plugins`: amplía el registro de productos y alias.
- `homlity_error_reporter_safe_context_keys`: amplía las claves permitidas del contexto.
- `homlity_error_reporter_breadcrumbs`: proporciona hasta 50 breadcrumbs, que serán saneados.
- `homlity_error_reporter_ignore_request`: permite descartar una solicitud adicional antes de crear un fatal.

Toda extensión debe conservar el principio de lista blanca y no incluir secretos ni datos personales.

## Pruebas

Desde el directorio del plugin:

```bash
composer test:error-reporting
```

La suite cubre el contrato del payload, los cinco orígenes oficiales, exclusiones, redacción, deduplicación, límites, expiración, lock, modos de ejecución, credenciales y la matriz de respuestas HTTP de Homi.
