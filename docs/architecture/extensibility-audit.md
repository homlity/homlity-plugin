# Auditoría de extensibilidad

Revisión del código de Homlity Real Estate con una sola pregunta: **¿qué le
impide hoy a un desarrollador externo construir una integración sin tocar el
núcleo?**

Fecha: agosto de 2026. Versión auditada: 2.7.10. Versión con las correcciones
aplicadas: 2.8.0.

Cada hallazgo lleva un estado:

- **Resuelto en 2.8.0** — arreglado en este mismo trabajo.
- **Abierto** — identificado, documentado, no abordado.
- **Aceptado** — es así a propósito; se explica por qué.

---

## Resumen

| Severidad | Hallazgos | Resueltos |
| --- | --- | --- |
| Crítica | 3 | 3 |
| Alta | 6 | 5 |
| Media | 7 | 3 |
| Baja | 4 | 2 |

---

## Crítica

### C-1 · No existía ningún evento del ciclo de vida de un inmueble
**Estado: resuelto en 2.8.0.**

El inventario de hooks tenía 17 `do_action`, y ninguno se disparaba al crear,
actualizar o borrar un inmueble. Una integración con un portal externo no tenía
forma de enterarse de un cambio salvo enganchándose a `save_post` de WordPress
—que dispara **antes** de que Homlity escriba las metas, las taxonomías y las
imágenes—, con lo que recibía inmuebles a medio guardar: sin precio, sin fotos
y sin ubicación.

Resuelto con seis acciones públicas que se disparan cuando la escritura ya
terminó del todo: `homlity/property/created`, `updated`, `deleted`,
`synchronized`, `status_changed` e `images_changed`.

### C-2 · La galería de un inmueble se guarda en cuatro formatos distintos
**Estado: resuelto en 2.8.0** (encapsulado; el almacenamiento no cambió).

`_property_gallery` puede contener:

| Origen | Formato |
| --- | --- |
| Editor de wp-admin | CSV de IDs de adjunto: `"12,45,77"` |
| Sincronización de CRM | Array de URLs absolutas |
| Algunos adaptadores | Array de arrays `['url' => …]` |
| Instalaciones antiguas | Cadena JSON |

`templates/parts/property-gallery.php` dedica 50 líneas a distinguirlas. Cada
extensión que quisiera leer la galería tendría que copiar esas 50 líneas, y
quedarse desactualizada en cuanto apareciera un quinto formato.

Resuelto encapsulándolo en `Property::getImages(): Image[]`, que absorbe las
cuatro formas. El almacenamiento se dejó exactamente como estaba: normalizarlo
habría sido una migración destructiva sin ganancia para el usuario final.

### C-3 · Los datos personales del propietario viven junto a los comerciales
**Estado: resuelto en 2.8.0.**

`_property_contact_name`, `_property_contact_email`, `_property_contact_phone`,
`_property_contact_whatsapp`, `_property_identification` y las cuatro banderas
de consentimiento están en el mismo post que el precio y las fotos. Además,
`_property_sync_payload` guarda la respuesta cruda del CRM, que puede contener
tokens.

Cualquier hook que hubiera entregado «los metadatos del inmueble» habría
publicado esos datos a todas las extensiones instaladas.

Resuelto con dos decisiones deliberadas: el modelo `Property` se construye
desde una **lista blanca** de claves —no desde «todo menos una lista negra»—, y
el diff que viaja en `PropertyChanges` excluye explícitamente esos campos. Hay
pruebas que lo verifican serializando el modelo y comprobando que los valores
privados no aparecen.

---

## Alta

### A-1 · Ninguna frontera entre API pública e implementación interna
**Estado: resuelto en 2.8.0.**

Todo vivía bajo `Homlity\PluginInmobiliario\`. Un integrador no tenía forma de
saber qué podía usar sin que se lo rompieran en la siguiente actualización, y
el plugin no tenía forma de refactorizar nada sin miedo.

Resuelto declarando `Homlity\Developer\` como el único namespace público. No se
movió una sola clase existente: mover 200 clases habría roto todas las
instalaciones, que es exactamente lo que una API de estabilidad debe evitar.

### A-2 · No existía un registro de extensiones
**Estado: resuelto en 2.8.0.**

Había un registro para *proveedores de sincronización* (`SyncRegistry`) y otro
para *adaptadores de CRM* (`CrmIntegrationManager`), ambos específicos. No
había forma de decir «este plugin extiende Homlity» ni de comprobar
compatibilidad antes de arrancar.

Resuelto con `ExtensionRegistry`, `ExtensionInterface` y `Requirements`.

### A-3 · `new` esparcido por todo el código, sin inyección de dependencias
**Estado: abierto.**

`PropertyUpsertService::__construct()` instancia cuatro colaboradores. `Crm
AdminService` construye la cola entera. `PluginBootstrap` instancia ~50
servicios a mano. No hay contenedor.

En la práctica no impide extender: los hooks de WordPress son el mecanismo de
sustitución, y ahora existen. Introducir un contenedor obligaría a tocar todos
los constructores, que es justo el refactor masivo que estas reglas prohíben.
Queda anotado como deuda: cuando un servicio necesite ser sustituible de
verdad, se le añade un `setter` o un filtro, no un contenedor.

### A-4 · Sin punto de extensión en la consulta de inmuebles
**Estado: resuelto en 2.8.0.**

`PropertySearchService::buildQueryArgs()` construye 280 líneas de argumentos de
`WP_Query` y los devolvía sin filtrar. Un plugin que quisiera añadir un filtro
de búsqueda propio tenía que engancharse a `pre_get_posts` y deshacer el
trabajo ya hecho.

Resuelto con `homlity/property/query_args`.

### A-5 · Sin punto de extensión en la escritura desde un CRM
**Estado: resuelto en 2.8.0.**

La carga canónica entraba en `PropertyUpsertService::upsert()` y se escribía
sin que nadie pudiera intervenir. Añadir un campo propio, corregir un dato del
CRM o marcar el origen exigía parchear el adaptador.

Resuelto con `homlity/property/normalized`, que corre antes de la validación,
de modo que un filtro puede incluso completar un campo obligatorio que el CRM
omite.

### A-6 · `node_modules/` está versionado en el repositorio
**Estado: abierto — requiere una decisión del mantenedor.**

`git ls-files node_modules | wc -l` devuelve miles de archivos. `.gitignore`
no lo incluía. Además `.gitignore` lista `composer.lock` mientras el archivo
está efectivamente versionado, que es una contradicción: o se ignora o se
versiona, y en un plugin distribuido conviene versionarlo.

Se corrigió `.gitignore`. **No se ejecutó la limpieza del índice**: sacar
`node_modules` del control de versiones es un commit enorme e irreversible sin
`--force`, y esa es una decisión del mantenedor, no de una auditoría. El
comando es:

```bash
git rm -r --cached node_modules
git commit -m "chore: dejar de versionar node_modules"
```

---

## Media

### M-1 · No existía representación estable de un inmueble
**Estado: resuelto en 2.8.0.** Ver `Homlity\Developer\Models\Property`.

### M-2 · `SyncRegistry` guarda los proveedores en estado estático
**Estado: aceptado.**

`private static array $providers` es estado global. En WordPress es la forma
habitual de que un registro sobreviva entre callbacks, y el registro se
repuebla en cada petición. Cambiarlo obligaría a romper la firma pública de
`SyncRegistry::addProvider()`, que ya usan plugins de terceros.

### M-3 · `SyncRegistry` usa `method_exists()` para descubrir capacidades
**Estado: abierto.**

`syncByCodeDetailed()` no está en `SyncProviderInterface`; se detecta con
`method_exists()`. Funciona, pero significa que el contrato real es mayor que
el declarado. Lo correcto sería una segunda interfaz opcional. No se tocó para
no romper los proveedores existentes.

### M-4 · Prioridades de proveedor codificadas por slug
**Estado: aceptado.**

`SyncRegistry::providerPriority()` tiene un `array` con `'simi' => 10`,
`'softinm-sync' => 20`, `'homlity-sync' => 100`. Son valores por defecto y hay
un filtro (`homlity_property_lookup_provider_priority`) para cambiarlos, así
que un proveedor nuevo no queda bloqueado: entra con prioridad 50.

### M-5 · Lógica dentro de las plantillas
**Estado: abierto.**

`templates/parts/property-gallery.php` contiene 400 líneas con normalización de
datos mezclada con maquetación. `Property::getImages()` cubre ahora el caso de
las extensiones; la plantilla se dejó intacta porque tocarla es riesgo visual
puro sin ganancia de extensibilidad.

### M-6 · Dos controladores REST de consignación conviven
**Estado: abierto.**

`class-homlity-consignment-rest-controller.php` y
`class-homlity-consignacion-rest-controller.php` hacen casi lo mismo, uno en
inglés y otro en español. Es duplicación heredada. Ambos se actualizaron para
declarar su origen en el nuevo contexto de eventos; consolidarlos es un trabajo
aparte con su propia migración.

### M-7 · Los requisitos declarados no coincidían entre sí
**Estado: resuelto en 2.8.0.**

`composer.json` exigía PHP ≥ 8.0, `readme.txt` decía PHP 7.4, y la cabecera del
plugin no declaraba ni `Requires PHP` ni `Requires at least`. Un sitio con PHP
7.4 podía instalar el plugin y romperse. Ahora los tres dicen PHP 8.0 y
WordPress 5.8.

---

## Baja

### B-1 · Dos convenciones de nombres de hook conviviendo
**Estado: resuelto por decisión explícita.**

Existían `homlity_*`, `homlity_plugin_*`, `homlity_crm_*`, `homlity_faq_*`,
`homlity_schema_*` y `homlity_consignment_*`. Ninguna estaba documentada.

Se estableció una regla que no rompe nada: **barra = público y estable,
guion bajo = heredado e interno**. Los 90 hooks existentes siguen funcionando
sin cambios; los nuevos usan `homlity/dominio/evento`. Ver
[la convención](../developers/api/overview.md#convención-de-nombres).

### B-2 · Sin mecanismo de deprecación
**Estado: resuelto en 2.8.0.** Ver `Homlity\Developer\Support\Deprecated`.

### B-3 · Constantes de plugin duplicadas
**Estado: aceptado.**

Conviven `HOMLITY_RE_PLUGIN_*` (sin guarda, siempre de esta copia) y
`HOMLITY_PLUGIN_*` (con guarda). Es deliberado: permite que dos copias del
plugin coexistan sin pisarse, y está explicado en un comentario del archivo
principal.

### B-4 · Funciones globales sin prefijo consistente
**Estado: abierto.**

`includes/admin/views/seo-geo-settings.php` define helpers `hsg_*` en el ámbito
global. Son de una sola vista y no forman parte de ninguna API, pero un
prefijo de dos letras en el espacio global es un riesgo de colisión. No se
tocaron: renombrarlos no aporta extensibilidad.

---

## Lo que se decidió *no* construir

Merece constar, porque no construirlo también fue una decisión:

- **Contenedor de servicios.** Ver A-3.
- **`PropertyExporterInterface` / `SynchronizationInterface`.** No hay ningún
  consumidor en el núcleo que los invoque. Una interfaz que nadie llama no
  desacopla nada: sólo obliga a mantenerla. Cuando el núcleo tenga un registro
  de exportadores, tendrá sentido publicar el contrato.
- **Objetos de evento por hook** (`PropertyUpdatedEvent`, etc.). Se evaluó y se
  descartó a favor de `(Property, PropertyChanges, PropertyContext)`. En
  WordPress la firma de un hook es el contrato, y tres parámetros con nombre
  claro se leen mejor en un `add_action` que un objeto opaco. Los datos
  compuestos —el diff y el origen— sí son objetos, que es donde el patrón
  aporta.
- **Migrar `includes/` a PSR-4.** Refactor grande, cero ganancia para quien
  extiende.
