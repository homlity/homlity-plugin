# Helpers globales

Siete funciones en el espacio global. Existen para lo que se hace **antes** de
poder usar clases: comprobar que Homlity está ahí.

Todas están definidas en `src/Developer/functions.php`, que Homlity carga al
principio de todo — antes de `plugins_loaded` — para que un plugin que se cargue
después pueda llamarlas desde su propio arranque.

---

## La regla del `function_exists`

WordPress **no garantiza el orden de carga de los plugins**. Si el tuyo se
carga antes que Homlity, estas funciones todavía no existen.

```php
// Correcto.
if (function_exists('homlity_is_available') && homlity_is_available()) {
    // …
}

// Incorrecto: fatal error si Homlity carga después.
if (homlity_is_available()) {
    // …
}
```

La forma robusta es hacer la comprobación en `plugins_loaded` con prioridad 21
o mayor. A partir de ahí todos los plugins están cargados y ya no hace falta
repetir el `function_exists`.

La comprobación más temprana posible, que funciona siempre, no es una función:

```php
if (defined('HOMLITY_PLUGIN_VERSION')) {
    // Homlity está cargado.
}
```

---

## `homlity_is_available(): bool`

Si Homlity Real Estate está cargado en esta petición.

**Desde.** 2.8.0

```php
add_action('plugins_loaded', function () {
    if (!function_exists('homlity_is_available') || !homlity_is_available()) {
        add_action('admin_notices', 'mi_plugin_aviso');
        return;
    }
    // …
}, 21);
```

---

## `homlity_version(): string`

Versión del plugin, o `''` si no está cargado.

**Desde.** 2.8.0

```php
echo homlity_version(); // "2.8.0"
```

---

## `homlity_api_version(): string`

Versión del contrato público de la Developer API.

**Desde.** 2.8.0

```php
echo homlity_api_version(); // "1.0.0"
```

Se mueve independientemente de la versión del plugin: sólo cambia cuando cambia
el contrato. Ver [SemVer](../versioning/semver.md).

---

## `homlity_is_version_supported(string $minimum): bool`

Si el plugin cargado es al menos `$minimum`.

**Desde.** 2.8.0

| Parámetro | Tipo | Descripción |
| --- | --- | --- |
| `$minimum` | `string` | Versión mínima aceptable, `'2.8.0'` |

Devuelve `false` si el plugin no está cargado, y también si `$minimum` está
vacío: preguntar por «ninguna versión» es un error de quien pregunta, y
responder que sí dejaría pasar una extensión que no declaró nada.

```php
if (!homlity_is_version_supported('2.8.0')) {
    add_action('admin_notices', 'mi_plugin_aviso_version');
    return;
}
```

La comparación es por número de versión, no alfabética: `2.10.0` es posterior a
`2.9.0`.

---

## `homlity_register_extension(ExtensionInterface $extension): bool`

Registra una extensión. Equivale a
`Homlity::extensions()->register($extension)`.

**Desde.** 2.8.0

| Parámetro | Tipo | Descripción |
| --- | --- | --- |
| `$extension` | `ExtensionInterface` | La extensión a registrar |

Devuelve `true` si se aceptó. `false` si se rechazó — los motivos están en
`homlity_extensions()->failures()` y viajan en `homlity/extension/failed`.
Nunca lanza.

```php
add_action('homlity/extensions/register', function () {
    homlity_register_extension(new MiIntegracion());
});
```

Llamarla dentro de `homlity/extensions/register` es lo esperado. Antes queda
en cola; después arranca en el acto.

---

## `homlity_extensions(): ExtensionRegistry`

El registro de extensiones. Equivale a `Homlity::extensions()`.

**Desde.** 2.8.0

```php
add_action('homlity/extensions/registered', function () {
    if (homlity_extensions()->has('otro-crm')) {
        // convivir con la otra integración
    }
});
```

---

## `homlity_properties(): PropertyRepository`

La API de lectura de inmuebles. Equivale a `Homlity::properties()`.

**Desde.** 2.8.0

```php
$property = homlity_properties()->findByCode('VTAP1320041');
```

---

## `homlity_get_property(int $propertyId): ?Property`

Un inmueble por ID. Atajo de `homlity_properties()->find($id)`.

**Desde.** 2.8.0

| Parámetro | Tipo | Descripción |
| --- | --- | --- |
| `$propertyId` | `int` | ID del post |

Devuelve `null` si el post no existe o no es un inmueble.

```php
$property = homlity_get_property(get_the_ID());

if ($property !== null) {
    echo esc_html($property->getLocation()->getCity());
}
```

---

## Helpers frente a clases

Los helpers son un envoltorio delgado sobre `Homlity` y `Api`. Ambas formas son
igual de válidas y estables.

| Helper | Equivalente |
| --- | --- |
| `homlity_version()` | `Homlity::version()` |
| `homlity_api_version()` | `Api::VERSION` |
| `homlity_is_available()` | `Homlity::isAvailable()` |
| `homlity_is_version_supported($v)` | `Homlity::isVersionSupported($v)` |
| `homlity_extensions()` | `Homlity::extensions()` |
| `homlity_register_extension($e)` | `Homlity::extensions()->register($e)` |
| `homlity_properties()` | `Homlity::properties()` |
| `homlity_get_property($id)` | `Homlity::properties()->find($id)` |

Usa los **helpers** donde Homlity puede no existir — el arranque de tu plugin —
porque `function_exists()` es más barato que `class_exists()` con autoload. Usa
las **clases** dentro de `boot()`, donde ya sabes que existe y el IDE te ayuda.

---

## Funciones globales que **no** son API

`homlity_plugin_get_option()`, `homlity_plugin_apply_filters()`,
`homlity_render_simulator()`, `homlity_get_seo_setting()`, `hsg_*()` y demás son
internas, aunque estén en el espacio global. No están documentadas y pueden
desaparecer.
