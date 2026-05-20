# Homlity Plugin: Mapa Técnico + Plan CRM (Fase 1)

Fecha: 2026-05-02
Alcance de este documento:
1. Mapa actual del plugin.
2. Plan de cambios para dos tipos de integraciones CRM.
3. Inicio de Fase 1 sin tocar integraciones reales (solo estándar y arquitectura base).

---

## 1) Mapa del plugin actual

### 1.1 Archivos/entrypoints principales
- Bootstrap plugin: `plugin-inmobiliario.php`
- Bootstrap de servicios: `src/Core/PluginBootstrap.php`
- Interfaz de servicios: `src/Core/Contracts/ServiceInterface.php`

### 1.2 Servicios principales registrados
Desde `PluginBootstrap`:
- Core: `I18nService`, `VersionService`, `SettingsService`, `PropertyPostType`, `PropertyTaxonomies`, `AdminMenuService`, `CapabilityService`, `LocationMetaService`, `UserMetaService`, `CurrencyService`, `SeoService`, `SeoIntegrationService`, `TemplateService`
- Listing/API: `PropertyAjaxService`
- Builders: `ElementorIntegrationService`, `WPBakeryIntegrationService`, `DiviIntegrationService`, `ShortcodeIntegrationService`
- CRM (nuevo módulo base): `CrmIntegrationService`, `CrmAdminService`

### 1.3 CPTs
- CPT principal: `property`
  - Registro en: `src/Services/PropertyPostType.php`
  - `public=true`, `show_in_rest=true`, `rewrite= inmbueble`
  - `supports`: title, editor, excerpt, thumbnail, revisions

### 1.4 Taxonomías del inmueble
Definidas en `src/Services/PropertyTaxonomies.php`:
- `property_type`
- `property_operation`
- `property_location`
- `property_category`
- `property_tag`
- `property_feature`
- `property_country`
- `property_state`
- `property_city`
- `property_neighborhood`
- `property_nearby`

### 1.5 Metadatos (meta keys) del inmueble
Definidos en `PropertyPostType::$metaKeys`:
- `_property_price_sale`
- `_property_currency_sale`
- `_property_price_rent`
- `_property_currency_rent`
- `_property_price_admin`
- `_property_currency_admin`
- `_property_area`
- `_property_area_lot`
- `_property_area_private`
- `_property_area_built`
- `_property_bedrooms`
- `_property_bathrooms`
- `_property_parking`
- `_property_condition`
- `_property_age` (usado como año/edad según UI)
- `_property_code`
- `_property_address`
- `_property_latitude`
- `_property_longitude`
- `_property_admin_included`
- `_property_gallery`
- `_property_featured`
- `_property_agent_id`
- `_property_agent_phone`
- `_property_agent_email`

Meta de sincronización CRM (actuales):
- `_property_external_source`
- `_property_external_id`
- `_property_last_sync_at`
- `_property_sync_payload`

### 1.6 Hooks clave (alto nivel)
- Registro plugin: `plugins_loaded` (i18n + bootstrap)
- CPT/tax/meta: `init`, `rest_api_init`, `save_post_property`, `admin_enqueue_scripts`
- Plantillas y rewrites: `query_vars`, `request`, `template_include`, `pre_get_posts`, `template_redirect`
- Listing AJAX: `wp_ajax_homlity_listing`, `wp_ajax_nopriv_homlity_listing`
- CRM queue: `cron_schedules`, `homlity_plugin_crm_process_queue`

### 1.7 Endpoints REST existentes (resumen)
- Settings: `/wp-json/homlity-real-estate/v1/settings`
- Property tools:
  - `/wp-json/homlity-real-estate/v1/property-next-code`
  - `/wp-json/homlity-real-estate/v1/property-geocode`
- Location terms: `/wp-json/homlity-real-estate/v1/location-terms`
- CRM:
  - `/wp-json/homlity/v1/crm/providers`
  - `/wp-json/homlity/v1/crm/schema/property`
  - `/wp-json/homlity/v1/crm/sync/{provider}`
  - `/wp-json/homlity/v1/crm/webhook/{provider}`
  - `/wp-json/homlity/v1/crm/sync-batch/{provider}`
  - `/wp-json/homlity/v1/crm/queue/stats`
  - `/wp-json/homlity/v1/crm/queue/retry-failed`
  - `/wp-json/homlity/v1/crm/logs`

### 1.8 Guardado de inmueble
Principal en `PropertyPostType::saveMeta()`:
- Valida nonces/permisos
- Guarda metas
- Sincroniza taxonomías
- Sincroniza galería
- Sincroniza asesor (id, teléfono, correo)
- Genera código automático por gestión+tipo+consecutivo

### 1.9 Relación inmueble ↔ asesor
- Relación base: `_property_agent_id`
- Datos espejo para render/listado: `_property_agent_phone`, `_property_agent_email`
- Fuentes de perfil asesor: `user_email` + user meta (`phone`, `telefono`, `mobile_phone`, `celular`, `billing_phone`)

---

## 2) Plan de cambios propuesto (dos modos de integración CRM)

## Modo A: Push/Webhook (Web Homlity, SEDI)
Objetivo:
- El paquete externo envía actualizaciones por webhook.
- El paquete realiza homologación antes de enviar o en adapter receptor.
- Debe seguir esquema obligatorio de inmueble y soportar actualización de imágenes.

Cambios propuestos:
1. Contrato "Webhook Adapter Profile" con validación de esquema y versionado (`schema_version`).
2. Endpoint por proveedor con auth robusta (key + firma HMAC opcional).
3. Validación estricta de payload y respuesta por item.
4. Pipeline de imágenes (descarga controlada, deduplicación por hash, set destacada, reordenación).

## Modo B: Pull/API masiva (Wasi, Simi, Domus, Smart Home, Mobilia, otros)
Objetivo:
- El plugin consume APIs externas y sincroniza volúmenes >3000 inmuebles.

Cambios propuestos:
1. Conector HTTP nativo WP (`wp_remote_get/wp_remote_post`) sin `exec` ni shell.
2. Jobs por lotes paginados (cursor/page), con checkpoint reanudable.
3. Cola interna con control de tasa/reintentos/backoff.
4. Estrategia de sincronización incremental (`updated_since`) + reconciliación de borrados.

## Regla técnica obligatoria
- Prohibido usar `exec`, `shell_exec`, `system`, `passthru`, `proc_open` para sincronizar.
- Toda integración debe ejecutarse con APIs nativas de WordPress/PHP y cola interna.

---

## 3) Fase 1 (iniciada en este documento, sin integraciones reales)

Fase 1 = Diseño y estandarización, sin conectar CRM reales aún.

Entregables Fase 1:
1. Definición formal de dos perfiles de integración:
   - `push_webhook`
   - `pull_api_batch`
2. Esquema canónico de inmueble (campos obligatorios/opcionales) y semántica.
3. Especificación de políticas de imágenes (alta/actualización/destacada/orden/eliminación).
4. Política de seguridad (auth, firma, anti-replay básico).
5. Política operativa para lotes >3000 (batch, checkpoint, backoff, límites).
6. Norma de implementación: "no shell/exec".

### 3.1 Esquema canónico mínimo obligatorio
Bloques:
- `external`: `source`, `id`, `updated_at`
- `post`: `title`, `description`, `short_description`, `status`
- `location`: `address`, `latitude`, `longitude`
- `pricing`: venta/arriendo/admin + monedas
- `metrics`: área, alcobas, baños, parqueaderos, etc.
- `taxonomy`: gestión, tipo, ubicación y demás términos
- `media`: galería, destacada
- `advisor`: asesor asignado

### 3.2 Reglas de imágenes (fase 1 especificación)
- Entrada permitida: URLs absolutas HTTPS.
- Mantener orden del CRM.
- Campo para identificar destacada.
- Reemplazo incremental: insertar nuevas, mantener existentes, eliminar ausentes si la estrategia lo define.
- Registrar resultados por imagen en logs.

### 3.3 Política operativa lotes masivos
- Tamaño de lote configurable (default 20).
- Retry por item fallido.
- Reintento de fallidos manual y por endpoint.
- Logs filtrables por proveedor y fecha.

### 3.4 Criterios de salida de Fase 1
- Aprobación del contrato de datos.
- Aprobación del flujo de seguridad.
- Aprobación del flujo de imágenes.
- Aprobación de política anti-shell/anti-exec.

---

## 4) Próxima fase sugerida (Fase 2)
Sin activar CRM real aún:
- Agregar validador de esquema versionado (`schema_version` + errores campo a campo).
- Agregar contrato explícito de imágenes (`media.images[]` con `url`, `position`, `is_featured`, `external_image_id`).
- Agregar estado de job para modo pull (checkpoint `page/cursor`, `updated_since`).


---

## 5) Diagnóstico breve (reutilización, mejoras, riesgos)

### Qué existe y se reutiliza
- Core sólido de inmuebles (`PropertyPostType`, taxonomías, metadatos, guardado).
- Capa REST y hooks ya establecida.
- Primer módulo CRM base ya presente (`CrmIntegrationService`, queue, logs, admin CRM).
- Upsert básico por `source + external_id` con fallback en postmeta.

### Qué debe mejorarse
- Contrato de adapter más completo para soportar push/pull de forma homogénea.
- DTO canónico explícito para desacoplar normalización de persistencia.
- Índice único en tabla dedicada para evitar dependencia exclusiva de `postmeta` en altas volumetrías.
- Evolución de esquema con versionado de infraestructura.

### Riesgos actuales
- Si se depende solo de `postmeta` para identidad externa a gran escala, puede degradar rendimiento.
- Mezcla de responsabilidad entre adapter y persistencia si no existe DTO canónico formal.
- Sin checkpoints fuertes de pull sync, corridas largas pueden perder continuidad.

---

## 6) Arquitectura de carpetas propuesta (incremental)

- `src/Integrations/CRM/Contracts/` (legacy)
- `src/Integrations/CRM/Contracts/V2/` (nuevo contrato empresarial)
- `src/Integrations/CRM/DTO/` (objetos canónicos)
- `src/Integrations/CRM/Repository/` (persistencia/índices)
- `src/Integrations/CRM/Services/` (normalización/validación/orquestación)
- `src/Integrations/CRM/Adapters/<Provider>/` (módulos por CRM)
- `src/Services/CrmInfrastructureService.php` (migración/tabla)

---

## 7) FASE 1 ejecutada en código (sin integraciones reales nuevas)

Cambios implementados:
1. Contrato empresarial V2:
   - `src/Integrations/CRM/Contracts/V2/HomlityCrmAdapterInterface.php`
2. DTOs canónicos:
   - `src/Integrations/CRM/DTO/NormalizedProperty.php`
   - `src/Integrations/CRM/DTO/CrmPageResult.php`
   - `src/Integrations/CRM/DTO/IntegrationTestResult.php`
3. Esquema canónico de referencia:
   - `src/Integrations/CRM/Services/CanonicalPropertySchema.php`
4. Índice técnico `source_key + external_id`:
   - `src/Integrations/CRM/Repository/SyncIndexRepository.php`
   - tabla: `{prefix}homlity_sync_index` con índice único compuesto
5. Servicio de infraestructura/migración:
   - `src/Services/CrmInfrastructureService.php`
   - registrado en `src/Core/PluginBootstrap.php`
6. Upsert mejorado para identidad e idempotencia con índice:
   - `src/Integrations/CRM/PropertyUpsertService.php`
   - búsqueda primero en tabla índice, luego fallback a `postmeta`
   - upsert del índice tras sincronizar

Compatibilidad preservada:
- No se removió ningún flujo de creación/edición manual de inmuebles.
- No se eliminaron endpoints/herramientas previas.
- No se agregaron integraciones reales nuevas en esta fase.

---

## 8) FASE 2 ejecutada en código (demo + webhook genérico)

Cambios implementados:
1. Adapter demo aislado:
   - `src/Integrations/CRM/Adapters/Demo/DemoCrmAdapter.php`
   - source key: `homlity_demo`
   - permite validar normalización, upsert y cola sin conectar CRM real.
2. Registro del adapter demo:
   - `src/Integrations/CRM/CrmIntegrationManager.php`
3. Tabla de eventos webhook:
   - `src/Integrations/CRM/Repository/WebhookEventRepository.php`
   - tabla: `{prefix}homlity_webhook_events`
   - índice único: `source_key + event_id`
4. Migración de infraestructura:
   - `src/Services/CrmInfrastructureService.php`
   - versión de esquema CRM: `2`
5. Endpoint webhook genérico:
   - `POST /wp-json/homlity/v1/crm/webhook`
   - recibe `source_key`, `event_id`, `event_type` y `property`
   - valida token por `x-homlity-integration-key` o `Authorization: Bearer`
   - registra evento y encola procesamiento.
6. Cola con metadatos de webhook:
   - `src/Integrations/CRM/CrmSyncQueueService.php`
   - marca eventos como `processing`, `done` o `failed`.

Ejemplo payload demo:

```json
{
  "source_key": "homlity_demo",
  "event_id": "demo-evt-001",
  "event_type": "property.updated",
  "property": {
    "external_id": "DEMO-1001",
    "title": "Apartamento demo en Bogota",
    "description": "Descripcion de prueba",
    "property_type": "apartamento",
    "management_type": "venta",
    "price": "600000000",
    "currency": "COP",
    "area_built": "133",
    "bedrooms": 4,
    "bathrooms": 4,
    "garages": 1,
    "city": "Bogota",
    "neighborhood": "Altos de Cabecera",
    "address": "Direccion demo",
    "latitude": "4.710989",
    "longitude": "-74.072092"
  }
}
```

Compatibilidad preservada:
- El webhook genérico es adicional; no reemplaza endpoints existentes.
- El adapter demo no toca Web Homlity, Sedi ni CRMs reales.
- Los eventos duplicados devuelven `duplicate: true` y no vuelven a encolarse.

---

## 9) FASE 3 core ejecutada (puntos de extensión PUSH, sin integraciones reales)

Alcance aplicado:
- No se implementó Web Homlity, Sedi ni ningún CRM externo dentro del core.
- El core queda preparado para que paquetes externos registren adapters y usen servicios centrales.

Cambios implementados:
1. Registro externo de adapters:
   - `src/Integrations/CRM/CrmIntegrationManager.php`
   - hooks:
     - `do_action('homlity_crm_register_adapters', $manager)`
     - `apply_filters('homlity_crm_adapters', $adapters)`
2. Autenticación webhook central:
   - `src/Integrations/CRM/Services/WebhookAuthenticator.php`
   - soporta:
     - `x-homlity-integration-key`
     - `Authorization: Bearer <token>`
     - HMAC con `x-homlity-timestamp` + `x-homlity-signature`
3. Schema documentable para payload PUSH:
   - `src/Integrations/CRM/Services/WebhookPayloadSchema.php`
   - endpoint: `GET /wp-json/homlity/v1/crm/schema/webhook`
4. Servicios centrales preparados:
   - `src/Integrations/CRM/Services/MediaSyncService.php`
   - `src/Integrations/CRM/Services/AdvisorSyncService.php`
5. Admin CRM admite secreto HMAC por proveedor:
   - `src/Services/CrmAdminService.php`

Notas para paquetes externos:
- Cada paquete externo debe implementar/registrar su adapter.
- El paquete debe homologar datos propios antes de llegar al upsert.
- El core no contiene lógica específica de CRMs reales.
- Para PUSH con imágenes, el paquete puede delegar en `MediaSyncService` cuando la descarga sea permitida.
- Para PULL, los paquetes deben guardar URLs y procesar por lotes; no descargar imágenes masivamente desde el core.

---

## 10) Fase de validación y contrato PULL ejecutada

Cambios implementados:
1. Validador de esquema versionado:
   - `src/Integrations/CRM/Services/VersionedPropertySchemaValidator.php`
   - DTO resultado: `src/Integrations/CRM/DTO/SchemaValidationResult.php`
   - schema actual: `1.0`
   - endpoint: `POST /wp-json/homlity/v1/crm/schema/validate`
2. Contrato explícito de imágenes:
   - `media.images[]`
   - campos:
     - `url`
     - `external_url` (alias legacy)
     - `external_image_id`
     - `title`
     - `alt`
     - `position`
     - `order` (alias legacy)
     - `is_featured`
     - `hash`
     - `checksum`
3. Estado de job para modo PULL:
   - `src/Integrations/CRM/Repository/PullSyncJobRepository.php`
   - tabla: `{prefix}homlity_sync_jobs`
   - campos clave:
     - `source_key`
     - `job_id`
     - `status`
     - `cursor_json`
     - `updated_since`
     - `batch_size`
     - `lock_token`
     - `locked_until`
     - `attempts`
     - `last_error`
   - endpoint: `GET /wp-json/homlity/v1/crm/pull/job-state/{provider}`
4. Infraestructura actualizada:
   - `src/Services/CrmInfrastructureService.php`
   - schema CRM interno version `3`

Alcance:
- No ejecuta sincronizaciones reales.
- No consume APIs externas.
- Deja listo el contrato para que paquetes PULL externos trabajen con cursor/checkpoint.
