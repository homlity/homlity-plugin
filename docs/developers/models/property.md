# El modelo `Property`

`Homlity\Developer\Models\Property` · desde 2.8.0 · `final` · sólo lectura

El objeto que representa un inmueble en la Developer API. Es lo que reciben
todos los hooks del ciclo de vida y lo que devuelve el repositorio.

---

## Por qué existe

Porque los datos de un inmueble, tal como se guardan, no son usables desde
fuera:

- Un inmueble tiene ~55 metadatos con prefijo `_property_`, cuyos nombres y
  formatos son internos.
- La **galería** se guarda en cuatro formas distintas según de dónde vino el
  inmueble: CSV de IDs de adjunto desde wp-admin, array de URLs desde un CRM,
  array de arrays desde algunos adaptadores, cadena JSON en instalaciones
  antiguas. La plantilla del tema dedica 50 líneas a distinguirlas.
- Los **precios** llegan de los CRM como texto libre: `450000000`,
  `$ 2.500.000`, `1,234.56`.
- Junto a los datos comerciales viven los **datos personales del propietario**
  captados por el formulario de consignación, y la respuesta cruda del CRM, que
  puede contener credenciales.

`Property` resuelve las cuatro cosas: normaliza, tipa, y no expone lo que no
debe salir.

---

## Cómo obtener uno

```php
// Por ID.
$property = homlity_get_property(128);

// Por código comercial.
$property = homlity_properties()->findByCode('VTAP1320041');

// Por identificador en un CRM.
$property = homlity_properties()->findByExternalId('wasi', 'EXT-77');

// O te llega en un hook.
add_action('homlity/property/updated', function ($property) { /* … */ });
```

Los tres métodos devuelven `null` cuando no encuentran nada. Compruébalo:

```php
$property = homlity_get_property($id);
if ($property === null) {
    return;
}
```

---

## Referencia

### Identidad

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getId()` | `int` | ID del post en WordPress |
| `getCode()` | `string` | Código comercial, `VTAP1320041`. Puede estar vacío |
| `getTitle()` | `string` | Título |
| `getDescription()` | `string` | Descripción larga, sin pasar por `the_content` |
| `getShortDescription()` | `string` | Descripción corta |
| `getUrl()` | `string` | URL pública canónica |

### Estado

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getStatus()` | `string` | Estado del post: `publish`, `draft`, `pending`, `private`, `trash` |
| `isAvailable()` | `bool` | Si está publicado **y** disponible comercialmente |
| `isFeatured()` | `bool` | Si está destacado |

`getStatus()` e `isAvailable()` no son lo mismo. Un inmueble puede estar
publicado y retirado del mercado: entonces `getStatus()` devuelve `publish`,
`isAvailable()` devuelve `false`, y el sitio muestra la página de «no
disponible» en lugar de la ficha.

### Clasificación

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getOperation()` | `string` | Slug de la operación principal, o `''` |
| `getOperations()` | `string[]` | Todas las operaciones |
| `getPropertyType()` | `string` | Slug del tipo principal, o `''` |
| `getPropertyTypes()` | `string[]` | Todos los tipos |
| `getFeatures()` | `string[]` | Slugs de características |

Son **slugs de término**, no nombres: `venta`, `apartamento`, `piscina`. Los
slugs son estables entre instalaciones porque pasan por la homologación del
plugin; los nombres dependen del idioma y del CRM de origen.

### Precios

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getSalePrice()` | `?Money` | Precio de venta |
| `getRentPrice()` | `?Money` | Canon de arriendo mensual |
| `getAdminFee()` | `?Money` | Administración mensual |
| `getPrice()` | `?Money` | El de venta si lo hay, si no el de arriendo |
| `getCurrency()` | `string` | Moneda de `getPrice()`, o `''` |

`null` significa «no tiene ese precio», y un importe de cero también se
considera ausencia de precio.

```php
$precio = $property->getSalePrice();

if ($precio !== null) {
    echo $precio->getFormatted();  // "$ 450.000.000"
    echo $precio->getAmount();     // 450000000.0
    echo $precio->getCurrency();   // "COP"
}
```

### Métricas

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getBedrooms()` | `int` | Habitaciones |
| `getBathrooms()` | `int` | Baños |
| `getParkingSpaces()` | `int` | Parqueaderos |
| `getArea()` | `float` | Área total en m² |
| `getPrivateArea()` | `float` | Área privada en m² |
| `getStratum()` | `int` | Estrato socioeconómico; `0` si no aplica |

Cuando el dato no existe devuelven `0`, no `null`.

### Relaciones

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getLocation()` | `Location` | Nunca `null` |
| `getImages()` | `Image[]` | En orden de visualización; puede estar vacío |
| `getVideos()` | `string[]` | URLs de vídeo |
| `getAgent()` | `?Agent` | `null` si no hay asesor asignado |

### Procedencia

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getExternalSource()` | `string` | Clave del CRM, o `''` si es local |
| `getExternalId()` | `string` | Su identificador en ese CRM |
| `isSynced()` | `bool` | Si se mantiene sincronizado con un sistema externo |
| `getLastSyncedAt()` | `string` | Marca ISO-8601 del último sync, o `''` |

### Salidas de emergencia

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getPost()` | `?WP_Post` | El post subyacente |
| `toArray()` | `array` | Todo el modelo, codificable a JSON |

`getPost()` está ahí para lo que el modelo no cubre: renderizar una plantilla,
leer un metadato que escribió tu propia extensión. **Todo lo que alcances a
través de él queda fuera del contrato de la API.**

`toArray()` aplana los objetos de valor con sus propios `toArray()`. Las claves
de ese array **sí** forman parte del contrato.

---

## Lo que este modelo no expone

Deliberadamente, y con pruebas que lo verifican:

| Metadato | Qué es |
| --- | --- |
| `_property_sync_payload` | Respuesta cruda del CRM; puede contener tokens |
| `_property_identification` | Documento de identidad del propietario |
| `_property_contact_name` | Nombre del propietario |
| `_property_contact_email` | Correo del propietario |
| `_property_contact_phone` | Teléfono del propietario |
| `_property_contact_whatsapp` | WhatsApp del propietario |
| `_consignment_*` | Banderas de consentimiento |

Son datos personales de un tercero, no datos comerciales del inmueble.
Publicarlos a todas las extensiones instaladas sería un problema de privacidad,
no una comodidad.

El modelo se construye desde una **lista blanca** de claves, no desde «todo
menos estas»: un metadato añadido mañana —por Homlity o por otro plugin— no
puede colarse en la API sin que alguien lo decida.

Si tu integración necesita legítimamente esos datos, léelos tú, con el
consentimiento del titular del sitio, y asume la responsabilidad de hacerlo.

Nota aparte: `Location::getAddress()` respeta la bandera de dirección oculta y
devuelve `''` cuando el propietario pidió no publicarla. Saltarse eso leyendo
`_property_address` a mano es técnicamente posible y comercialmente una mala
idea.

---

## Esquema canónico

La forma en la que un CRM entrega un inmueble a Homlity. Es lo que produce
`CrmAdapterInterface::mapRecordToNormalized()`, lo que filtra
`homlity/property/normalized`, y de donde salen los nombres de campo de
`PropertyChanges`.

```php
[
    'external' => [
        'source'     => 'wasi',        // requerido — clave del CRM
        'id'         => 'EXT-77',      // requerido — id en ese CRM
        'updated_at' => '2026-08-01T10:00:00Z',
        'raw'        => [/* registro original */],
    ],

    'post' => [
        'title'             => 'Apartamento en El Poblado',  // requerido
        'description'       => '…',
        'short_description' => '…',
        'status'            => 'publish',  // publish | draft | pending | private
    ],

    'location' => [
        'address'            => 'Calle 10 # 43-25',  // requerido
        'address_dane'       => '…',
        'latitude'           => 6.2088,              // requerido
        'longitude'          => -75.5736,            // requerido
        'show_exact_address' => true,
        'address_complement' => 'Torre 3 Apto 502',
        'location_reference' => 'Frente al parque',
        'maps_url'           => '…',
    ],

    'pricing' => [
        'sale_price'      => '450000000',
        'sale_currency'   => 'COP',
        'rent_price'      => '2500000',
        'rent_currency'   => 'COP',
        'admin_price'     => '350000',
        'admin_currency'  => 'COP',
        'admin_included'  => false,
        'negotiable'      => true,
        'commercial_note' => '…',
    ],

    'metrics' => [
        'area' => '85', 'area_lot' => '', 'area_private' => '78', 'area_built' => '85',
        'bedrooms' => 3, 'bathrooms' => 2, 'parking' => 1,
        'condition' => 'usado', 'year_built' => 2015,
        'code' => 'VTAP1320041',
        'stratum' => 5, 'floor' => 8, 'levels' => 1, 'elevators' => 2,
        'featured' => false,
    ],

    'taxonomy' => [
        'property_operation'    => ['Venta'],
        'property_type'         => ['Apartamento'],
        'property_category'     => ['Residencial'],
        'property_tag'          => ['Nuevo'],
        'property_feature'      => ['Piscina', 'Gimnasio'],
        'property_country'      => ['Colombia'],
        'property_state'        => ['Antioquia'],
        'property_city'         => ['Medellín'],
        'property_neighborhood' => ['El Poblado'],
        'property_nearby'       => ['Centro comercial'],
    ],

    'media' => [
        'gallery'            => ['https://…/1.jpg', 'https://…/2.jpg'],
        'featured_image_url' => 'https://…/portada.jpg',
        'videos'             => ['https://…'],
        'tour_360'           => ['https://…'],
        'photos_360'         => ['https://…'],
        'brochure'           => 'https://…/ficha.pdf',
        'photo_note'         => 'Imágenes de referencia',
    ],

    'advisor' => [
        'external_id' => 'ADV-9',
        'name'        => 'Ana Ruiz',
        'email'       => 'ana@example.com',
        'phone'       => '+573001112233',
        'photo'       => 'https://…/ana.jpg',
        'role'        => 'Asesora comercial',
        'user_id'     => 0,
    ],
]
```

En `taxonomy` van **nombres**, no slugs: Homlity los homologa contra sus
términos canónicos, de modo que «Apto» de un CRM y «Apartamento» de otro
terminen en el mismo término.

### Campos en `PropertyChanges`

Los nombres que devuelve `$changes->fields()` son las rutas de este esquema:

| Grupo | Ejemplos |
| --- | --- |
| `post.` | `post.title`, `post.description`, `post.status` |
| `pricing.` | `pricing.sale_price`, `pricing.rent_currency` |
| `metrics.` | `metrics.bedrooms`, `metrics.area`, `metrics.code` |
| `location.` | `location.address`, `location.latitude` |
| `media.` | `media.gallery`, `media.videos` |
| `advisor.` | `advisor.name`, `advisor.email` |
| `taxonomy.` | `taxonomy.property_operation`, `taxonomy.property_city` |
| `availability.` | `availability.status`, `availability.available` |

```php
$changes->has('pricing.sale_price');   // un campo concreto
$changes->hasGroup('pricing');         // cualquier cosa del grupo
```

---

## Ejemplo completo

```php
add_action('homlity/property/created', function ($property, $context) {
    if (!$context->isExternal()) {
        return;
    }

    $precio = $property->getPrice();
    $ubicacion = $property->getLocation();

    mi_portal_publicar([
        'referencia'  => $property->getCode(),
        'titulo'      => $property->getTitle(),
        'descripcion' => wp_strip_all_tags($property->getDescription()),
        'operacion'   => $property->getOperation(),
        'tipo'        => $property->getPropertyType(),
        'precio'      => $precio?->getAmount(),
        'moneda'      => $precio?->getCurrency(),
        'habitaciones'=> $property->getBedrooms(),
        'banos'       => $property->getBathrooms(),
        'area'        => $property->getArea(),
        'ciudad'      => $ubicacion->getCity(),
        'barrio'      => $ubicacion->getNeighborhood(),
        'lat'         => $ubicacion->getLatitude(),
        'lng'         => $ubicacion->getLongitude(),
        'direccion'   => $ubicacion->getAddress(),  // '' si el dueño la ocultó
        'fotos'       => array_map(fn($i) => $i->getUrl(), $property->getImages()),
        'asesor'      => $property->getAgent()?->getName(),
        'url'         => $property->getUrl(),
    ]);
}, 10, 2);
```
