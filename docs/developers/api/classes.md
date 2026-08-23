# Clases públicas

Sólo se documenta lo que está bajo `Homlity\Developer\`. Todo lo demás es
interno y no forma parte del contrato.

---

## Índice

| Clase | Para qué |
| --- | --- |
| [`Homlity`](#homlity) | Fachada: punto de entrada de toda la API |
| [`Api`](#api) | Versiones y comprobaciones de entorno |
| [`Extension\ExtensionRegistry`](#extensionextensionregistry) | Censo de extensiones |
| [`Extension\Requirements`](#extensionrequirements) | Qué necesita una extensión |
| [`Models\Property`](#modelsproperty) | Un inmueble |
| [`Models\Money`](#modelsmoney) | Importe con moneda |
| [`Models\Location`](#modelslocation) | Ubicación |
| [`Models\Image`](#modelsimage) | Una imagen de la galería |
| [`Models\Agent`](#modelsagent) | El asesor |
| [`Events\PropertyContext`](#eventspropertycontext) | Quién escribió y por qué |
| [`Events\PropertyChanges`](#eventspropertychanges) | Qué cambió |
| [`Services\PropertyRepository`](#servicespropertyrepository) | Buscar inmuebles |
| [`Support\Hooks`](#supporthooks) | Nombres de hook como constantes |
| [`Support\Deprecated`](#supportdeprecated) | Mecanismo de deprecación |

---

## `Homlity`

`Homlity\Developer\Homlity` · desde 2.8.0 · `final`

La fachada. Todo lo demás es alcanzable desde aquí.

| Método | Retorno | Descripción |
| --- | --- | --- |
| `version()` | `string` | Versión del plugin, o `''` si no está cargado |
| `apiVersion()` | `string` | Versión del contrato público |
| `isAvailable()` | `bool` | Si el plugin está cargado |
| `isVersionSupported(string $minimum)` | `bool` | Si el plugin es al menos `$minimum` |
| `extensions()` | `ExtensionRegistry` | El registro de extensiones |
| `properties()` | `PropertyRepository` | La API de lectura de inmuebles |

```php
use Homlity\Developer\Homlity;

if (Homlity::isVersionSupported('2.8.0')) {
    $property = Homlity::properties()->findByCode('VTAP1320041');
}
```

Los métodos `setExtensionRegistry()` y `setPropertyRepository()` existen para
las pruebas y están marcados `@internal`. Llamarlos en producción descarta lo
que hubiera registrado.

---

## `Api`

`Homlity\Developer\Api` · desde 2.8.0 · `final`

| Constante | Valor | Descripción |
| --- | --- | --- |
| `VERSION` | `'1.0.0'` | Versión del contrato público |
| `MINIMUM_PHP` | `'8.0'` | PHP mínimo del plugin |
| `MINIMUM_WP` | `'5.8'` | WordPress mínimo del plugin |

| Método | Retorno | Descripción |
| --- | --- | --- |
| `pluginVersion()` | `string` | Versión del plugin |
| `isAvailable()` | `bool` | Si el plugin está cargado |
| `isVersionSupported(string $minimum)` | `bool` | Comparación con la versión del plugin |
| `isApiVersionSupported(string $minimum)` | `bool` | Comparación con la versión de la API |
| `wordPressVersion()` | `string` | Versión de WordPress |
| `phpVersion()` | `string` | Versión de PHP |

Prefiere `isApiVersionSupported()` cuando lo que necesitas es un hook o una
clase, e `isVersionSupported()` cuando lo que necesitas es una funcionalidad
del plugin.

---

## `Extension\ExtensionRegistry`

`Homlity\Developer\Extension\ExtensionRegistry` · desde 2.8.0 · `final`

Obtenlo con `Homlity::extensions()` o `homlity_extensions()`. No lo construyas.

| Método | Retorno | Descripción |
| --- | --- | --- |
| `register(ExtensionInterface $extension)` | `bool` | Registra. `false` si se rechaza; nunca lanza |
| `has(string $slug)` | `bool` | Si hay una extensión arrancada con ese slug |
| `get(string $slug)` | `?ExtensionInterface` | La extensión, o `null` |
| `all()` | `array<string,ExtensionInterface>` | Todas las arrancadas, por slug |
| `failures()` | `array<string,string[]>` | Motivos de rechazo, por slug |
| `isDispatched()` | `bool` | Si ya se despachó el arranque |

El slug se normaliza con `sanitize_key()` tanto al registrar como al consultar,
así que `get('Mi-CRM')` encuentra lo registrado como `mi-crm`.

Ver [Registro de extensiones](../extensions/extension-registration.md).

---

## `Extension\Requirements`

`Homlity\Developer\Extension\Requirements` · desde 2.8.0 · `final` · inmutable

| Método | Retorno | Descripción |
| --- | --- | --- |
| `create(array $requirements)` | `self` | Construye desde un array |
| `none()` | `self` | Sin requisitos; siempre se cumple |
| `areSatisfied()` | `bool` | Si el entorno los cumple todos |
| `unmetRequirements()` | `string[]` | Motivos traducidos; vacío si se cumplen |
| `homlityVersion()` | `string` | Lo declarado |
| `apiVersion()` | `string` | Lo declarado |
| `phpVersion()` | `string` | Lo declarado |
| `wordPressVersion()` | `string` | Lo declarado |
| `plugins()` | `string[]` | Lo declarado |

Claves reconocidas en `create()`: `homlity`, `api`, `php`, `wordpress`,
`plugins`. Las desconocidas se ignoran, para que una extensión escrita contra
una versión posterior siga arrancando en vez de quedar bloqueada.

---

## `Models\Property`

`Homlity\Developer\Models\Property` · desde 2.8.0 · `final` · sólo lectura

El modelo central. Ver [su documentación completa](../models/property.md).

---

## `Models\Money`

`Homlity\Developer\Models\Money` · desde 2.8.0 · `final` · inmutable

| Método | Retorno | Descripción |
| --- | --- | --- |
| `__construct(float $amount, string $currency = '')` | — | Moneda vacía → la del sitio |
| `fromMeta($amount, $currency)` | `?self` | Desde metadatos crudos; `null` si no hay importe |
| `getAmount()` | `float` | Importe en unidades mayores |
| `getCurrency()` | `string` | Código ISO-4217, en mayúsculas |
| `getFormatted()` | `string` | Con el formato de precios del sitio |
| `toArray()` | `array` | `amount`, `currency`, `formatted` |
| `__toString()` | `string` | Igual que `getFormatted()` |

`fromMeta()` entiende las notaciones que mandan los CRM: `450000000`,
`$ 2.500.000`, `2,500,000.00`, `1.234,56`. Un separador solitario seguido de
exactamente tres dígitos se lee como separador de miles, porque un precio con
tres decimales no existe pero uno escrito `2.500` sí.

```php
$precio = $property->getSalePrice();

if ($precio !== null) {
    echo $precio->getFormatted();   // "$ 450.000.000"
    echo $precio->getAmount();      // 450000000.0
    echo $precio->getCurrency();    // "COP"
}
```

---

## `Models\Location`

`Homlity\Developer\Models\Location` · desde 2.8.0 · `final` · sólo lectura

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getAddress()` | `string` | Dirección, o `''` si el inmueble la oculta |
| `getAddressComplement()` | `string` | Torre/apto, o `''` si se oculta |
| `getReference()` | `string` | Referencia libre; siempre pública |
| `isExactAddressPublic()` | `bool` | Si la dirección exacta es pública |
| `getCountry()` | `string` | Nombre del país |
| `getState()` | `string` | Nombre del departamento o provincia |
| `getCity()` | `string` | Nombre de la ciudad |
| `getNeighborhood()` | `string` | Nombre del barrio |
| `getLatitude()` | `?float` | Latitud, o `null` |
| `getLongitude()` | `?float` | Longitud, o `null` |
| `hasCoordinates()` | `bool` | Si hay latitud y longitud |
| `toArray()` | `array` | Todo lo anterior |

`getAddress()` respeta la bandera de dirección oculta. Una extensión que
publique en portales externos **no debe** saltársela leyendo el metadato: es
una decisión del propietario.

---

## `Models\Image`

`Homlity\Developer\Models\Image` · desde 2.8.0 · `final` · sólo lectura

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getUrl()` | `string` | URL del tamaño completo |
| `getAttachmentId()` | `int` | ID en la biblioteca, o `0` si es remota |
| `getAlt()` | `string` | Texto alternativo |
| `isLocal()` | `bool` | Si el archivo está en esta instalación |
| `getSizeUrl(string $size = 'large')` | `string` | URL de un tamaño registrado |
| `toArray()` | `array` | `url`, `attachment_id`, `alt` |
| `__toString()` | `string` | Igual que `getUrl()` |

`getSizeUrl()` devuelve la URL completa para imágenes remotas: no tienen
tamaños generados.

---

## `Models\Agent`

`Homlity\Developer\Models\Agent` · desde 2.8.0 · `final` · sólo lectura

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getUserId()` | `int` | Usuario de WordPress, o `0` |
| `getName()` | `string` | Nombre |
| `getEmail()` | `string` | Correo |
| `getPhone()` | `string` | Teléfono |
| `getRole()` | `string` | Cargo |
| `getPhotoUrl()` | `string` | Foto |
| `getExternalId()` | `string` | Su identificador en el CRM de origen |
| `toArray()` | `array` | Todo lo anterior |

Son los datos de contacto **de la inmobiliaria**, los que ya aparecen en la
ficha pública. Los datos del propietario captados por el formulario de
consignación no forman parte de este modelo ni de ningún otro de la API.

---

## `Events\PropertyContext`

`Homlity\Developer\Events\PropertyContext` · desde 2.8.0 · `final` · inmutable

Responde a «¿quién escribió esto?».

| Constante | Valor |
| --- | --- |
| `ORIGIN_ADMIN` | `'admin'` |
| `ORIGIN_CRM` | `'crm'` |
| `ORIGIN_CONSIGNMENT` | `'consignment'` |
| `ORIGIN_SYNC` | `'sync'` |
| `ORIGIN_UNKNOWN` | `'unknown'` |

| Método | Retorno | Descripción |
| --- | --- | --- |
| `getOrigin()` | `string` | Una de las constantes `ORIGIN_*` |
| `getSource()` | `string` | Clave del CRM, o `''` |
| `isNew()` | `bool` | Si esta escritura creó el inmueble |
| `isExternal()` | `bool` | Si vino de fuera de wp-admin |
| `toArray()` | `array` | `origin`, `source`, `is_new` |

Compara siempre contra las constantes: pueden aparecer orígenes nuevos en
versiones futuras.

---

## `Events\PropertyChanges`

`Homlity\Developer\Events\PropertyChanges` · desde 2.8.0 · `final` · inmutable

Responde a «¿qué cambió exactamente?». Los campos se nombran con las rutas
canónicas — `pricing.sale_price`, `metrics.bedrooms`, `post.title` — no con las
claves de metadato internas.

| Método | Retorno | Descripción |
| --- | --- | --- |
| `diff(array $before, array $after)` | `self` | Construye por comparación |
| `isEmpty()` | `bool` | Si no cambió nada |
| `has(string $field)` | `bool` | Si cambió ese campo |
| `hasGroup(string $group)` | `bool` | Si cambió algo del grupo (`pricing`, `location`, …) |
| `fields()` | `string[]` | Nombres de los campos que cambiaron |
| `previous(string $field)` | `mixed` | Valor anterior, o `null` |
| `current(string $field)` | `mixed` | Valor actual, o `null` |
| `toArray()` | `array` | Todo el diff |

Nunca transporta los datos personales del propietario: esos campos están
excluidos de la comparación.

---

## `Services\PropertyRepository`

`Homlity\Developer\Services\PropertyRepository` · desde 2.8.0 · `final`

Obtenlo con `Homlity::properties()` o `homlity_properties()`.

| Método | Retorno | Descripción |
| --- | --- | --- |
| `find(int $propertyId)` | `?Property` | Por ID de post |
| `findByCode(string $code)` | `?Property` | Por código comercial |
| `findByExternalId(string $source, string $externalId)` | `?Property` | Por identificador de CRM |

`find()` devuelve `null` si el post no existe o no es un inmueble.
`findByCode()` compara el código exacto y **no** dispara una sincronización bajo
demanda.

---

## `Support\Hooks`

`Homlity\Developer\Support\Hooks` · desde 2.8.0 · `final`

Los nombres de todos los hooks públicos como constantes, más
`Hooks::actions()` y `Hooks::filters()`, que devuelven las listas completas.

El valor de cada constante forma parte del contrato: no cambiará dentro de la
versión mayor.

---

## `Support\Deprecated`

`Homlity\Developer\Support\Deprecated` · desde 2.8.0 · `final`

| Método | Descripción |
| --- | --- |
| `action(string $hook, array $args, string $version, string $replacement = '')` | Dispara una acción deprecada |
| `filter(string $hook, array $args, string $version, string $replacement = '')` | Aplica un filtro deprecado |
| `fn(string $function, string $version, string $replacement = '')` | Avisa de una función deprecada |
| `argument(string $function, string $version, string $message = '')` | Avisa de un argumento deprecado |

Los avisos sólo se emiten con `WP_DEBUG` activo. Puedes usarlos para las
deprecaciones de tu propia extensión. Ver
[Deprecaciones](../versioning/deprecations.md).
