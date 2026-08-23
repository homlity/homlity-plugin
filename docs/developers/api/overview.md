# Developer API — visión general

La Homlity Developer API **1.0.0**, introducida en Homlity Real Estate 2.8.0.

---

## Convención de nombres

Homlity usa dos convenciones y la diferencia entre ellas *es* información:

| Forma | Significado | Estabilidad |
| --- | --- | --- |
| `homlity/dominio/evento` | Developer API pública | Garantizada dentro de la versión mayor |
| `homlity_lo_que_sea` | Hook heredado, anterior a la API | Sin garantía; puede cambiar en una versión menor |

La barra no es estética. Es la marca de que ese punto de extensión está
documentado, probado y sujeto a SemVer.

Los ~90 hooks con guion bajo que ya existían (`homlity_crm_adapters`,
`homlity_consignment_payload`, `homlity_schema_graph`, …) **siguen funcionando
exactamente igual**. No están deprecados. Simplemente no forman parte del
contrato: úsalos si los necesitas, sabiendo que no hay promesa detrás.

### Estructura del nombre público

```
homlity / <dominio> / <evento>
          │           │
          │           └── qué pasó, en pasado: created, updated, deleted
          └────────────── de qué se habla: property, extension, extensions
```

---

## La superficie completa

Doce acciones, cuatro filtros, once clases, tres interfaces, siete funciones
globales. Eso es todo lo que Homlity se compromete a mantener.

### Acciones

| Hook | Cuándo |
| --- | --- |
| `homlity/loaded` | El núcleo terminó de registrar sus servicios |
| `homlity/extensions/register` | Ventana para registrar extensiones |
| `homlity/extension/registered` | Una extensión concreta arrancó |
| `homlity/extension/failed` | Una extensión fue rechazada o falló |
| `homlity/extensions/registered` | Todas las extensiones arrancaron |
| `homlity/initialized` | CPT, taxonomías y reescrituras listos |
| `homlity/property/created` | Se creó un inmueble |
| `homlity/property/updated` | Se actualizó un inmueble |
| `homlity/property/deleted` | Se va a borrar un inmueble |
| `homlity/property/synchronized` | Un origen externo escribió un inmueble |
| `homlity/property/status_changed` | Cambió el estado del post |
| `homlity/property/images_changed` | Cambió la galería |

Detalle completo: [Actions](actions.md).

### Filtros

| Hook | Qué filtra |
| --- | --- |
| `homlity/property/normalized` | La carga canónica antes de guardarse |
| `homlity/property/data` | Los datos con los que se construye un `Property` |
| `homlity/property/query_args` | Los argumentos de `WP_Query` de la búsqueda |
| `homlity/extension/is_compatible` | Si una extensión se considera compatible |

Detalle completo: [Filters](filters.md).

### Clases e interfaces

[Clases públicas](classes.md) · [Interfaces](interfaces.md)

### Funciones globales

[Helpers](helpers.md)

---

## Usar constantes en lugar de cadenas

Las dos formas son válidas y equivalentes:

```php
add_action('homlity/property/updated', $callback, 10, 3);

use Homlity\Developer\Support\Hooks;
add_action(Hooks::PROPERTY_UPDATED, $callback, 10, 3);
```

La constante hace imposible una errata: un `add_action` con el nombre mal
escrito no falla, simplemente no se ejecuta nunca, y eso es difícil de
diagnosticar. La cadena, en cambio, funciona aunque tu extensión se cargue en
un sitio donde Homlity no esté.

Regla práctica: **cadena** en el arranque de tu plugin (donde Homlity puede no
existir), **constante** dentro de `boot()` (donde ya sabes que existe).

---

## Estabilidad: qué significa exactamente

Dentro de la versión mayor 1.x de la API:

- Un hook público no cambiará de nombre ni desaparecerá.
- Un hook no perderá parámetros ni cambiará su orden. **Puede ganar
  parámetros al final** — por eso siempre debes declarar en `add_action` cuántos
  argumentos aceptas.
- Un método público no cambiará de firma ni de tipo de retorno.
- Se pueden añadir métodos, clases y hooks nuevos.
- Nada se elimina sin pasar antes por
  [deprecación](../versioning/deprecations.md).

Ver [SemVer](../versioning/semver.md).
