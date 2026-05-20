# Prompt técnico para generar un paquete de integración CRM

Usa este prompt para crear un nuevo adapter CRM en Homlity Plugin.

---

Actúa como arquitecto backend senior de WordPress y PHP.

Contexto del proyecto:
- Plugin: `wp-content/plugins/homlity-real-estate`
- Arquitectura de integraciones CRM ya existe en:
  - `src/Integrations/CRM/Contracts/CrmAdapterInterface.php`
  - `src/Integrations/CRM/CrmIntegrationManager.php`
  - `src/Integrations/CRM/PropertyUpsertService.php`
  - `src/Integrations/CRM/FieldMap/PropertyFieldSchema.php`
  - `src/Services/CrmIntegrationService.php`

Objetivo:
Implementar un nuevo paquete de integración para el proveedor: `{PROVIDER_NAME}`
con key interna `{provider_key}`.

Requisitos obligatorios:
1. Crear adapter en ruta:
   `src/Integrations/CRM/Adapters/{ProviderClass}/{ProviderClass}Adapter.php`
2. Implementar `CrmAdapterInterface`.
3. Mapear payload del CRM al esquema normalizado de inmueble Homlity.
4. Soportar aliases de campos frecuentes (es/en) y listas multi-valor.
5. Incluir validaciones mínimas para campos obligatorios:
   - `external.source`
   - `external.id`
   - `post.title`
6. No romper compatibilidad con integraciones existentes.
7. Registrar el adapter en `CrmIntegrationManager`.
8. Añadir documentación en `docs/crm-integration-spec.md` con:
   - estructura del payload esperado
   - tabla de mapeo campo-origen -> campo-normalizado -> meta/tax destino
9. Añadir ejemplos de request para:
   - sync manual (`/crm/sync/{provider}`)
   - webhook (`/crm/webhook/{provider}`)
10. Entregar tests unitarios o, si no existe framework de tests, al menos un archivo de ejemplos JSON de entrada/salida normalizada.

Esquema normalizado objetivo (usar exactamente estos bloques):
- `external`
- `post`
- `location`
- `pricing`
- `metrics`
- `taxonomy`
- `media`
- `advisor`

Criterios de calidad:
- Código PSR-12
- Métodos pequeños y legibles
- Sanitización de entradas
- Nombres consistentes en inglés técnico
- Comentarios solo cuando aporten contexto de negocio

Entregables:
1. Lista de archivos creados/modificados.
2. Código final completo.
3. Resumen técnico de decisiones.
4. Ejemplo de payload real del CRM transformado al esquema normalizado.

Además:
- Si el CRM expone SDK oficial, diseña una clase `Client` aislada para API calls futuras, pero mantén este primer entregable enfocado en mapeo + upsert.
---
