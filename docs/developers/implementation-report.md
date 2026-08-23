# Informe de implementación

Conversión de **Homlity Real Estate** en un framework extensible con una
Developer API pública.

| | |
| --- | --- |
| Versión auditada | 2.7.10 |
| Versión publicada | 2.8.0 |
| Developer API | 1.0.0 |
| Fecha | 23 de agosto de 2026 |
| Repositorio | <https://github.com/homlity/homlity-plugin> |

---

## 1. Resumen ejecutivo

Homlity Real Estate era un plugin inmobiliario grande y bien organizado —~250
clases, 874 pruebas, un subsistema de CRM con homologación de datos entre
proveedores— pero sin frontera declarada entre lo que un desarrollador externo
podía usar y lo que era implementación interna. Tenía 17 acciones y 75 filtros,
y **ninguno** se disparaba al crear, actualizar o borrar un inmueble: una
integración con un portal externo no tenía forma soportada de enterarse de un
cambio.

Lo que se hizo:

**Una frontera declarada.** `Homlity\Developer\` es el namespace público; todo
lo demás es interno. No se movió ninguna clase existente: mover 200 clases
habría roto todas las instalaciones, que es exactamente lo que una API de
estabilidad debe evitar.

**Una convención de nombres que no rompe nada.** Barra (`homlity/property/updated`)
= público y estable. Guion bajo (`homlity_crm_adapters`) = heredado, funciona,
sin garantía. Los ~90 hooks existentes siguen igual y ninguno se deprecó.

**Doce acciones del ciclo de vida** que se disparan cuando la escritura ha
terminado del todo —post, metadatos, taxonomías, galería y asesor—, no a mitad
como hace `save_post`. Esa diferencia es la que distingue recibir un inmueble
completo de recibir uno sin precio ni fotos.

**Cuatro filtros**, no cuarenta. Sólo los puntos donde una integración real
necesita intervenir.

**Un modelo público de inmueble** que absorbe los cuatro formatos en los que se
guarda una galería y las seis notaciones en las que llegan los precios, y que
**no expone** los datos personales del propietario ni la respuesta cruda del
CRM.

**Un sistema de extensiones** con requisitos declarativos, validación de slug y
aislamiento de errores: una extensión rota se convierte en un fallo reportado,
no en un sitio caído.

**Documentación completa**: 25 documentos para desarrolladores, dos de
arquitectura interna, una extensión de ejemplo funcional —con pruebas que la
ejecutan en cada cambio del plugin— y el mapa de publicación en BetterDocs.

**113 pruebas nuevas.** Las 874 existentes siguen pasando sin modificación.

**Cero cambios que rompan compatibilidad.**

---

## 2. Arquitectura anterior

Documentada en detalle en
[`docs/architecture/current-architecture.md`](../architecture/current-architecture.md)
y auditada en
[`docs/architecture/extensibility-audit.md`](../architecture/extensibility-audit.md).

En resumen, lo que impedía extender el plugin sin tocarlo:

| Hallazgo | Severidad |
| --- | --- |
| Ningún evento del ciclo de vida de un inmueble | Crítica |
| La galería se guarda en cuatro formatos distintos | Crítica |
| Los datos personales del propietario junto a los comerciales | Crítica |
| Ninguna frontera entre API pública e implementación interna | Alta |
| No existía un registro de extensiones | Alta |
| Sin punto de extensión en la consulta de inmuebles | Alta |
| Sin punto de extensión en la escritura desde un CRM | Alta |
| Requisitos declarados contradictorios entre archivos | Media |
| Dos convenciones de nombres de hook sin documentar | Baja |
| Sin mecanismo de deprecación | Baja |

Lo que **ya estaba bien** y se aprovechó tal cual: el esquema canónico de
inmueble (`PropertyFieldSchema`), el sistema de homologación entre CRMs, los
contratos `SyncProviderInterface` y `CrmAdapterInterface`, y el hecho de que
toda escritura de inmueble venida de fuera pasa por un único método
(`PropertyUpsertService::upsert()`). Ese embudo es lo que hizo posible instrumentar
el ciclo de vida en dos sitios en lugar de en veinte.

---

## 3. Developer API introducida

### 3.1 Actions — 12

| Hook | Parámetros | Se dispara desde |
| --- | --- | --- |
| `homlity/loaded` | — | `PluginBootstrap::init()` |
| `homlity/extensions/register` | `ExtensionRegistry` | `DeveloperApiService` |
| `homlity/extension/registered` | `ExtensionInterface`, `string` | `ExtensionRegistry` |
| `homlity/extension/failed` | `ExtensionInterface`, `string[]`, `string` | `ExtensionRegistry` |
| `homlity/extensions/registered` | `ExtensionRegistry` | `DeveloperApiService` |
| `homlity/initialized` | — | `DeveloperApiService` (`init`:100) |
| `homlity/property/created` | `Property`, `PropertyContext` | `PropertyEventDispatcher` |
| `homlity/property/updated` | `Property`, `PropertyChanges`, `PropertyContext` | `PropertyEventDispatcher` |
| `homlity/property/deleted` | `Property`, `int` | `before_delete_post` |
| `homlity/property/synchronized` | `Property`, `PropertyChanges`, `PropertyContext` | `PropertyEventDispatcher` |
| `homlity/property/status_changed` | `Property`, `string`, `string` | `transition_post_status` |
| `homlity/property/images_changed` | `Property`, `PropertyChanges`, `PropertyContext` | `PropertyEventDispatcher` |

Documentación: [`api/actions.md`](api/actions.md).

### 3.2 Filters — 4

| Hook | Filtra | Se aplica en |
| --- | --- | --- |
| `homlity/property/normalized` | La carga canónica antes de guardarse | `PropertyUpsertService::upsert()` |
| `homlity/property/data` | Los datos de hidratación de un `Property` | `PropertyRepository::hydrate()` |
| `homlity/property/query_args` | Los argumentos de `WP_Query` | `PropertySearchService::buildQueryArgs()` |
| `homlity/extension/is_compatible` | Si una extensión es compatible | `ExtensionRegistry::register()` |

Documentación: [`api/filters.md`](api/filters.md).

### 3.3 Clases — 14

Todas bajo `Homlity\Developer\`, todas `final`, todas con PHPDoc completo y
`@since 2.8.0`.

| Clase | Rol |
| --- | --- |
| `Homlity` | Fachada de la API |
| `Api` | Versiones y comprobaciones de entorno |
| `Extension\ExtensionRegistry` | Censo de extensiones |
| `Extension\Requirements` | Requisitos declarativos, inmutable |
| `Models\Property` | El inmueble, sólo lectura |
| `Models\Money` | Importe con moneda, inmutable |
| `Models\Location` | Ubicación, respeta la dirección oculta |
| `Models\Image` | Imagen de galería |
| `Models\Agent` | Asesor |
| `Events\PropertyContext` | Quién escribió y por qué |
| `Events\PropertyChanges` | Qué campos cambiaron |
| `Services\PropertyRepository` | Buscar inmuebles |
| `Support\Hooks` | Nombres de hook como constantes |
| `Support\Deprecated` | Mecanismo de deprecación |

Documentación: [`api/classes.md`](api/classes.md).

### 3.4 Interfaces — 3

| Interfaz | Consumidor en el núcleo |
| --- | --- |
| `Contracts\ExtensionInterface` | `ExtensionRegistry` |
| `Contracts\PropertySyncProviderInterface` | `SyncRegistry` (extiende el contrato interno) |
| `Contracts\CrmAdapterInterface` | `CrmIntegrationManager` (extiende el interno) |

Las dos últimas **extienden** los contratos internos que el plugin ya enviaba,
no los sustituyen: las implementaciones existentes siguen funcionando sin
cambios, y las nuevas implementan la versión pública.

Documentación: [`api/interfaces.md`](api/interfaces.md).

### 3.5 Helpers globales — 8

`homlity_version()`, `homlity_api_version()`, `homlity_is_available()`,
`homlity_is_version_supported()`, `homlity_extensions()`,
`homlity_register_extension()`, `homlity_properties()`,
`homlity_get_property()`.

Definidos en `src/Developer/functions.php`, cargado antes de `plugins_loaded`
para que un plugin que se cargue después pueda llamarlos desde su arranque.

Documentación: [`api/helpers.md`](api/helpers.md).

### 3.6 Constantes — 2

`HOMLITY_API_VERSION` (`'1.0.0'`) y `HOMLITY_DEVELOPER_NAMESPACE`.

---

## 4. Sistema de extensiones

### Cómo funciona

```
plugins_loaded : 20   El núcleo registra sus servicios → homlity/loaded
plugins_loaded : 25   homlity/extensions/register
                      → validación → boot() de cada extensión
                      → homlity/extensions/registered
plugins_loaded : 30   homlity_plugin_register_sync_providers (preexistente)
init           : 100  homlity/initialized
```

La prioridad 25 se eligió para caer **después** del arranque del núcleo (20) y
**antes** del registro de proveedores de sincronización (30), de modo que un
proveedor pueda vivir dentro de una extensión.

### Registrar

```php
add_action('homlity/extensions/register', function ($registry) {
    $registry->register(new MiIntegracion());
});
```

Se implementaron **ambas** formas que se pedían —el helper global
`homlity_register_extension()` y la fachada
`Homlity::extensions()->register()`— porque cuestan lo mismo y sirven a
públicos distintos: la función global no necesita `use` ni autoload resuelto, la
fachada es descubrible desde el IDE.

### Qué valida

En orden, deteniéndose en el primer fallo: slug utilizable tras
`sanitize_key()`, slug libre, requisitos declarados satisfechos, filtro de
compatibilidad, y `boot()` sin excepciones.

### Qué garantiza

- **Slugs duplicados imposibles**: la segunda extensión se rechaza, la primera
  sigue funcionando.
- **Extensiones incompatibles no arrancan**, y el motivo llega en castellano por
  `failures()` y por `homlity/extension/failed`.
- **Un error no se propaga**: `register()` nunca lanza, y una excepción dentro
  de `boot()` se convierte en un fallo reportado mientras el resto de las
  extensiones sigue arrancando.
- **Registro tolerante en el tiempo**: antes de la ventana queda en cola,
  después arranca en el acto.

### Requisitos declarativos

```php
Requirements::create([
    'homlity'   => '2.8.0',
    'api'       => '1.0.0',
    'php'       => '8.1',
    'wordpress' => '6.4',
    'plugins'   => ['woocommerce/woocommerce.php'],
]);
```

Las claves desconocidas se ignoran a propósito: una extensión escrita contra una
versión posterior de la API que declare un requisito que esta no entiende sigue
arrancando en vez de quedar bloqueada sin motivo.

Documentación: [`extensions/extension-registration.md`](extensions/extension-registration.md)
y [`extensions/extension-lifecycle.md`](extensions/extension-lifecycle.md).

---

## 5. Ejemplo de extensión

**Ubicación:**
[`docs/examples/basic-extension/homlity-example-extension/`](../examples/basic-extension/homlity-example-extension/README.md)

```
homlity-example-extension/
├── homlity-example-extension.php   cabecera, comprobaciones, registro, activación
├── src/Plugin.php                  implementa ExtensionInterface
├── composer.json
└── README.md
```

Demuestra las diez cosas que se pedían: comprobar Homlity, comprobar versión,
registrar la extensión, escuchar eventos, usar un filtro, manejar activación y
desactivación, manejar incompatibilidad sin fatal error, evitar el bucle de
sincronización, no dejar escapar excepciones desde un callback, y empaquetar.

**Funciona de verdad, y hay pruebas que lo demuestran.**
`tests/Unit/Developer/ExampleExtensionTest.php` carga `src/Plugin.php` tal cual
está en `docs/`, lo registra contra la Developer API real y verifica seis
comportamientos: que se registra y arranca, que anota los inmuebles nuevos, que
distingue un cambio de precio de uno de descripción, que no le devuelve al CRM
lo que él mismo mandó, que su filtro marca los inmuebles importados, y que su
log no crece sin límite.

Un ejemplo que no compila es peor que ninguno: el desarrollador que lo copia
pierde la tarde averiguando que el error no era suyo. Esa prueba impide que la
documentación se quede atrás del código.

---

## 6. Backward compatibility

**Ninguna clase, función, hook, opción, metadato ni tabla existente ha cambiado
de nombre, de firma o de comportamiento.**

### Medidas tomadas

**No se movió ni una clase.** Se evaluó trasladar el código interno a
`Homlity\Internal\`, como se sugería. Se descartó: habría roto todas las
instalaciones y todos los plugins de terceros que hoy referencian
`SyncRegistry` o `CrmAdapterInterface`. La frontera se declara, no se muda.

**Ningún hook existente se tocó ni se deprecó.** Los ~90 hooks con guion bajo
siguen exactamente igual. La convención con barra los distingue sin necesidad de
retirarlos.

**Los contratos públicos extienden a los internos.** `PropertySyncProviderInterface
extends SyncProviderInterface` significa que un proveedor que implemente el
público sigue siendo aceptado por `SyncRegistry::addProvider()`, y que los que
implementan el interno siguen funcionando. Sin `class_alias`, sin duplicación,
con seguridad de tipos.

**El único cambio de firma es aditivo.** `PropertyUpsertService::upsert()` ganó
un segundo parámetro **opcional** con valor por defecto `'crm'`. Todas las
llamadas existentes —incluidas las de los dos controladores de consignación,
que lo invocan con un nombre de clase dinámico— siguen funcionando. Las dos del
formulario de consignación se actualizaron para declarar su origen real.

**Se buscaron las referencias antes de tocar.** `PropertyUpsertService` se
invoca desde cinco sitios; los cinco se revisaron. `PropertyPostType::saveMeta()`
sale temprano cuando no hay nonce de formulario, y por eso una escritura de CRM
no dispara los hooks dos veces — se verificó leyendo el código, no asumiéndolo.

**Se corrigió una incompatibilidad que ya existía.** `composer.json` exigía PHP
≥ 8.0 mientras `readme.txt` decía 7.4 y la cabecera no declaraba nada: un sitio
con PHP 7.4 podía instalar el plugin y romperse. Ahora los tres coinciden. Es un
cambio de metadatos, no de comportamiento.

**Mecanismo de deprecación en su sitio antes de necesitarlo.**
`Support\Deprecated` envuelve `do_action_deprecated()`,
`apply_filters_deprecated()`, `_deprecated_function()` y
`_deprecated_argument()`. Hoy no lo usa nada, porque nada está deprecado.

**Las 874 pruebas existentes pasan sin una sola modificación.** Es la
comprobación que más pesa: ninguna necesitó adaptarse.

Política documentada en [`versioning/deprecations.md`](versioning/deprecations.md)
y [`versioning/semver.md`](versioning/semver.md).

---

## 7. Seguridad

### Lo que se encontró

Los metadatos de un inmueble mezclan datos comerciales con **datos personales
del propietario** captados por el formulario de consignación —nombre, documento
de identidad, teléfono, correo, WhatsApp, banderas de consentimiento— y con
`_property_sync_payload`, la respuesta cruda del CRM, que puede contener tokens.

Un hook que hubiera entregado «los metadatos del inmueble» habría publicado todo
eso a cada extensión instalada en el sitio. Es el hallazgo más importante de la
auditoría, y condicionó el diseño del modelo.

### Lo que se hizo

**Lista blanca, no lista negra.** `PropertyRepository::readMeta()` enumera las
35 claves que el modelo puede leer. Un metadato añadido mañana —por Homlity o
por otro plugin— no puede colarse en la API sin que alguien lo decida. Un
filtro de exclusión habría sido más corto y habría fallado en silencio la
primera vez que alguien añadiera un campo sensible.

**El diff tampoco los transporta.** `PropertyEventDispatcher::PRIVATE_FIELDS`
excluye los mismos campos de la comparación, para que un hook del ciclo de vida
no pueda convertirse en una fuga.

**La dirección oculta sigue oculta.** `Location::getAddress()` devuelve `''`
cuando el propietario pidió no publicar la dirección exacta, y la documentación
dice explícitamente que saltárselo leyendo el metadato es una mala idea
comercial, no sólo técnica.

**Hay pruebas que lo verifican.** `PropertyModelTest` serializa el modelo
completo y comprueba que ninguno de los valores privados aparece;
`PublicHooksTest` hace lo mismo con el diff.

### Lo que se revisó y no requirió cambios

| Riesgo | Estado |
| --- | --- |
| Escalada de privilegios | Ningún elemento nuevo escribe ni cambia capacidades |
| Bypass de nonce | Los hooks nuevos se disparan **después** de la validación existente |
| Bypass de capacidades | `PropertyPostType::saveMeta()` conserva intactos su nonce y su `current_user_can()` |
| Inyección SQL | El repositorio usa `get_posts()` con `meta_query`; ninguna consulta construida a mano |
| XSS | La API no imprime nada; devuelve datos y documenta que hay que escaparlos |
| SSRF | No se introdujo ninguna petición HTTP |
| Ejecución arbitraria | Ningún `eval()`, `unserialize()` ni carga dinámica |
| Manipulación de archivos | Ninguna escritura de archivos |
| Exposición de credenciales | Ningún hook público transporta secretos |
| Denegación de servicio | El registro es de un solo despacho; `boot()` no puede ejecutarse dos veces |

**Aislamiento de errores.** `ExtensionRegistry` captura los `Throwable` de
`boot()` y de `getRequirements()`. Una extensión de terceros que lance no puede
tumbar el sitio.

**Filtros defensivos.** Los tres filtros que devuelven estructuras descartan lo
que no sea un array y conservan el valor original: un filtro mal escrito no
puede vaciar un inmueble ni romper una búsqueda. Hay una prueba por cada uno.

Documentación: [`open-source/security.md`](open-source/security.md) y
[`SECURITY.md`](../../SECURITY.md).

---

## 8. Testing

### Resultados

```
$ composer test
OK (987 tests, 3423 assertions)

$ php tests/error-reporting/run.php
OK (76 assertions)

$ composer validate --no-check-publish
./composer.json is valid

$ php -l  (src/, includes/, templates/, tests/, docs/examples/, raíz)
Sin errores de sintaxis
```

| | Antes | Después |
| --- | --- | --- |
| Pruebas | 874 | 987 |
| Aserciones | 3118 | 3423 |
| Pruebas modificadas | — | **0** |

### Pruebas nuevas — 113

| Archivo | Pruebas | Qué protege |
| --- | ---: | --- |
| `Developer/ExtensionRegistryTest` | 14 | Slug vacío y duplicado, normalización, requisitos, arranque diferido y tardío, aislamiento de excepciones, filtro de compatibilidad, despacho único |
| `Developer/CompatibilityTest` | 17 | Versiones de plugin y de API, comparación numérica y no alfabética, requisitos incumplidos, claves desconocidas, plugins requeridos |
| `Developer/PropertyModelTest` | 45 | Hidratación, disponibilidad, las once notaciones de precio, taxonomías, ubicación, dirección oculta, los cuatro formatos de galería, asesor, procedencia, serialización y los datos privados |
| `Developer/PublicHooksTest` | 22 | Que cada acción dispare cuando debe y sólo cuando debe, el diff real, el diff vacío, origen y sincronización, borrado, cambio de estado, y que el diff no lleve datos personales |
| `Developer/PublicFiltersTest` | 9 | Que cada filtro reciba lo prometido y que devolver basura no rompa nada |
| `Developer/ExampleExtensionTest` | 6 | Que la extensión de la documentación funcione de verdad |

### Un fallo real encontrado por las pruebas

`Money::fromMeta()` interpretaba `$ 2.500.000` como **2,5**: quitaba todo menos
dígitos y puntos y casteaba a `float`. Los CRM colombianos mandan los precios en
notación local siempre. Se reescribió el parseo con una regla explícita —un
separador solitario seguido de exactamente tres dígitos es separador de miles,
porque un precio con tres decimales no existe— y se fijó con un proveedor de
datos de once notaciones.

### Soporte de pruebas ampliado

`tests/Support/wp-functions.php` ganó los stubs de `wp_get_attachment_url()`,
`wp_get_attachment_image_src()` y `get_site_option()`, y `WP_Post` ganó
`post_excerpt`, que existe en el `WP_Post` real. `tests/bootstrap.php` lee ahora
la versión de la cabecera del plugin en lugar de usar la cadena `'test'`, que no
era comparable con `version_compare()` y habría hecho pasar pruebas que en
producción fallaban.

---

## 9. Documentación generada

### Para desarrolladores — `docs/developers/`

```
README.md                                  Índice general
implementation-report.md                   Este documento
betterdocs-structure.md                    Mapa de publicación en BetterDocs

getting-started/
    introduction.md                        Qué es y qué no es la API
    requirements.md                        Entorno y comprobaciones
    installation.md                        Entorno de desarrollo
    architecture.md                        Mapa, capas y orden de arranque

extensions/
    introduction.md                        Anatomía de una extensión
    create-your-first-extension.md         Guía paso a paso, código completo
    extension-lifecycle.md                 Qué ocurre y cuándo
    extension-registration.md              Registro y validación
    best-practices.md                      Los quince errores frecuentes
    compatibility.md                       Garantías y detección progresiva
    compatible-with-homlity.md             Política de ecosistema

api/
    overview.md                            Convención y superficie completa
    actions.md                             Las 12 acciones
    filters.md                             Los 4 filtros
    classes.md                             Las 14 clases públicas
    interfaces.md                          Las 3 interfaces
    helpers.md                             Las 8 funciones globales

models/
    property.md                            El modelo y el esquema canónico

integration/
    architecture.md                        Entrante, saliente y bidireccional
    sdk-usage.md                           Los 9 SDK oficiales

open-source/
    contributing.md                        Estándares y flujo
    reporting-issues.md                    Cómo reportar
    pull-requests.md                       Criterios de revisión
    security.md                            Modelo de seguridad

versioning/
    semver.md                              Las dos versiones
    deprecations.md                        Cómo se retira algo
    changelog-policy.md                    Qué se documenta
```

### Interna — `docs/architecture/`

- `current-architecture.md` — cómo funciona el plugin por dentro, con
  diagramas Mermaid del arranque, las capas y el flujo de un inmueble.
- `extensibility-audit.md` — 20 hallazgos clasificados en Crítica, Alta, Media
  y Baja, cada uno con su estado y, en los no resueltos, la razón.

### Ejemplo — `docs/examples/`

`basic-extension/homlity-example-extension/` con su `README.md`.

### Raíz del repositorio

`README.md` (reescrito), `CONTRIBUTING.md` (nuevo), `SECURITY.md` (nuevo),
`CHANGELOG.md` (nuevo, formato Keep a Changelog), `readme.txt` (entrada 2.8.0 y
requisitos corregidos), `.gitignore` (corregido).

### Verificación

Los 41 documentos Markdown se comprobaron con un validador de enlaces
relativos: **0 enlaces rotos**.

---

## 10. BetterDocs

El mapa completo está en [`betterdocs-structure.md`](betterdocs-structure.md).

**10 categorías, 24 artículos**, con título, slug, descripción y archivo fuente
para cada uno.

```
Homlity Developers
├──  1. Introducción         3 artículos
├──  2. Homlity Real Estate  2 artículos
├──  3. Crear extensiones    5 artículos
├──  4. Developer API        4 artículos
├──  5. Hooks                1 artículo (divisible en 12)
├──  6. Filters              1 artículo (divisible en 4)
├──  7. SDKs                 2 artículos
├──  8. Compatibilidad       2 artículos
├──  9. Open Source          4 artículos
└── 10. Versionamiento       3 artículos
```

El documento incluye además:

- la **tabla de conversión** de las 27 rutas relativas del repositorio a slugs
  de BetterDocs;
- los slugs sugeridos por si se prefiere **un artículo por hook** (12 acciones
  y 4 filtros);
- las **notas de publicación**: los diagramas Mermaid necesitan soporte en el
  tema o exportarse a SVG, las tablas anchas necesitan `overflow-x: auto`, y el
  orden de los artículos es de lectura, no alfabético;
- **qué no publicar**: los dos documentos de arquitectura y este informe son
  documentación interna del repositorio.

---

## 11. Cambios pendientes

Lo que se identificó y **no** se implementó, con la razón.

### Requiere una decisión del mantenedor

**`node_modules/` está versionado.** 57.643 archivos en el índice de Git.
`.gitignore` se corrigió, pero sacarlos del control de versiones es un commit
enorme e irreversible sin `--force`, y esa decisión no corresponde a una
auditoría. El comando es:

```bash
git rm -r --cached node_modules
git commit -m "chore: dejar de versionar node_modules"
```

Hay además cuatro `.DS_Store` versionados, que salen con el mismo tipo de
comando.

### Descartado a propósito

**`PropertyExporterInterface` y `SynchronizationInterface`.** No hay ningún
consumidor en el núcleo que los invoque. Una interfaz que nadie llama no
desacopla nada: sólo obliga a mantenerla y confunde a quien la encuentra en la
documentación. Cuando el núcleo tenga un registro de exportadores, tendrá
sentido publicar el contrato.

**Objetos de evento por hook** (`PropertyUpdatedEvent` y familia). Se evaluó,
como se pedía, y se descartó a favor de `(Property, PropertyChanges,
PropertyContext)`. En WordPress la firma de un hook **es** el contrato, y tres
parámetros con nombre claro se leen mejor en un `add_action` que un objeto
opaco. Donde el patrón sí aporta —los datos compuestos— sí se usó: el diff y el
origen son objetos.

**Contenedor de servicios.** `PluginBootstrap` instancia ~50 servicios a mano y
varios se construyen sus propias dependencias. Introducir un contenedor
obligaría a tocar todos los constructores, que es justo el refactor masivo que
las reglas del encargo prohíben. Los hooks de WordPress son el mecanismo de
sustitución, y ahora existen.

**Mover el código interno a `Homlity\Internal\`.** Habría roto todas las
instalaciones. La frontera se declara.

### Anotado como deuda

**Sin envoltorio público para registrar proveedores de sincronización ni para
escribir la carga canónica.** Una extensión que implemente
`PropertySyncProviderInterface` tiene que llamar a `SyncRegistry::addProvider()`
y a `PropertyUpsertService::upsert()`, que son clases internas. La interfaz que
implementa sí es pública, que es lo que fija la forma de su código, pero el
registro y la escritura todavía no lo son. Está documentado explícitamente en
[`api/interfaces.md`](api/interfaces.md#propertysyncproviderinterface) para que
nadie se lleve una sorpresa.

**`SyncRegistry` descubre capacidades con `method_exists()`.**
`syncByCodeDetailed()` no está en la interfaz; se detecta a mano. El contrato
real es mayor que el declarado. Lo correcto sería una segunda interfaz
opcional; no se tocó para no romper los proveedores existentes.

**Los eventos no cubren las escrituras que Homlity no controla.** Un plugin de
terceros que cree inmuebles con `wp_insert_post()` por su cuenta no dispara
`created` ni `updated`, porque el núcleo no participa en esa escritura. Sí
dispara `status_changed`, que es de nivel WordPress. Está documentado en
[la sección «Cobertura»](api/actions.md#cobertura), con la alternativa correcta.

**Lógica dentro de las plantillas.** `templates/parts/property-gallery.php` son
400 líneas con normalización de datos mezclada con maquetación.
`Property::getImages()` cubre el caso de las extensiones; la plantilla se dejó
intacta porque tocarla es riesgo visual puro sin ganancia de extensibilidad.

**Dos controladores REST de consignación duplicados**, uno en inglés y otro en
español. Ambos se actualizaron para declarar su origen; consolidarlos es un
trabajo aparte con su propia migración.

**Sin `phpcs` configurado.** No hay `phpcs.xml` ni la dependencia de desarrollo
en el repositorio, así que no se pudo ejecutar la comprobación de estándares que
se pedía en las validaciones finales. Se verificó en su lugar sintaxis PHP en
todos los archivos, `composer validate`, las dos suites de pruebas y la ausencia
de restos de depuración. Añadir `squizlabs/php_codesniffer` con el conjunto de
reglas de WordPress es una tarea independiente: el código actual se aparta de
WPCS a propósito en el uso de `camelCase` dentro de `src/`, así que la
configuración tendría que reflejar esa decisión antes de ser útil.

---

## 12. Breaking changes

```
NINGUNO
```

No se renombró, eliminó ni cambió de firma ninguna clase, función, hook,
opción, metadato, tabla ni ruta REST existente.

El único cambio de firma es **aditivo**:
`PropertyUpsertService::upsert(array $normalized, string $origin = 'crm')` ganó
un parámetro opcional. Toda llamada existente se comporta exactamente igual que
antes.

Las 874 pruebas anteriores pasan **sin una sola modificación**, que es la
comprobación que sostiene esta afirmación.

---

## Resultado

Un desarrollador externo puede construir hoy `mi-crm-homlity/` como plugin
independiente:

- sin modificar `homlity-plugin`;
- usando exclusivamente `Homlity\Developer\` y los hooks `homlity/…`;
- con la compatibilidad comprobada antes de que su código se ejecute;
- con sus errores aislados del sitio;
- leyendo <https://homlity.com/desarrolladores/>.

Y el núcleo comercial de Homlity sigue siendo interno, refactorizable, y
desacoplado de todo lo que se construya encima.
