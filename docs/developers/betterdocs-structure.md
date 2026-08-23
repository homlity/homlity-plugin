# Estructura para BetterDocs

Cómo montar la documentación de <https://homlity.com/desarrolladores/> en
BetterDocs a partir de los archivos Markdown de `docs/developers/`.

**Base de URL:** `/desarrolladores/docs/wordpress/`

---

## Cómo usar este documento

1. Crea en BetterDocs las **10 categorías** de la sección siguiente, en ese
   orden.
2. Dentro de cada una, crea los artículos de su tabla.
3. El contenido de cada artículo es el archivo Markdown de la columna
   **Fuente**.
4. Los enlaces relativos entre archivos (`../api/actions.md`) hay que
   convertirlos a los slugs de BetterDocs. La tabla de equivalencias está
   [al final](#tabla-de-conversión-de-enlaces).

---

## Las categorías

```
Homlity Developers
│
├── 1. Introducción
├── 2. Homlity Real Estate
├── 3. Crear extensiones
├── 4. Developer API
├── 5. Hooks
├── 6. Filters
├── 7. SDKs
├── 8. Compatibilidad
├── 9. Open Source
└── 10. Versionamiento
```

---

## 1. Introducción

> Qué es la Homlity Developer API, para quién y qué se puede construir con ella.

### 1.1

```
Título:      Introducción a la Homlity Developer API
Slug:        /desarrolladores/docs/wordpress/introduccion/
Categoría:   Introducción
Descripción: Qué es la Developer API, qué garantiza, y la diferencia entre API
             pública e implementación interna.
Fuente:      docs/developers/getting-started/introduction.md
```

### 1.2

```
Título:      Requisitos
Slug:        /desarrolladores/docs/wordpress/requisitos/
Categoría:   Introducción
Descripción: Versiones de PHP, WordPress y Homlity necesarias, y cómo
             comprobarlas desde una extensión sin romper el sitio.
Fuente:      docs/developers/getting-started/requirements.md
```

### 1.3

```
Título:      Instalación y entorno de desarrollo
Slug:        /desarrolladores/docs/wordpress/instalacion/
Categoría:   Introducción
Descripción: Montar Homlity, crear el esqueleto de una extensión, activar el
             modo depuración y espiar los eventos.
Fuente:      docs/developers/getting-started/installation.md
```

---

## 2. Homlity Real Estate

> El plugin por dentro, en lo que le interesa a quien va a extenderlo.

### 2.1

```
Título:      Arquitectura de Homlity Real Estate
Slug:        /desarrolladores/docs/wordpress/arquitectura/
Categoría:   Homlity Real Estate
Descripción: Las capas del plugin, la frontera entre lo público y lo interno,
             el orden de arranque y el flujo de un inmueble.
Fuente:      docs/developers/getting-started/architecture.md
```

### 2.2

```
Título:      El modelo Property
Slug:        /desarrolladores/docs/wordpress/modelo-property/
Categoría:   Homlity Real Estate
Descripción: La representación estable de un inmueble, todo lo que expone, lo
             que no expone a propósito, y el esquema canónico de sincronización.
Fuente:      docs/developers/models/property.md
```

---

## 3. Crear extensiones

> De cero a un plugin funcional.

### 3.1

```
Título:      Qué es una extensión de Homlity
Slug:        /desarrolladores/docs/wordpress/extensiones/
Categoría:   Crear extensiones
Descripción: Anatomía de una extensión, qué gana al registrarse y qué tipos de
             integración se pueden construir.
Fuente:      docs/developers/extensions/introduction.md
```

### 3.2

```
Título:      Crear tu primera extensión para Homlity
Slug:        /desarrolladores/docs/wordpress/crear-extension/
Categoría:   Crear extensiones
Descripción: Guía paso a paso con código completo: carpeta, cabecera,
             comprobaciones, registro, hooks, filtros, pruebas y empaquetado.
Fuente:      docs/developers/extensions/create-your-first-extension.md
```

### 3.3

```
Título:      Ciclo de vida de una extensión
Slug:        /desarrolladores/docs/wordpress/ciclo-de-vida/
Categoría:   Crear extensiones
Descripción: Qué ocurre en cada fase del arranque y qué se puede hacer en cada
             momento.
Fuente:      docs/developers/extensions/extension-lifecycle.md
```

### 3.4

```
Título:      Registro de extensiones
Slug:        /desarrolladores/docs/wordpress/registro-extensiones/
Categoría:   Crear extensiones
Descripción: Cómo se da de alta una extensión, qué valida Homlity y qué ocurre
             cuando se rechaza.
Fuente:      docs/developers/extensions/extension-registration.md
```

### 3.5

```
Título:      Buenas prácticas para extensiones
Slug:        /desarrolladores/docs/wordpress/buenas-practicas/
Categoría:   Crear extensiones
Descripción: Los quince errores que se cometen siempre, y qué hacer en su lugar.
Fuente:      docs/developers/extensions/best-practices.md
```

---

## 4. Developer API

> La referencia de clases, interfaces y funciones.

### 4.1

```
Título:      Visión general de la Developer API
Slug:        /desarrolladores/docs/wordpress/developer-api/
Categoría:   Developer API
Descripción: La convención de nombres, la superficie completa y qué significa
             exactamente la garantía de estabilidad.
Fuente:      docs/developers/api/overview.md
```

### 4.2

```
Título:      Clases públicas
Slug:        /desarrolladores/docs/wordpress/clases/
Categoría:   Developer API
Descripción: Referencia de las once clases públicas: fachada, modelos, eventos,
             registro y soporte.
Fuente:      docs/developers/api/classes.md
```

### 4.3

```
Título:      Interfaces
Slug:        /desarrolladores/docs/wordpress/interfaces/
Categoría:   Developer API
Descripción: ExtensionInterface, PropertySyncProviderInterface y
             CrmAdapterInterface, con ejemplos de implementación completos.
Fuente:      docs/developers/api/interfaces.md
```

### 4.4

```
Título:      Funciones globales
Slug:        /desarrolladores/docs/wordpress/helpers/
Categoría:   Developer API
Descripción: Los siete helpers globales, cuándo usarlos en vez de las clases, y
             la regla del function_exists.
Fuente:      docs/developers/api/helpers.md
```

---

## 5. Hooks

> Los eventos del ciclo de vida.

### 5.1

```
Título:      Actions de Homlity
Slug:        /desarrolladores/docs/wordpress/actions/
Categoría:   Hooks
Descripción: Las doce acciones públicas: cuándo se ejecutan, qué parámetros
             reciben, qué cubren y qué no.
Fuente:      docs/developers/api/actions.md
```

> **Sugerencia.** Este archivo es largo. Si prefieres un artículo por hook,
> divídelo por sus encabezados de primer nivel, respetando estos slugs:
>
> | Hook | Slug |
> | --- | --- |
> | `homlity/loaded` | `/desarrolladores/docs/wordpress/action-loaded/` |
> | `homlity/initialized` | `/desarrolladores/docs/wordpress/action-initialized/` |
> | `homlity/extensions/register` | `/desarrolladores/docs/wordpress/action-extensions-register/` |
> | `homlity/extension/registered` | `/desarrolladores/docs/wordpress/action-extension-registered/` |
> | `homlity/extension/failed` | `/desarrolladores/docs/wordpress/action-extension-failed/` |
> | `homlity/extensions/registered` | `/desarrolladores/docs/wordpress/action-extensions-registered/` |
> | `homlity/property/created` | `/desarrolladores/docs/wordpress/action-property-created/` |
> | `homlity/property/updated` | `/desarrolladores/docs/wordpress/action-property-updated/` |
> | `homlity/property/deleted` | `/desarrolladores/docs/wordpress/action-property-deleted/` |
> | `homlity/property/synchronized` | `/desarrolladores/docs/wordpress/action-property-synchronized/` |
> | `homlity/property/status_changed` | `/desarrolladores/docs/wordpress/action-property-status-changed/` |
> | `homlity/property/images_changed` | `/desarrolladores/docs/wordpress/action-property-images-changed/` |
>
> Si los divides, mantén el artículo 5.1 como índice de la categoría con la
> sección «Cobertura» y la tabla de enlaces.

---

## 6. Filters

> Los puntos donde se cambia el comportamiento.

### 6.1

```
Título:      Filters de Homlity
Slug:        /desarrolladores/docs/wordpress/filters/
Categoría:   Filters
Descripción: Los cuatro filtros públicos: qué filtran, qué deben devolver y qué
             puede salir mal si se usan sin cuidado.
Fuente:      docs/developers/api/filters.md
```

> **Sugerencia.** Igual que con las actions, si prefieres un artículo por filtro:
>
> | Filtro | Slug |
> | --- | --- |
> | `homlity/property/normalized` | `/desarrolladores/docs/wordpress/filter-property-normalized/` |
> | `homlity/property/data` | `/desarrolladores/docs/wordpress/filter-property-data/` |
> | `homlity/property/query_args` | `/desarrolladores/docs/wordpress/filter-property-query-args/` |
> | `homlity/extension/is_compatible` | `/desarrolladores/docs/wordpress/filter-extension-is-compatible/` |

---

## 7. SDKs

> Los paquetes oficiales de comunicación con servicios externos.

### 7.1

```
Título:      SDKs oficiales de Homlity
Slug:        /desarrolladores/docs/wordpress/sdks/
Categoría:   SDKs
Descripción: Los nueve SDK publicados en Packagist, dónde termina el SDK y
             empieza tu extensión, y los patrones de integración entrante y
             saliente.
Fuente:      docs/developers/integration/sdk-usage.md
```

### 7.2

```
Título:      Arquitectura de integración
Slug:        /desarrolladores/docs/wordpress/arquitectura-integracion/
Categoría:   SDKs
Descripción: Integración entrante y saliente, la homologación de datos entre
             CRMs, y cómo cortar el bucle de sincronización.
Fuente:      docs/developers/integration/architecture.md
```

---

## 8. Compatibilidad

> Qué garantiza Homlity, y qué se espera de una extensión.

### 8.1

```
Título:      Compatibilidad entre versiones
Slug:        /desarrolladores/docs/wordpress/compatibilidad/
Categoría:   Compatibilidad
Descripción: Qué garantiza la Developer API, cómo declarar requisitos, la
             detección progresiva y la convivencia entre extensiones.
Fuente:      docs/developers/extensions/compatibility.md
```

### 8.2

```
Título:      Política «Compatible con Homlity»
Slug:        /desarrolladores/docs/wordpress/compatible-con-homlity/
Categoría:   Compatibilidad
Descripción: Qué puedes construir y vender sobre Homlity, y las seis
             condiciones para usar la frase «Compatible con Homlity».
Fuente:      docs/developers/extensions/compatible-with-homlity.md
```

---

## 9. Open Source

> Cómo participar en el plugin.

### 9.1

```
Título:      Contribuir a Homlity Real Estate
Slug:        /desarrolladores/docs/wordpress/contribuir/
Categoría:   Open Source
Descripción: Preparar el entorno, estándares de código, commits, pruebas y
             documentación.
Fuente:      docs/developers/open-source/contributing.md
```

### 9.2

```
Título:      Reportar incidencias
Slug:        /desarrolladores/docs/wordpress/reportar-incidencias/
Categoría:   Open Source
Descripción: Cómo escribir un reporte útil, cómo pedir un punto de extensión
             que falta, y qué esperar.
Fuente:      docs/developers/open-source/reporting-issues.md
```

### 9.3

```
Título:      Pull requests
Slug:        /desarrolladores/docs/wordpress/pull-requests/
Categoría:   Open Source
Descripción: Qué se revisa en un cambio, qué lo acelera y qué lo atasca.
Fuente:      docs/developers/open-source/pull-requests.md
```

### 9.4

```
Título:      Seguridad
Slug:        /desarrolladores/docs/wordpress/seguridad/
Categoría:   Open Source
Descripción: Cómo reportar una vulnerabilidad, el modelo de seguridad de la
             Developer API, y cómo escribir una extensión que no introduzca
             ninguna.
Fuente:      docs/developers/open-source/security.md
```

---

## 10. Versionamiento

> Cómo se numeran las versiones y cómo se retira lo que sobra.

### 10.1

```
Título:      Versionamiento semántico
Slug:        /desarrolladores/docs/wordpress/semver/
Categoría:   Versionamiento
Descripción: Las dos versiones —plugin y API—, qué significa cada número, y qué
             está cubierto por la garantía de estabilidad.
Fuente:      docs/developers/versioning/semver.md
```

### 10.2

```
Título:      Deprecaciones
Slug:        /desarrolladores/docs/wordpress/deprecaciones/
Categoría:   Versionamiento
Descripción: Cómo se retira algo de la API, cuánto tiempo hay para migrar, y el
             mecanismo para deprecar en tu propia extensión.
Fuente:      docs/developers/versioning/deprecations.md
```

### 10.3

```
Título:      Política de changelog
Slug:        /desarrolladores/docs/wordpress/changelog/
Categoría:   Versionamiento
Descripción: Qué se documenta en cada versión, cómo se escribe y qué revisar
             antes de actualizar.
Fuente:      docs/developers/versioning/changelog-policy.md
```

---

## Tabla de conversión de enlaces

Al importar cada Markdown, sustituye las rutas relativas por estos slugs:

| Ruta en el repositorio | Slug de BetterDocs |
| --- | --- |
| `getting-started/introduction.md` | `/desarrolladores/docs/wordpress/introduccion/` |
| `getting-started/requirements.md` | `/desarrolladores/docs/wordpress/requisitos/` |
| `getting-started/installation.md` | `/desarrolladores/docs/wordpress/instalacion/` |
| `getting-started/architecture.md` | `/desarrolladores/docs/wordpress/arquitectura/` |
| `extensions/introduction.md` | `/desarrolladores/docs/wordpress/extensiones/` |
| `extensions/create-your-first-extension.md` | `/desarrolladores/docs/wordpress/crear-extension/` |
| `extensions/extension-lifecycle.md` | `/desarrolladores/docs/wordpress/ciclo-de-vida/` |
| `extensions/extension-registration.md` | `/desarrolladores/docs/wordpress/registro-extensiones/` |
| `extensions/best-practices.md` | `/desarrolladores/docs/wordpress/buenas-practicas/` |
| `extensions/compatibility.md` | `/desarrolladores/docs/wordpress/compatibilidad/` |
| `extensions/compatible-with-homlity.md` | `/desarrolladores/docs/wordpress/compatible-con-homlity/` |
| `api/overview.md` | `/desarrolladores/docs/wordpress/developer-api/` |
| `api/actions.md` | `/desarrolladores/docs/wordpress/actions/` |
| `api/filters.md` | `/desarrolladores/docs/wordpress/filters/` |
| `api/classes.md` | `/desarrolladores/docs/wordpress/clases/` |
| `api/interfaces.md` | `/desarrolladores/docs/wordpress/interfaces/` |
| `api/helpers.md` | `/desarrolladores/docs/wordpress/helpers/` |
| `models/property.md` | `/desarrolladores/docs/wordpress/modelo-property/` |
| `integration/architecture.md` | `/desarrolladores/docs/wordpress/arquitectura-integracion/` |
| `integration/sdk-usage.md` | `/desarrolladores/docs/wordpress/sdks/` |
| `open-source/contributing.md` | `/desarrolladores/docs/wordpress/contribuir/` |
| `open-source/reporting-issues.md` | `/desarrolladores/docs/wordpress/reportar-incidencias/` |
| `open-source/pull-requests.md` | `/desarrolladores/docs/wordpress/pull-requests/` |
| `open-source/security.md` | `/desarrolladores/docs/wordpress/seguridad/` |
| `versioning/semver.md` | `/desarrolladores/docs/wordpress/semver/` |
| `versioning/deprecations.md` | `/desarrolladores/docs/wordpress/deprecaciones/` |
| `versioning/changelog-policy.md` | `/desarrolladores/docs/wordpress/changelog/` |

Enlaces que apuntan **fuera** de `docs/developers/`:

| Ruta | A dónde debe apuntar |
| --- | --- |
| `../../../CHANGELOG.md` | <https://github.com/homlity/homlity-plugin/blob/main/CHANGELOG.md> |
| `../../../CONTRIBUTING.md` | <https://github.com/homlity/homlity-plugin/blob/main/CONTRIBUTING.md> |
| `../../../SECURITY.md` | <https://github.com/homlity/homlity-plugin/blob/main/SECURITY.md> |
| `../../../license.txt` | <https://github.com/homlity/homlity-plugin/blob/main/license.txt> |
| `../../examples/basic-extension/…` | <https://github.com/homlity/homlity-plugin/tree/main/docs/examples/basic-extension> |
| `docs/architecture/*.md` | No se publican: son documentación interna del repositorio |

---

## Notas de publicación

**Diagramas.** Varios artículos incluyen diagramas Mermaid en bloques
` ```mermaid `. BetterDocs no los renderiza de serie: hay que añadir Mermaid al
tema, o exportarlos como SVG y sustituir el bloque por la imagen.

**Tablas.** Todas las tablas son Markdown estándar. Comprueba que el tema les
da `overflow-x: auto`: varias son anchas y en móvil desbordan.

**Código.** Los bloques declaran su lenguaje (`php`, `bash`, `json`,
`markdown`). Comprueba que el resaltado los reconoce.

**Orden.** Los artículos de cada categoría están numerados en el orden en el que
conviene leerlos, no alfabéticamente. Configura el orden manualmente.

**Artículo de entrada.** El índice general está en
`docs/developers/README.md`. Puede publicarse como la página raíz de
`/desarrolladores/` en lugar de como un artículo.

**Qué no se publica.** `docs/architecture/current-architecture.md`,
`docs/architecture/extensibility-audit.md` y
`docs/developers/implementation-report.md` son documentación interna del
repositorio: describen decisiones de mantenimiento, no la API pública.
