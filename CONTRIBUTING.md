# Contribuir a Homlity Real Estate

Gracias por querer aportar. Esta es la versión corta; la guía completa está en
[`docs/developers/open-source/contributing.md`](docs/developers/open-source/contributing.md).

**¿Una vulnerabilidad de seguridad?** No abras una incidencia pública. Ver
[SECURITY.md](SECURITY.md).

---

## Empezar

```bash
# 1. Haz un fork en https://github.com/homlity/homlity-plugin

# 2. Clónalo
git clone https://github.com/TU-USUARIO/homlity-plugin.git
cd homlity-plugin

# 3. Instala las dependencias
composer install

# 4. Comprueba que las pruebas pasan antes de tocar nada
composer test
```

Las pruebas **no necesitan una instalación de WordPress**: el arranque define
las constantes y provee stubs de las funciones del núcleo.

---

## Ramas

```bash
git checkout -b fix/precio-con-separadores-de-miles
```

| Prefijo | Para |
| --- | --- |
| `feat/` | Funcionalidad nueva |
| `fix/` | Corrección |
| `docs/` | Documentación |
| `refactor/` | Cambio interno sin efecto observable |
| `test/` | Sólo pruebas |
| `chore/` | Mantenimiento |

Trabaja sobre `main`. No hagas commits directos ahí: siempre una rama y un PR.

---

## Estándares de código

- **PHP 8.0** como mínimo.
- **PSR-4** en `src/`, `declare(strict_types=1)` en archivos nuevos.
- **Tipado** en parámetros y retornos.
- **`if (!defined('ABSPATH')) { exit; }`** al principio de cada archivo.
- **PHPDoc completo** en todo lo público, con `@since`.
- Los comentarios explican **por qué**, no qué.
- El código nuevo se parece al que lo rodea: misma densidad de comentarios,
  mismos nombres, mismo idioma.

### La frontera pública

| | |
| --- | --- |
| `Homlity\Developer\` | **API pública.** Documentada, probada, sujeta a SemVer |
| `Homlity\PluginInmobiliario\` | Interno. Puede cambiar en cualquier versión |
| Hooks `homlity/dominio/evento` | **Públicos** |
| Hooks `homlity_lo_que_sea` | Heredados. Funcionan, pero sin garantía |

Un elemento público nuevo es un compromiso de mantenimiento para siempre. Si
propones uno, explica el caso de uso real.

---

## Commits

[Conventional Commits](https://www.conventionalcommits.org/es/).

```
<tipo>(<ámbito>): <resumen en imperativo>

<por qué, no qué>

Refs #123
```

Tipos: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `perf`, `build`.

Un cambio que rompe compatibilidad lleva `!` y un pie `BREAKING CHANGE:`.

```
fix(developer-api): interpretar los precios con separadores de miles

Money::fromMeta() quitaba todo menos dígitos y puntos y casteaba a float,
así que "$ 2.500.000" se convertía en 2.5. Los CRM mandan los precios en
notación local y ninguno lo hacía en el formato que la función esperaba.

Refs #142
```

---

## Pruebas

Toda corrección lleva una prueba que **falla sin el arreglo**. Toda
funcionalidad nueva lleva pruebas de su comportamiento y de sus casos límite.

```bash
composer test                          # todo
vendor/bin/phpunit --filter MiTest     # una clase
```

Los tests de este repositorio se nombran en castellano y describen el
**comportamiento**, no el método:

```php
public function testUnInmuebleRetiradoDelMercadoNoEstaDisponible(): void
```

Y la clase lleva un docblock que explica qué se rompe si eso falla. No es
decoración: es lo que le dice al siguiente si puede tocar ese código.

**No rompas las pruebas existentes.** Si un cambio tuyo hace fallar una,
entiende por qué antes de tocarla: casi siempre la prueba tiene razón.

---

## Pull requests

Antes de abrirlo:

- [ ] `composer test` pasa entero.
- [ ] Hay una prueba que falla sin tu cambio.
- [ ] `php -l` limpio en los archivos tocados.
- [ ] No queda `var_dump`, `print_r`, `dd(`, `dump(`, `die(` ni `TODO` sin
      ticket.
- [ ] `vendor/` y `node_modules/` no están en el diff.
- [ ] La documentación pública está actualizada.
- [ ] Hay entrada en `CHANGELOG.md`, si el cambio es observable.

En la descripción, empieza por **por qué**. El diff ya dice qué.

Ver [Pull requests](docs/developers/open-source/pull-requests.md) para los
criterios de revisión completos.

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
| Un hook nuevo | Añade su constante en `Support\Hooks` |
| Cualquiera de los anteriores | `CHANGELOG.md` |

---

## Compatibilidad hacia atrás

Es la regla que más pesa en una revisión. Antes de cambiar algo que ya existe:

1. ¿Puede estar usándolo alguien de fuera?
2. ¿Hay referencias en el repositorio?
3. ¿Hace falta un alias o una deprecación?
4. ¿Está documentada la migración?

Casi siempre existe una forma que no rompe: añadir un parámetro opcional en vez
de cambiar la firma, añadir un método en vez de renombrar, publicar un hook
nuevo y mantener el viejo.

Ver [Deprecaciones](docs/developers/versioning/deprecations.md).

---

## Seguridad

**No abras una incidencia pública para una vulnerabilidad.** Ver
[SECURITY.md](SECURITY.md).

---

## Licencia

Al contribuir aceptas que tu aportación se distribuya bajo **GPLv2 o
posterior**, la licencia del plugin.
