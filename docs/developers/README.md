# Homlity Developers

Documentación de la **Homlity Developer API**: la interfaz pública y estable
sobre la que se construyen extensiones, integraciones y productos compatibles
con Homlity Real Estate sin modificar el núcleo del plugin.

Publicada en **<https://homlity.com/desarrolladores/>**.

---

## Empieza aquí

| Si quieres… | Lee |
| --- | --- |
| Entender qué es y qué no es esta API | [Introducción](getting-started/introduction.md) |
| Saber si tu instalación sirve | [Requisitos](getting-started/requirements.md) |
| Poner en marcha un entorno | [Instalación](getting-started/installation.md) |
| Ver el mapa general | [Arquitectura](getting-started/architecture.md) |
| **Escribir tu primera extensión** | [Crear tu primera extensión](extensions/create-your-first-extension.md) |

---

## Referencia

### Extensiones

- [Introducción a las extensiones](extensions/introduction.md)
- [Crear tu primera extensión](extensions/create-your-first-extension.md)
- [Ciclo de vida de una extensión](extensions/extension-lifecycle.md)
- [Registro de extensiones](extensions/extension-registration.md)
- [Buenas prácticas](extensions/best-practices.md)
- [Compatibilidad](extensions/compatibility.md)
- [Política «Compatible con Homlity»](extensions/compatible-with-homlity.md)

### Developer API

- [Visión general](api/overview.md)
- [Actions](api/actions.md)
- [Filters](api/filters.md)
- [Clases públicas](api/classes.md)
- [Interfaces](api/interfaces.md)
- [Helpers globales](api/helpers.md)

### Modelos

- [Property](models/property.md)

### Integración

- [Arquitectura de integración](integration/architecture.md)
- [SDKs oficiales](integration/sdk-usage.md)

### Versionamiento

- [SemVer](versioning/semver.md)
- [Deprecaciones](versioning/deprecations.md)
- [Política de changelog](versioning/changelog-policy.md)

### Open source

- [Contribuir](open-source/contributing.md)
- [Reportar incidencias](open-source/reporting-issues.md)
- [Pull requests](open-source/pull-requests.md)
- [Seguridad](open-source/security.md)

---

## La regla que resume todo

> Las extensiones dependen de `Homlity\Developer\`.
> Todo lo demás es interno y puede cambiar en cualquier versión menor.

```php
// Sí.
use Homlity\Developer\Models\Property;

// No: es interno, aunque hoy funcione.
use Homlity\PluginInmobiliario\Services\PropertyPostType;
```

---

## Enlaces

- Documentación: <https://homlity.com/desarrolladores/>
- Código: <https://github.com/homlity/homlity-plugin>
- Organización: <https://github.com/homlity>
- Paquetes: <https://packagist.org/packages/homlity/>
