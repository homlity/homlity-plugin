# Seguridad

Cómo reportar una vulnerabilidad, y cómo escribir una extensión que no
introduzca ninguna.

---

## Reportar una vulnerabilidad

**No abras una incidencia pública.** Una incidencia en GitHub es visible al
instante para cualquiera, incluida gente con más interés en explotar el fallo
que en verlo corregido.

> **TODO: definir canal privado de seguridad.**
>
> Homlity todavía no tiene publicado un canal privado de divulgación
> responsable. Hasta que exista, usa el formulario de contacto de
> <https://homlity.com/> indicando en el asunto que se trata de un reporte de
> seguridad, y **no incluyas los detalles técnicos en ese primer mensaje**:
> pide un canal seguro y espera respuesta antes de enviarlos.
>
> Cuando el canal esté definido, se documentará aquí y en
> [`SECURITY.md`](../../../SECURITY.md).

### Qué incluir

- Descripción de la vulnerabilidad y de su impacto.
- Pasos para reproducirla.
- Versiones afectadas.
- Prueba de concepto, si la tienes.
- Cómo quieres que se te acredite, o si prefieres el anonimato.

### Qué esperar

- **Acuse de recibo.**
- **Evaluación** de impacto y alcance.
- **Corrección**, con la urgencia que corresponda a la severidad.
- **Divulgación coordinada**: los detalles se publican cuando hay una versión
  corregida disponible.
- **Crédito**, si lo quieres.

### Divulgación responsable

Se te pide que no publiques los detalles hasta que exista una corrección
disponible, y que no accedas a datos de terceros, ni degrades el servicio, ni
modifiques información durante la investigación.

---

## Modelo de seguridad de la Developer API

Lo que el núcleo garantiza a los administradores de sitios, y lo que espera de
las extensiones.

### Lo que la API no publica

Los hooks del ciclo de vida **nunca** transportan:

| | |
| --- | --- |
| Datos personales del propietario | `_property_contact_*`, `_property_identification` |
| Banderas de consentimiento | `_consignment_*` |
| Respuesta cruda del CRM | `_property_sync_payload` — puede contener tokens |
| Credenciales de integración | Ninguna, de ninguna forma |

El modelo `Property` se construye desde una **lista blanca** de metadatos. Un
metadato añadido mañana no puede colarse en la API sin que alguien lo decida.

`PropertyChanges` excluye explícitamente los mismos campos: el diff que viaja en
un hook no puede convertirse en una fuga de datos.

### Lo que la API respeta

`Location::getAddress()` devuelve `''` cuando el propietario pidió no publicar
la dirección exacta. Es una decisión suya, no un ajuste de presentación.

### Lo que la API no hace por ti

Ser una extensión de Homlity no exime de nada. Tus rutas REST, tus formularios y
tus pantallas necesitan las mismas comprobaciones que cualquier plugin.

---

## Escribir una extensión que no rompa nada

### Capacidades

```php
if (!current_user_can('edit_post', $postId)) {
    wp_die(esc_html__('No autorizado.', 'mi-crm'));
}
```

Comprueba la capacidad **específica**, no `is_admin()` —que sólo dice en qué
pantalla estás, no quién eres— ni `is_user_logged_in()`.

En una ruta REST, en el `permission_callback`, nunca en el handler:

```php
register_rest_route('mi-crm/v1', '/sync', [
    'methods'             => 'POST',
    'callback'            => [$this, 'handle'],
    'permission_callback' => static fn() => current_user_can('manage_options'),
]);
```

Un `permission_callback` que devuelve `true` es una ruta pública. Que sea a
propósito.

### Nonces

```php
wp_nonce_field('mi_crm_accion', 'mi_crm_nonce');

if (!isset($_POST['mi_crm_nonce'])
    || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mi_crm_nonce'])), 'mi_crm_accion')
) {
    return;
}
```

Un nonce no es autenticación: comprueba **además** la capacidad.

### Entrada

```php
$codigo = sanitize_text_field(wp_unslash($_POST['codigo'] ?? ''));
$id     = absint($_POST['id'] ?? 0);
$url    = esc_url_raw(wp_unslash($_POST['url'] ?? ''));
$email  = sanitize_email(wp_unslash($_POST['email'] ?? ''));
```

### Salida

```php
echo esc_html($property->getTitle());
echo esc_url($property->getUrl());
echo esc_attr($codigo);
echo wp_kses_post($property->getDescription());
```

Escapa **al imprimir**, no al guardar: sólo en el punto de salida sabes qué
contexto de escape hace falta.

### SQL

```php
// ✓
$wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mi_tabla WHERE post_id = %d",
    $postId
));

// ✗
$wpdb->get_results("SELECT * FROM {$wpdb->prefix}mi_tabla WHERE post_id = $postId");
```

El nombre de la tabla no se puede parametrizar: constrúyelo con
`$wpdb->prefix` y una constante, nunca con entrada del usuario.

### SSRF

Si tu extensión hace peticiones a una URL que viene de fuera, valida el destino:

```php
$url = esc_url_raw($input);

if (!wp_http_validate_url($url)) {
    return;
}

$response = wp_remote_get($url, ['timeout' => 10, 'redirection' => 3]);
```

Usa `wp_remote_*`, que respeta la configuración del sitio, en vez de cURL a
pelo.

### Archivos

```php
// ✓ La API de WordPress: valida el tipo y sanea el nombre.
$attachmentId = media_handle_sideload($file, $postId);

// ✗ Escribir directamente donde diga el usuario.
file_put_contents(WP_CONTENT_DIR . '/' . $_POST['nombre'], $contenido);
```

Nunca construyas una ruta de archivo con entrada del usuario sin
`sanitize_file_name()` y sin comprobar que el resultado sigue dentro del
directorio esperado.

### Ejecución arbitraria

No uses `eval()`, ni `create_function()`, ni `unserialize()` sobre datos que
vienen de fuera —usa `json_decode()`—, ni cargues código desde una URL en tiempo
de ejecución.

### Secretos

```php
// Mejor: fuera de la base de datos y de los backups.
define('MI_CRM_API_KEY', '…');   // wp-config.php

// Aceptable: sin autoload y sin exponerla en REST.
update_option('mi_crm_api_key', $key, false);
```

Y **nunca** pases una credencial por un hook: cualquier plugin instalado puede
escucharlo.

```php
// ✗ Esto publica tu token a todo el sitio.
do_action('mi_crm/before_request', $token, $payload);
```

### Escalada de privilegios

Cuidado con lo que dejas hacer a través de tus filtros:

```php
// ✗ Un filtro que decide el estado de publicación es un filtro que
//   puede publicar inmuebles que nadie revisó.
$status = apply_filters('mi_crm/post_status', $_POST['status']);
```

Valida contra una lista blanca **después** del filtro, no antes.

---

## Lista de comprobación

Antes de publicar tu extensión:

- [ ] Toda ruta REST tiene un `permission_callback` real.
- [ ] Toda escritura comprueba capacidad **y** nonce.
- [ ] Toda entrada se sanea con la función adecuada a su tipo.
- [ ] Toda salida se escapa en el punto de impresión.
- [ ] Toda consulta SQL usa `$wpdb->prepare()`.
- [ ] Las URLs externas se validan antes de pedirlas.
- [ ] Las subidas pasan por la API de medios de WordPress.
- [ ] No hay `eval()` ni `unserialize()` sobre datos externos.
- [ ] Las credenciales no están en el repositorio ni en claro en la base de
      datos.
- [ ] Ningún hook propio publica secretos ni datos personales.
- [ ] Los datos del propietario no salen del sitio.
- [ ] La dirección oculta de un inmueble sigue oculta.
