# Arquitectura actual de Homlity Real Estate

Estado del plugin en la versión **2.8.0**. Este documento describe cómo
funciona el núcleo por dentro. No es documentación para desarrolladores de
extensiones — para eso está [`docs/developers/`](../developers/README.md) —
sino el mapa que necesita quien vaya a tocar el propio plugin.

---

## 1. Identidad

| | |
| --- | --- |
| Nombre | Homlity Real Estate |
| Slug | `homlity-real-estate` |
| Archivo principal | `plugin-inmobiliario.php` |
| Namespace interno | `Homlity\PluginInmobiliario\` → `src/` |
| Namespace público | `Homlity\Developer\` → `src/Developer/` |
| Licencia | GPLv2 o posterior |
| PHP mínimo | 8.0 |
| WordPress mínimo | 5.8 |

---

## 2. Arranque

El arranque es explícito y está todo en `plugin-inmobiliario.php`. No hay
contenedor de servicios ni descubrimiento automático: la lista de servicios se
declara a mano en `Core\PluginBootstrap::init()`.

```mermaid
flowchart TD
    A["plugin-inmobiliario.php<br/>(carga del plugin)"] --> B["fatal-bootstrap.php<br/>captura de fatales tempranos"]
    B --> C["Constantes HOMLITY_*"]
    C --> D["spl_autoload_register<br/>Homlity\\Developer\\ → src/Developer/<br/>Homlity\\PluginInmobiliario\\ → src/"]
    D --> E["src/Developer/functions.php<br/>helpers públicos"]
    E --> F["vendor/autoload.php<br/>(Guzzle, Dompdf)"]

    F --> G20["plugins_loaded : 20<br/>PluginBootstrap::init()"]
    G20 --> G20a["~50 servicios → register()"]
    G20a --> G20b["do_action('homlity/loaded')"]

    G20b --> G25["plugins_loaded : 25<br/>DeveloperApiService"]
    G25 --> G25a["do_action('homlity/extensions/register')"]
    G25a --> G25b["ExtensionRegistry::bootAll()"]
    G25b --> G25c["do_action('homlity/extensions/registered')"]

    G25c --> G30["plugins_loaded : 30<br/>SyncRegistry::dispatch()"]
    G30 --> G30a["do_action('homlity_plugin_register_sync_providers')"]

    G30a --> G35["plugins_loaded : 25/30/35<br/>Schema · Elementor FAQ · Consignación"]

    G35 --> I10["init : 10<br/>CPT, taxonomías, reescrituras"]
    I10 --> I99["init : 99<br/>VersionService::maybeUpgrade()"]
    I99 --> I100["init : 100<br/>do_action('homlity/initialized')"]
```

Los módulos de `includes/` (schema, Elementor FAQ, consignación) usan clases
con prefijo `Homlity_` y `require_once` explícito, no PSR-4. Son anteriores a
la reorganización en `src/` y siguen la convención antigua de WordPress.

---

## 3. Capas

```mermaid
flowchart TB
    subgraph pub["Homlity\\Developer — API pública, estable en 1.x"]
        P1["Homlity (fachada)"]
        P2["Contracts/"]
        P3["Models/ — Property, Money, Location, Image, Agent"]
        P4["Events/ — PropertyContext, PropertyChanges"]
        P5["Extension/ — ExtensionRegistry, Requirements"]
        P6["Services/PropertyRepository"]
        P7["Support/ — Hooks, Deprecated"]
    end

    subgraph int["Homlity\\PluginInmobiliario — interno, sin garantía"]
        C1["Core/ — PluginBootstrap, DeveloperApiService,<br/>PropertyEventDispatcher, ScheduledHooks"]
        C2["Services/ — ~60 servicios de dominio y de WordPress"]
        C3["Integrations/ — CRM, Elementor, Divi, WPBakery, CF7, shortcodes"]
        C4["Homologation/ — mapeo canónico entre CRMs"]
        C5["Listing/ — configuración y render de listados"]
        C6["ErrorReporting/ — telemetría de errores"]
    end

    subgraph leg["includes/ — módulos con prefijo Homlity_"]
        L1["schema/ — JSON-LD"]
        L2["consignment/ — formulario público"]
        L3["elementor/ — widget FAQ"]
    end

    ext["Extensiones de terceros"] --> pub
    pub --> int
    leg --> int
    int --> wp["WordPress"]
```

La regla es de una sola dirección: las extensiones dependen de
`Homlity\Developer\`, y `Homlity\Developer\` es lo único que el núcleo se
compromete a no romper.

---

## 4. Modelo de datos

### Custom post types

| Post type | Clase | Para qué |
| --- | --- | --- |
| `property` | `Services\PropertyPostType` | El inmueble |
| `homlity_locality` | `Services\LocalityPostType` | Localidades y sus barrios |

### Taxonomías del inmueble

Todas en `Services\PropertyTaxonomies`:

`property_type`, `property_operation`, `property_location`,
`property_category`, `property_tag`, `property_feature`, `property_country`,
`property_state`, `property_city`, `property_neighborhood`, `property_nearby`,
`property_condition`.

`property_location` es una taxonomía plana que recoge todos los términos
geográficos del inmueble a la vez, para poder consultar por cualquier nivel sin
saber cuál es.

### Post meta

Alrededor de 55 claves, todas con prefijo `_property_` o `_consignment_`. La
lista completa vive en `PropertyPostType::$metaKeys` y su correspondencia con
el esquema canónico en `Integrations\CRM\FieldMap\PropertyFieldSchema::metaMap()`.

### Tablas propias

| Tabla | Repositorio | Contenido |
| --- | --- | --- |
| `{prefix}homlity_homologation` | `Homologation\HomologationRepository` | Mapeo CRM → término canónico |
| `{prefix}homlity_sync_index` | `Integrations\CRM\Repository\SyncIndexRepository` | `(source, external_id) → post_id` |
| `{prefix}homlity_sync_jobs` | `PullSyncJobRepository` | Estado de los trabajos de descarga |
| `{prefix}homlity_webhook_events` | `WebhookEventRepository` | Eventos recibidos por webhook |
| `{prefix}homlity_property_visits` | `Services\PropertyVisitTrackingService` | Analítica de visitas |
| `{prefix}homlity_property_contact_clicks` | `PropertyContactClickTrackingService` | Clics de contacto |
| `{prefix}homlity_property_sheet_downloads` | `PropertyTechnicalSheetDownloadTrackingService` | Descargas de ficha |

### Opciones

Prefijo `homlity_` (o `homlity_plugin_`). Las principales:
`homlity_plugin_settings`, `homlity_plugin_version`,
`homlity_plugin_crm_integrations`, `homlity_plugin_crm_sync_queue`,
`homlity_plugin_crm_sync_logs`, `homlity_seo_settings`,
`homlity_schema_settings`, `homlity_llms_full`,
`homlity_error_reporter_queue`.

---

## 5. Ciclo de vida de un inmueble

Hay exactamente dos caminos por los que un inmueble se escribe entero, y ambos
terminan disparando las acciones públicas.

```mermaid
sequenceDiagram
    participant CRM as CRM externo
    participant REST as CrmIntegrationService<br/>(REST / webhook / cola)
    participant AD as Adaptador de CRM
    participant UP as PropertyUpsertService
    participant WP as WordPress
    participant EV as PropertyEventDispatcher
    participant EXT as Extensión

    CRM->>REST: POST /homlity/v1/crm/webhook
    REST->>AD: mapRecordToNormalized()
    AD-->>REST: carga canónica
    REST->>UP: upsert(normalized, 'crm')
    UP->>UP: apply_filters('homlity/property/normalized')
    UP->>UP: snapshot del estado anterior
    UP->>WP: wp_insert_post / wp_update_post
    UP->>WP: meta · media · asesor · taxonomías · homologación
    UP->>EV: dispatchSaved()
    EV->>EXT: homlity/property/created | updated
    EV->>EXT: homlity/property/synchronized
    EV->>EXT: homlity/property/images_changed (si cambió la galería)
```

El camino de wp-admin es análogo: `save_post_property` →
`PropertyPostType::saveMeta()`, que valida el nonce, escribe todo y llama al
mismo despachador con origen `admin`. `saveMeta()` sale temprano cuando no hay
nonce de formulario, y por eso una escritura de CRM no dispara los hooks dos
veces.

Borrado y cambio de estado no pasan por ninguno de los dos: se enganchan a
`before_delete_post` y `transition_post_status` desde `DeveloperApiService`, de
modo que se detectan escriba quien escriba.

---

## 6. Integraciones

### CRM

`Integrations/CRM/` es el subsistema más grande.

- `CrmIntegrationManager` — registro de adaptadores. Dispara
  `homlity_crm_register_adapters` y filtra por `homlity_crm_adapters`.
- `CrmAdapterInterface` — un adaptador sólo traduce; no escribe.
- `PropertyUpsertService` — la única escritura de inmuebles del subsistema.
- `CrmSyncQueueService` — cola en una opción, procesada por WP-Cron
  (`homlity_plugin_crm_process_queue`).
- `Services/WebhookAuthenticator` — verificación de firma de los webhooks.
- `Services/VersionedPropertySchemaValidator` — validación de la carga canónica.
- `Services/MediaSyncService` — descarga de imágenes.
- `Services/AdvisorSyncService` — resuelve o crea el usuario asesor.

### Sincronización bajo demanda

`Services\SyncRegistry` + `Contracts\SyncProviderInterface`. Cuando alguien
abre `/inmueble/{CODIGO}` y el inmueble no existe en local,
`PropertyCodeRoutingService` recorre los proveedores registrados por orden de
prioridad hasta que uno lo crea, y redirige 301 a la URL canónica.

### Constructores de página

Elementor, Divi y WPBakery tienen cada uno ~25 widgets que comparten
maquetación a través de `templates/parts/`. Cada `*IntegrationService` es un
no-op cuando su constructor no está instalado.

### Otras

Contact Form 7, Elementor Pro Forms, Ninja Forms (override de WhatsApp),
Polylang y WPML (registro del post type), y once shortcodes.

---

## 7. Superficie HTTP

### REST

| Namespace | Alcance |
| --- | --- |
| `homlity/v1` | CRM: webhooks, sincronización, cola, esquema, logs |
| `homlity-real-estate/v1` | Editor de inmuebles, ajustes, analítica, versiones, diagnóstico |
| Homologación | `homologation/*` — mapeos, fuentes, estadísticas |
| Consignación | `free/v1/*` y `v1/*` — formulario público y datos geográficos |

### AJAX

`homlity_listing` (con y sin sesión) y `homlity_track_contact_click`.

### WP-Cron

`homlity_plugin_crm_process_queue`, `homlity_error_reporter_deliver`,
`homlity_purge_orphan_analytics`. Todos declarados en `Core\ScheduledHooks`,
que los desprograma al desactivar el plugin.

### WP-CLI

No hay comandos propios. `ErrorEventFactory` sólo detecta si la petición viene
de WP-CLI para etiquetar el contexto del error.

---

## 8. Seguridad

- Todos los archivos empiezan con la guarda `if (!defined('ABSPATH')) exit;`.
- Las escrituras de wp-admin verifican nonce y `current_user_can('edit_post')`.
- Los webhooks de CRM se autentican con firma (`WebhookAuthenticator`).
- Las rutas REST administrativas exigen capacidad; las públicas están acotadas
  al formulario de consignación y llevan límite de tasa
  (`homlity_consignment_rate_limit_allowed`).
- Las capacidades propias las gestiona `Services\CapabilityService`, con el rol
  de asesor y su equivalente heredado.
- El reporter de errores sanea el contexto antes de enviarlo
  (`ErrorReporting\ErrorSanitizer`) y sólo publica claves permitidas.

---

## 9. Internacionalización

Text domain `homlity-real-estate`, cargado en `init`. Traducciones en
`languages/`. Los mensajes del código están en español; los identificadores,
en inglés.

---

## 10. Actualizaciones

`Services\VersionService` compara `HOMLITY_PLUGIN_VERSION` con la opción
`homlity_plugin_version` en `init` con prioridad 99, ejecuta las migraciones
necesarias y refresca las reglas de reescritura.

`Services\HomlityPluginVersionsService` habla con el servicio de licencias de
Homlity para ofrecer actualizaciones de los plugins del ecosistema desde el
escritorio.

---

## 11. Dependencias

Composer: `guzzlehttp/guzzle` (HTTP hacia servicios externos) y `dompdf/dompdf`
(ficha técnica en PDF). En desarrollo, `phpunit/phpunit` 10.5.

Las pruebas no necesitan una instalación de WordPress: `tests/bootstrap.php`
define las constantes y `tests/Support/wp-functions.php` provee los stubs de
las funciones usadas.
