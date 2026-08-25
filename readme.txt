=== Homlity Real Estate ===
Contributors: homlity
Tags: real estate, property, listings, agents, property management
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.8.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real estate plugin to manage property listings, agents, currencies, and multi-country configurations in WordPress.

== Description ==

**Homlity Real Estate** is a WordPress plugin built for the real estate industry. It allows agencies, brokers, and independent agents to create and manage professional property websites quickly and efficiently.

Designed for multi-country and multi-currency operation, it adapts to the needs of real estate professionals across Latin America, Spain, and beyond.

### Key Features

- **Custom property post type** for structured listing management.
- **Multi-country support** with country, city, zone, and neighborhood fields.
- **Multi-currency support** (COP, MXN, EUR, USD, and more).
- **Fully customizable**:
  - Property types (houses, apartments, offices, retail, land, warehouses, etc.).
  - Operation types (sale, rent, short-term, transfer, etc.).
  - Custom attributes (bedrooms, bathrooms, parking, area, amenities, etc.).
  - Property status (available, reserved, sold, rented, etc.).
- **Agent management**: link properties to agents with per-property contact details.
- **Real estate SEO**: clean URLs, metadata-ready structure, and JSON-LD/schema-friendly field layout.
- **Page builder integrations**: Elementor widgets, Divi module, WPBakery, and shortcodes.
- **Extensible architecture**: hooks, filters, and modular structure for ERP and CRM integrations.

### About Homlity

Homlity is a digital ecosystem focused on real estate web solutions, specializing in property website development, custom web applications, and integrations with real estate systems and ERPs.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the WordPress admin panel.
2. Activate the plugin from the **Plugins** menu.
3. Go to the **Real Estate** or **Properties** menu in the admin dashboard.
4. Configure your countries, currencies, property types, and operation types.
5. Create your first property and publish it.

== Frequently Asked Questions ==

= Is this plugin limited to a specific country? =

No. The plugin supports multiple countries and regions. You can configure countries, cities, and zones to match your market.

= Can I use multiple currencies? =

Yes. The plugin supports multiple currencies (COP, MXN, USD, EUR, etc.) and displays values based on your site configuration.

= Is it compatible with any WordPress theme? =

The plugin follows WordPress coding standards and works with most themes. For best results, use a modern or custom-built theme.

= Can I customize property types and operation types? =

Yes. You can define your own property types (house, apartment, lot, office, etc.) and operation types (sale, rent, seasonal, etc.) to match your business model.

= Is the plugin translation-ready? =

Yes. The plugin uses standard WordPress translation mechanisms (.po/.mo files) and works with WPML and Polylang.

= Who develops and maintains this plugin? =

Developed and maintained by **Ecosistema Inmobiliario Homlity**, a team specializing in real estate websites and digital solutions for the property sector.

== Screenshots ==

1. Property listing in the WordPress admin dashboard.
2. Property creation and editing form.
3. Configuration of property types, operation types, and attributes.
4. Public property detail page.
5. Public property listing on the WordPress site.

== Changelog ==

= 2.8.0 =
* Homlity Real Estate incorpora una **Developer API** pública y estable: doce acciones, cuatro filtros, once clases, tres interfaces y siete funciones globales bajo el namespace `Homlity\Developer\`. A partir de ahora un desarrollador externo puede construir integraciones con CRMs, portales, ERP, analítica o IA como plugins independientes, sin modificar ni un archivo del plugin. Documentación en https://homlity.com/desarrolladores/
* Nuevos eventos del ciclo de vida de un inmueble: creado, actualizado, borrado, sincronizado, cambio de estado y cambio de galería. Se disparan cuando la escritura ha terminado del todo —post, metadatos, taxonomías, fotos y asesor—, no a mitad como hace `save_post` de WordPress, así que una integración recibe el inmueble completo y no uno sin precio ni fotos.
* La actualización de un inmueble informa de qué cambió exactamente. Un CRM que reenvía un registro idéntico deja de provocar trabajo inútil en las integraciones, y una integración puede saber si el cambio lo causó ella misma para no devolvérselo al CRM en un bucle.
* Nuevo modelo público de inmueble. La galería de un inmueble se guarda en cuatro formatos distintos según de dónde vino, y los precios llegan de los CRM como texto libre; el modelo lo normaliza todo, de modo que ninguna integración tenga que saberlo.
* Los datos personales del propietario captados por el formulario de consignación —nombre, documento, teléfono, correo— y la respuesta cruda del CRM, que puede contener credenciales, no se publican a las extensiones. El modelo se construye desde una lista de campos permitidos, no descartando los prohibidos, así que un dato nuevo tampoco puede colarse por descuido.
* La dirección exacta de un inmueble cuyo propietario pidió ocultarla sigue oculta también para las extensiones.
* Nuevo sistema de registro de extensiones con comprobación automática de compatibilidad. Una extensión que necesita una versión superior de Homlity, de PHP o de WordPress no arranca y lo explica, en vez de romper el sitio. Una extensión que falla al arrancar queda aislada y no se lleva por delante a las demás.
* Corregido: el plugin declaraba requisitos contradictorios. Composer exigía PHP 8.0, este archivo decía PHP 7.4 y la cabecera del plugin no decía nada, así que un sitio con PHP 7.4 podía instalarlo y romperse. Ahora los tres dicen PHP 8.0 y WordPress 5.8.
* Ningún cambio rompe compatibilidad: no se ha renombrado ni eliminado ninguna clase, función, hook, opción ni metadato existente.

= 2.7.9 =
* Corregido: el widget "Destacados por ubicación y tipo" salía sin estilos en la web pública, por la misma razón que el de asesores: no declaraba la hoja donde vive su CSS, y Elementor solo carga lo que cada widget pide. Los grupos se apilaban en vez de repartirse en columnas y las listas salían con las viñetas del navegador. En Divi y WPBakery no pasaba. Los widgets por taxonomía —"Ciudades destacadas" y sus hermanos— no estaban afectados: llevan su maquetación en el propio marcado.

= 2.7.8 =
* Corregido: el widget "Asesores con inmuebles disponibles" salía sin estilos en la web pública. En el editor de Elementor se veía bien —el plugin mete sus hojas dentro del iframe de previsualización—, pero fuera de ahí Elementor solo carga lo que cada widget declara y este no declaraba ninguna, así que la maquetación de las tarjetas se deshacía al publicar. En Divi y WPBakery no pasaba, porque esos dos cargan la hoja para todo el sitio.
* Corregido: el widget no enseñaba la foto ni el teléfono de los asesores sincronizados desde un CRM. Pedía el avatar con la función de WordPress, que solo sabe de gravatar, y el teléfono en dos campos que no son donde el CRM lo guarda. Ahora usa la misma cadena que el resto del sitio: foto del CRM, plugins de avatar, gravatar y, en último lugar, el logo de la inmobiliaria.
* Nuevo interruptor "Mostrar en los listados de asesores del sitio" en el perfil de cada asesor. Un asesor que deja la inmobiliaria conserva sus inmuebles publicados y su rol, así que no había forma de distinguirlo de quien sigue: al desmarcarlo deja de aparecer en el widget, sin tocar sus inmuebles y sin dejar de ser el contacto en las fichas de los suyos. Viene marcado para todo el mundo, de modo que actualizar no oculta a nadie. Se puede decidir por código con el filtro `homlity_agent_is_publicly_listed`.
* La consulta de asesores del widget pasa a `AvailableAgentsService`, en vez de estar copiada dentro de cada uno de los tres widgets. Los empates en número de inmuebles se desempatan por nombre, así que el orden del listado deja de cambiar entre una visita y la siguiente.

= 2.7.7 =
* El botón "Descargar ficha técnica" avisa de que está trabajando. Componer el PDF tarda —el generador arma la ficha, se trae las fotos del inmueble y las rasteriza—, y como la respuesta llega como archivo adjunto el navegador no navega ni pinta ningún indicador: el botón se quedaba quieto y sin decir nada hasta que la descarga aparecía, así que parecía que el clic no había llegado. Ahora muestra un aro girando y el texto "Generando ficha…" mientras dura la espera, no acepta un segundo clic hasta terminar —cada uno vuelve a componer la ficha entera en el servidor— y avisa si algo falla en lugar de dejar el botón mudo. Sin JavaScript el enlace sigue descargando el PDF como hasta ahora.
* Corregido: cuando el servidor devolvía una página de error en lugar del PDF, el navegador la guardaba igualmente con extensión .pdf y el visitante se quedaba con un archivo que no abre. Ahora se detecta y se avisa.

= 2.7.1 =
* Corregido: cuando WordPress rechazaba el guardado de un inmueble venido de un CRM, el servicio de sincronización escribía sus metadatos, taxonomías y asesor sobre el post con ID 1 del sitio. El error de WordPress se convertía a entero antes de comprobarlo, y un objeto convertido a entero vale 1, así que la comprobación de error no llegaba a ejecutarse nunca. Ahora un fallo al guardar se devuelve como tal y no se escribe nada.
* Corregido: la caché del módulo de homologación sólo se invalidaba a medias al escribir. Tras crear un mapeo, la consulta por origen seguía respondiendo "no existe" durante cinco minutos —así que cada inmueble repetía la búsqueda por nombre—, y al sustituir un mapeo que apuntaba a un término borrado el reemplazo se perdía. Ahora una escritura invalida todo lo memorizado.
* Corregido: en las preguntas frecuentes automáticas, el valor de administración no heredaba la moneda de la venta cuando no tenía una propia. La comprobación usaba `??`, que sólo responde ante un valor nulo, y la moneda sin informar llega como cadena vacía; el respaldo nunca se activaba y la cuota se publicaba sin unidad.
* La retícula del PDF de la ficha técnica pasa a píxeles fijos: columnas de 240 px sobre los 737 útiles de una A4, en vez de porcentajes. El generador de PDF no aplica `box-sizing`, así que un 33,33 % no entraba tres veces y el reparto dependía de cuánto padding llevara cada sección.
* El logo de la cabecera ocupa el ancho completo del título cuando el sitio no tiene logo configurado.
* Corregido: la descripción del inmueble insertaba la página entera del constructor. Con Elementor activo, el filtro `the_content` devuelve el documento completo sin mirar lo que recibe, así que la ficha técnica —en pantalla y en PDF— y los widgets "Contenido del inmueble" y "Resumen del inmueble" acababan con la página dentro. Ahora se pinta el texto del inmueble, sin shortcodes, con respaldo al extracto y con el filtro `homlity_property_description` para quien necesite otra cosa.
* La ficha técnica en PDF usa el color corporativo configurado en SEO & GEO → Marca visual, que hasta ahora se guardaba y no lo leía nadie. Los encabezados y los bordes toman "Color principal", y los botones su propio par "Color de botones" y "Color de texto en botón". El color del widget sigue mandando sobre todos para esa ficha, y un sitio que no haya tocado esa pestaña conserva el color general del plugin.
* La foto del asesor en el PDF ya no se deforma: va recortada en redondo dentro de un marco fijo, y llena el marco por el lado que corresponda según sea vertical o apaisada. Sale de la misma cadena de preferencia que usan los widgets —foto del CRM, plugins de avatar, gravatar—, así que la ficha enseña la misma foto que el resto del sitio. El logo de la inmobiliaria se centra junto a los datos de contacto y ya no se repite a la derecha cuando el asesor no tiene foto.
* La ficha técnica en PDF deja de repetir datos: el asesor y la inmobiliaria salen en la cabecera y el pie de cada página, y ya no se vuelven a listar en tarjetas aparte ni en el cierre. Una ficha completa baja de cuatro páginas a tres.
* La ficha técnica en PDF se rehace con el diseño de la ficha del sistema: cabecera y pie de página repetidos en cada hoja, tarjetas con encabezado en el color de la marca, cifras y características en tres columnas, catálogo de hasta nueve fotos y botones de WhatsApp para contactar, agendar visita u ofertar. La ficha en pantalla no cambia.
* Eliminado el widget "Inmuebles relacionados" de Elementor, Divi y WPBakery. El widget "Listado de inmuebles" ya cubre lo mismo con su modo de consulta "Inmuebles relacionados al inmueble de la página", que además permite elegir taxonomías, estrategia de coincidencia y qué hacer cuando no hay resultados. La plantilla de inmueble que genera el plugin deja de incluir la sección; donde el widget ya esté colocado hay que sustituirlo a mano por el listado en ese modo.
* Los datos del asesor se exponen a Elementor como etiquetas dinámicas: "Asesor: dato" (nombre, cargo, teléfono, correo, biografía, sitio web y número de inmuebles), "Asesor: enlace" (perfil, WhatsApp, llamada, correo y sitio web) y "Asesor: foto". Funcionan en /author/{asesor}/ y en la ficha del inmueble, y admiten fijar un asesor concreto para previsualizar en el editor.

= 2.7.0 =
* El botón "Ficha técnica" descarga el PDF del inmueble en lugar de abrir la ficha en una pestaña nueva.
* El widget trae un nuevo control "Acción" por si se prefiere seguir abriendo la ficha en el sitio.
* Nuevo filtro `homlity_technical_sheet_pdf_available`.
* El widget "Asesor del inmueble" permite disponer la tarjeta en vertical (foto sobre los datos) u horizontal (foto al lado), y por dispositivo.
* El widget "Listado de inmuebles" añade el origen de consulta "Inmuebles del asesor de la página", con un asesor de referencia opcional para la vista previa del editor.
* Corregido: con Elementor, la página del asesor /author/{asesor}/ y la ficha técnica /ficha-tecnica/{inmueble}/ se servían sin la hoja de estilos del plugin.
* Corregido: con Elementor, los widgets del plugin colocados fuera de una página de inmuebles se pintaban sin estilos; ahora cada widget declara la hoja que necesita.
* El PDF de la ficha técnica se rediseña para aprovechar el espacio: información general, dimensiones, finanzas, características, fotos y enlaces multimedia van a tres columnas, y las fotos se acotan a 150 px de ancho. Una ficha completa pasa de ocho páginas a dos.

= 2.6.0 =
* El perfil público del asesor pasa a su URL de usuario: /author/{asesor}/. Muestra foto, nombre, cargo, correo, teléfono, descripción e inmuebles relacionados.
* Solo se toman los usuarios que son asesores (rol de asesor o con inmuebles asignados); el archivo de autor del resto lo sigue sirviendo el tema.
* La ruta anterior /property-agent/{asesor}/ redirige con 301 a la URL de usuario, para no competir consigo misma en el índice.
* La página de perfil maquetada con Divi o WPBakery ya se renderiza también en la URL de autor (antes solo Elementor).
* Nuevos filtros: `homlity_agent_profile_use_author_url` (volver a la ruta antigua si el sitio tiene desactivados los archivos de autor) y `homlity_user_is_agent`.

= 2.5.0 =
* Corregido: el orden "Precio: menor a mayor" (y mayor a menor) no ordenaba nada. WordPress resolvía `meta_value_num` contra el filtro de disponibilidad en lugar del precio.
* Corregido: la búsqueda por código y por palabra clave podía no devolver resultados en servidores con MySQL 5.7+ (ONLY_FULL_GROUP_BY).
* Ficha técnica editable con Elementor, Divi y WPBakery: nuevo widget "Ficha técnica del inmueble" con controles de espaciado, márgenes, colores y visibilidad por sección.
* Nueva página configurable en Configuración → Plantillas ("Página de ficha técnica"); la ficha pasa a servirse en /ficha-tecnica/{inmueble}/ y la renderiza el maquetador.
* La dirección del inmueble ya no se publica en la ficha (opcional desde el widget).
* Corregidos desbordamientos que cortaban datos largos, URLs, tablas e imágenes de la descripción.
* Nuevo shortcode [homlity_technical_sheet].

= 2.4.0 =
* Cada asesor tiene su página pública en /property-agent/{asesor}/, editable con Elementor, Divi o WPBakery.
* El widget de asesor añade la fuente "Asesor de la página", que toma el asesor de la consulta actual.
* El widget de listado de inmuebles puede mostrar solo los inmuebles de un asesor (el de la página o uno fijo).

= 2.3.16 =
* Added centralized, privacy-safe error reporting for official Homlity plugins.
* Added bounded retries, license-aware Homi delivery and the Incidencias diagnostics tab.

= 2.3.15 =
* Prevented stale image-CDN thumbnails from showing photos that belong to another property.
* Added per-attachment cache versioning to property gallery image URLs.

= 2.3.14 =
* Added an admin Versiones tab for Homlity plugin upgrades and controlled downgrades from Homi.
* Added permission, compatibility, HTTPS, checksum, fresh-catalog and WordPress rollback safeguards.

= 12.0.3 =
* WordPress.org compliance: readme translated to English.
* Reduced tags to 5.
* Updated "Tested up to" to WordPress 6.9.
* Plugin name changed to Homlity Real Estate.

= 1.0.0 =
* Initial release.
* Basic property management.
* Property types, operation types, and custom attributes.
* Multi-country and multi-currency support.
* Basic WordPress admin panel integration.

== Third-Party Libraries ==

This plugin bundles the following open-source libraries:

* **GLightbox** — MIT License. Lightbox gallery for property photos.
  https://github.com/biati-digital/glightbox
* **Guzzle HTTP** (guzzlehttp/guzzle) — MIT License. HTTP client used for CRM integrations.
  https://github.com/guzzle/guzzle
* **Guzzle Promises** (guzzlehttp/promises) — MIT License. Async promise support for Guzzle.
  https://github.com/guzzle/promises
* **Guzzle PSR-7** (guzzlehttp/psr7) — MIT License. PSR-7 HTTP message implementation.
  https://github.com/guzzle/psr7
* **Dompdf** (dompdf/dompdf) — LGPL-2.1 License. Generates PDF technical sheets for properties.
  https://github.com/dompdf/dompdf
* **PHP Font Library** (dompdf/php-font-lib) — LGPL-2.1-or-later License. Font handling for Dompdf.
  https://github.com/dompdf/php-font-lib
* **PHP SVG Library** (dompdf/php-svg-lib) — LGPL-3.0-or-later License. SVG rendering for Dompdf.
  https://github.com/dompdf/php-svg-lib
* **HTML5 PHP** (masterminds/html5) — MIT License. HTML5 parser used by Dompdf.
  https://github.com/Masterminds/html5-php
* **PSR HTTP Client** (psr/http-client) — MIT License. HTTP client interface.
  https://github.com/php-fig/http-client
* **PSR HTTP Factory** (psr/http-factory) — MIT License. HTTP factory interface.
  https://github.com/php-fig/http-factory
* **PSR HTTP Message** (psr/http-message) — MIT License. HTTP message interface.
  https://github.com/php-fig/http-message
* **PHP CSS Parser** (sabberworm/php-css-parser) — MIT License. CSS parsing for Dompdf.
  https://github.com/sabberworm/PHP-CSS-Parser

All MIT-licensed libraries are GPL-compatible. LGPL-licensed libraries (Dompdf, php-font-lib, php-svg-lib) are used as independent components and are compatible with GPL-2.0-or-later under the LGPL linking exception.

== External Services ==

This plugin may connect to the following external services:

= WhatsApp (Meta Platforms, Inc.) =
Used to generate contact links that open a WhatsApp conversation with a property agent, and to generate share links so visitors can send a property listing via WhatsApp.
* **Data sent:** The agent's phone number and an optional pre-filled text message (property title and URL) are included in the link URL. No data is sent to WhatsApp servers unless the visitor clicks the link.
* **When:** The link is generated on every property detail page that has an agent with a phone number configured. Data is only transmitted to WhatsApp when the visitor clicks the button.
* **Service provider:** Meta Platforms, Inc.
* **Privacy policy:** https://www.whatsapp.com/legal/privacy-policy
* **Terms of service:** https://www.whatsapp.com/legal/terms-of-service

= Google Maps (Google LLC) =
Used to display an interactive map of the property location, a Street View preview, and a "Get directions" link on property detail pages.
* **Data sent:** The property's geographic coordinates (latitude and longitude) stored by the site administrator. The visitor's IP address and browser information may be collected by Google when the map or Street View iframe loads.
* **When:** Only on property detail pages where geographic coordinates have been saved. The map iframe is loaded automatically when the visitor opens the page.
* **Service provider:** Google LLC.
* **Privacy policy:** https://policies.google.com/privacy
* **Terms of service:** https://developers.google.com/maps/terms-20180207

= YouTube (Google LLC) =
Used to embed property video tours directly on property detail pages.
* **Data sent:** The YouTube video ID is included in the iframe URL. The visitor's IP address and browser information may be collected by YouTube/Google when the iframe loads.
* **When:** Only on property detail pages that have a YouTube video URL saved. The iframe is loaded automatically when the visitor opens the page.
* **Service provider:** Google LLC.
* **Privacy policy:** https://policies.google.com/privacy
* **Terms of service:** https://www.youtube.com/t/terms

= OpenStreetMap Nominatim (Geocoding) =
Used to convert property addresses into geographic coordinates (latitude/longitude).
* **Data sent:** Street address, neighborhood, city, state/region, and country entered by the site administrator.
* **When:** Only when the administrator explicitly clicks the "Geocode" button in the property editor. No data is sent automatically.
* **Service:** Nominatim geocoding API, operated by the OpenStreetMap Foundation.
* **Privacy policy:** https://osmfoundation.org/wiki/Privacy_Policy
* **Terms of use:** https://operations.osmfoundation.org/policies/nominatim/

= CKEditor 5 (CKSource / Mateusz Bukowski Foundation) =
Used to provide a rich-text description editor for property listings inside the WordPress admin dashboard.
* **Data sent:** No property or user data is sent to CKEditor's servers. The browser downloads the CKEditor 5 JavaScript library file from the CDN when an administrator opens the property editor. The download request includes the visitor's IP address and browser information as standard HTTP headers.
* **When:** Only when an administrator opens the property editing screen in wp-admin. End users on the public website are never affected.
* **URL loaded:** https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js
* **Service provider:** CKSource Sp. z o.o. (operating as Mateusz Bukowski Foundation).
* **Privacy policy:** https://ckeditor.com/legal/privacy-policy/
* **Terms of service:** https://ckeditor.com/legal/ckeditor-oss-license/

= CRM Integrations (Optional) =
When a CRM integration is configured and enabled by the site administrator, the plugin synchronizes property data with external real estate CRM platforms.
* **Data sent:** Property details (title, description, price, location, media), agent information.
* **When:** Only when the administrator enables and configures a CRM connection. Disabled by default.
* **Note:** The specific privacy policy and terms of service depend on the CRM provider configured by the site administrator.

= Analytics / Visit Tracking (Optional) =
The plugin includes optional built-in analytics to count property visits, contact clicks, and PDF downloads. This feature is **disabled by default** and must be explicitly enabled by the site administrator.
* **Data stored:** Anonymized visitor identifier (random cookie), hashed IP address (SHA-256), hashed user-agent string (SHA-256), and event timestamps. All data is stored locally in the WordPress database and is never transmitted to external servers.
* **When:** Only when the "Enable Analytics" option is active in the plugin settings. The plugin respects the WP Consent API if a compatible consent plugin is installed.
* **Important:** When enabling this feature, site administrators are responsible for obtaining visitor consent in accordance with applicable privacy laws (GDPR, CCPA, etc.) and updating their site's privacy policy accordingly.

== Upgrade Notice ==

= 12.0.3 =
Maintenance release with WordPress.org compliance fixes.
