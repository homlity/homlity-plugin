# Extensiones — introducción

Una extensión de Homlity es **un plugin de WordPress normal** que se da de alta
en Homlity y usa la Developer API.

No hay formato especial, ni carpeta especial, ni instalador. Se instala,
actualiza y desactiva como cualquier otro plugin.

---

## ¿Hace falta ser una extensión?

No. Puedes usar la Developer API sin registrarte:

```php
add_action('homlity/property/updated', 'mi_callback', 10, 3);
```

Eso funciona. Entonces, ¿para qué registrarse?

| Sin registrar | Registrado como extensión |
| --- | --- |
| Compruebas la compatibilidad a mano | Se declara y Homlity la verifica |
| Si te equivocas de versión, fatal error | La extensión no arranca y se avisa |
| Una excepción tuya rompe la petición | El registro la aísla |
| Nadie sabe que existes | Apareces en el censo y en los diagnósticos |
| Decides tú cuándo enganchar | Homlity te llama en el momento correcto |

La regla práctica: **un `add_action` suelto no necesita registro; una
integración sí**.

---

## Anatomía

```
mi-crm-homlity/
├── mi-crm-homlity.php     ← cabecera, comprobaciones y registro
├── src/
│   └── Plugin.php         ← implementa ExtensionInterface
├── composer.json          ← opcional
└── README.md
```

### El archivo principal

Sólo hace tres cosas: declarar la cabecera, comprobar que Homlity está y es
suficientemente nuevo, y registrar la extensión.

```php
add_action('plugins_loaded', function () {
    if (!function_exists('homlity_is_available') || !homlity_is_available()) {
        add_action('admin_notices', 'mi_crm_aviso_falta_homlity');
        return;
    }

    if (!homlity_is_version_supported('2.8.0')) {
        add_action('admin_notices', 'mi_crm_aviso_version');
        return;
    }

    add_action('homlity/extensions/register', function ($registry) {
        $registry->register(new MiCrm\Plugin());
    });
}, 21);
```

La comprobación de versión va **fuera** de la clase: si `ExtensionInterface` no
existe en esta versión de Homlity, instanciar la clase sería un fatal error.

### La clase de la extensión

```php
final class Plugin implements ExtensionInterface
{
    public function getName(): string    { return 'Mi CRM'; }
    public function getSlug(): string    { return 'acme-mi-crm'; }
    public function getVersion(): string { return '1.0.0'; }

    public function getRequirements(): Requirements
    {
        return Requirements::create(['homlity' => '2.8.0', 'php' => '8.1']);
    }

    public function boot(): void
    {
        add_action('homlity/property/updated', [$this, 'push'], 10, 3);
    }
}
```

---

## Qué puede hacer una extensión

### Escuchar

Los [doce eventos](../api/actions.md) del ciclo de vida del plugin y de los
inmuebles.

### Modificar

Los [cuatro filtros](../api/filters.md): lo que se guarda, lo que se expone, lo
que se consulta.

### Leer

El [modelo `Property`](../models/property.md) y el repositorio.

### Aportar

- Un **adaptador de CRM**, implementando
  [`CrmAdapterInterface`](../api/interfaces.md#crmadapterinterface).
- Un **proveedor de sincronización bajo demanda**, implementando
  [`PropertySyncProviderInterface`](../api/interfaces.md#propertysyncproviderinterface).

### Y todo lo que WordPress permite

Rutas REST, comandos WP-CLI, pantallas de administración, bloques, shortcodes,
tareas de cron. Homlity no te limita: es un plugin más.

---

## Tipos de extensión

| Tipo | Qué hace | Piezas que usa |
| --- | --- | --- |
| **CRM entrante** | Trae inmuebles de un CRM | `CrmAdapterInterface`, `homlity/property/normalized` |
| **Portal saliente** | Publica inmuebles en un portal | `homlity/property/created`, `updated`, `deleted`, `images_changed` |
| **ERP** | Sincroniza contratos y comisiones | Eventos del ciclo de vida + rutas REST propias |
| **Analítica** | Envía eventos a una plataforma | Eventos del ciclo de vida |
| **Automatización** | Dispara flujos ante cambios | `homlity/property/updated` + `PropertyChanges` |
| **IA** | Genera descripciones, responde consultas | `homlity/property/normalized`, `homlity/property/data` |
| **Personalización** | Campos, consultas, salida | `homlity/property/query_args`, `homlity/property/data` |

---

## Antes de empezar

1. [Requisitos](../getting-started/requirements.md) — comprueba el entorno.
2. [Crear tu primera extensión](create-your-first-extension.md) — la guía paso
   a paso, con código completo.
3. [Ciclo de vida](extension-lifecycle.md) — cuándo ocurre cada cosa.
4. [Buenas prácticas](best-practices.md) — los errores que se cometen siempre.

Y si prefieres leer código antes que prosa, el ejemplo funcional está en
[`docs/examples/basic-extension/`](../../examples/basic-extension/homlity-example-extension/README.md).
