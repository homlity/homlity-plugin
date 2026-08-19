# Pruebas del plugin

## Requisitos

```bash
composer install
```

No hace falta instalar WordPress ni una base de datos: el bootstrap define las
constantes del plugin y carga stubs de las funciones de WordPress.

## Ejecutar

```bash
composer test                 # toda la suite unitaria (vendor/bin/phpunit)
composer test:unit            # igual, seleccionando la suite "unit"
composer test:coverage        # informe HTML en build/coverage (requiere Xdebug o PCOV)
composer test:error-reporting # suite heredada, sin dependencias (tests/error-reporting)

vendor/bin/phpunit --filter CurrencyServiceTest      # una clase
vendor/bin/phpunit tests/Unit/Services               # un directorio
```

## Estructura

```
phpunit.xml.dist               Configuración de PHPUnit (bootstrap, suites, cobertura)
tests/bootstrap.php            Constantes de WP y del plugin + carga de stubs
tests/Support/WpStubs.php      Estado simulado: opciones, post meta, filtros, get_posts…
tests/Support/wp-functions.php Stubs de funciones/clases de WP (get_option, WP_REST_Request…)
tests/Support/TestCase.php     Caso base; reinicia WpStubs antes y después de cada prueba
tests/Unit/<Namespace>/        Pruebas, espejando la estructura de src/
tests/error-reporting/run.php  Suite heredada del reporter (script independiente)
```

## Escribir una prueba nueva

1. Crea el archivo en `tests/Unit/` reproduciendo la ruta de la clase en `src/`
   (`src/Services/Foo.php` → `tests/Unit/Services/FooTest.php`).
2. Extiende `Homlity\PluginInmobiliario\Tests\Support\TestCase`.
3. Prepara el entorno con `WpStubs` y ejecuta la clase bajo prueba:

```php
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class FooTest extends TestCase
{
    public function testAlgo(): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['base_currency' => 'USD']);
        WpStubs::setPost(10, 'Título', 'https://x.test/', ['_property_code' => 'A-1']);
        WpStubs::addFilter('mi_filtro', static fn ($valor) => $valor);
        WpStubs::$postTypes[] = 'whatsapp-accounts';
        WpStubs::$posts[] = [WpStubs::makePost(500, ['meta' => 'valor'])]; // cola de get_posts()

        // ...
    }
}
```

Si la clase bajo prueba usa una función de WordPress que aún no está simulada,
añádela a `tests/Support/wp-functions.php` (siempre dentro de un
`if (!function_exists(...))`) y, si necesita estado, exponlo en `WpStubs`.
