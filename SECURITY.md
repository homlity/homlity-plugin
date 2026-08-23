# Política de seguridad

## Versiones soportadas

| Versión | Soporte de seguridad |
| --- | --- |
| 2.8.x | ✅ |
| 2.7.x | ⚠️ Sólo vulnerabilidades críticas |
| < 2.7 | ❌ |

Actualiza siempre a la última versión estable.

---

## Reportar una vulnerabilidad

**No abras una incidencia pública en GitHub.**

Una incidencia es visible al instante para cualquiera, incluida gente con más
interés en explotar el fallo que en verlo corregido. Un reporte responsable da
tiempo a publicar una corrección antes de que los detalles sean públicos.

### Canal

> **TODO: definir canal privado de seguridad.**
>
> Homlity todavía no tiene publicado un canal privado de divulgación
> responsable —ni una dirección de correo dedicada, ni GitHub Security
> Advisories habilitado en el repositorio.
>
> **Mientras tanto:** usa el formulario de contacto de <https://homlity.com/>,
> indica en el asunto que es un **reporte de seguridad**, y **no incluyas los
> detalles técnicos en ese primer mensaje**. Pide un canal seguro y espera
> respuesta antes de enviarlos.
>
> Cuando el canal esté definido se documentará aquí y en
> [`docs/developers/open-source/security.md`](docs/developers/open-source/security.md).

### Qué incluir

- Descripción de la vulnerabilidad y de su impacto.
- Pasos para reproducirla.
- Versiones de Homlity, WordPress y PHP afectadas.
- Prueba de concepto, si la tienes.
- Cómo quieres que se te acredite, o si prefieres el anonimato.

### Qué esperar

1. **Acuse de recibo.**
2. **Evaluación** de impacto y alcance.
3. **Corrección**, con la urgencia que corresponda a la severidad.
4. **Divulgación coordinada**: los detalles se publican cuando hay una versión
   corregida disponible.
5. **Crédito**, si lo quieres.

### Divulgación responsable

Se te pide que:

- no publiques los detalles hasta que exista una corrección disponible;
- no accedas a datos de terceros durante la investigación;
- no degrades el servicio ni modifiques información;
- pruebes sólo en instalaciones propias o con permiso del titular.

---

## Alcance

### Dentro

- El código del plugin: `src/`, `includes/`, `templates/`,
  `plugin-inmobiliario.php`.
- Las rutas REST y los endpoints AJAX que registra.
- El formulario público de consignación.
- La verificación de firma de los webhooks de CRM.
- La Developer API: que ningún hook público filtre datos que no debe.

### Fuera

- Extensiones de terceros: repórtalas a quien las publica.
- Vulnerabilidades del núcleo de WordPress: <https://wordpress.org/about/security/>.
- Vulnerabilidades de las dependencias: repórtalas al proyecto correspondiente
  y avísanos si afectan a Homlity.
- Configuraciones inseguras del servidor o del sitio.
- Ataques que requieren acceso de administrador ya comprometido.
- Ingeniería social.

---

## Qué protege el plugin

### Datos que la Developer API nunca publica

Los hooks públicos y el modelo `Property` **no** transportan:

| | |
| --- | --- |
| Datos personales del propietario | `_property_contact_*`, `_property_identification` |
| Banderas de consentimiento | `_consignment_*` |
| Respuesta cruda del CRM | `_property_sync_payload` — puede contener tokens |
| Credenciales de integración | Ninguna, de ninguna forma |

El modelo se construye desde una **lista blanca** de metadatos: un metadato
añadido en el futuro no puede colarse en la API sin que alguien lo decida.
`PropertyChanges` excluye los mismos campos, de modo que el diff que viaja en un
hook no pueda convertirse en una fuga.

### Otras defensas

- Guarda `if (!defined('ABSPATH')) { exit; }` en todos los archivos.
- Verificación de nonce y de capacidades en las escrituras de wp-admin.
- Autenticación por firma en los webhooks de CRM.
- Capacidad requerida en las rutas REST administrativas.
- Límite de tasa en el formulario público de consignación.
- Saneado del contexto antes de enviar cualquier reporte de error.
- La dirección exacta de un inmueble se oculta cuando el propietario lo pidió.

---

## Para desarrolladores de extensiones

Ser una extensión de Homlity no exime de nada. La guía de seguridad para
extensiones —capacidades, nonces, saneado, escapado, SQL, SSRF, archivos,
secretos— está en
[`docs/developers/open-source/security.md`](docs/developers/open-source/security.md).

Si encuentras que la Developer API permite algo que no debería —una escalada de
privilegios, un bypass de capacidades, una fuga de datos a través de un hook—
eso **sí** es una vulnerabilidad del plugin. Repórtala por el canal privado.

---

## Enlaces

- <https://homlity.com/>
- <https://github.com/homlity/homlity-plugin>
- [Guía de seguridad para extensiones](docs/developers/open-source/security.md)
