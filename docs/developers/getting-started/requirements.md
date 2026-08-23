# Requisitos

## Entorno

| | Mínimo | Recomendado |
| --- | --- | --- |
| PHP | 8.0 | 8.2 o superior |
| WordPress | 5.8 | 6.4 o superior |
| Homlity Real Estate | 2.8.0 | la última estable |
| Developer API | 1.0.0 | — |

La Developer API se introdujo en Homlity Real Estate **2.8.0**. En versiones
anteriores no existen ni el namespace `Homlity\Developer\`, ni los hooks con
barra, ni los helpers globales.

---

## Conocimientos

- PHP moderno: namespaces, interfaces, tipado.
- El sistema de hooks de WordPress: `add_action`, `add_filter`, prioridades.
- El ciclo de vida de un plugin: `plugins_loaded`, `init`, hooks de activación.

No necesitas Composer: una extensión puede cargar sus clases con
`require_once`. Tampoco necesitas conocer el interior de Homlity.

---

## Comprobar el entorno desde tu extensión

Los helpers globales existen desde que Homlity carga, pero WordPress **no
garantiza el orden de carga de los plugins**. Si tu plugin se carga antes que
Homlity, la función todavía no existe. Por eso hay dos reglas:

1. Comprueba siempre con `function_exists()` la primera vez.
2. Haz la comprobación en `plugins_loaded` con prioridad **21 o mayor**.

```php
add_action('plugins_loaded', function () {
    if (!function_exists('homlity_is_available') || !homlity_is_available()) {
        add_action('admin_notices', 'mi_plugin_aviso_falta_homlity');
        return;
    }

    if (!homlity_is_version_supported('2.8.0')) {
        add_action('admin_notices', 'mi_plugin_aviso_version');
        return;
    }

    // A partir de aquí la Developer API está disponible.
}, 21);
```

### Por qué prioridad 21

Homlity registra su núcleo en `plugins_loaded` con prioridad 20. Antes de esa
prioridad las clases se autocargan pero el plugin no ha arrancado.

| Prioridad | Qué pasa |
| --- | --- |
| < 20 | Sólo existen las constantes y los helpers globales |
| 20 | Homlity registra sus servicios y dispara `homlity/loaded` |
| **21+** | **Sitio seguro para comprobar y engancharse** |
| 25 | Se abre la ventana `homlity/extensions/register` |
| 30 | Se registran los proveedores de sincronización |

---

## Comprobar el entorno desde fuera de WordPress

En un script de despliegue o de CI:

```bash
wp eval 'echo defined("HOMLITY_PLUGIN_VERSION") ? HOMLITY_PLUGIN_VERSION : "no instalado";'
wp eval 'echo homlity_api_version();'
```

---

## Requisitos declarativos

Si tu extensión implementa `ExtensionInterface`, no tienes que comprobar nada a
mano: los declaras y Homlity los verifica antes de arrancarte.

```php
use Homlity\Developer\Extension\Requirements;

public function getRequirements(): Requirements
{
    return Requirements::create([
        'homlity'   => '2.8.0',
        'api'       => '1.0.0',
        'php'       => '8.1',
        'wordpress' => '6.4',
        'plugins'   => ['woocommerce/woocommerce.php'],
    ]);
}
```

Si algo no se cumple, la extensión no arranca, se dispara
`homlity/extension/failed` con los motivos en español y el sitio sigue
funcionando. Ver [Compatibilidad](../extensions/compatibility.md).

---

## Constantes disponibles

| Constante | Ejemplo | Para qué |
| --- | --- | --- |
| `HOMLITY_PLUGIN_VERSION` | `'2.8.0'` | Versión del plugin |
| `HOMLITY_API_VERSION` | `'1.0.0'` | Versión del contrato público |
| `HOMLITY_PLUGIN_SLUG` | `'homlity-real-estate'` | Slug del plugin |
| `HOMLITY_PLUGIN_PATH` | `/…/homlity-real-estate/` | Ruta en disco |
| `HOMLITY_PLUGIN_URL` | `https://…/plugins/…/` | URL base |
| `HOMLITY_DEVELOPER_NAMESPACE` | `'Homlity\Developer\'` | Namespace público |

`defined('HOMLITY_PLUGIN_VERSION')` es la comprobación más temprana posible:
funciona incluso antes de `plugins_loaded`.
