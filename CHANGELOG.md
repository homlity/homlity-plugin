# Changelog

Todos los cambios relevantes de **Homlity Real Estate** se documentan en este
archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y
el versionado sigue [Semantic Versioning](https://semver.org/lang/es/).

La Developer API se versiona por separado. Ver
[SemVer](docs/developers/versioning/semver.md).

---

## [No publicado]

---

## [2.8.0] - 2026-08-23

**Developer API 1.0.0.** Homlity Real Estate pasa a exponer una API pública y
estable sobre la que se pueden construir extensiones externas sin modificar el
núcleo.

**Sin cambios que rompan compatibilidad.** Ninguna clase, función, hook,
opción, metadato ni tabla existente ha cambiado de nombre, de firma o de
comportamiento.

### Added

#### Developer API

- Nuevo namespace público **`Homlity\Developer\`**, mapeado a `src/Developer/`.
  Es el único namespace del que una extensión externa debe depender; todo lo
  demás es interno.
- Nueva constante `HOMLITY_API_VERSION` (`1.0.0`) y `HOMLITY_DEVELOPER_NAMESPACE`.

#### Actions

Doce acciones públicas, todas declaradas como constantes en
`Homlity\Developer\Support\Hooks`:

- `homlity/loaded` — el núcleo terminó de registrar sus servicios.
- `homlity/extensions/register` — ventana de registro de extensiones.
- `homlity/extension/registered` — una extensión concreta arrancó.
- `homlity/extension/failed` — una extensión fue rechazada o falló al arrancar.
- `homlity/extensions/registered` — todas las extensiones arrancaron.
- `homlity/initialized` — post types, taxonomías y reescrituras listos.
- `homlity/property/created` — se creó un inmueble, con todo ya escrito.
- `homlity/property/updated` — se actualizó un inmueble, con el diff de campos.
- `homlity/property/deleted` — un inmueble va a borrarse, y todavía se puede leer.
- `homlity/property/synchronized` — un origen externo escribió un inmueble.
- `homlity/property/status_changed` — cambió el estado de publicación.
- `homlity/property/images_changed` — cambió la galería.

Los eventos de inmueble se disparan **después** de escribir post, metadatos,
taxonomías, galería y asesor, nunca a mitad. Es la diferencia con `save_post`,
que dispara antes de que Homlity haya escrito nada de eso.

#### Filters

- `homlity/property/normalized` — la carga canónica antes de guardarse. Corre
  antes de la validación, de modo que un filtro puede completar un campo
  obligatorio que el CRM omitió.
- `homlity/property/data` — los datos con los que se construye un `Property`.
- `homlity/property/query_args` — los argumentos de `WP_Query` de la búsqueda y
  los listados.
- `homlity/extension/is_compatible` — si una extensión se considera compatible.

#### Clases

- `Homlity\Developer\Homlity` — la fachada de toda la API.
- `Homlity\Developer\Api` — versiones y comprobaciones de entorno.
- `Homlity\Developer\Extension\ExtensionRegistry` — censo de extensiones.
- `Homlity\Developer\Extension\Requirements` — requisitos declarativos.
- `Homlity\Developer\Models\Property` — la representación estable de un inmueble.
- `Homlity\Developer\Models\Money` — importe con moneda.
- `Homlity\Developer\Models\Location` — ubicación, con la dirección oculta
  respetada.
- `Homlity\Developer\Models\Image` — una imagen de la galería.
- `Homlity\Developer\Models\Agent` — el asesor.
- `Homlity\Developer\Events\PropertyContext` — quién escribió y por qué.
- `Homlity\Developer\Events\PropertyChanges` — qué campos cambiaron.
- `Homlity\Developer\Services\PropertyRepository` — buscar inmuebles.
- `Homlity\Developer\Support\Hooks` — nombres de hook como constantes.
- `Homlity\Developer\Support\Deprecated` — mecanismo de deprecación.

#### Interfaces

- `Homlity\Developer\Contracts\ExtensionInterface` — el contrato de una
  extensión.
- `Homlity\Developer\Contracts\PropertySyncProviderInterface` — publica bajo el
  namespace estable el contrato de sincronización bajo demanda que el plugin
  envía desde la 2.4.
- `Homlity\Developer\Contracts\CrmAdapterInterface` — íd. para el contrato de
  adaptadores de CRM, en uso desde la 2.6.

Las implementaciones existentes de los contratos internos siguen funcionando
sin cambios: los públicos los extienden, no los sustituyen.

#### Helpers globales

`homlity_version()`, `homlity_api_version()`, `homlity_is_available()`,
`homlity_is_version_supported()`, `homlity_extensions()`,
`homlity_register_extension()`, `homlity_properties()`,
`homlity_get_property()`.

Se cargan antes de `plugins_loaded` para que un plugin que se cargue después
pueda llamarlos desde su propio arranque.

#### Sistema de extensiones

- Registro de extensiones con validación de slug, detección de duplicados,
  comprobación de requisitos y aislamiento de errores: una extensión que lanza
  al arrancar se convierte en un fallo reportado, no en un sitio caído.
- Requisitos declarativos por versión de Homlity, de la API, de PHP, de
  WordPress y por plugins activos, con motivos legibles en castellano.

#### Documentación

- `docs/developers/` — 24 documentos: introducción, requisitos, instalación,
  arquitectura, guía de extensiones, referencia completa de la API, modelo
  `Property`, integración, SDKs, versionamiento y open source.
- `docs/examples/basic-extension/` — extensión de ejemplo funcional, con
  pruebas automatizadas que la ejecutan en cada cambio del plugin para que no
  se quede atrás de la documentación.
- `docs/architecture/current-architecture.md` y
  `docs/architecture/extensibility-audit.md` — documentación interna.
- `README.md`, `CONTRIBUTING.md`, `SECURITY.md` y este `CHANGELOG.md`.

### Changed

- `PropertyUpsertService::upsert()` acepta un segundo parámetro opcional
  `$origin`, que describe quién escribe para que los hooks públicos puedan
  distinguir una sincronización de CRM de una consignación. Las llamadas
  existentes siguen funcionando: el valor por defecto es `'crm'`.
- La cabecera del plugin declara ahora `Requires at least: 5.8` y
  `Requires PHP: 8.0`.
- Las pruebas usan la versión real de la cabecera del plugin en lugar de la
  cadena `'test'`, que no era comparable con `version_compare()`.

### Fixed

- **Requisitos declarados inconsistentes.** `composer.json` exigía PHP ≥ 8.0,
  `readme.txt` decía PHP 7.4 y la cabecera del plugin no declaraba ninguno de
  los dos. Un sitio con PHP 7.4 podía instalar el plugin y romperse. Los tres
  dicen ahora PHP 8.0 y WordPress 5.8.
- **`.gitignore` incompleto.** No incluía `node_modules/`, `.env` ni
  `.DS_Store`, y listaba `composer.lock` pese a estar versionado. Corregido.
  (El contenido de `node_modules` ya versionado sigue en el índice: sacarlo es
  un commit destructivo que corresponde decidir al mantenedor. Ver
  [la auditoría](docs/architecture/extensibility-audit.md#a-6--node_modules-está-versionado-en-el-repositorio).)

### Security

- **Los datos personales del propietario no salen por la API.** El modelo
  `Property` se construye desde una lista blanca de metadatos, de modo que
  `_property_contact_*`, `_property_identification`, las banderas
  `_consignment_*` y el `_property_sync_payload` —que puede contener tokens del
  CRM— no llegan a las extensiones. Un metadato añadido en el futuro tampoco
  puede colarse sin que alguien lo decida.
- **El diff tampoco los transporta.** `PropertyChanges` excluye explícitamente
  esos campos, para que un hook del ciclo de vida no pueda convertirse en una
  fuga de datos.
- **La dirección oculta sigue oculta.** `Location::getAddress()` devuelve una
  cadena vacía cuando el propietario pidió no publicar la dirección exacta.

Hay pruebas que verifican las tres cosas serializando el modelo y el diff y
comprobando que los valores privados no aparecen.

### Testing

987 pruebas, 3423 aserciones. Las 874 existentes siguen pasando sin
modificación; 113 son nuevas y cubren el registro de extensiones, la
compatibilidad, el modelo `Property`, las acciones y filtros públicos, y la
extensión de ejemplo de la documentación.

### Breaking changes

**NINGUNO.**

---

## [2.7.10] - 2026-08-20

Ver [`readme.txt`](readme.txt) para el detalle de esta y las versiones
anteriores.

## [2.7.9]

### Fixed
- El widget «Destacados por ubicación y tipo» salía sin estilos en la web
  pública: no declaraba la hoja donde vive su CSS y Elementor sólo carga lo que
  cada widget pide.

## [2.7.8]

### Added
- Interruptor «Mostrar en los listados de asesores del sitio» en el perfil de
  cada asesor, con el filtro `homlity_agent_is_publicly_listed`.

### Fixed
- El widget «Asesores con inmuebles disponibles» salía sin estilos en la web
  pública.
- El widget no enseñaba la foto ni el teléfono de los asesores sincronizados
  desde un CRM.

### Changed
- La consulta de asesores del widget pasa a `AvailableAgentsService`, con
  desempate por nombre para que el orden deje de cambiar entre visitas.

## [2.7.7]

### Added
- El botón «Descargar ficha técnica» muestra el progreso mientras se compone el
  PDF.

### Fixed
- Cuando el servidor devolvía una página de error en lugar del PDF, el
  navegador la guardaba con extensión `.pdf`.

## [2.7.1]

### Fixed
- **Crítico.** Cuando WordPress rechazaba el guardado de un inmueble venido de
  un CRM, el servicio escribía metadatos, taxonomías y asesor sobre el post con
  ID 1 del sitio: el `WP_Error` se casteaba a entero antes de comprobarlo, y un
  objeto casteado a entero vale 1.
- La caché del módulo de homologación sólo se invalidaba a medias al escribir.
- En las FAQ automáticas, el valor de administración no heredaba la moneda de
  la venta cuando no tenía una propia.
- La descripción del inmueble insertaba la página entera del constructor con
  Elementor activo. Nuevo filtro `homlity_property_description`.

### Changed
- La ficha técnica en PDF se rehace con el diseño de la ficha del sistema y usa
  el color corporativo de SEO & GEO. Una ficha completa baja de cuatro páginas
  a tres.

### Removed
- El widget «Inmuebles relacionados» de Elementor, Divi y WPBakery. El widget
  «Listado de inmuebles» cubre lo mismo con su modo de consulta «Inmuebles
  relacionados al inmueble de la página». Donde el widget ya esté colocado hay
  que sustituirlo a mano.

## [2.7.0]

### Added
- El botón «Ficha técnica» descarga el PDF, con un control «Acción» para seguir
  abriendo la ficha en el sitio.
- Filtro `homlity_technical_sheet_pdf_available`.
- Origen de consulta «Inmuebles del asesor de la página» en el listado.

### Fixed
- Con Elementor, varias páginas del plugin se servían sin su hoja de estilos.

## [2.6.0]

### Changed
- El perfil público del asesor pasa a `/author/{asesor}/`. La ruta anterior
  `/property-agent/{asesor}/` redirige con 301.

### Added
- Filtros `homlity_agent_profile_use_author_url` y `homlity_user_is_agent`.

## [2.5.0]

### Added
- Ficha técnica editable con Elementor, Divi y WPBakery, servida en
  `/ficha-tecnica/{inmueble}/`.

### Fixed
- El orden «Precio: menor a mayor» no ordenaba nada: WordPress resolvía
  `meta_value_num` contra el filtro de disponibilidad en lugar del precio.
- La búsqueda por código y por palabra clave podía no devolver resultados en
  MySQL 5.7+ con `ONLY_FULL_GROUP_BY`.

---

[No publicado]: https://github.com/homlity/homlity-plugin/compare/v2.8.0...HEAD
[2.8.0]: https://github.com/homlity/homlity-plugin/releases/tag/v2.8.0
[2.7.10]: https://github.com/homlity/homlity-plugin/releases/tag/v2.7.10
