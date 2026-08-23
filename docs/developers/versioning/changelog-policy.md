# Política de changelog

Cómo se documentan los cambios de Homlity Real Estate y qué puedes esperar
encontrar antes de actualizar.

---

## Dónde está

| Archivo | Para quién |
| --- | --- |
| [`CHANGELOG.md`](../../../CHANGELOG.md) | Desarrolladores. Formato Keep a Changelog |
| [`readme.txt`](../../../readme.txt) | Usuarios finales. Formato de WordPress.org |

El primero es el técnico: qué cambió en la API, qué se depreció, qué rompió. El
segundo cuenta lo mismo en términos de producto.

---

## Formato

[Keep a Changelog 1.1.0](https://keepachangelog.com/es-ES/1.1.0/) con
[SemVer](semver.md).

```markdown
## [2.9.0] - 2026-10-15

### Added
- Nueva action `homlity/property/archived`.

### Changed
- `Property::getImages()` ahora resuelve también las galerías en formato X.

### Deprecated
- El filtro `homlity_legacy_thing`. Usa `homlity/property/data`.

### Fixed
- `Money::fromMeta()` interpretaba «2.500.000» como 2,5.

### Security
- La ruta REST X exige ahora la capacidad `edit_posts`.
```

### Las categorías

| Categoría | Qué va aquí |
| --- | --- |
| `Added` | Funcionalidad nueva. Hooks, clases, métodos, opciones |
| `Changed` | Comportamiento que cambia sin romper |
| `Deprecated` | Lo que dejará de existir en la próxima mayor |
| `Removed` | Lo que ya no existe. **Sólo en una versión MAJOR** |
| `Fixed` | Correcciones |
| `Security` | Vulnerabilidades corregidas |

---

## Qué se documenta

**Siempre**, sin excepción:

- Todo cambio en la Developer API: hooks, clases, interfaces, helpers.
- Toda deprecación, con su reemplazo.
- Toda eliminación, con la ruta de migración.
- Todo cambio de comportamiento visible desde fuera.
- Toda corrección de seguridad.
- Todo cambio en los requisitos mínimos.
- Todo cambio en la estructura de datos que requiera migración.

**No** se documenta: refactorizaciones internas sin efecto observable, cambios
de formato, actualizaciones de dependencias de desarrollo.

Un cambio interno que altere el comportamiento observable **sí** se documenta:
lo que decide no es dónde está el código, sino si alguien puede notarlo.

---

## Cómo se escribe

### Di lo que cambió, no lo que tocaste

```markdown
✗ ### Fixed
  - Corregido PropertyUpsertService.

✓ ### Fixed
  - `PropertyUpsertService` escribía sobre el post con ID 1 del sitio cuando
    `wp_insert_post()` devolvía un error: el casteo a entero convertía el
    `WP_Error` en 1 y la comprobación de error nunca llegaba a dispararse.
```

La segunda le dice a quien administra un sitio si le pasó, y a quien programa,
por qué.

### Nombra los elementos públicos exactamente

```markdown
✓ - Nueva action `homlity/property/archived`.
✗ - Nuevo hook de archivado.
```

### Explica el impacto de una deprecación

```markdown
✓ ### Deprecated
  - El filtro `homlity_legacy_thing`, sustituido por `homlity/property/data`.
    Sigue funcionando durante toda la serie 2.x y se eliminará en la 3.0.0.
    Migración: [Deprecaciones](deprecations.md).
```

### En castellano

El changelog está en castellano, como el resto de la documentación de producto.
Los identificadores —nombres de hook, de clase, de método— van en su forma
literal, sin traducir.

---

## Antes de actualizar

1. Lee las entradas entre tu versión y la nueva.
2. Mira `Removed` y `Deprecated`.
3. Comprueba si cambiaron los requisitos mínimos.
4. Prueba en staging con `WP_DEBUG` activo.
5. Busca `homlity` en `debug.log`.

Si sólo cambió el `PATCH`, actualiza sin más: por definición no hay nada que
migrar. Si cambió el `MINOR`, revisa `Deprecated`. Si cambió el `MAJOR`, léelo
entero.

---

## En tu extensión

Mantén tu propio `CHANGELOG.md` con el mismo formato, y añade una línea cuando
cambies la compatibilidad:

```markdown
## [1.5.0] - 2026-11-02

### Changed
- Compatible con Homlity Real Estate 2.9.x. Mínimo sigue siendo 2.8.0.

### Added
- Se escucha `homlity/property/archived` cuando Homlity ≥ 2.9.0.
```

Es lo primero que mira quien va a instalar tu extensión.
