# Reportar incidencias

<https://github.com/homlity/homlity-plugin/issues>

**Las vulnerabilidades de seguridad no van aquí.** Ver
[Seguridad](security.md).

---

## Antes de abrir una

1. **Busca**: puede estar ya reportado, o resuelto en una versión más nueva.
2. **Actualiza**: prueba con la última versión estable.
3. **Aísla**: desactiva las demás extensiones y cambia a un tema por defecto.
   Si el problema desaparece, es un conflicto, y saberlo ahorra medio hilo.
4. **Reproduce**: si sólo pasó una vez y no sabes cómo repetirlo, dilo — pero
   dilo, en vez de omitirlo.

---

## Un error

Lo que hace útil un reporte es que alguien pueda reproducirlo sin preguntarte
nada.

```markdown
### Qué ocurre

`Property::getSalePrice()` devuelve 2.5 en vez de 2500000 cuando el precio
está guardado como «2.500.000».

### Cómo reproducirlo

1. Crear un inmueble.
2. Guardar `_property_price_sale` con el valor `2.500.000`.
3. `wp eval 'var_dump(homlity_get_property(128)->getSalePrice()->getAmount());'`

### Qué esperaba

`float(2500000)`

### Qué obtengo

`float(2.5)`

### Entorno

- Homlity Real Estate: 2.8.0
- Developer API: 1.0.0
- WordPress: 6.7.1
- PHP: 8.2.29
- Tema: Twenty Twenty-Four
- Otros plugins activos: ninguno

### Notas

Ocurre con cualquier importe que use el punto como separador de miles. Los
CRM colombianos lo usan siempre.
```

### Los datos del entorno, rápido

```bash
wp eval '
printf("Homlity: %s%sAPI: %s%sWordPress: %s%sPHP: %s%s",
    homlity_version(), PHP_EOL,
    homlity_api_version(), PHP_EOL,
    get_bloginfo("version"), PHP_EOL,
    PHP_VERSION, PHP_EOL);'

wp plugin list --status=active --field=name
```

---

## Un punto de extensión que falta

El tipo de incidencia más útil que puedes abrir, porque describe un caso real
que la API no cubre.

```markdown
### Qué necesito hacer

Registrar en un ERP externo cada vez que un inmueble se marca como vendido.

### Por qué no puedo hoy

`homlity/property/updated` avisa de que cambió `availability.status`, pero para
saber si pasó a «vendido» tengo que leer `_property_status`, que es interno.

### Qué he probado

- `homlity/property/status_changed` — sólo cubre el estado del post, no la
  disponibilidad comercial.
- Leer el metadato directamente — funciona, pero depende de algo interno.

### Qué propongo

Una action `homlity/property/availability_changed` con
`(Property $property, string $new, string $old)`.

O, más simple: exponer `Property::getAvailabilityStatus(): string`.
```

Di **qué necesitas conseguir**, no sólo qué API quieres. A menudo hay una forma
ya soportada que no habías visto, o el caso real sugiere un punto de extensión
mejor que el propuesto.

---

## Un problema de documentación

Con la ruta del archivo y qué está mal:

```markdown
`docs/developers/api/actions.md` dice que `homlity/property/deleted` recibe
`(Property, int)`, pero el ejemplo de más abajo usa tres parámetros.
```

---

## Etiquetas

| Etiqueta | Qué significa |
| --- | --- |
| `bug` | Algo no funciona como está documentado |
| `enhancement` | Funcionalidad nueva |
| `developer-api` | Afecta al contrato público |
| `breaking-change` | Rompe compatibilidad |
| `documentation` | Sólo documentación |
| `needs-repro` | No se ha podido reproducir |
| `wontfix` | Es así a propósito; se explica por qué |

---

## Qué esperar

- **Confirmación** de que se ha leído.
- **Una pregunta**, si falta información.
- **Una decisión**: se acepta, se pospone, o se explica por qué no.

Un `wontfix` siempre lleva explicación. «Es así a propósito» sin decir el
propósito no es una respuesta.

---

## Si además quieres arreglarlo

Aún mejor. Ver [Contribuir](contributing.md) y
[Pull requests](pull-requests.md).
