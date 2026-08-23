# Registro de extensiones

Cómo se da de alta una extensión, qué comprueba Homlity, y qué pasa cuando algo
no cuadra.

---

## Las dos formas

Equivalentes. Elige por gusto.

```php
// Recibiendo el registro.
add_action('homlity/extensions/register', function ($registry) {
    $registry->register(new MiIntegracion());
});

// Con el helper global.
add_action('homlity/extensions/register', function () {
    homlity_register_extension(new MiIntegracion());
});

// O explícitamente, con la fachada.
use Homlity\Developer\Homlity;

add_action('homlity/extensions/register', function () {
    Homlity::extensions()->register(new MiIntegracion());
});
```

---

## Qué comprueba Homlity

En este orden. El primero que falla detiene el proceso.

### 1 · El slug es utilizable

Se normaliza con `sanitize_key()`: minúsculas, dígitos, guiones y guiones bajos.
Si el resultado queda vacío, se rechaza.

```php
'acme-mi-crm'  →  'acme-mi-crm'   ✓
'MiCRM'        →  'micrm'         ✓  (normalizado)
'!!!'          →  ''              ✗  rechazado
```

La normalización también se aplica al consultar, así que `get('Mi-CRM')`
encuentra lo registrado como `mi-crm`.

### 2 · El slug está libre

Dos extensiones no pueden compartirlo. La **segunda** se rechaza; la primera
sigue funcionando.

Por eso el slug se prefija con el vendor: `acme-mi-crm`, no `crm`.

### 3 · Los requisitos se cumplen

Se evalúa `getRequirements()`. Cada incumplimiento produce una frase legible en
español.

```php
Requirements::create([
    'homlity'   => '2.8.0',
    'api'       => '1.0.0',
    'php'       => '8.1',
    'wordpress' => '6.4',
    'plugins'   => ['woocommerce/woocommerce.php'],
]);
```

Si `getRequirements()` lanza una excepción, también se rechaza, con el mensaje
de la excepción como motivo.

### 4 · El filtro lo confirma

```php
apply_filters('homlity/extension/is_compatible', $compatible, $extension, $unmet);
```

Permite forzar o vetar. Ver
[el filtro](../api/filters.md#homlityextensionis_compatible).

### 5 · `boot()` no lanza

Si lanza, la excepción se captura, la extensión pasa a fallida y el resto sigue
arrancando.

---

## Qué pasa cuando se rechaza

Tres cosas, siempre:

1. `register()` devuelve `false`. **Nunca lanza.**
2. Los motivos quedan en `failures()`, indexados por slug.
3. Se dispara `homlity/extension/failed`.

```php
$ok = homlity_register_extension(new MiIntegracion());

if (!$ok) {
    $motivos = homlity_extensions()->failures()['acme-mi-crm'] ?? [];
    // ['Requiere PHP 8.1 o superior (en ejecución: 8.0.30).']
}
```

Para avisar al usuario:

```php
add_action('homlity/extension/failed', function ($extension, $reasons, $slug) {
    if ($slug !== 'acme-mi-crm') {
        return;
    }

    add_action('admin_notices', function () use ($reasons) {
        printf(
            '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
            esc_html__('Mi CRM no pudo arrancar:', 'mi-crm-homlity'),
            esc_html(implode(' ', $reasons))
        );
    });
}, 10, 3);
```

Este `add_action` va en el archivo principal, dentro de tu `plugins_loaded` y
**antes** del `add_action('homlity/extensions/register', …)`: si no, se
engancha después de que el rechazo ya ocurrió.

---

## Cuándo registrar

| Momento | Qué pasa |
| --- | --- |
| Antes de `homlity/extensions/register` | Queda en cola; arranca en el despacho |
| Durante `homlity/extensions/register` | **Lo esperado** |
| Después del despacho | Arranca inmediatamente |

Registrarse tarde funciona, pero tus hooks pueden llegar después del primer
evento. Regístrate en la ventana.

```php
if (homlity_extensions()->isDispatched()) {
    // Un register() aquí arrancará la extensión en el acto.
}
```

---

## Consultar el registro

```php
$registry = homlity_extensions();

$registry->has('acme-mi-crm');     // bool
$registry->get('acme-mi-crm');     // ?ExtensionInterface
$registry->all();                  // array<string,ExtensionInterface>
$registry->failures();             // array<string,string[]>
$registry->isDispatched();         // bool
```

Desde la consola:

```bash
# Extensiones activas
wp eval '
foreach (Homlity\Developer\Homlity::extensions()->all() as $slug => $e) {
    printf("%-24s %s v%s%s", $slug, $e->getName(), $e->getVersion(), PHP_EOL);
}'

# Rechazos y motivos
wp eval 'print_r(Homlity\Developer\Homlity::extensions()->failures());'
```

---

## Convivir con otras extensiones

El censo permite adaptarse en vez de duplicar trabajo:

```php
add_action('homlity/extensions/registered', function ($registry) {
    // Si ya hay una integración de analítica, no aportar la mía.
    if ($registry->has('homlity-analytics')) {
        remove_action('homlity/property/created', [$this, 'track']);
    }

    // O reaccionar a una versión concreta de otra extensión.
    $otra = $registry->get('otro-crm');
    if ($otra !== null && version_compare($otra->getVersion(), '2.0.0', '<')) {
        // modo compatibilidad
    }
});
```

Regla de convivencia: **nunca desregistres una extensión ajena**. Si crees que
hay un conflicto, avisa al administrador; no decidas por él.

---

## Elegir un slug

| | |
| --- | --- |
| ✓ | `acme-mi-crm`, `inmoplus-sync`, `homlity-wasi` |
| ✗ | `crm`, `sync`, `integration`, `homlity` |

Debe ser único en el ecosistema entero, estable entre versiones —cambiarlo es
registrar una extensión distinta—, y descriptivo de quién lo publica.

---

## Errores frecuentes

| Síntoma | Causa | Solución |
| --- | --- | --- |
| No aparece en `all()` ni en `failures()` | `register()` nunca se llamó | Revisa la prioridad de tu `plugins_loaded` (≥ 21) |
| «Ya hay una extensión registrada con ese slug» | Estás registrando dos veces | Un solo `register()` por extensión |
| «El slug está vacío» | `sanitize_key()` lo deja en nada | Usa minúsculas, dígitos y guiones |
| `boot()` no se ejecuta | Requisitos incumplidos | Mira `failures()` |
| Fatal error al activar | Instancias la clase antes de comprobar la versión | Comprueba **fuera** de la clase |
