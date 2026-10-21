# Plan: página «Inmueble no disponible» sin diseño

## Síntoma

Al entrar a la URL de un inmueble retirado, vendido o arrendado, el visitante ve un HTML sin estilos:

- Un H1 con la fuente serif del navegador.
- El texto «El inmueble que buscas fue retirado…».
- Un enlace azul subrayado «Ver otros inmuebles».

No aparecen la cabecera ni el pie del sitio, ni inmuebles similares. El visitante no tiene a dónde ir y se va.

Ese texto es el de la plantilla Elementor que crea `DataSeederService::seedUnavailableElementorPage()`. La plantilla sí se está usando, pero se renderiza mal.

## Causas (verificadas en el código)

1. **El modo canvas no imprime el documento HTML.** El seeder deja `homlity_plugin_unavailable_page_layout = elementor_canvas` (`DataSeederService.php:857-858`). En ese modo, `PropertyUnavailableService::renderRecoveryTemplate()` y `renderUnavailableTemplate()` (y también `PropertyCodeRoutingService::renderElementorUnavailableTemplate()`) solo hacen `echo` del contenido. No imprimen `<!DOCTYPE>`, `<head>`, `wp_head()` ni `wp_footer()`. Sin `<head>` no carga ningún CSS: ni el del tema, ni el de Elementor, ni el del plugin. Por eso falta la cabecera.
   - La plantilla canvas real de Elementor (`elementor/modules/page-templates/templates/canvas.php`) sí imprime todo eso.

2. **El CSS de Elementor nunca se encola, ni siquiera en el modo `default`.**
   - `maybeShowUnavailable()` corre en `template_redirect` con prioridad 5 y termina con `exit`.
   - `Elementor\Frontend::init()` está en `template_redirect` con prioridad 10, así que nunca llega a ejecutarse.
   - Esa función es la que engancha `enqueue_styles`, `print_fonts_links` y `wp_footer`. Sin ella no se carga `elementor-frontend.css` y el botón sale como un enlace sin estilo.

3. **`wp_kses_post()` elimina los estilos.** Cuando el contenido se renderiza después de `wp_head`, `get_builder_content()` imprime el CSS del post como `<style>` en línea, y `wp_kses_post` lo borra. También borra los SVG de iconos y los formularios.
   - `templates/single-property.php:56` ya hace `echo` directo de `get_builder_content_for_display($id, true)`. Hay que hacer lo mismo aquí.

4. **La plantilla Elementor sembrada no tiene inmuebles similares.** Solo trae el título, el texto y el botón. Las semillas de Divi y WPBakery (`unavailableBuilderContent()`, línea 1025) sí incluyen `[homlity_unavailable_notice][homlity_unavailable_search_context][homlity_unavailable_similar_properties]`.
   - Además, si hay plantilla configurada, sustituye a `property-recovery.php`, que es la página buena (con similares, contexto de búsqueda y código HTTP correcto). Hoy la plantilla configurada empeora el resultado.

5. **Se elige el renderizador por la presencia del plugin, no por la plantilla.**
   - `class_exists('\Elementor\Plugin')` decide la rama Elementor. Si la página se sembró con Divi o WPBakery y Elementor está activo, `get_builder_content_for_display()` devuelve una cadena vacía: página en blanco. Es lo que pasaría en local (la página 1519 es una semilla Divi).
   - `single-property.php` ya resolvió esto detectando el constructor por los metadatos de la plantilla.

6. **El administrador nunca la ve.** `maybeShowUnavailable()` sale si `current_user_can('edit_posts')`, así que quien diseña la plantilla no puede comprobar cómo la ve el público.

## Objetivo

- Con cualquier constructor y en cualquier modo, la página muestra la cabecera y el pie del sitio, los estilos del constructor, el aviso, el contexto de búsqueda y los inmuebles similares.
- Si la plantilla no se puede renderizar, se usa `property-recovery.php`.

## Fase 1: renderizador único

Crear `src/Services/UnavailablePageRenderer.php`, usado por `PropertyUnavailableService` (en los modos de recuperación y legado) y por `PropertyCodeRoutingService`. Eliminar la lógica triplicada.

```php
final class UnavailablePageRenderer
{
    /** @return bool true si se renderizó la plantilla; false para usar el fallback PHP. */
    public function renderConfiguredTemplate(): bool;
}
```

Reglas:

1. **Resolver la plantilla.**
   - `homlity_plugin_unavailable_template_id` > 0 y `get_post_status() === 'publish'`; en otro caso, devolver `false`.
   - Detectar el constructor con la misma lógica que `single-property.php:27-44`: `_elementor_edit_mode === 'builder'` o `_homlity_seeded_builder === 'elementor'` es Elementor; luego Divi, WPBakery y nativo.
   - Extraer esa detección a un helper estático (por ejemplo `TemplateService::detectTemplateBuilder(int $id): string`) y usarlo también en `single-property.php`.
   - Si la plantilla es de Elementor pero Elementor no está activo, devolver `false`.

2. **Elementor.** Antes de imprimir nada, encolar lo que normalmente hace `Frontend::init()`:
   ```php
   $frontend = \Elementor\Plugin::$instance->frontend;
   add_action('wp_enqueue_scripts', static function () use ($frontend, $templateId): void {
       $frontend->enqueue_styles();
       \Elementor\Core\Files\CSS\Post::create($templateId)->enqueue();
   }, 20);
   add_action('wp_head', [$frontend, 'print_fonts_links'], 7);
   add_action('wp_footer', [$frontend, 'wp_footer']);
   ```
   - Comprobar con la versión de Elementor instalada que esos métodos son públicos. Si alguno no existe, proteger la llamada con `method_exists`.
   - Si Elementor Pro está activo, encolar también el CSS de su header y footer: con `get_header()` Elementor Pro los inyecta por su cuenta. Verificar que aparecen.
   - Generar el contenido **antes** de `get_header()` (`$html = $frontend->get_builder_content_for_display($id, true)`) para poder comprobarlo. Si `trim(wp_strip_all_tags($html)) === ''`, devolver `false` sin haber impreso nada.
   - Imprimir `$html` sin `wp_kses_post`, con el mismo `phpcs:ignore` que `single-property.php:55`.

3. **Divi, WPBakery y nativo.** Mover aquí `PropertyUnavailableService::renderBuilderPage()` tal como está (`setup_postdata` y `the_content`). `PropertyCodeRoutingService` hoy no tiene esta rama, así que la gana.

4. **Modos de diseño.**
   - `default`: `get_header()` + contenido + `get_footer()`.
   - `elementor_canvas`: imprimir el esqueleto completo, igual que `elementor/.../canvas.php`: `<!DOCTYPE html>`, `<html language_attributes()>`, `<head>` con `charset`, `wp_head()` y viewport, `<body body_class()>`, `wp_body_open()`, el contenido y `wp_footer()`.
   - Poner el esqueleto en `templates/parts/canvas-document.php` (o un helper) para reutilizarlo en el punto 7.

5. **Las llamadas.**
   - `renderRecoveryTemplate($context)`: `if (!$renderer->renderConfiguredTemplate()) { include property-recovery.php; }`.
   - `renderUnavailableTemplate()`: lo mismo, con `property-unavailable.php`.
   - `PropertyCodeRoutingService::renderNotFoundMessage()`: lo mismo, manteniendo el `status_header(404)`.
   - Los códigos HTTP, `X-Robots-Tag` y `nocache_headers()` no cambian.

6. **Vista previa para el administrador.**
   - Con `?homlity_preview_unavailable={property_id}` y `current_user_can('edit_posts')`, `maybeShowUnavailable()` no sale antes de tiempo: renderiza la página como la ve el público, con `noindex` y sin cambiar el código HTTP (200).
   - En `ElementorTemplateSettingsService`, junto al selector de la plantilla, añadir el enlace «Ver cómo lo ve un visitante», que apunte a un inmueble no publicado o inactivo cualquiera. Si no hay ninguno, mostrar un texto que lo diga.

7. **Mismo fallo en `single-property.php`.** El modo canvas de la ficha (`homlity_plugin_single_page_layout = elementor_canvas`) tampoco imprime `<head>`. Usar ahí el mismo esqueleto del punto 4. Allí no hace falta el encolado del punto 2, porque esa plantilla llega por `template_include` y `Frontend::init()` sí se ejecuta. Verificarlo.

## Fase 2: plantilla Elementor con inmuebles similares

1. En `seedUnavailableElementorPage()`, cambiar el contenido sembrado:
   - Una sección con un widget `shortcode` (o `text-editor`) que contenga `[homlity_unavailable_notice]`.
   - Una sección con `[homlity_unavailable_search_context]`.
   - Una sección con `[homlity_unavailable_similar_properties]`.
   - Mantener el título y el texto de ahora como valores por defecto del shortcode notice: revisar sus atributos en `UnavailablePropertyShortcodesService.php:106-125`.

2. **Actualizar sin pisar lo editado.**
   - Guardar el meta `_homlity_seeded_template_version` en la página Elementor, igual que en `seedBuilderPage()`.
   - Si la página tiene el `_elementor_data` exacto de la semilla anterior (compararlo con una función `legacyUnavailableElementorData()` que reproduzca el array actual, normalizando los ids de los elementos), reemplazarla.
   - Si el usuario la editó, no tocarla. En su lugar, mostrar en el admin un aviso descartable: «Tu plantilla de inmueble no disponible no incluye inmuebles similares; añade el shortcode [homlity_unavailable_similar_properties]».
   - Para detectarlo, buscar la cadena `homlity_unavailable_similar_properties` dentro de `_elementor_data`.

3. **Diseño por defecto.**
   - Dejar de fijar `elementor_canvas` en la semilla y usar `default`, para que el visitante tenga el menú del sitio.
   - Migración única (opción `homlity_unavailable_layout_migrated`): si la plantilla sigue siendo la semilla sin editar y el diseño es `elementor_canvas`, cambiarlo a `default`.
   - Si el usuario eligió canvas a propósito con una plantilla editada, respetarlo.

4. Ejecutar la reparación al actualizar el plugin, por la misma ruta que las otras semillas (revisar cómo se dispara `DataSeederService::seed()` en cada actualización), no solo al activarlo.

## Pruebas (`tests/Unit/Services/UnavailablePageRendererTest.php`)

- Una plantilla Elementor con Elementor activo encola `elementor-frontend` y el CSS del post, e imprime el contenido sin pasar por kses (un `<style>` y un `<svg>` sobreviven).
- Si la plantilla Elementor da un render vacío, devuelve `false` y no imprime nada.
- Si la plantilla es Divi y Elementor está activo, no llama a Elementor y usa `the_content`.
- Con plantilla inexistente, en borrador o con ID 0, devuelve `false`.
- El modo canvas imprime `<!DOCTYPE html>`, `wp_head` y `wp_footer`; el modo default llama a `get_header` y `get_footer`.
- `PropertyCodeRoutingService` usa el mismo renderizador y mantiene el 404.
- La vista previa renderiza la página para un editor y no la renderiza sin el parámetro.
- Semilla: la página sin editar se actualiza a la versión con shortcodes; la editada no se toca; la migración de canvas a default solo ocurre en la semilla sin editar.
- `single-property.php` en modo canvas imprime `<head>`.

Añadir los stubs que falten en `tests/Support/WpStubs.php`. Ejecutar `vendor/bin/phpunit` completo.

## Entrega

- Versión 2.9.17, con entrada en CHANGELOG y versión en `plugin-inmobiliario.php` y `readme.txt`.
- Pasos manuales en el CHANGELOG:
  1. Abrir la URL de un inmueble retirado en una ventana privada y comprobar que se ven la cabecera, los estilos y los similares.
  2. Si la plantilla fue editada, añadirle el shortcode de similares.
  3. Si hay caché de página (LiteSpeed, WP Rocket, Cloudflare), purgarla.
- No cambiar códigos HTTP ni robots, ni el comportamiento de `RetiredPropertyRecoveryService`.
