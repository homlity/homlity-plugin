# Homlity Real Estate

[![Versión](https://img.shields.io/badge/versi%C3%B3n-2.8.0-0b7285)](CHANGELOG.md)
[![Developer API](https://img.shields.io/badge/Developer%20API-1.0.0-2b8a3e)](docs/developers/api/overview.md)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)](https://www.php.net/)
[![Licencia](https://img.shields.io/badge/licencia-GPLv2%2B-blue)](license.txt)

Plugin de WordPress para el sector inmobiliario: gestión de inmuebles,
asesores, ubicaciones, monedas, SEO y GEO, con integraciones a CRMs, portales y
constructores de página.

Desde la versión 2.8.0 es además un **framework de integración**: expone una
Developer API pública y estable sobre la que se pueden construir extensiones
externas sin modificar el núcleo.

**Documentación para desarrolladores:** <https://homlity.com/desarrolladores/>

---

## Descripción

Homlity Real Estate está pensado para agencias, corredores y asesores
independientes que necesitan un sitio inmobiliario profesional en WordPress,
con operación multipaís y multimoneda.

El plugin cubre el catálogo completo —inmuebles, asesores, geografía,
características, precios— y lo conecta con el resto del ecosistema: CRMs a
través de adaptadores, portales inmobiliarios a través de los SDK oficiales, y
el sitio público a través de plantillas, shortcodes y widgets para Elementor,
Divi y WPBakery.

---

## Características

### Catálogo

- Custom post type `property` con ~55 campos estructurados.
- Doce taxonomías: tipo, operación, categoría, características, condición,
  etiquetas y la jerarquía geográfica completa.
- Multipaís y multimoneda: COP, MXN, USD, EUR y más.
- Códigos de inmueble automáticos, con prefijo por operación y tipo.
- Gestión de asesores, con datos de contacto por inmueble.
- Galería, vídeos, recorridos 360° y fichas técnicas en PDF.

### Sincronización

- Subsistema de CRM con adaptadores, webhooks firmados, cola y trabajos por
  lotes.
- **Homologación**: un mapeo canónico que traduce los valores de cada CRM a un
  término único, de modo que «Apto» y «Apartamento» acaben en el mismo sitio.
- Sincronización bajo demanda por código de inmueble: `/inmueble/{CODIGO}` trae
  el inmueble del CRM si todavía no existe en local.
- Índice de sincronización con deduplicación por origen y por código.

### Web pública

- Plantillas de ficha, archivo, taxonomía, perfil de asesor y página de
  inmueble no disponible.
- Listado con filtros, mapa, ordenación y paginación.
- Widgets nativos para Elementor, Divi y WPBakery, y once shortcodes.
- Formulario público de consignación de inmuebles.

### SEO y GEO

- Panel SEO & GEO con trece secciones.
- Schema.org JSON-LD: inmueble, listado, agencia, migas y FAQs.
- Endpoint `/llms-full.txt` para contexto de modelos de lenguaje.
- Compatible con WPML y Polylang.

### Analítica

- Visitas, clics de contacto y descargas de ficha, con detección de bots y
  purga automática.

---

## Developer API

La API pública vive bajo un solo namespace:

```php
Homlity\Developer\
```

Y sus hooks bajo una sola convención:

```
homlity/dominio/evento
```

Todo lo demás —`Homlity\PluginInmobiliario\*`, los hooks con guion bajo, los
metadatos `_property_*`— es interno y puede cambiar en cualquier versión.

### Superficie

| | |
| --- | --- |
| **12 actions** | Ciclo de vida del plugin y de los inmuebles |
| **4 filters** | Carga canónica, datos del modelo, consultas, compatibilidad |
| **11 clases** | Fachada, modelos, eventos, registro, soporte |
| **3 interfaces** | Extensiones, proveedores de sincronización, adaptadores de CRM |
| **7 helpers** | Comprobación de versión y acceso rápido |

### Ejemplo

```php
add_action('homlity/property/updated', function ($property, $changes, $context) {
    // Un CRM que reenvía un registro idéntico dispara una actualización igual.
    if ($changes->isEmpty()) {
        return;
    }

    // Y devolverle al CRM lo que él mismo mandó sería un bucle.
    if ($context->getSource() === 'mi-crm') {
        return;
    }

    if ($changes->hasGroup('pricing')) {
        mi_portal_actualizar_precio(
            $property->getCode(),
            $property->getSalePrice()?->getAmount()
        );
    }
}, 10, 3);
```

**Referencia:** [Actions](docs/developers/api/actions.md) ·
[Filters](docs/developers/api/filters.md) ·
[Clases](docs/developers/api/classes.md) ·
[Interfaces](docs/developers/api/interfaces.md) ·
[Helpers](docs/developers/api/helpers.md) ·
[Modelo Property](docs/developers/models/property.md)

---

## Crear extensiones

Una extensión de Homlity es un plugin de WordPress normal que se registra en el
núcleo:

```php
use Homlity\Developer\Contracts\ExtensionInterface;
use Homlity\Developer\Extension\Requirements;

final class MiIntegracion implements ExtensionInterface
{
    public function getName(): string    { return 'Mi CRM'; }
    public function getSlug(): string    { return 'acme-mi-crm'; }
    public function getVersion(): string { return '1.0.0'; }

    public function getRequirements(): Requirements
    {
        return Requirements::create(['homlity' => '2.8.0', 'php' => '8.0']);
    }

    public function boot(): void
    {
        add_action('homlity/property/created', [$this, 'push'], 10, 2);
    }
}

add_action('homlity/extensions/register', function ($registry) {
    $registry->register(new MiIntegracion());
});
```

A cambio, Homlity comprueba los requisitos antes de arrancarla, la llama en el
momento correcto del ciclo de vida y aísla sus errores para que una extensión
rota no tumbe el sitio.

- **Guía paso a paso:** [Crear tu primera extensión](docs/developers/extensions/create-your-first-extension.md)
- **Ejemplo funcional:** [`docs/examples/basic-extension/`](docs/examples/basic-extension/homlity-example-extension/README.md)
- **Buenas prácticas:** [best-practices.md](docs/developers/extensions/best-practices.md)

---

## SDKs

Paquetes oficiales en PHP que encapsulan la comunicación con CRMs y portales:

| Paquete | Servicio |
| --- | --- |
| [`homlity/chat-sdk`](https://packagist.org/packages/homlity/chat-sdk) | Chat de Homlity |
| [`homlity/sdk-ciencuadras`](https://packagist.org/packages/homlity/sdk-ciencuadras) | Ciencuadras |
| [`homlity/sdk-domus`](https://packagist.org/packages/homlity/sdk-domus) | Domus |
| [`homlity/sdk-fincaraiz`](https://packagist.org/packages/homlity/sdk-fincaraiz) | Fincaraíz |
| [`homlity/sdk-mobilia`](https://packagist.org/packages/homlity/sdk-mobilia) | Mobilia |
| [`homlity/sdk-proppit`](https://packagist.org/packages/homlity/sdk-proppit) | Proppit |
| [`homlity/sdk-smarthome`](https://packagist.org/packages/homlity/sdk-smarthome) | SmartHome |
| [`homlity/sdk-softinm`](https://packagist.org/packages/homlity/sdk-softinm) | Softinm |
| [`homlity/sdk-wasi-php8`](https://packagist.org/packages/homlity/sdk-wasi-php8) | Wasi |

> Los SDK encapsulan la comunicación con servicios externos. La lógica de
> sincronización y de negocio pertenece a cada implementación o extensión.

**Guía:** [SDKs oficiales](docs/developers/integration/sdk-usage.md)

---

## Documentación

| | |
| --- | --- |
| **Para desarrolladores** | <https://homlity.com/desarrolladores/> · [`docs/developers/`](docs/developers/README.md) |
| **Arquitectura interna** | [`docs/architecture/`](docs/architecture/current-architecture.md) |
| **Auditoría de extensibilidad** | [extensibility-audit.md](docs/architecture/extensibility-audit.md) |
| **CRM** | [crm-integration-spec.md](docs/crm-integration-spec.md) |
| **Reporte de errores** | [error-reporting.md](docs/error-reporting.md) |

---

## Instalación

### Desde el ZIP

1. **Plugins → Añadir nuevo → Subir plugin**.
2. Selecciona el ZIP y activa.
3. Configura países, monedas, tipos y operaciones en el menú **Inmuebles**.
4. Crea tu primer inmueble.

### Desde el repositorio

```bash
cd wp-content/plugins
git clone https://github.com/homlity/homlity-plugin.git homlity-real-estate
cd homlity-real-estate
composer install --no-dev
wp plugin activate homlity-real-estate
```

`composer install` es obligatorio: el plugin depende de Guzzle y Dompdf. El ZIP
de distribución ya incluye `vendor/`.

---

## Requisitos

| | Mínimo | Recomendado |
| --- | --- | --- |
| PHP | 8.0 | 8.2+ |
| WordPress | 5.8 | 6.4+ |
| MySQL | 5.7 | 8.0+ |

Dependencias de Composer: `guzzlehttp/guzzle ^7.10`, `dompdf/dompdf ^3.0`.

---

## Desarrollo

```bash
composer install
composer test        # 987 pruebas, sin necesidad de instalar WordPress
```

Las pruebas usan stubs de las funciones de WordPress
(`tests/Support/wp-functions.php`), así que corren en cualquier máquina con PHP
8 y sin base de datos.

---

## Contributing

Las contribuciones son bienvenidas. Antes de abrir un PR, lee
[CONTRIBUTING.md](CONTRIBUTING.md).

- [Reportar una incidencia](docs/developers/open-source/reporting-issues.md)
- [Pull requests](docs/developers/open-source/pull-requests.md)
- [Estándares de código](docs/developers/open-source/contributing.md)

---

## Security

**No abras una incidencia pública para una vulnerabilidad.** El proceso de
divulgación responsable está en [SECURITY.md](SECURITY.md).

El modelo de seguridad de la Developer API —qué datos no publica nunca, y qué se
espera de una extensión— está en
[docs/developers/open-source/security.md](docs/developers/open-source/security.md).

---

## License

GPLv2 o posterior. Ver [license.txt](license.txt).

Copyright © Ecosistema Inmobiliario Homlity.

---

## Links

- **Documentación para desarrolladores:** <https://homlity.com/desarrolladores/>
- **Sitio del proyecto:** <https://homlity.com/>
- **Repositorio:** <https://github.com/homlity/homlity-plugin>
- **Organización:** <https://github.com/homlity>
- **Paquetes:** <https://packagist.org/packages/homlity/>
- **Changelog:** [CHANGELOG.md](CHANGELOG.md)
