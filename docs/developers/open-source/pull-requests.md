# Pull requests

Cómo se revisa un cambio en <https://github.com/homlity/homlity-plugin>.

---

## Antes de abrirlo

- [ ] `composer test` pasa entero.
- [ ] Hay una prueba nueva que **falla sin tu cambio**.
- [ ] `php -l` limpio en todos los archivos tocados.
- [ ] No queda `var_dump`, `print_r`, `dd(`, `dump(`, `die(`, `error_log` de
      depuración ni `TODO` sin ticket.
- [ ] `vendor/` y `node_modules/` no están en el diff.
- [ ] La documentación pública está actualizada.
- [ ] `CHANGELOG.md` tiene una entrada, si el cambio es observable.
- [ ] Los commits siguen Conventional Commits.

---

## La descripción

```markdown
## Qué hace

Money::fromMeta() interpretaba «$ 2.500.000» como 2.5.

## Por qué

Quitaba todo menos dígitos y puntos y casteaba a float. Los CRM mandan los
precios en notación local: en Colombia el punto es separador de miles.

## Cómo

Un separador solitario seguido de exactamente tres dígitos se lee como
separador de miles; con dos separadores distintos, el de más a la derecha es
el decimal. Un precio con tres decimales no existe, pero uno escrito «2.500» sí.

## Pruebas

`PropertyModelTest::notacionesDePrecio` cubre once notaciones: entero plano,
miles con punto y con coma, símbolos, decimales anglosajones y europeos,
vacío, sin dígitos y cero.

## Compatibilidad

Ninguna ruptura. Los precios que ya se leían bien siguen leyéndose igual;
sólo cambia el resultado de los que se leían mal.

Refs #142
```

Lo que se lee primero es **por qué**. El diff ya dice qué.

---

## Qué se revisa

### 1 · Compatibilidad hacia atrás

La pregunta que más pesa. Antes de cambiar algo existente:

- ¿Puede estar usándolo alguien de fuera?
- ¿Hay referencias en el repositorio?
- ¿Hace falta un alias o una deprecación?

Un cambio que rompe compatibilidad necesita una justificación extraordinaria,
una ruta de migración documentada, y una entrada en el changelog. La mayoría de
las veces existe una forma que no rompe: añadir un parámetro opcional en vez de
cambiar la firma, añadir un método en vez de renombrar el que hay, publicar un
hook nuevo y mantener el viejo.

### 2 · Superficie pública

Un elemento público nuevo es un compromiso de mantenimiento para siempre. Se
pregunta:

- ¿Hay un caso de uso real, o es «por si acaso»?
- ¿Se puede resolver con algo que ya existe?
- ¿Es el diseño correcto, o el primero que se nos ocurrió?
- ¿Está bajo `Homlity\Developer\` si es público?
- ¿Lleva `@since` y PHPDoc completo?
- ¿Está en `Support\Hooks` si es un hook?

### 3 · Seguridad

- ¿Se sanea la entrada?
- ¿Se escapa la salida?
- ¿Se comprueban capacidades y nonces?
- ¿Hay consultas SQL sin preparar?
- ¿Algún hook nuevo publica secretos o datos personales?

Ver [Seguridad](security.md).

### 4 · Pruebas

- ¿Falla sin el cambio?
- ¿Cubre los casos límite?
- ¿Prueba comportamiento, no implementación?
- ¿El nombre dice qué comportamiento protege?

### 5 · Legibilidad

El código nuevo debe parecerse al que lo rodea: misma densidad de comentarios,
mismos nombres, mismos idiomas. Un archivo con comentarios en castellano no
recibe comentarios en inglés, y al revés.

---

## Lo que hace que un PR se acepte rápido

**Uno pequeño y con un solo propósito.** Un PR que arregla un error y de paso
reordena imports y renombra tres variables tarda cinco veces más en revisarse.

**Una prueba que demuestra el problema.** Convierte «creo que esto está mal» en
«esto está mal, mira».

**Una descripción que explica el porqué.** Quien revisa no tiene el contexto que
tú tenías al encontrarlo.

**Documentación al día.** Un cambio en la API pública sin su documentación está
a medias.

---

## Lo que hace que se atasque

**Refactorizaciones grandes sin hablarlo antes.** Abre una incidencia primero.

**Cambios que rompen compatibilidad sin justificación.**

**API pública «por si acaso».** Sin un caso de uso real, se pide esperar a que
lo haya.

**Cambios de formato mezclados con cambios de lógica.** Van en PR separados.

**Dependencias nuevas sin discutir.** Cada dependencia es peso en cada
instalación.

---

## El proceso

1. **Automático**: sintaxis, pruebas, validación de `composer.json`.
2. **Revisión**: alguien lee el cambio y comenta.
3. **Iteración**: respondes o ajustas. Los comentarios son sobre el código, no
   sobre quien lo escribió.
4. **Aprobación y merge**: en `main`, en squash, con el mensaje del PR.

---

## Después del merge

- Entra en el `CHANGELOG.md` de la siguiente versión.
- Si toca la API pública, la documentación se publica en
  <https://homlity.com/desarrolladores/>.
- Si es una corrección de seguridad, se coordina la divulgación antes de
  publicar los detalles.

---

## Licencia

Al abrir un PR aceptas que tu aportación se distribuya bajo **GPLv2 o
posterior**.
