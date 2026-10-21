# Plan: inmuebles con descripción duplicada + sitemap único

Plugin: `wp-content/plugins/homlity-real-estate` (v2.9.14). Tests: `vendor/bin/phpunit` (stubs en `tests/Support/WpStubs.php`).
Comentarios en español, mismo estilo que el código vecino. No tocar `seo-by-rank-math`.

---

## Diagnóstico (ya verificado, no hace falta repetirlo)

### A. Descripciones duplicadas en `/llms.txt`
- `/llms.txt` lo sirve **Rank Math** (`seo-by-rank-math/includes/modules/llms/class-llms-txt.php::add_post_types_data`). La línea de cada ficha es `- [título](url): %excerpt%`, con el filtro `rank_math/llms_txt/post_description` ($desc, WP_Post).
- `%excerpt%` = `post_excerpt` o, si está vacío, el arranque de `post_content`. Ambos llegan tal cual del CRM (`src/Integrations/CRM/PropertyUpsertService.php:108-111`, `homlity-sync/src/Sync/PropertyMapper.php:77`). Homlity no genera ni corrige ese texto.
- La deduplicación del upsert ya existe (`findByExternal` y, si falla, `findByCode`). Así que los pares (Casa Teusaquillo ×2, Casa Centro Nariño ×2, Casa El Salitre ×2, Oficina Chicó ×2) son **posts con external_id y código distintos**. Pueden ser: (a) el mismo inmueble publicado dos veces en el CRM, por ejemplo uno en venta y otro en arriendo, o (b) inmuebles distintos con la descripción copiada y pegada.
- "Pie de la Popa": la taxonomía dice `Oficina` y el texto libre dice "casa". Es un error de datos en el CRM. El plugin no puede adivinar cuál de los dos está bien, pero sí puede detectarlo y hacer que el texto público empiece por el dato estructurado.
- Los inmuebles **no están en la BD local** (347 `property` publicados; solo coincide "Popalito, Barbosa"). El diagnóstico de datos se hace en producción con la herramienta de la Fase 1.

### B. Sitemaps
`src/Services/SeoIntegrationService.php` hace todo esto a la vez:
1. Mete `homlity-properties-sitemap[-N].xml` en el índice de Rank Math (`rank_math/sitemap/index`) y de Yoast (`wpseo_sitemap_index`), y lo sirve con rewrite propio. **Repite** el `property-sitemap.xml` nativo de Rank Math: mismas URLs y `lastmod = gmdate('c')` falso en el índice.
2. Mete `homlity-search-sitemap.xml` con hasta unas 7.500 URLs de combinaciones gestión×tipo×ciudad (`buildSearchResultUrls`). Ninguna de esas páginas tiene control de `robots` ni de canonical. Los ajustes `seo_index_search`, `seo_noindex_filtered`, `seo_noindex_empty` y `seo_enable_sitemap` (`SeoGeoSettingsService.php:203-209`, UI en `includes/admin/views/seo-geo-settings.php:290-298`) **se guardan pero ningún código los lee**.
3. Los filtros `rank_math/sitemaps/post_types`, `rank_math/sitemaps/taxonomies`, `wpseo_sitemap_post_types` y `wpseo_sitemap_taxonomies` **no existen** en esos plugins. Es código muerto: la inclusión del CPT en Rank Math depende de su ajuste `pt_property_sitemap`.

Decisión: **el único sitemap es el del plugin SEO activo (Rank Math o Yoast)**. Si no hay plugin SEO, queda el sitemap core de WP (`wp-sitemap.xml`, ya cubierto por `addWpCoreSitemapPostType`). Homlity deja de emitir sitemaps propios.

---

## Fase 1: Sitemap único (hacer primero, es de bajo riesgo)

**1.1 `SeoIntegrationService.php`**
- Eliminar: `addYoastSearchSitemapIndex`, `addRankMathSearchSitemapIndex`, `addYoastPropertySitemapIndex`, `addRankMathPropertySitemapIndex`, `appendPropertySitemapEntries`, `maybeRenderPropertySitemap`, `maybeRenderSearchSitemap`, `buildSearchResultUrls` y los hooks que los registran.
- Eliminar los cuatro filtros inexistentes (`addYoastPostType`, `addYoastTaxonomies`, `addRankMathPostType`, `addRankMathTaxonomies`).
- Conservar `addWpCoreSitemapPostType` y `addRankMathPropertySchema`.
- Para que las URLs viejas, que ya están en Search Console, no den 404 con el tema, conservar las rewrites y `requestedPropertySitemapPage` / `requestedRelativePath`, pero responder con una **redirección 301** al índice activo:
  - Rank Math activo (`class_exists('\\RankMath\\Sitemap\\Sitemap')` o `\RankMath\Helper::is_module_active('sitemap')`) → `home_url('/sitemap_index.xml')`
  - Yoast (`defined('WPSEO_VERSION')`) → `/sitemap_index.xml`
  - Si no hay ninguno → `/wp-sitemap.xml`
  - Sacar la resolución del destino a un método privado `sitemapIndexUrl(): string` y probarlo.
- Actualizar el docblock de la clase.

**1.2 Taxonomías en el sitemap.** No se añade ninguna desde código. Documentar en el README o CHANGELOG que en Rank Math → Sitemap conviene dejar activas solo `property_city`, `property_neighborhood`, `property_type` y `property_operation`. Las de características, etiquetas, cercanos, país y estado generan archivos flojos.

**1.3 Ajuste `seo_enable_sitemap`.** Quitar el checkbox de la vista y su clave de las listas booleanas de `SeoGeoSettingsService` (líneas 203 y 372). No hace nada y confunde. Si `sanitize` aplica una whitelist, comprobar que una opción guardada con esa clave no rompe nada.

**1.4 Robots de las páginas de búsqueda** (es lo que de verdad evita que se indexen filtros sin valor). Se construye sobre los ajustes que ya existen:
- Crear `src/Services/SearchRobotsService.php` (`ServiceInterface`) y registrarlo en `src/Core/PluginBootstrap.php` junto a `SeoIntegrationService`.
- Detectar la página de resultados: el CPT archive `property` y las rutas `/inmuebles/{gestion|tipo|ciudad|barrios}/…`. Usar las query vars que lee `src/Listing/ListingRenderer.php:72-114` y la rewrite de `src/Services/TemplateService.php`.
- Reglas (`noindex,follow`):
  - `seo_noindex_filtered = 1` y hay parámetros GET de filtro (precio, habitaciones, `operacion`, `orden`, `pagina`…).
  - `seo_noindex_empty = 1` y la consulta principal devuelve 0 resultados.
  - Combinaciones de 2 o más segmentos SEO (p. ej. `gestion/x/tipo/y`, `…/ciudad/z`), salvo que `seo_index_search = 1`.
  - Indexables por defecto: `/inmuebles/` y las de un solo segmento (`/inmuebles/ciudad/bogota/`).
- Aplicarlas vía `rank_math/robots` (array) y `wpseo_robots` (string), copiando el patrón de `PropertyUnavailableService::filterRecoveryRobots`. Sin plugin SEO, usar el filtro core `wp_robots`.
- Añadir la cabecera `X-Robots-Tag` solo cuando la regla produce noindex.

**1.5 Tests** (`tests/Unit/Services/`)
- Borrar `OperationArchitectureTest::testSitemapDeBusquedasSoloPublicaLasVariantesPreferidas`. Antes, comprobar que la canonicalización de `gestion` (`PropertyTaxonomies::canonicalOperationSlug`) está cubierta en otro test. Si no lo está, pasar las aserciones a un test de `PropertyTaxonomies`.
- `SeoIntegrationServiceTest`: `register()` no engancha `rank_math/sitemap/index` ni `wpseo_sitemap_index`. `sitemapIndexUrl()` devuelve el destino correcto en los tres escenarios.
- `SearchRobotsServiceTest`: cada regla de 1.4, activada y desactivada.

**1.6 Después del deploy (lo hace un humano, dejarlo en el CHANGELOG):** guardar enlaces permanentes, quitar `homlity-search-sitemap.xml` y `homlity-properties-sitemap.xml` en Search Console y comprobar que `/sitemap_index.xml` solo lista `property-sitemap.xml` y las taxonomías elegidas.

---

## Fase 2: Detección de duplicados (no cambia datos)

**2.1 `src/Services/PropertyDuplicateDetector.php`** (clase pura y testeable)
- `findGroups(): array` agrupa los `property` publicados por **huella de descripción**: `md5` del texto normalizado de `post_excerpt` o, si falta, de `post_content` (sin tags ni shortcodes, en minúsculas, sin tildes, espacios colapsados, recorte a 500 caracteres). Hacerlo con una sola consulta SQL de `ID, post_excerpt, post_content` y agrupar en PHP.
- Para cada par del grupo, calcular `same_physical` (bool) con: misma dirección normalizada, o lat/lng a menos de 30 m; y `area`, `bedrooms` y `bathrooms` iguales. Clasificar el grupo como:
  - `same_listing`: mismo inmueble físico. Se propone consolidar.
  - `copied_text`: inmuebles distintos con texto copiado. Se propone diferenciar.
- `typeMismatch(int $postId): ?string`: compara el término `property_type` con las palabras de tipo que aparecen en el título y el texto (casa, apartamento, apartaestudio, oficina, local, bodega, lote, finca, consultorio). Devuelve la palabra en conflicto si el texto nombra un tipo distinto del asignado y nunca nombra el asignado. Mapa de sinónimos filtrable con `homlity_property_type_keywords`.

**2.2 Dónde se ve el resultado**
- Comando WP-CLI `wp homlity duplicates [--format=table|json|csv]`. Revisar si el plugin ya registra comandos con `WP_CLI::add_command`; si no, registrar este solo cuando exista `WP_CLI`.
- Aviso en el listado admin de `property`: una columna o un badge "Descripción duplicada (n)" o "Tipo ≠ texto", con un enlace a los hermanos del grupo.
- Cachear el resultado en un transient `homlity_dup_groups` (12 h) e invalidarlo en `save_post_property`.

**2.3 Tests:** agrupación con textos que solo cambian en tildes o espacios; `same_listing` frente a `copied_text`; `typeMismatch` en el caso Pie de la Popa (tipo Oficina, texto "casa") → `'casa'`.

---

## Fase 3: Diferenciar el texto público (automático y reversible)

El texto del CRM no se modifica. Lo que cambia es la línea que se publica.

**3.1 `src/Services/PropertySummaryComposer.php`**: `compose(int $postId): string`. Arma un resumen factual y único con datos estructurados, en este orden:
`{Tipo} en {gestión} en {barrio}, {ciudad} · {área} m² · {hab} hab · {baños} baños · {precio} · Ref. {código}`.
Toma el tipo de la taxonomía, no del texto, y así resuelve "Oficina descrita como casa". Si existe, reutilizar el formateo de precio de `LlmsFullGenerator::formatPrice` o `CardPriceResolver` en lugar de duplicarlo.

**3.2 Engancharlo en Rank Math** con `add_filter('rank_math/llms_txt/post_description', …, 10, 2)`, solo para `post_type === property`:
- Ficha en un grupo duplicado (2.1) o con `typeMismatch`: devolver `compose()` + `' — '` + el extracto original recortado a unas 20 palabras. Si hay `typeMismatch`, omitir el extracto.
- En el resto de casos, anteponer `compose()` al extracto. Así todas las líneas empiezan por un dato diferenciador y la descripción no puede volver a duplicarse.
- Hacerlo configurable con un ajuste nuevo `ai_llms_structured_summary` (on por defecto) en `SeoGeoSettingsService`.

**3.3 `/llms-full.txt`** (`src/Services/Ai/LlmsFullGenerator.php::renderProperty`): si la ficha pertenece a un grupo `same_listing`, añadir `- **Ver también:** {url del hermano}`. Si tiene `typeMismatch`, omitir `- **Descripción:**`, porque el resto de campos ya son estructurados y correctos.

**3.4 Meta description (opcional, el mismo problema en Google):** con `rank_math/frontend/description`, si la ficha está en un grupo duplicado y no tiene `rank_math_description` manual, devolver `compose()`. Tiene que convivir con `PropertyUnavailableService::filterRecoveryDescription` (prioridad 20); usar prioridad 15.

**3.5 Tests:** `compose()` con campos ausentes (no deja separadores huérfanos); el filtro de llms.txt da salidas distintas para dos posts con el mismo extracto; no toca otros post types.

---

## Fase 4: Consolidar los que son el mismo inmueble (manual y asistido)

No se automatiza el borrado: el CRM los volvería a crear en la siguiente sync.

**4.1** En el aviso admin de un grupo `same_listing`, añadir la acción "Consolidar en este" (nonce + capability `edit_others_posts`):
- Conservar el post elegido. Si los hermanos son venta y arriendo, copiar al conservado el precio y la moneda de la otra gestión y añadirle el término de operación. El modelo dual ya existe: `_property_price_sale` y `_property_price_rent`, y el test `testDatosEstructuradosPublicanDosOfertas…`.
- Pasar el hermano a `draft` y guardar en él la meta `_homlity_merged_into = {id}`.
- Redirigir con 301 el permalink del hermano al conservado. Revisar antes si `PropertyUnavailableService` o `RetiredPropertyRecoveryService` ya resuelven redirecciones por meta; si es así, añadir el caso allí en lugar de crear otro `template_redirect`.
- **Protección ante la sync:** en `PropertyUpsertService::upsert`, si el post encontrado tiene `_homlity_merged_into`, no volver a publicarlo (forzar `draft`), y registrar un aviso con nivel de configuración. Según la política de reporte de errores, no se notifica.

**4.2** Dejar en el CHANGELOG la recomendación de corregirlo también en el CRM de origen: eliminar el duplicado y arreglar el texto de Pie de la Popa. Es la solución definitiva.

---

## Orden de entrega y criterios de aceptación

1. Fase 1 y su release. `/sitemap_index.xml` no contiene ninguna URL `homlity-*`; las URLs viejas responden 301; `/inmuebles/gestion/x/tipo/y/` emite `noindex,follow`; `/inmuebles/ciudad/bogota/` es indexable.
2. Fases 2 y 3 y su release. `wp homlity duplicates` en producción lista los cuatro pares y marca Pie de la Popa. En `/llms.txt` no hay dos líneas de `property` con la misma descripción y Pie de la Popa empieza por "Oficina…".
3. Fase 4: el humano consolida los pares `same_listing` desde el admin.

Al terminar cada fase: `vendor/bin/phpunit` en verde, `composer phpcs` si existe el script, entrada en `CHANGELOG.md` y subida de versión en `plugin-inmobiliario.php` y `readme.txt`.
