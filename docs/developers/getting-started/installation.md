# Instalación

Cómo preparar un entorno para desarrollar contra la Developer API.

---

## 1. Instalar Homlity Real Estate

### Desde el ZIP

**Plugins → Añadir nuevo → Subir plugin**, elige el ZIP y activa.

### Desde el repositorio

```bash
cd wp-content/plugins
git clone https://github.com/homlity/homlity-plugin.git homlity-real-estate
cd homlity-real-estate
composer install --no-dev
wp plugin activate homlity-real-estate
```

`composer install` es obligatorio: el plugin depende de Guzzle (comunicación
con servicios externos) y Dompdf (ficha técnica en PDF). El ZIP de distribución
ya trae `vendor/`.

---

## 2. Comprobar que la API está viva

```bash
wp eval 'echo homlity_version() . " / API " . homlity_api_version();'
# 2.8.0 / API 1.0.0
```

Si sale un error de función indefinida, el plugin no está activo.

---

## 3. Crear el esqueleto de tu extensión

```bash
cd wp-content/plugins
mkdir -p mi-crm-homlity/src
```

```
mi-crm-homlity/
├── mi-crm-homlity.php     ← cabecera de WordPress y arranque
├── src/
│   └── Plugin.php         ← la clase que implementa ExtensionInterface
├── composer.json          ← opcional
└── README.md
```

Hay un ejemplo completo y funcional en el propio repositorio:
[`docs/examples/basic-extension/`](../../examples/basic-extension/homlity-example-extension/README.md).
Cópialo y renómbralo:

```bash
cp -r homlity-real-estate/docs/examples/basic-extension/homlity-example-extension mi-crm-homlity
```

---

## 4. Activar el modo de desarrollo

En `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

Con `WP_DEBUG` activo, Homlity emite avisos cuando usas un hook o una función
deprecados. Sin él, el aviso se calla — y te enteras cuando desaparece.

---

## 5. Ver qué está pasando

### Extensiones registradas

```bash
wp eval '
foreach (Homlity\Developer\Homlity::extensions()->all() as $slug => $ext) {
    printf("%s  %s  v%s%s", $slug, $ext->getName(), $ext->getVersion(), PHP_EOL);
}'
```

### Extensiones rechazadas y por qué

```bash
wp eval 'print_r(Homlity\Developer\Homlity::extensions()->failures());'
```

Si tu extensión no aparece en ninguna de las dos listas, es que no llegó a
llamar a `register()`: revisa la prioridad de tu `add_action`.

### Un inmueble concreto

```bash
wp eval 'print_r(homlity_get_property(128)?->toArray());'
```

---

## 6. Espiar los eventos

Pega esto en un plugin `mu-plugins/homlity-debug.php` mientras desarrollas:

```php
<?php
foreach (\Homlity\Developer\Support\Hooks::actions() as $hook) {
    add_action($hook, function (...$args) use ($hook) {
        error_log(sprintf(
            '[homlity] %s (%d args)',
            $hook,
            count($args)
        ));
    }, 1, 5);
}
```

Edita un inmueble y mira `wp-content/debug.log`.

---

## 7. Empaquetar

```bash
cd wp-content/plugins/mi-crm-homlity
zip -r ../mi-crm-homlity.zip . -x '*.git*' -x 'node_modules/*' -x 'tests/*'
```

Si tu extensión usa Composer, instala sólo lo de producción antes:

```bash
composer install --no-dev --optimize-autoloader
```

---

## Problemas frecuentes

| Síntoma | Causa | Solución |
| --- | --- | --- |
| `Call to undefined function homlity_is_available()` | Tu plugin cargó antes que Homlity | Comprueba con `function_exists()` y engánchate a `plugins_loaded` con prioridad ≥ 21 |
| `Class 'Homlity\Developer\...' not found` | Homlity no está activo, o es anterior a 2.8.0 | Comprueba `homlity_is_version_supported('2.8.0')` |
| La extensión no aparece en `all()` | Se registró tarde, o fue rechazada | Mira `failures()` |
| `homlity/property/updated` no dispara | El inmueble se escribió por una vía que el núcleo no controla | Ver [Actions](../api/actions.md#cobertura) |
| Los avisos de deprecación no salen | `WP_DEBUG` desactivado | Actívalo |
