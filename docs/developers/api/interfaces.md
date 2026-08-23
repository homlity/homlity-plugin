# Interfaces

Tres contratos públicos. Cada uno tiene un consumidor real dentro del núcleo:
Homlity no publica interfaces que nadie llama.

---

## `ExtensionInterface`

`Homlity\Developer\Contracts\ExtensionInterface` · desde 2.8.0

### Responsabilidad

Describir una extensión ante Homlity: cómo se llama, qué versión es, qué
necesita para funcionar y qué hace al arrancar.

Es el contrato que convierte un plugin cualquiera en una extensión de Homlity.
A cambio de implementarlo, la extensión obtiene:

- comprobación automática de compatibilidad antes de ejecutarse;
- un arranque en el momento exacto del ciclo de vida;
- aislamiento: si lanza una excepción, el sitio no se cae;
- presencia en el censo de extensiones y en los hooks de diagnóstico.

### Métodos

#### `getName(): string`

Nombre legible, el que se muestra en las pantallas de administración.
Puede estar traducido.

#### `getSlug(): string`

Identificador único, en minúsculas, con dígitos, guiones y guiones bajos.
Se normaliza con `sanitize_key()`.

Dos extensiones no pueden compartir slug: la segunda se rechaza. Prefíjalo con
tu vendor, `acme-mi-crm`, no `crm`.

#### `getVersion(): string`

Versión semántica de la extensión, `'1.4.2'`. No es la versión de Homlity ni la
de la API.

#### `getRequirements(): Requirements`

Lo que la extensión necesita del entorno. Devuelve `Requirements::none()` si
funciona en cualquier instalación que pueda ejecutar Homlity.

Homlity lo evalúa **antes** de llamar a `boot()`. Si algo no se cumple, la
extensión no arranca y se dispara `homlity/extension/failed` con los motivos.

#### `boot(): void`

El único punto de entrada. Se llama una sola vez, en `plugins_loaded`
prioridad 25.

En ese momento el núcleo está registrado pero **los post types y las taxonomías
todavía no**. Aquí se enganchan hooks; el trabajo va en `init` o en
`homlity/initialized`.

Las excepciones que salgan de aquí las captura el registro: se convierten en un
rechazo y en un `homlity/extension/failed`. No tumban el sitio. Aun así,
captúralas tú: el registro no puede saber si el fallo era recuperable.

### Ejemplo de implementación

```php
<?php

namespace Acme\MiCrm;

use Homlity\Developer\Contracts\ExtensionInterface;
use Homlity\Developer\Extension\Requirements;
use Homlity\Developer\Models\Property;

final class Extension implements ExtensionInterface
{
    public function getName(): string
    {
        return __('Acme · Mi CRM', 'acme-mi-crm');
    }

    public function getSlug(): string
    {
        return 'acme-mi-crm';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getRequirements(): Requirements
    {
        return Requirements::create([
            'homlity' => '2.8.0',
            'api'     => '1.0.0',
            'php'     => '8.1',
        ]);
    }

    public function boot(): void
    {
        add_action('homlity/property/updated', [$this, 'push'], 10, 3);
        add_action('homlity/initialized', [$this, 'registerRoutes']);
    }

    public function push(Property $property, $changes, $context): void
    {
        if ($changes->isEmpty() || $context->getSource() === 'acme') {
            return;
        }

        // …
    }

    public function registerRoutes(): void
    {
        // …
    }
}
```

---

## `PropertySyncProviderInterface`

`Homlity\Developer\Contracts\PropertySyncProviderInterface` · desde 2.8.0

Extiende el contrato interno `SyncProviderInterface`, que el plugin envía desde
la 2.4. Implementar la versión pública es la forma soportada de escribir un
proveedor nuevo; los que ya implementan la interna siguen funcionando sin
cambios.

### Responsabilidad

Resolver un inmueble que existe en un CRM externo pero todavía no en WordPress.

Cuando un visitante abre `/inmueble/{CODIGO}` y ningún inmueble local lleva ese
código, Homlity recorre los proveedores registrados por orden de prioridad. El
primero que devuelve un ID de post gana, y el visitante se redirige con un 301 a
la URL canónica.

### Métodos

#### `getProviderId(): string`

Identificador único del proveedor: `'mi-crm'`, `'wasi-sync'`, `'simi'`.
Determina también su prioridad por defecto.

#### `syncByCode(string $code): ?int`

Trae el inmueble del CRM, créalo en WordPress con sus metadatos, taxonomías e
imágenes, y devuelve el ID del post.

Devuelve `null` si el inmueble no existe en ese CRM o si el intento falló. No
hagas la redirección: de eso se encarga el núcleo.

`$code` llega tal como aparece en la URL, por ejemplo `VTAP1320041`.

### Ejemplo de implementación

```php
<?php

namespace Acme\MiCrm;

use Homlity\Developer\Contracts\PropertySyncProviderInterface;
use Homlity\PluginInmobiliario\Services\SyncRegistry;

final class SyncProvider implements PropertySyncProviderInterface
{
    public function getProviderId(): string
    {
        return 'acme-mi-crm';
    }

    public function syncByCode(string $code): ?int
    {
        $record = (new Client())->fetchByReference($code);
        if ($record === null) {
            return null;
        }

        // La forma recomendada de escribir: la carga canónica, no wp_insert_post.
        $result = (new \Homlity\PluginInmobiliario\Integrations\CRM\PropertyUpsertService())
            ->upsert($this->toCanonical($record), 'sync');

        return !empty($result['ok']) ? (int) $result['post_id'] : null;
    }

    private function toCanonical(array $record): array
    {
        return [
            'external' => ['source' => 'acme-mi-crm', 'id' => (string) $record['id']],
            'post'     => ['title' => (string) $record['titulo']],
            // …
        ];
    }
}

// El registro se hace en el hook heredado, que sigue siendo el oficial:
add_action('homlity_plugin_register_sync_providers', function () {
    SyncRegistry::addProvider(new SyncProvider());
});
```

`SyncRegistry` y `PropertyUpsertService` son clases internas. Se usan aquí
porque hoy no existe un envoltorio público para registrar proveedores ni para
escribir la carga canónica; está anotado como pendiente en el
[informe de implementación](../implementation-report.md#11-cambios-pendientes).
La interfaz que implementas sí es pública, que es lo que fija la forma de tu
código.

---

## `CrmAdapterInterface`

`Homlity\Developer\Contracts\CrmAdapterInterface` · desde 2.8.0

Extiende el contrato interno del mismo nombre, en uso desde la 2.6.

### Responsabilidad

Traducir los registros de **un** CRM a la forma canónica de Homlity. Nada más:
escribir el post, resolver taxonomías, homologar características y descargar
imágenes es responsabilidad del núcleo.

Esta separación es lo que hace que añadir un CRM sea un trabajo de traducción y
no una reimplementación de la lógica de guardado.

### Métodos

#### `key(): string`

Clave del proveedor, `'wasi'`, `'simi'`. Es lo que aparece como
`external.source` en los inmuebles importados y en
`$context->getSource()` de los hooks.

#### `label(): string`

Nombre legible, para la pantalla de integraciones.

#### `capabilities(): array`

Lista de cadenas con lo que el adaptador sabe hacer: `'webhook'`, `'pull'`,
`'media'`, …

#### `mapRecordToNormalized(array $payload, array $context = []): array`

El método que hace el trabajo. Recibe el registro tal como lo manda el CRM y
devuelve la carga canónica.

`$context` trae pistas del origen: `from_webhook`, `from_queue`, `provider`.

### Ejemplo de implementación

```php
<?php

namespace Acme\MiCrm;

use Homlity\Developer\Contracts\CrmAdapterInterface;

final class Adapter implements CrmAdapterInterface
{
    public function key(): string
    {
        return 'acme';
    }

    public function label(): string
    {
        return 'Acme CRM';
    }

    public function capabilities(): array
    {
        return ['webhook', 'pull', 'media'];
    }

    public function mapRecordToNormalized(array $payload, array $context = []): array
    {
        return [
            'external' => [
                'source'     => 'acme',
                'id'         => (string) ($payload['id'] ?? ''),
                'updated_at' => (string) ($payload['modified'] ?? ''),
                'raw'        => $payload,
            ],
            'post' => [
                'title'       => (string) ($payload['title'] ?? ''),
                'description' => (string) ($payload['description'] ?? ''),
                'status'      => 'publish',
            ],
            'pricing' => [
                'sale_price'    => (string) ($payload['price'] ?? ''),
                'sale_currency' => (string) ($payload['currency'] ?? 'COP'),
            ],
            'metrics' => [
                'code'      => (string) ($payload['reference'] ?? ''),
                'bedrooms'  => (int) ($payload['rooms'] ?? 0),
                'bathrooms' => (int) ($payload['baths'] ?? 0),
                'area'      => (string) ($payload['area'] ?? ''),
            ],
            'location' => [
                'address'   => (string) ($payload['address'] ?? ''),
                'latitude'  => (float) ($payload['lat'] ?? 0),
                'longitude' => (float) ($payload['lng'] ?? 0),
            ],
            'taxonomy' => [
                'property_operation' => [(string) ($payload['operation'] ?? '')],
                'property_type'      => [(string) ($payload['type'] ?? '')],
                'property_city'      => [(string) ($payload['city'] ?? '')],
            ],
            'media' => [
                'gallery' => array_map('strval', (array) ($payload['photos'] ?? [])),
            ],
        ];
    }
}

add_action('homlity_crm_register_adapters', function ($manager) {
    $manager->registerAdapter(new Adapter());
});
```

El esquema canónico completo está en
[el modelo Property](../models/property.md#esquema-canónico).
