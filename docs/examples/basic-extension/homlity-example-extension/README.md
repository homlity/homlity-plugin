# Homlity Example Extension

Extensión de ejemplo, funcional y completa, para **Homlity Real Estate**.
Sirve como plantilla de cualquier integración: CRM, portal inmobiliario, ERP,
analítica o automatización.

No modifica el núcleo de Homlity. Todo lo que hace pasa por la
[Homlity Developer API](https://homlity.com/desarrolladores/).

---

## Qué demuestra

| Concepto | Dónde mirar |
| --- | --- |
| Comprobar que Homlity está activo | `homlity-example-extension.php` → `homlity_is_available()` |
| Comprobar la versión mínima | `homlity-example-extension.php` → `homlity_is_version_supported()` |
| Registrar la extensión | `homlity-example-extension.php` → `homlity/extensions/register` |
| Declarar requisitos | `src/Plugin.php` → `getRequirements()` |
| Escuchar eventos del ciclo de vida | `src/Plugin.php` → `onPropertyCreated()`, `onPropertyUpdated()`, `onPropertyDeleted()` |
| Evitar bucles de sincronización | `src/Plugin.php` → `onPropertyUpdated()` |
| Usar un filtro público | `src/Plugin.php` → `stampImportedProperties()` |
| Manejar activación y desactivación | `homlity-example-extension.php` → `register_activation_hook()` |
| Manejar incompatibilidad sin fatal error | `homlity_example_notice_missing()`, `homlity_example_notice_outdated()` |
| No dejar escapar excepciones desde un hook | `src/Plugin.php` → `log()` |

---

## Requisitos

- WordPress 5.8 o superior
- PHP 8.0 o superior
- Homlity Real Estate 2.8.0 o superior

---

## Instalación

```bash
cp -r homlity-example-extension /ruta/a/wp-content/plugins/
wp plugin activate homlity-example-extension
```

No hace falta `composer install`: el plugin carga su única clase con un
`require_once`. El `composer.json` está ahí para cuando la extensión crezca.

---

## Cómo comprobar que funciona

1. Activa **Homlity Real Estate** y después esta extensión.
2. Crea o edita un inmueble en **Inmuebles → Añadir nuevo** y publícalo.
3. Lee el registro:

```bash
wp option get homlity_example_synced_log --format=json
```

Deberías ver una entrada parecida a esta:

```json
[
  {
    "event": "created",
    "at": "2026-08-23T14:05:11+00:00",
    "id": 128,
    "code": "VTAP1320041",
    "title": "Apartamento en El Poblado",
    "price": 450000000,
    "currency": "COP",
    "images": 6,
    "city": "Medellín",
    "origin": "admin",
    "source": ""
  }
]
```

Cambia el precio y vuelve a guardar: aparecerá una entrada `updated` con la
lista de campos que cambiaron. Cambia sólo la descripción y **no** aparecerá
ninguna — la extensión filtra a propósito lo que no es comercial.

---

## Comprobar el manejo de incompatibilidades

Desactiva Homlity Real Estate dejando esta extensión activa. En el escritorio
verás un aviso en lugar de un error fatal, y la extensión no engancha nada.

---

## Empaquetar

```bash
cd homlity-example-extension
zip -r ../homlity-example-extension.zip . -x '*.git*' -x 'vendor/*'
```

---

## Licencia

GPLv2 o posterior, igual que WordPress y que Homlity Real Estate.
