# Buenas prácticas

Los errores que se cometen siempre, y qué hacer en su lugar.

---

## 1 · No dependas de lo interno

```php
// ✗ Interno. Hoy funciona; en 2.9.0 puede no existir.
use Homlity\PluginInmobiliario\Services\PropertyPostType;
$posts = get_posts(['post_type' => PropertyPostType::POST_TYPE]);

$precio = get_post_meta($id, '_property_price_sale', true);

// ✓ Público.
$property = homlity_get_property($id);
$precio = $property?->getSalePrice()?->getAmount();
```

La frontera es una sola línea: **`Homlity\Developer\` sí, todo lo demás no**.

Si necesitas algo que la API no cubre, dilo en una
[incidencia](../open-source/reporting-issues.md). Mientras tanto usa
`$property->getPost()` — que existe justo para eso — sabiendo que lo que
alcances por ahí no está cubierto.

---

## 2 · Comprueba antes de usar

```php
// ✗ Fatal error si tu plugin carga antes que Homlity.
add_action('plugins_loaded', function () {
    homlity_register_extension(new MiExtension());
});

// ✓
add_action('plugins_loaded', function () {
    if (!function_exists('homlity_is_available') || !homlity_is_available()) {
        add_action('admin_notices', 'mi_aviso');
        return;
    }
    // …
}, 21);
```

Y comprueba la versión **fuera** de la clase que implementa
`ExtensionInterface`: si esa interfaz no existe, instanciar la clase es un fatal
error antes de llegar a comprobar nada.

---

## 3 · En `boot()` sólo se engancha

```php
// ✗ Esto corre en cada petición del sitio.
public function boot(): void
{
    $inmuebles = $this->crm->fetchAll();   // llamada HTTP
    $this->sync($inmuebles);
}

// ✓
public function boot(): void
{
    add_action('homlity/initialized', [$this, 'maybeSync']);
    add_action('mi_crm_sync_cron', [$this, 'sync']);
}
```

Recuerda además que en `boot()` los post types todavía no existen.

---

## 4 · Filtra el ruido antes de llamar al exterior

Un CRM que reenvía inmuebles cada quince minutos dispara `updated` cada quince
minutos, cambie algo o no.

```php
public function onUpdated(Property $p, PropertyChanges $c, PropertyContext $ctx): void
{
    // 1. ¿Cambió algo de verdad?
    if ($c->isEmpty()) {
        return;
    }

    // 2. ¿Lo causé yo? Devolvérselo al CRM sería un bucle.
    if ($ctx->getSource() === $this->getSlug()) {
        return;
    }

    // 3. ¿Me importa lo que cambió?
    if (!$c->hasGroup('pricing') && !$c->has('media.gallery')) {
        return;
    }

    $this->push($p);
}
```

Las tres comprobaciones son baratas. La llamada HTTP que evitan, no.

---

## 5 · Nunca dejes escapar una excepción

Tus callbacks corren dentro del guardado de un inmueble. Una excepción que
salga rompe esa petición: el administrador ve una pantalla en blanco al pulsar
«Publicar».

```php
public function push(Property $property): void
{
    try {
        $this->client->send($property->toArray());
    } catch (Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[mi-crm] ' . $e->getMessage());
        }
        // Reintentar más tarde, no ahora.
        $this->queue($property->getId());
    }
}
```

El registro captura las excepciones de `boot()`, pero no las de tus callbacks:
ahí ya no está en medio.

---

## 6 · El trabajo lento va en segundo plano

Los hooks corren en la petición del usuario. Una llamada HTTP de tres segundos
son tres segundos que el administrador espera mirando el navegador.

```php
public function onUpdated(Property $property, $changes, $context): void
{
    if ($changes->isEmpty()) {
        return;
    }

    // Encolar es instantáneo.
    wp_schedule_single_event(time() + 30, 'mi_crm_push', [$property->getId()]);
}

// Y en boot():
add_action('mi_crm_push', function (int $propertyId) {
    $property = homlity_get_property($propertyId);
    if ($property === null) {
        return;   // lo borraron entre medias
    }

    $this->client->send($property->toArray());
});
```

Encola el **ID**, no el objeto: WP-Cron serializa los argumentos, y para cuando
la tarea corra el inmueble puede haber cambiado o desaparecido.

Y limpia tus eventos al desactivar:

```php
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('mi_crm_push');
});
```

---

## 7 · Declara cuántos argumentos aceptas

```php
// ✗ Sólo recibes $property. $changes y $context se pierden en silencio.
add_action('homlity/property/updated', [$this, 'onUpdated']);

// ✓
add_action('homlity/property/updated', [$this, 'onUpdated'], 10, 3);
```

Esto importa el doble en una API con compromiso de estabilidad: los hooks
**pueden ganar parámetros** en versiones menores. Declarar el número exacto que
usas te aísla de eso.

---

## 8 · Sanea, escapa, comprueba capacidades

Ser una extensión de Homlity no exime de nada.

```php
// Entrada.
$codigo = sanitize_text_field(wp_unslash($_POST['codigo'] ?? ''));

// Salida.
echo esc_html($property->getTitle());
echo esc_url($property->getUrl());

// Permisos y nonce.
if (!current_user_can('edit_posts') || !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'mi_accion')) {
    wp_die('No autorizado.');
}
```

Ver [Seguridad](../open-source/security.md).

---

## 9 · No expongas los datos del propietario

El modelo `Property` no los incluye a propósito. Si tu integración los lee por
su cuenta —desde `getPost()` o desde los metadatos— asumes esa responsabilidad:
no los mandes a un portal externo, no los pongas en un log, no los publiques en
un endpoint.

Lo mismo con `Location::getAddress()`: devuelve `''` cuando el propietario pidió
ocultar la dirección exacta. Saltárselo leyendo `_property_address` es
técnicamente posible y comercialmente una mala idea.

---

## 10 · No guardes secretos en claro

```php
// ✗ Visible para cualquiera con acceso a la base de datos o a los backups.
update_option('mi_crm_api_key', $key);

// ✓ Fuera de la base de datos.
define('MI_CRM_API_KEY', '…');   // en wp-config.php

// O al menos fuera del autoload y sin exponerla en REST.
update_option('mi_crm_api_key', $key, false);
```

Y **nunca** las pases por un hook: cualquier plugin instalado puede escucharlo.

---

## 11 · Prefija todo

Opciones, metadatos, hooks propios, tablas, clases globales, transients,
eventos de cron.

```php
'acme_mi_crm_ajustes'
'_acme_mi_crm_ultimo_sync'
'acme/mi-crm/inmueble/enviado'
```

Sin prefijo, la colisión no da error: sobrescribe.

---

## 12 · Versiona tu extensión con SemVer

`getVersion()` es lo que ven otras extensiones para decidir si pueden convivir
contigo. Que signifique algo.

Ver [SemVer](../versioning/semver.md).

---

## 13 · Declara requisitos honestos

```php
// ✗ Optimista: fallará en producción, y tarde.
Requirements::none()

// ✗ Pesimista: te deja fuera de sitios donde funcionarías.
Requirements::create(['homlity' => '2.8.0', 'php' => '8.3', 'wordpress' => '6.7'])

// ✓ Lo que de verdad necesitas.
Requirements::create(['homlity' => '2.8.0', 'php' => '8.0'])
```

Declara `api` en vez de `homlity` cuando lo que usas es un hook o una clase, no
una funcionalidad del plugin.

---

## 14 · Prueba tu extensión

Los hooks se pueden simular sin WordPress:

```php
public function testNoEnviaCuandoNoCambioNada(): void
{
    $extension = new Plugin();
    $enviados = 0;
    // …
    $extension->onUpdated($property, new PropertyChanges([]), $context);
    self::assertSame(0, $enviados);
}
```

Prueba al menos: diff vacío, cambio que no te interesa, origen propio (bucle),
inmueble borrado entre el encolado y la ejecución, y el sistema externo caído.

---

## 15 · Documenta la compatibilidad

En tu README, di con qué versiones de Homlity funciona tu extensión y
mantenlo al día. Es lo primero que mira quien va a instalarla.

Ver [Compatibilidad](compatibility.md) y
[Compatible con Homlity](compatible-with-homlity.md).

---

## Lista de comprobación

Antes de publicar:

- [ ] Comprueba `function_exists()` antes de la primera llamada.
- [ ] Comprueba la versión fuera de la clase de la extensión.
- [ ] `boot()` sólo engancha.
- [ ] Declara el número de argumentos en cada `add_action`.
- [ ] Filtra el diff vacío y el origen propio.
- [ ] Captura las excepciones en los callbacks.
- [ ] El trabajo lento va a cron o a una cola.
- [ ] Limpia tus eventos de cron al desactivar.
- [ ] Sanea la entrada y escapa la salida.
- [ ] Comprueba capacidades y nonces.
- [ ] No expones datos personales del propietario.
- [ ] No guardas secretos en claro ni los pasas por hooks.
- [ ] Todo prefijado.
- [ ] Requisitos declarados y honestos.
- [ ] Slug prefijado con tu vendor.
- [ ] Probado con Homlity desactivado.
- [ ] Probado con una versión de Homlity insuficiente.
