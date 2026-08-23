# Compatibilidad

Cómo declarar qué necesitas, qué garantiza Homlity a cambio, y cómo sobrevivir
a las actualizaciones.

---

## Lo que Homlity garantiza

Dentro de una misma versión mayor de la Developer API (hoy la **1.x**):

| | |
| --- | --- |
| ✓ | Un hook público no cambia de nombre ni desaparece |
| ✓ | Un hook no pierde parámetros ni cambia su orden |
| ✓ | Un método público no cambia de firma ni de tipo de retorno |
| ✓ | Las claves de `Property::toArray()` no cambian de significado |
| ✓ | Nada se elimina sin pasar antes por deprecación |
| ⚠ | Un hook **puede ganar parámetros al final** |
| ⚠ | Un modelo **puede ganar métodos** |
| ⚠ | Pueden aparecer hooks y clases nuevos |

Los dos avisos son la razón de dos reglas prácticas: declara siempre el número
de argumentos en `add_action`, y no implementes interfaces públicas fuera de las
pensadas para implementar.

**Fuera de la garantía:** todo lo que no está bajo `Homlity\Developer\`. Los
hooks con guion bajo, las clases `Homlity\PluginInmobiliario\*`, los metadatos
`_property_*`, las tablas propias. Pueden cambiar en una versión de parche.

---

## Declarar requisitos

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

Todas las claves son opcionales. `Requirements::none()` si tu extensión funciona
en cualquier instalación que pueda ejecutar Homlity.

### `homlity` frente a `api`

| Declara | Cuando dependes de |
| --- | --- |
| `homlity` | Una funcionalidad del plugin: una taxonomía, un shortcode, una pantalla |
| `api` | Un hook, una clase o un método de la Developer API |

Prefiere `api`: es más preciso y no te ata a la versión del plugin. Si sólo usas
`homlity/property/updated` y el modelo `Property`, lo que necesitas es
`'api' => '1.0.0'`, no una versión concreta del plugin.

### Las claves desconocidas se ignoran

Deliberado. Una extensión escrita contra una versión posterior de la API puede
declarar un requisito que esta no entiende; ignorarlo la deja arrancar en vez de
bloquearla sin motivo.

---

## Detección progresiva

Cuando quieras aprovechar algo nuevo sin exigirlo:

```php
public function boot(): void
{
    add_action('homlity/property/updated', [$this, 'onUpdated'], 10, 3);

    // Hook añadido en una versión posterior.
    if (homlity_is_version_supported('2.9.0')) {
        add_action('homlity/property/archived', [$this, 'onArchived'], 10, 2);
    }
}
```

Para métodos nuevos de un modelo:

```php
$energia = method_exists($property, 'getEnergyRating')
    ? $property->getEnergyRating()
    : null;
```

---

## Convivir con otras extensiones

```php
add_action('homlity/extensions/registered', function ($registry) {
    if ($registry->has('homlity-analytics')) {
        // Ya hay analítica: no aporto la mía.
        remove_action('homlity/property/created', [$this, 'track']);
    }
});
```

Dos reglas:

1. **Nunca desregistres una extensión ajena.** Si crees que hay conflicto,
   avisa al administrador; no decidas por él.
2. **Nunca asumas que eres la única.** Otro plugin puede filtrar los mismos
   datos antes o después que tú.

Sobre las prioridades: si tu filtro debe correr después de todos los demás, usa
una prioridad alta (`99`), no `PHP_INT_MAX`. Deja sitio.

---

## Matriz de compatibilidad

| Homlity Real Estate | Developer API | Notas |
| --- | --- | --- |
| < 2.8.0 | — | No existe |
| 2.8.x | 1.0.0 | Versión inicial |

Manténla en tu README y actualízala cuando pruebes con una versión nueva.

---

## Probar contra varias versiones

```bash
# Instalar una versión concreta
wp plugin install https://github.com/homlity/homlity-plugin/archive/refs/tags/v2.8.0.zip --force

# Comprobar qué hay
wp eval 'echo homlity_version() . " / API " . homlity_api_version();'

# Y que tu extensión arrancó
wp eval 'var_dump(homlity_extensions()->has("acme-mi-crm"));'
```

En CI, una matriz de PHP × WordPress × Homlity con la combinación mínima que
declaras y la última de cada una.

---

## Cuando Homlity deprecia algo

1. Aparece un aviso en el log con `WP_DEBUG` activo, con el reemplazo indicado.
2. Se documenta en [Deprecaciones](../versioning/deprecations.md) y en el
   changelog.
3. Sigue funcionando durante todo el ciclo de la versión mayor.
4. Se elimina en la siguiente versión mayor.

Tienes al menos un ciclo mayor completo para migrar. Desarrolla con `WP_DEBUG`
activo y te enterarás al escribir el código, no al leer un ticket de soporte.

---

## Diagnóstico

| Síntoma | Causa probable | Comprobación |
| --- | --- | --- |
| `Call to undefined function homlity_*` | Tu plugin cargó antes que Homlity | Prioridad ≥ 21 y `function_exists()` |
| `Class 'Homlity\Developer\…' not found` | Homlity inactivo o anterior a 2.8.0 | `homlity_is_version_supported('2.8.0')` |
| La extensión no arranca | Requisitos incumplidos | `homlity_extensions()->failures()` |
| Un hook no dispara | Nombre mal escrito, o vía no cubierta | Usa las constantes de `Hooks`; ver [cobertura](../api/actions.md#cobertura) |
| Un callback recibe menos argumentos | No declaraste cuántos aceptas | Cuarto parámetro de `add_action` |
| Funcionaba y dejó de funcionar tras actualizar | Dependías de algo interno | Revisa el [changelog](../versioning/changelog-policy.md) |
