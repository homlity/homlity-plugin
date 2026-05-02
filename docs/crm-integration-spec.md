# Homlity CRM Integration Spec (Backend)

## Objetivo
Estandarizar la integración de uno o varios CRM/SDK para sincronizar inmuebles al CPT `property` de Homlity.

## Arquitectura
- `src/Integrations/CRM/Contracts/CrmAdapterInterface.php`
- `src/Integrations/CRM/CrmIntegrationManager.php`
- `src/Integrations/CRM/PropertyUpsertService.php`
- `src/Integrations/CRM/FieldMap/PropertyFieldSchema.php`
- `src/Integrations/CRM/Support/*`
- `src/Integrations/CRM/Adapters/<Provider>/<Provider>Adapter.php`
- `src/Services/CrmIntegrationService.php` (REST API)

## Flujo
1. CRM envía payload a `POST /wp-json/homlity/v1/crm/webhook/{provider}`
2. Servicio valida `x-homlity-integration-key`
3. `CrmIntegrationManager` resuelve adapter
4. Adapter normaliza el payload al esquema estándar
5. `PropertyUpsertService` crea/actualiza inmueble
6. Se guardan trazas de sincronización por meta

## Endpoints
- `GET /wp-json/homlity/v1/crm/providers`
- `GET /wp-json/homlity/v1/crm/schema/property`
- `POST /wp-json/homlity/v1/crm/sync/{provider}` (manual)
- `POST /wp-json/homlity/v1/crm/webhook/{provider}` (token header)

## Seguridad
Configurar opción:
- `homlity_plugin_crm_integrations[provider][webhook_key]`

Enviar header:
- `x-homlity-integration-key: <secret>`

## Modelo normalizado de inmueble
Bloques:
- `external`: source, id, updated_at, raw
- `post`: title, description, short_description, status
- `location`: address, latitude, longitude
- `pricing`: sale/rent/admin + monedas + admin_included
- `metrics`: area*, bedrooms, bathrooms, parking, condition, year_built, code, featured
- `taxonomy`: operation, type, category, tag, feature, country, state, city, neighborhood, nearby
- `media`: gallery, featured_image
- `advisor`: email, phone, user_id

## Mapeo a WordPress
- Post:
  - `post_title`, `post_content`, `post_excerpt`, `post_status`
- Meta externos:
  - `_property_external_source`
  - `_property_external_id`
  - `_property_last_sync_at`
  - `_property_sync_payload`
- Meta de inmueble:
  - ver `PropertyFieldSchema::metaMap()`
- Taxonomías:
  - se resuelven por slug/nombre; si no existen, se crean

## Idempotencia
La clave de upsert es:
- (`_property_external_source`, `_property_external_id`)

## Primer provider: Web Homlity
Adapter:
- `src/Integrations/CRM/Adapters/WebHomlity/WebHomlityAdapter.php`

Acepta payload en:
- raíz del JSON
- o dentro de `property`

Soporta aliases de claves en español/inglés (`titulo/title`, `direccion/address`, etc.).
