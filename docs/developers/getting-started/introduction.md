# Introducción

Homlity Real Estate es un plugin de WordPress para el sector inmobiliario:
gestiona inmuebles, asesores, ubicaciones, monedas, SEO y la sincronización con
CRMs y portales.

A partir de la versión **2.8.0** también es un **framework de integración**: un
núcleo con una API pública documentada sobre la que se pueden construir
extensiones externas.

---

## Qué es la Developer API

Un contrato. Un conjunto acotado de hooks, clases e interfaces que Homlity se
compromete a no romper mientras no cambie de versión mayor.

```
WordPress
   │
   ▼
Homlity Real Estate  ← el núcleo
   │
   ├── Developer API  ← el contrato: actions, filters, contracts, models
   │
   └── Extensiones    ← tu código
         CRM · Portales · ERP · Analítica · Automatización · IA
```

Todo lo público vive bajo un solo namespace:

```php
Homlity\Developer\
```

Y sus hooks bajo una sola convención:

```
homlity/dominio/evento
```

---

## Qué **no** es

No es la lista de todo lo que técnicamente puedes llamar. Homlity tiene unas
250 clases; sólo una docena son públicas. El resto —`Homlity\PluginInmobiliario\*`,
las clases `Homlity_*` de `includes/`, los metadatos `_property_*`, las tablas
propias— es implementación interna.

Funciona hoy. Puede no funcionar mañana, sin aviso y sin deprecación, en una
versión de parche.

Esa frontera no está para incordiar: es lo que permite que la parte pública sea
de verdad estable.

---

## Qué puedes construir

| Tipo | Ejemplo |
| --- | --- |
| Integración con CRM | Traer inmuebles de un CRM propio |
| Publicación en portales | Empujar inmuebles a un portal inmobiliario |
| ERP y contabilidad | Sincronizar contratos y comisiones |
| Analítica | Enviar eventos a una plataforma de datos |
| Automatización | Disparar flujos cuando cambia un precio |
| IA | Generar descripciones o responder consultas |
| Personalización | Añadir campos, cambiar consultas, adaptar la salida |

Comercial o gratuito, público o privado. Ver la
[política «Compatible con Homlity»](../extensions/compatible-with-homlity.md).

---

## Los cuatro conceptos

### 1. Extensión

Un plugin de WordPress normal que implementa
[`ExtensionInterface`](../api/interfaces.md#extensioninterface) y se registra en
Homlity. A cambio obtiene comprobación de compatibilidad, un arranque en el
momento correcto y aislamiento de errores.

### 2. Eventos

Homlity anuncia lo que pasa con los inmuebles mediante *actions* de WordPress.
Se disparan cuando la escritura ha terminado del todo, nunca a medias.

```php
add_action('homlity/property/updated', function ($property, $changes, $context) {
    if ($changes->hasGroup('pricing')) {
        // el precio cambió
    }
}, 10, 3);
```

### 3. Modelo `Property`

Un objeto de sólo lectura que representa un inmueble. Existe para que no tengas
que conocer los metadatos internos ni los cuatro formatos en los que se ha
guardado históricamente una galería.

```php
$property->getCode();
$property->getSalePrice()?->getFormatted();
$property->getLocation()->getCity();
$property->getImages();
```

### 4. Filtros

Puntos donde puedes cambiar lo que Homlity hace: la carga que va a guardar, los
datos que expone, la consulta que ejecuta.

---

## Cinco líneas para empezar

```php
add_action('plugins_loaded', function () {
    if (!function_exists('homlity_is_available') || !homlity_is_available()) {
        return;
    }

    add_action('homlity/property/created', function ($property) {
        error_log('Nuevo inmueble: ' . $property->getCode());
    });
}, 21);
```

Cuando esto te funcione, sigue por
[Crear tu primera extensión](../extensions/create-your-first-extension.md).
