# Contribuir a Homlity Real Estate

El código vive en <https://github.com/homlity/homlity-plugin>.

Este documento es la versión para desarrolladores. La versión corta está en
[`CONTRIBUTING.md`](../../../CONTRIBUTING.md).

---

## Qué se acepta con más ganas

| | |
| --- | --- |
| **Puntos de extensión** | Si la Developer API no cubre tu caso, la propuesta es bienvenida |
| **Correcciones** | Con una prueba que falle antes y pase después |
| **Documentación** | Especialmente ejemplos que funcionen |
| **Adaptadores de CRM** | Aunque suelen encajar mejor como extensión externa |
| **Traducciones** | En `languages/` |

Qué requiere hablarlo antes en una incidencia:

- refactorizaciones grandes;
- cambios que rompen compatibilidad;
- dependencias nuevas;
- cambios en la estructura de datos.

---

## Preparar el entorno

```bash
git clone https://github.com/homlity/homlity-plugin.git
cd homlity-plugin
composer install
```

Las pruebas **no necesitan una instalación de WordPress**:
`tests/bootstrap.php` define las constantes y `tests/Support/wp-functions.php`
provee los stubs de las funciones que usa el código bajo prueba.

```bash
composer test
# OK (987 tests, 3423 assertions)
```

Para desarrollar contra un WordPress real, clona dentro de
`wp-content/plugins/homlity-real-estate`.

---

## Flujo de trabajo

### 1 · Fork y rama

```bash
git checkout -b fix/precio-con-separadores-de-miles
```

Nombra la rama por lo que hace:

| Prefijo | Para |
| --- | --- |
| `feat/` | Funcionalidad nueva |
| `fix/` | Corrección |
| `docs/` | Documentación |
| `refactor/` | Cambio interno sin efecto observable |
| `test/` | Sólo pruebas |
| `chore/` | Mantenimiento |

### 2 · Escribe la prueba primero

Especialmente en una corrección. Una prueba que falla antes del arreglo es la
demostración de que el arreglo hacía falta.

```php
public function testUnPrecioConSeparadoresSeInterpretaComoNumero(): void
{
    $this->inmueble(10, ['_property_price_rent' => '$ 2.500.000']);

    self::assertSame(2500000.0, $this->repository->find(10)->getRentPrice()->getAmount());
}
```

### 3 · Implementa

### 4 · Ejecuta todo

```bash
composer test
find src includes -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors'
```

### 5 · Commit

---

## Estándares de código

### PHP

- **PHP 8.0** como mínimo. Nada de sintaxis de 8.1+ sin subir el requisito y
  documentarlo.
- **PSR-4** para todo lo que viva en `src/`.
- **`declare(strict_types=1)`** en los archivos nuevos.
- **Tipado** en parámetros y retornos.
- **`if (!defined('ABSPATH')) { exit; }`** al principio de cada archivo.
- **WordPress Coding Standards** donde sea razonable. El plugin usa 4 espacios,
  llaves en línea aparte para clases y métodos, y `camelCase` en `src/` — que
  se aparta de WPCS a propósito, por coherencia con el resto del código.

### Nombres

| Cosa | Convención |
| --- | --- |
| Clases | `PascalCase` |
| Métodos y propiedades | `camelCase` |
| Constantes | `UPPER_SNAKE_CASE` |
| Hooks públicos | `homlity/dominio/evento` |
| Funciones globales | `homlity_snake_case` |
| Opciones | `homlity_` o `homlity_plugin_` |
| Metadatos | `_property_`, `_consignment_` |

### Comentarios

Los comentarios explican **por qué**, no qué. El código ya dice qué hace.

```php
// ✗
// Convierte a entero.
$postId = (int) $saved;

// ✓
// Sin el `(int)` a propósito: con él, un WP_Error se convertía en 1 y la
// comprobación de error de abajo no llegaba a dispararse nunca.
$saved = wp_insert_post($payload, true);
```

### PHPDoc

Obligatorio en todo lo público:

```php
/**
 * Descripción de una línea.
 *
 * Explicación más larga cuando haga falta.
 *
 * @since 2.9.0
 *
 * @param  string $code Para qué sirve.
 * @return Property|null Qué devuelve, y cuándo devuelve null.
 * @throws RuntimeException Cuándo lanza.
 */
```

Marca `@internal` lo que sea público por necesidad técnica pero no forme parte
del contrato.

---

## Commits

[Conventional Commits](https://www.conventionalcommits.org/es/).

```
<tipo>(<ámbito>): <resumen en imperativo>

<cuerpo: por qué, no qué>

<pie: Refs #123>
```

Tipos: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `perf`, `build`.

```
fix(developer-api): interpretar los precios con separadores de miles

Money::fromMeta() quitaba todo menos dígitos y puntos y casteaba a float,
así que "$ 2.500.000" se convertía en 2.5. Los CRM mandan los precios en
notación local y ninguno lo hacía en el formato que la función esperaba.

Ahora un separador solitario seguido de exactamente tres dígitos se lee
como separador de miles: un precio con tres decimales no existe.

Refs #142
```

Un cambio que rompe compatibilidad lleva `!` y un pie `BREAKING CHANGE:`.

---

## Pruebas

Ver [Pull requests](pull-requests.md) para los criterios de revisión.

### Dónde van

```
tests/Unit/<Namespace>/<Clase>Test.php
```

### Cómo se escriben en este repositorio

Los nombres de los tests están en castellano y describen **el comportamiento**,
no el método:

```php
public function testUnInmuebleRetiradoDelMercadoNoEstaDisponible(): void
public function testReenviarElMismoRegistroDisparaConUnDiffVacio(): void
```

Y la clase lleva un docblock que explica **qué se rompe si esto falla**:

```php
/**
 * La escritura de un inmueble que llega de un CRM.
 *
 * Es el punto de entrada de todo lo que sincroniza el plugin, y el que más
 * daño puede hacer: si la deduplicación falla, cada pase crea un inmueble
 * nuevo y el catálogo se llena de copias.
 */
```

No es decoración: es lo que le dice al siguiente si puede tocar ese código.

### Qué hay que probar

- La API pública, siempre.
- Los casos límite: vacío, cero, `null`, formato inesperado.
- Los caminos de error.
- Que los datos privados no salen.

---

## Documentación

Un cambio en la API pública **no está terminado** sin su documentación:

| Cambias | Actualiza |
| --- | --- |
| Una action | `docs/developers/api/actions.md` |
| Un filter | `docs/developers/api/filters.md` |
| Una clase pública | `docs/developers/api/classes.md` |
| Una interfaz | `docs/developers/api/interfaces.md` |
| Un helper | `docs/developers/api/helpers.md` |
| El modelo `Property` | `docs/developers/models/property.md` |
| Cualquiera de los anteriores | `CHANGELOG.md` |
| Un hook nuevo | `Support\Hooks` — y su constante |

---

## Seguridad

**No abras una incidencia pública para una vulnerabilidad.** Ver
[Seguridad](security.md) y [`SECURITY.md`](../../../SECURITY.md).

---

## Licencia

Al contribuir aceptas que tu aportación se distribuya bajo **GPLv2 o
posterior**, la licencia del plugin.
