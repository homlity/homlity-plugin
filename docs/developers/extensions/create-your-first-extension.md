# Crear tu primera extensión

Guía completa. Al terminar tendrás un plugin de WordPress instalable que se
registra en Homlity, escucha los cambios de los inmuebles y modifica lo que
Homlity guarda.

Todo el código está aquí; nada queda «como ejercicio».

**Qué necesitas:** WordPress 5.8+, PHP 8.0+, Homlity Real Estate 2.8.0+ activo,
y acceso a `wp-content/plugins/`.

---

## 1. Crear la carpeta

```bash
cd wp-content/plugins
mkdir -p mi-crm-homlity/src
cd mi-crm-homlity
```

```
mi-crm-homlity/
├── mi-crm-homlity.php
└── src/
    └── Plugin.php
```

---

## 2. El archivo principal

`mi-crm-homlity/mi-crm-homlity.php`

```php
<?php

declare(strict_types=1);

/**
 * Plugin Name:       Mi CRM para Homlity
 * Description:       Integra Mi CRM con Homlity Real Estate.
 * Version:           1.0.0
 * Author:            Acme
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Text Domain:       mi-crm-homlity
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

const MI_CRM_VERSION          = '1.0.0';
const MI_CRM_REQUIERE_HOMLITY = '2.8.0';

require_once __DIR__ . '/src/Plugin.php';
```

### La metadata de WordPress

`Plugin Name` es lo único obligatorio, pero declara siempre `Requires PHP` y
`Requires at least`: WordPress impide la activación si el servidor no llega, en
vez de dejar que el sitio se rompa después.

---

## 3. Comprobar Homlity

Añade al final del archivo principal:

```php
/**
 * Arranque.
 *
 * Prioridad 21: Homlity registra su núcleo en `plugins_loaded` con prioridad
 * 20, así que a partir de la 21 la Developer API existe.
 */
add_action('plugins_loaded', static function (): void {
    // WordPress no garantiza el orden de carga de los plugins: el helper
    // puede no existir todavía aunque Homlity esté instalado.
    if (!function_exists('homlity_is_available') || !homlity_is_available()) {
        add_action('admin_notices', 'mi_crm_aviso_falta_homlity');
        return;
    }

    // Esta comprobación va aquí y no dentro de la clase: si la interfaz
    // ExtensionInterface no existe en esta versión de Homlity, instanciar
    // la clase sería un fatal error.
    if (!homlity_is_version_supported(MI_CRM_REQUIERE_HOMLITY)) {
        add_action('admin_notices', 'mi_crm_aviso_version');
        return;
    }

    // Paso 5.
}, 21);

function mi_crm_aviso_falta_homlity(): void
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    printf(
        '<div class="notice notice-warning"><p>%s</p></div>',
        esc_html__('Mi CRM para Homlity necesita el plugin Homlity Real Estate activo.', 'mi-crm-homlity')
    );
}

function mi_crm_aviso_version(): void
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    printf(
        '<div class="notice notice-warning"><p>%s</p></div>',
        esc_html(sprintf(
            /* translators: 1: versión requerida, 2: versión instalada. */
            __('Mi CRM para Homlity necesita Homlity Real Estate %1$s o superior. Instalada: %2$s.', 'mi-crm-homlity'),
            MI_CRM_REQUIERE_HOMLITY,
            homlity_version() !== '' ? homlity_version() : '—'
        ))
    );
}
```

Fíjate en que **el sitio nunca se cae**: si falta Homlity o es antiguo, tu
plugin se calla y muestra un aviso.

---

## 4. La clase de la extensión

`mi-crm-homlity/src/Plugin.php`

```php
<?php

declare(strict_types=1);

namespace MiCrm;

use Homlity\Developer\Contracts\ExtensionInterface;
use Homlity\Developer\Events\PropertyChanges;
use Homlity\Developer\Events\PropertyContext;
use Homlity\Developer\Extension\Requirements;
use Homlity\Developer\Models\Property;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin implements ExtensionInterface
{
    public function getName(): string
    {
        return __('Mi CRM para Homlity', 'mi-crm-homlity');
    }

    public function getSlug(): string
    {
        // Prefijado con el vendor: dos extensiones no pueden compartir slug.
        return 'acme-mi-crm';
    }

    public function getVersion(): string
    {
        return MI_CRM_VERSION;
    }

    public function getRequirements(): Requirements
    {
        return Requirements::create([
            'homlity' => MI_CRM_REQUIERE_HOMLITY,
            'api'     => '1.0.0',
            'php'     => '8.0',
        ]);
    }

    /**
     * Homlity llama aquí una vez, cuando ya comprobó los requisitos.
     * Aquí sólo se engancha; el trabajo va en `init` o después.
     */
    public function boot(): void
    {
        // Paso 6 y 7.
    }
}
```

---

## 5. Registrar la extensión

Sustituye el comentario `// Paso 5.` del archivo principal por:

```php
    add_action('homlity/extensions/register', static function ($registry): void {
        $registry->register(new MiCrm\Plugin());
    });
```

Equivalente, si prefieres el helper global:

```php
    add_action('homlity/extensions/register', static function (): void {
        homlity_register_extension(new MiCrm\Plugin());
    });
```

**Comprueba que funciona.** Activa el plugin y ejecuta:

```bash
wp eval 'print_r(array_keys(Homlity\Developer\Homlity::extensions()->all()));'
```

Debe aparecer `acme-mi-crm`. Si no está, mira los rechazos:

```bash
wp eval 'print_r(Homlity\Developer\Homlity::extensions()->failures());'
```

---

## 6. Escuchar un hook

En `boot()`:

```php
    public function boot(): void
    {
        add_action('homlity/property/created', [$this, 'onCreated'], 10, 2);
        add_action('homlity/property/updated', [$this, 'onUpdated'], 10, 3);
    }

    public function onCreated(Property $property, PropertyContext $context): void
    {
        $this->enviar('created', $property, ['origen' => $context->getOrigin()]);
    }

    public function onUpdated(Property $property, PropertyChanges $changes, PropertyContext $context): void
    {
        // Un CRM que reenvía un registro idéntico dispara una actualización
        // igual: sin esto, cada pase de sincronización sería una llamada
        // al sistema externo que no cambia nada.
        if ($changes->isEmpty()) {
            return;
        }

        // Y si el cambio lo provocó nuestro propio CRM, devolvérselo es un bucle.
        if ($context->getSource() === 'acme-mi-crm') {
            return;
        }

        // Sólo lo comercial merece un viaje al exterior.
        if (!$changes->hasGroup('pricing') && !$changes->has('media.gallery')) {
            return;
        }

        $this->enviar('updated', $property, ['campos' => $changes->fields()]);
    }
```

Y el método que hace el trabajo:

```php
    /**
     * En una integración real, aquí iría la llamada HTTP al CRM.
     * Aquí se deja constancia en una opción para poder inspeccionarlo.
     *
     * @param array<string,mixed> $extra
     */
    private function enviar(string $evento, Property $property, array $extra = []): void
    {
        // Una excepción que salga de un callback rompe la petición que lo
        // invocó — que puede ser el guardado de un inmueble.
        try {
            $precio  = $property->getPrice();
            $entries = (array) get_option('mi_crm_log', []);

            $entries[] = array_merge([
                'evento'   => $evento,
                'fecha'    => gmdate('c'),
                'codigo'   => $property->getCode(),
                'titulo'   => $property->getTitle(),
                'precio'   => $precio?->getAmount(),
                'moneda'   => $precio?->getCurrency(),
                'ciudad'   => $property->getLocation()->getCity(),
                'fotos'    => count($property->getImages()),
            ], $extra);

            update_option('mi_crm_log', array_slice($entries, -50), false);
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[mi-crm] ' . $e->getMessage());
            }
        }
    }
```

Añade los `use` que faltan al principio del archivo:
`PropertyChanges`, `PropertyContext`, `Property`, `Throwable`.

---

## 7. Usar un filtro

Añade en `boot()`:

```php
        add_filter('homlity/property/normalized', [$this, 'marcarImportados'], 10, 2);
```

Y el método:

```php
    /**
     * Marca cada inmueble que llega de un CRM con quién y cuándo lo importó.
     *
     * @param array<string,mixed> $normalized
     * @return array<string,mixed>
     */
    public function marcarImportados(array $normalized, string $source): array
    {
        if ($source === '') {
            return $normalized;
        }

        $normalized['external']['raw']['importado_por'] = $this->getSlug();
        $normalized['external']['raw']['importado_en']  = gmdate('c');

        return $normalized;
    }
```

---

## 8. Probar

### Activar

```bash
wp plugin activate mi-crm-homlity
```

### Comprobar el registro

```bash
wp eval 'print_r(array_keys(Homlity\Developer\Homlity::extensions()->all()));'
```

### Disparar un evento

En wp-admin, **Inmuebles → Añadir nuevo**, rellena y publica. Después:

```bash
wp option get mi_crm_log --format=json
```

Deberías ver algo así:

```json
[{"evento":"created","fecha":"2026-08-23T14:05:11+00:00","codigo":"VTAP1320041",
  "titulo":"Apartamento en El Poblado","precio":450000000,"moneda":"COP",
  "ciudad":"Medellín","fotos":6,"origen":"admin"}]
```

Cambia el precio y guarda: aparece una entrada `updated` con los campos que
cambiaron. Cambia sólo la descripción y **no** aparece ninguna, porque el
filtrado del paso 6 lo descarta a propósito.

### Espiar todos los eventos

Mientras desarrollas, en `wp-content/mu-plugins/homlity-debug.php`:

```php
<?php
foreach (\Homlity\Developer\Support\Hooks::actions() as $hook) {
    add_action($hook, function (...$args) use ($hook) {
        error_log(sprintf('[homlity] %s (%d args)', $hook, count($args)));
    }, 1, 5);
}
```

---

## 9. Manejar la incompatibilidad

Ya lo hiciste en el paso 3, pero compruébalo de verdad:

```bash
wp plugin deactivate homlity-real-estate
```

Entra en wp-admin: debe salir el aviso, **no** un error fatal. Si sale un error,
tienes una llamada a la Developer API fuera de la comprobación.

Para probar el caso de versión insuficiente, sube temporalmente
`MI_CRM_REQUIERE_HOMLITY` a `'99.0.0'` y recarga: sale el otro aviso.

Y el tercer caso, el que gestiona Homlity por ti — declara en
`getRequirements()` algo imposible:

```php
'php' => '99.0',
```

La extensión no arranca, no hay error, y el motivo está en:

```bash
wp eval 'print_r(Homlity\Developer\Homlity::extensions()->failures());'
```

```
Array ( [acme-mi-crm] => Array ( [0] => Requiere PHP 99.0 o superior (en ejecución: 8.2.29). ) )
```

Para mostrarlo al usuario, engánchate a `homlity/extension/failed`:

```php
add_action('homlity/extension/failed', function ($extension, $reasons, $slug) {
    if ($slug !== 'acme-mi-crm') {
        return;
    }

    add_action('admin_notices', function () use ($reasons) {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html('Mi CRM no pudo arrancar: ' . implode(' ', $reasons))
        );
    });
}, 10, 3);
```

Ponlo en el archivo principal, dentro del `plugins_loaded` del paso 3 y **antes**
del `add_action('homlity/extensions/register', …)`.

---

## 10. Empaquetar

```bash
cd wp-content/plugins/mi-crm-homlity
zip -r ../mi-crm-homlity.zip . \
    -x '*.git*' -x 'node_modules/*' -x 'tests/*' -x '*.DS_Store'
```

Si usas Composer, instala antes sólo lo de producción:

```bash
composer install --no-dev --optimize-autoloader
```

El ZIP resultante se instala desde **Plugins → Añadir nuevo → Subir plugin**.

---

## Lo que has construido

- Un plugin de WordPress independiente.
- Que comprueba que Homlity está y es suficientemente nuevo.
- Que se registra como extensión con requisitos declarados.
- Que reacciona a la creación y actualización de inmuebles.
- Que filtra el ruido para no llamar al exterior sin motivo.
- Que evita el bucle de sincronización.
- Que modifica lo que Homlity guarda.
- Que no rompe el sitio cuando algo falta.

Sin tocar una sola línea de `homlity-plugin`.

---

## Siguientes pasos

- [Buenas prácticas](best-practices.md) — los errores que se cometen siempre.
- [Ciclo de vida](extension-lifecycle.md) — qué ocurre y cuándo.
- [El modelo Property](../models/property.md) — todo lo que puedes leer.
- [Actions](../api/actions.md) y [Filters](../api/filters.md) — la referencia.
- [Compatible con Homlity](compatible-with-homlity.md) — si vas a distribuirla.

El código completo y funcional de esta guía está en
[`docs/examples/basic-extension/`](../../examples/basic-extension/homlity-example-extension/README.md),
y hay pruebas automatizadas que lo ejecutan en cada cambio del plugin, para que
no se quede atrás.
