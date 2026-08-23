# Actions

Las doce acciones públicas de la Homlity Developer API.

Todas se declaran como constantes en
`Homlity\Developer\Support\Hooks`.

---

## Cobertura

Antes del catálogo, lo que estas acciones cubren y lo que no.

Los eventos de inmueble se disparan desde los dos caminos por los que Homlity
escribe un inmueble entero:

1. **`PropertyUpsertService::upsert()`** — todo lo que llega de fuera: webhooks
   de CRM, sincronización manual, trabajos por lotes, formulario de consignación.
2. **`PropertyPostType::saveMeta()`** — el editor de wp-admin.

En ambos casos el hook dispara **después** de escribir post, metadatos,
taxonomías, galería y asesor. Nunca a mitad.

`homlity/property/deleted` y `homlity/property/status_changed` no dependen de
ese camino: se enganchan a `before_delete_post` y `transition_post_status` de
WordPress, así que detectan el cambio lo escriba quien lo escriba.

**Lo que no cubren:** un plugin de terceros que cree inmuebles llamando a
`wp_insert_post()` por su cuenta no dispara `created` ni `updated`, porque
Homlity no participa en esa escritura. Sí disparará `status_changed`. Si
escribes un plugin así, la vía correcta es
[`PropertySyncProviderInterface`](interfaces.md#propertysyncproviderinterface) o
un adaptador de CRM, no `wp_insert_post()` directo.

---

# homlity/loaded

**Descripción.** El núcleo de Homlity terminó de registrar todos sus servicios.

**Cuándo se ejecuta.** `plugins_loaded`, prioridad 20, al final de
`PluginBootstrap::init()`. Los custom post types y las taxonomías **todavía no
están registrados** — eso ocurre en `init`.

**Desde.** 2.8.0

**Parámetros.** Ninguno.

**Ejemplo.**

```php
add_action('homlity/loaded', function () {
    if (!homlity_is_version_supported('2.9.0')) {
        add_action('admin_notices', 'mi_plugin_aviso_version');
    }
});
```

**Relacionado.** `homlity/initialized`, `homlity/extensions/register`

---

# homlity/extensions/register

**Descripción.** Ventana de registro de extensiones. Es el sitio donde una
extensión se da de alta.

**Cuándo se ejecuta.** `plugins_loaded`, prioridad 25 — después de que el núcleo
arranque (20) y antes de los proveedores de sincronización (30).

**Desde.** 2.8.0

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `ExtensionRegistry` | `$registry` | Registro donde darse de alta |

**Ejemplo.**

```php
add_action('homlity/extensions/register', function ($registry) {
    $registry->register(new MiIntegracion());
});
```

Equivalente, sin recibir el registro:

```php
add_action('homlity/extensions/register', function () {
    homlity_register_extension(new MiIntegracion());
});
```

**Relacionado.** `homlity/extension/registered`, `homlity/extension/failed`

---

# homlity/extension/registered

**Descripción.** Una extensión concreta acaba de arrancar correctamente.

**Cuándo se ejecuta.** Justo después de que su método `boot()` retorne sin
lanzar.

**Desde.** 2.8.0

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `ExtensionInterface` | `$extension` | La extensión que arrancó |
| 2 | `string` | `$slug` | Su slug ya saneado |

**Ejemplo.**

```php
add_action('homlity/extension/registered', function ($extension, $slug) {
    if ($slug === 'competidor-crm') {
        // desactivar mi propia sincronización para no duplicar
    }
}, 10, 2);
```

**Relacionado.** `homlity/extensions/registered`

---

# homlity/extension/failed

**Descripción.** Una extensión fue rechazada, o lanzó una excepción al arrancar.

**Cuándo se ejecuta.** En el momento del rechazo: slug vacío o duplicado,
requisitos incumplidos, veto del filtro de compatibilidad, o excepción dentro
de `boot()`.

**Desde.** 2.8.0

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `ExtensionInterface` | `$extension` | La extensión rechazada |
| 2 | `string[]` | `$reasons` | Motivos, traducidos y legibles |
| 3 | `string` | `$slug` | Su slug, o `'unknown'` si no era usable |

**Ejemplo.**

```php
add_action('homlity/extension/failed', function ($extension, $reasons, $slug) {
    if ($slug !== 'mi-crm') {
        return;
    }

    add_action('admin_notices', function () use ($reasons) {
        printf(
            '<div class="notice notice-error"><p>Mi CRM no pudo arrancar: %s</p></div>',
            esc_html(implode(' ', $reasons))
        );
    });
}, 10, 3);
```

**Relacionado.** `homlity/extension/is_compatible`

---

# homlity/extensions/registered

**Descripción.** Todas las extensiones que iban a arrancar ya arrancaron.

**Cuándo se ejecuta.** `plugins_loaded`, prioridad 25, después de
`bootAll()`.

**Desde.** 2.8.0

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `ExtensionRegistry` | `$registry` | El registro, ya completo |

**Ejemplo.**

```php
add_action('homlity/extensions/registered', function ($registry) {
    if (!$registry->has('homlity-analytics')) {
        // mi extensión aporta su propia analítica
    }
});
```

**Relacionado.** `homlity/extensions/register`

---

# homlity/initialized

**Descripción.** Homlity está completamente inicializado: post types,
taxonomías, reglas de reescritura y shortcodes registrados.

**Cuándo se ejecuta.** `init`, prioridad 100 — después de los registros (10) y
de las migraciones de versión (99).

**Desde.** 2.8.0

**Parámetros.** Ninguno.

**Ejemplo.**

```php
add_action('homlity/initialized', function () {
    // Aquí ya es seguro consultar inmuebles.
    $property = homlity_get_property(128);
});
```

**Relacionado.** `homlity/loaded`

---

# homlity/property/created

**Descripción.** Se creó un inmueble y toda su información ya está guardada.

**Cuándo se ejecuta.** Al terminar la escritura completa: post, metadatos,
taxonomías, homologación, galería y asesor.

**Desde.** 2.8.0

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `Property` | `$property` | El inmueble creado |
| 2 | `PropertyContext` | `$context` | De dónde vino la escritura |

**Ejemplo.**

```php
add_action('homlity/property/created', function ($property, $context) {
    // Sólo los que llegan de un CRM, no los cargados a mano.
    if (!$context->isExternal()) {
        return;
    }

    mi_portal_publicar([
        'ref'    => $property->getCode(),
        'precio' => $property->getSalePrice()?->getAmount(),
        'ciudad' => $property->getLocation()->getCity(),
        'fotos'  => array_map(fn($i) => $i->getUrl(), $property->getImages()),
    ]);
}, 10, 2);
```

**Relacionado.** `homlity/property/updated`, `homlity/property/synchronized`

---

# homlity/property/updated

**Descripción.** Se actualizó un inmueble existente.

**Cuándo se ejecuta.** Igual que `created`, al terminar la escritura completa.

**Desde.** 2.8.0

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `Property` | `$property` | El inmueble, ya actualizado |
| 2 | `PropertyChanges` | `$changes` | Qué campos canónicos cambiaron |
| 3 | `PropertyContext` | `$context` | De dónde vino la escritura |

**El diff puede estar vacío.** Un CRM que reenvía un registro idéntico dispara
una actualización igual. Comprueba `isEmpty()` antes de hacer trabajo caro.

**Ejemplo.**

```php
add_action('homlity/property/updated', function ($property, $changes, $context) {
    if ($changes->isEmpty()) {
        return;
    }

    // Evitar el bucle: no devolverle al CRM lo que él mismo acaba de mandar.
    if ($context->getSource() === 'mi-crm') {
        return;
    }

    if ($changes->hasGroup('pricing')) {
        mi_crm_actualizar_precio(
            $property->getCode(),
            $changes->previous('pricing.sale_price'),
            $changes->current('pricing.sale_price')
        );
    }
}, 10, 3);
```

**Relacionado.** `homlity/property/created`, `homlity/property/images_changed`

---

# homlity/property/deleted

**Descripción.** Un inmueble está a punto de borrarse definitivamente.

**Cuándo se ejecuta.** En `before_delete_post`, cuando el post y sus metadatos
**todavía existen**, para que puedas leer el inmueble una última vez.

Mover un inmueble a la papelera **no** dispara esta acción — eso es un cambio
de estado. Escucha `homlity/property/status_changed` para eso.

**Desde.** 2.8.0

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `Property` | `$property` | El inmueble, todavía legible |
| 2 | `int` | `$postId` | Su ID |

**Ejemplo.**

```php
add_action('homlity/property/deleted', function ($property, $postId) {
    mi_portal_retirar($property->getCode());
}, 10, 2);
```

**Relacionado.** `homlity/property/status_changed`

---

# homlity/property/synchronized

**Descripción.** Un inmueble fue escrito por un origen externo: un CRM, el
formulario de consignación o una sincronización bajo demanda.

**Cuándo se ejecuta.** Además de `created` o `updated`, nunca en su lugar. Usa
`$context->isNew()` para distinguir una importación de una actualización.

**Desde.** 2.8.0

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `Property` | `$property` | El inmueble sincronizado |
| 2 | `PropertyChanges` | `$changes` | Qué cambió; vacío si es nuevo |
| 3 | `PropertyContext` | `$context` | Origen y CRM de procedencia |

**Ejemplo.**

```php
add_action('homlity/property/synchronized', function ($property, $changes, $context) {
    error_log(sprintf(
        '[%s] %s %s desde %s',
        gmdate('c'),
        $context->isNew() ? 'importado' : 'actualizado',
        $property->getCode(),
        $context->getSource() ?: $context->getOrigin()
    ));
}, 10, 3);
```

**Relacionado.** `homlity/property/created`, `homlity/property/updated`

---

# homlity/property/status_changed

**Descripción.** Cambió el estado de publicación de un inmueble en WordPress.

**Cuándo se ejecuta.** En `transition_post_status`, lo cause quien lo cause:
wp-admin, una sincronización, WP-CLI u otro plugin. No dispara en la creación
del post — para eso está `homlity/property/created`.

**Desde.** 2.8.0

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `Property` | `$property` | El inmueble afectado |
| 2 | `string` | `$newStatus` | Estado nuevo |
| 3 | `string` | `$oldStatus` | Estado anterior |

**Estados posibles:** `publish`, `draft`, `pending`, `private`, `trash`.

**Ejemplo.**

```php
add_action('homlity/property/status_changed', function ($property, $new, $old) {
    if ($old === 'publish' && $new !== 'publish') {
        mi_portal_despublicar($property->getCode());
    }
}, 10, 3);
```

**Nota.** El estado del post no es lo mismo que la disponibilidad comercial: un
inmueble puede estar publicado y retirado del mercado. Para eso,
`$property->isAvailable()`.

**Relacionado.** `homlity/property/deleted`

---

# homlity/property/images_changed

**Descripción.** Cambió la galería de imágenes de un inmueble.

**Cuándo se ejecuta.** Junto a `created` o `updated`, cuando el diff incluye
`media.gallery`.

**Desde.** 2.8.0

**Parámetros.**

| # | Tipo | Nombre | Descripción |
| --- | --- | --- | --- |
| 1 | `Property` | `$property` | El inmueble; `getImages()` ya devuelve las nuevas |
| 2 | `PropertyChanges` | `$changes` | Incluye `media.gallery` |
| 3 | `PropertyContext` | `$context` | De dónde vino la escritura |

La galería anterior está en `$changes->previous('media.gallery')` como una
cadena opaca separada por `|`. Sirve para comparar, no para interpretar: su
formato no forma parte del contrato.

**Ejemplo.**

```php
add_action('homlity/property/images_changed', function ($property, $changes) {
    mi_portal_subir_fotos(
        $property->getCode(),
        array_map(fn($img) => $img->getUrl(), $property->getImages())
    );
}, 10, 2);
```

**Relacionado.** `homlity/property/updated`

---

## Hooks heredados

Homlity tiene además ~90 hooks con guion bajo, anteriores a la Developer API:
`homlity_crm_register_adapters`, `homlity_plugin_register_sync_providers`,
`homlity_consignment_*`, `homlity_schema_*`, `homlity_faq_*`, …

Siguen funcionando y no están deprecados, pero **no forman parte del contrato
público**: pueden cambiar en una versión menor. Si dependes de uno, fija la
versión de Homlity en los requisitos de tu extensión y vigila el
[changelog](../versioning/changelog-policy.md).
