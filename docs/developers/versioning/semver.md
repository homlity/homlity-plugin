# Versionamiento semántico

Homlity Real Estate y la Developer API siguen
[Semantic Versioning 2.0.0](https://semver.org/lang/es/).

---

## Dos versiones, dos ritmos

| | Ejemplo | Cambia cuando |
| --- | --- | --- |
| **Versión del plugin** — `HOMLITY_PLUGIN_VERSION` | `2.8.0` | Cambia cualquier cosa del plugin |
| **Versión de la API** — `HOMLITY_API_VERSION` | `1.0.0` | Cambia el **contrato público** |

Son independientes a propósito. El plugin publica correcciones, mejoras de
rendimiento y widgets nuevos con frecuencia; el contrato público debe moverse lo
menos posible. Una extensión que sólo usa hooks y modelos no tiene por qué
enterarse de nada de eso.

```php
homlity_version();      // "2.8.0"  ← el plugin
homlity_api_version();  // "1.0.0"  ← el contrato
```

Cuál declarar en tus requisitos:

| Declara | Cuando dependes de |
| --- | --- |
| `api` | Un hook, una clase o un método de la Developer API |
| `homlity` | Una funcionalidad del plugin: una taxonomía, un shortcode, una pantalla |

---

## `MAJOR.MINOR.PATCH`

### MAJOR — cambios que rompen

Se incrementa cuando algo que funcionaba deja de funcionar.

En el plugin:
- se elimina una funcionalidad;
- sube el mínimo de PHP o de WordPress;
- cambia la estructura de datos de forma no migrable.

En la API:
- un hook público cambia de nombre o desaparece;
- un hook pierde parámetros o cambia su orden;
- un método público cambia de firma o de tipo de retorno;
- una clase o interfaz pública desaparece.

**Nada llega a un MAJOR sin haber pasado antes por deprecación.** Ver
[Deprecaciones](deprecations.md).

### MINOR — funcionalidad nueva, compatible hacia atrás

En el plugin: funcionalidades nuevas, widgets nuevos, integraciones nuevas.

En la API:
- hooks públicos nuevos;
- clases, interfaces y métodos públicos nuevos;
- **parámetros nuevos al final** de un hook existente;
- deprecaciones —que avisan, pero no rompen.

Las dos cosas marcadas son la razón de dos reglas prácticas:

```php
// Declara cuántos argumentos aceptas: un parámetro nuevo al final
// no te afectará.
add_action('homlity/property/updated', $callback, 10, 3);
```

Y no implementes interfaces públicas que no estén pensadas para implementar: si
`ExtensionInterface` gana un método en 1.1, tu implementación seguirá siendo
válida porque los métodos nuevos llegarán con valor por defecto o en una
interfaz aparte, pero eso sólo vale para los contratos declarados como
implementables.

### PATCH — correcciones

En ambos: correcciones de errores, mejoras de rendimiento, cambios internos,
documentación. Nada del contrato cambia.

---

## Qué está y qué no está cubierto

### Cubierto por SemVer

- Todo bajo `Homlity\Developer\`.
- Los hooks `homlity/…` documentados en
  [Actions](../api/actions.md) y [Filters](../api/filters.md).
- Los helpers globales documentados en [Helpers](../api/helpers.md).
- Las claves de `Property::toArray()`.
- Las constantes `HOMLITY_PLUGIN_VERSION`, `HOMLITY_API_VERSION`,
  `HOMLITY_PLUGIN_SLUG`, `HOMLITY_PLUGIN_PATH`, `HOMLITY_PLUGIN_URL`.

### No cubierto

- `Homlity\PluginInmobiliario\*` y las clases `Homlity_*` de `includes/`.
- Los hooks con guion bajo: `homlity_*`, `homlity_plugin_*`, `homlity_crm_*`, …
- Los metadatos `_property_*` y `_consignment_*`.
- Las tablas propias del plugin.
- Las rutas REST internas.
- La estructura de `templates/` y de `assets/`.
- Las clases y métodos marcados `@internal`.

Pueden cambiar en cualquier versión, incluida una de parche. Si construyes sobre
ellos, fija la versión de Homlity y prueba antes de cada actualización.

---

## Preestrenos

```
2.9.0-beta.1
2.9.0-rc.1
```

No son estables. `version_compare()` de PHP los ordena correctamente:
`2.9.0-beta.1` < `2.9.0-rc.1` < `2.9.0`.

---

## Cómo comparar versiones

```php
// ✓ Compara por número de versión.
if (homlity_is_version_supported('2.8.0')) { }

// ✓ Equivalente, a mano.
if (version_compare(homlity_version(), '2.8.0', '>=')) { }

// ✗ Compara como texto: dice que 2.10.0 es anterior a 2.9.0.
if (homlity_version() >= '2.8.0') { }
```

`homlity_is_version_supported('')` devuelve `false`: preguntar por «ninguna
versión» es un error de quien pregunta, y responder que sí dejaría pasar una
extensión que no declaró nada.

---

## Versiona tu extensión igual

`getVersion()` es lo que ven otras extensiones para decidir si pueden convivir
contigo. Que signifique algo:

- **MAJOR** — cambias un hook propio, o dejas de soportar una versión de Homlity.
- **MINOR** — añades funcionalidad sin romper nada.
- **PATCH** — corriges un error.

```php
public function getVersion(): string
{
    return '1.4.2';
}
```

---

## Historial de la API

| API | Homlity | Qué trajo |
| --- | --- | --- |
| 1.0.0 | 2.8.0 | Versión inicial: 12 actions, 4 filters, 11 clases, 3 interfaces, 7 helpers |
