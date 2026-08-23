# Filters

Los cuatro filtros públicos de la Homlity Developer API.

Son pocos a propósito. Un filtro es más peligroso que una acción: lo que
devuelve se guarda o se consulta. Publicar cien filtros «por si acaso» convierte
en contrato cada detalle interno y hace imposible cambiar nada. Estos cuatro
son los puntos donde una integración real necesita intervenir.

Los cuatro comparten dos garantías:

- **Llegan con los datos prometidos**, en el momento documentado.
- **Devolver algo del tipo equivocado no rompe nada**: el valor se descarta y
  el plugin sigue con el original.

---

# homlity/property/normalized

**Descripción.** Filtra la carga canónica de un inmueble justo antes de que
Homlity la escriba en la base de datos.

Es el punto de extensión más potente de la API: ahí están todos los campos del
esquema canónico, y todavía no se han resuelto las taxonomías ni descargado las
imágenes.

**Desde.** 2.8.0

**Se aplica en.** `PropertyUpsertService::upsert()`, antes de la validación —
de modo que un filtro puede incluso completar un campo obligatorio que el CRM
omitió.

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `array` | `$normalized` | Carga canónica. Ver [el esquema](../models/property.md#esquema-canónico) |
| 2 | `string` | `$source` | Clave del CRM de origen, o `''` |
| 3 | `string` | `$origin` | `admin`, `crm`, `consignment`, `sync` o `unknown` |

**Retorno.** `array` — la carga, modificada o no. Un valor que no sea array se
ignora y se conserva la original.

**Ejemplo.** Marcar la procedencia y forzar el borrador para un CRM concreto:

```php
add_filter('homlity/property/normalized', function (array $normalized, string $source) {
    $normalized['external']['raw']['importado_por'] = 'mi-crm';

    // Los inmuebles de este CRM se revisan antes de publicar.
    if ($source === 'crm-sin-revisar') {
        $normalized['post']['status'] = 'draft';
    }

    return $normalized;
}, 10, 2);
```

**Ejemplo.** Normalizar un dato que llega mal de un CRM:

```php
add_filter('homlity/property/normalized', function (array $normalized) {
    // Este CRM manda el área con la unidad pegada: "85 m2".
    if (isset($normalized['metrics']['area'])) {
        $normalized['metrics']['area'] = preg_replace(
            '/[^0-9.]/',
            '',
            (string) $normalized['metrics']['area']
        );
    }

    return $normalized;
});
```

**Cuidado.** Esto escribe en la base de datos. Un filtro que vacíe
`post.title` hace que el inmueble se rechace; uno que cambie `external.id`
puede duplicar inmuebles o sobrescribir el que no era.

**Relacionado.** `homlity/property/data`, `homlity/property/synchronized`

---

# homlity/property/data

**Descripción.** Filtra los datos con los que se construye un objeto
`Property`, antes de entregárselo a quien lo pidió.

**Desde.** 2.8.0

**Se aplica en.** `PropertyRepository::hydrate()` — es decir, cada vez que la
Developer API entrega un inmueble, incluidos los que viajan en las acciones del
ciclo de vida. Mantén el callback barato.

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `array` | `$data` | Datos de hidratación. Las claves reflejan `Property::toArray()`, salvo precios, imágenes, ubicación y asesor, que aún son objetos o subarrays |
| 2 | `int` | `$postId` | ID del inmueble |
| 3 | `WP_Post` | `$post` | El post subyacente |

**Retorno.** `array`. Un valor que no sea array se ignora.

**Ejemplo.** Añadir un prefijo comercial al título en toda la API:

```php
add_filter('homlity/property/data', function (array $data) {
    if ($data['featured'] ?? false) {
        $data['title'] = '★ ' . $data['title'];
    }

    return $data;
}, 10);
```

**Ejemplo.** Sustituir la ciudad por la que guarda otro plugin:

```php
add_filter('homlity/property/data', function (array $data, int $postId) {
    $ciudad = get_post_meta($postId, '_mi_plugin_ciudad', true);
    if ($ciudad !== '') {
        $data['location']['city'] = $ciudad;
    }

    return $data;
}, 10, 2);
```

**Cuidado.** Este filtro **no** cambia lo que hay en la base de datos, sólo lo
que ve quien consume la API. Para cambiar lo que se guarda, usa
`homlity/property/normalized`.

**Relacionado.** `homlity/property/normalized`

---

# homlity/property/query_args

**Descripción.** Filtra los argumentos de `WP_Query` con los que Homlity lista
y busca inmuebles.

**Desde.** 2.8.0

**Se aplica en.** `PropertySearchService::buildQueryArgs()`, al final. Afecta al
shortcode `[homlity_listing]`, a los widgets de Elementor, Divi y WPBakery, y
al endpoint AJAX del listado.

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `array` | `$args` | Argumentos listos para `WP_Query` |
| 2 | `array` | `$params` | Parámetros normalizados de la petición |

**Retorno.** `array`. Un valor que no sea array se ignora.

**Ejemplo.** Excluir de los listados los inmuebles marcados por otro plugin:

```php
add_filter('homlity/property/query_args', function (array $args) {
    $args['meta_query'][] = [
        'relation' => 'OR',
        ['key' => '_mi_plugin_oculto', 'compare' => 'NOT EXISTS'],
        ['key' => '_mi_plugin_oculto', 'value' => '1', 'compare' => '!='],
    ];

    return $args;
});
```

**Ejemplo.** Filtro propio por año de construcción:

```php
add_filter('homlity/property/query_args', function (array $args, array $params) {
    $desde = (int) ($_GET['anio_desde'] ?? 0);
    if ($desde > 0) {
        $args['meta_query'][] = [
            'key'     => '_property_age',
            'value'   => $desde,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }

    return $args;
}, 10, 2);
```

**Cuidado.** `$args['meta_query']` ya trae dos cláusulas, y son las que
mantienen fuera de la web los inmuebles retirados del mercado. **Añade**
cláusulas; no sustituyas el array entero. Reemplazarlo publica inventario que
la inmobiliaria había retirado.

**Relacionado.** `homlity/property/data`

---

# homlity/extension/is_compatible

**Descripción.** Filtra si una extensión se considera compatible con esta
instalación.

**Desde.** 2.8.0

**Se aplica en.** `ExtensionRegistry::register()`, después de evaluar los
requisitos declarados y antes de aceptar la extensión.

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `bool` | `$isCompatible` | Si todos los requisitos se cumplen |
| 2 | `ExtensionInterface` | `$extension` | La extensión evaluada |
| 3 | `string[]` | `$unmet` | Motivos de incumplimiento; vacío si es compatible |

**Retorno.** `bool`.

**Ejemplo.** Desbloquear una extensión en un entorno de pruebas:

```php
add_filter('homlity/extension/is_compatible', function ($compatible, $extension) {
    if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'staging') {
        if ($extension->getSlug() === 'mi-crm-beta') {
            return true;
        }
    }

    return $compatible;
}, 10, 2);
```

**Ejemplo.** Vetar una extensión concreta:

```php
add_filter('homlity/extension/is_compatible', function ($compatible, $extension) {
    return $extension->getSlug() === 'extension-problematica' ? false : $compatible;
}, 10, 2);
```

**Cuidado.** Devolver `true` no hace compatible a la extensión: sólo hace que
Homlity la arranque igualmente. Si el requisito era real, el fallo aparecerá
más tarde y en un sitio peor. Úsalo en desarrollo, no en producción.

**Relacionado.** `homlity/extension/failed`

---

## Filtros heredados

Homlity tiene además ~75 filtros con guion bajo:
`homlity_plugin_format_price`, `homlity_crm_adapters`,
`homlity_consignment_payload`, `homlity_schema_graph`, `homlity_faq_*`, …

Funcionan y no están deprecados, pero no están cubiertos por la garantía de
estabilidad. Ver la [nota sobre hooks heredados](actions.md#hooks-heredados).
