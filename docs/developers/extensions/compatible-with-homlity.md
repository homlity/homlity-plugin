# Política «Compatible con Homlity»

Qué puedes construir, publicar y vender sobre Homlity Real Estate, y en qué
condiciones puedes decir que tu producto es **compatible con Homlity**.

> Este documento describe una política de producto y de ecosistema. No es
> asesoramiento legal. Para las condiciones de licencia del código, mira
> [`license.txt`](../../../license.txt) y la sección
> [Licencias](#licencias) más abajo.

---

## Lo que puedes construir

Todo esto está expresamente permitido y bienvenido:

| Tipo | Ejemplos |
| --- | --- |
| **Extensiones gratuitas** | Conectores, utilidades, personalizaciones publicadas libremente |
| **Extensiones comerciales** | Productos de pago, con licencia y soporte propios |
| **Integraciones privadas** | Desarrollos a medida para un cliente concreto |
| **Productos SaaS** | Servicios externos que se conectan a sitios con Homlity |

No hace falta permiso ni acuerdo previo. No hay comisión sobre tus ventas. No
hay exclusividad: puedes integrar el mismo CRM que integra otro, y puedes
integrar Homlity y la competencia a la vez.

---

## Cuándo puedes decir «Compatible con Homlity»

Puedes usar la frase **«Compatible con Homlity»** —o «Compatible con Homlity
Real Estate»— en la ficha de tu producto, tu web y tu documentación si se
cumplen estas seis condiciones:

### 1 · No afirmas ser parte oficial de Homlity

Tu producto es **tuyo**. No es de Homlity, no está desarrollado por Homlity, no
está certificado ni respaldado por Homlity salvo que exista un acuerdo escrito.

| | |
| --- | --- |
| ✓ | «Mi CRM — Compatible con Homlity» |
| ✓ | «Integración para Homlity Real Estate» |
| ✓ | «Funciona con Homlity» |
| ✗ | «Homlity Mi CRM» |
| ✗ | «Extensión oficial de Homlity» |
| ✗ | «Certificado por Homlity» |
| ✗ | «Homlity Pro», «Homlity Plus» y similares |

La regla, en una frase: **el nombre de tu producto es tuyo, y «Homlity» aparece
sólo para decir con qué es compatible.**

### 2 · No usas los logos de forma engañosa

Puedes mencionar «Homlity» y «Homlity Real Estate» como nombres. Puedes decir
que tu producto se integra con ellos.

No puedes usar el logotipo, el isotipo ni la identidad visual de Homlity como
si fueran los de tu producto: no como icono de tu plugin, no como favicon, no
como imagen destacada de tu ficha, no en tu dominio ni en tu marca.

Si necesitas material de marca, pídelo: <https://homlity.com/>.

### 3 · Cumples los requisitos técnicos

- Usas la **Developer API pública** (`Homlity\Developer\` y los hooks
  `homlity/…`) en lugar de depender de implementaciones internas.
- **No modificas los archivos del plugin.** Un producto que exige parchear
  `homlity-plugin` no es compatible: es un fork.
- No dependes de la base de datos de Homlity por acceso directo cuando existe
  una API que hace lo mismo.
- Tu producto se desactiva limpiamente: sin errores fatales, sin datos
  huérfanos, sin eventos de cron sin dueño.
- Si Homlity no está o es demasiado antiguo, tu producto avisa en vez de
  romper el sitio.

### 4 · Indicas las versiones compatibles

En un sitio visible de tu ficha y de tu README:

```
Compatible con Homlity Real Estate 2.8.0 – 2.9.x
Developer API 1.x
```

Y mantenlo al día. Anunciar compatibilidad con «Homlity» a secas, sin versión,
es lo que genera los tickets de soporte que ninguno de los dos quiere.

### 5 · Respetas las licencias

Ver [Licencias](#licencias).

### 6 · No comprometes la seguridad

Tu extensión no debe:

- eludir comprobaciones de capacidades o de nonce del núcleo;
- exponer datos personales del propietario de un inmueble —los que capta el
  formulario de consignación— a terceros;
- publicar la dirección exacta de un inmueble cuyo propietario pidió ocultarla;
- filtrar credenciales, tokens o claves de API, ni por hooks, ni en logs, ni en
  endpoints;
- ejecutar código remoto, ni cargar código desde fuera del plugin en tiempo de
  ejecución;
- enviar datos del sitio a terceros sin que el administrador lo sepa y lo
  acepte.

Ver [Seguridad](../open-source/security.md) y
[Buenas prácticas](best-practices.md).

---

## Licencias

Homlity Real Estate se distribuye bajo **GPLv2 o posterior**, como WordPress.

Eso tiene una consecuencia práctica que conviene entender bien: **el código PHP
de un plugin de WordPress que interactúa con el núcleo se considera
generalmente una obra derivada**, y por tanto debe distribuirse también bajo
GPLv2 o compatible. Es la interpretación de la Free Software Foundation y la
posición del proyecto WordPress.

Qué significa en la práctica:

- Tu extensión puede ser **comercial**. GPL no significa gratis: puedes cobrar
  por tu producto, por el soporte, por las actualizaciones y por el servicio.
- Tus **assets** —CSS, JavaScript, imágenes— pueden llevar otra licencia.
- Tu **servicio SaaS** al otro lado de la API es tuyo y no se ve afectado: la
  GPL cubre lo que distribuyes, no lo que ejecutas en tus servidores.
- Una **integración privada** para un solo cliente no se «distribuye» al
  público; se entrega a ese cliente bajo GPL, y ahí termina tu obligación.

Si tu modelo de negocio depende de estos detalles, consulta con un abogado. No
es una decisión que deba tomarse leyendo un README.

---

## Lo que Homlity se compromete a hacer

A cambio de que construyas sobre la API pública:

- **Estabilidad.** La Developer API no rompe dentro de su versión mayor. Ver
  [SemVer](../versioning/semver.md).
- **Deprecación con aviso.** Nada se elimina sin pasar por un ciclo mayor
  completo de deprecación documentada. Ver
  [Deprecaciones](../versioning/deprecations.md).
- **Documentación.** Todo lo público está documentado en
  <https://homlity.com/desarrolladores/>.
- **Changelog honesto.** Lo que cambia se cuenta. Ver
  [Política de changelog](../versioning/changelog-policy.md).
- **Un canal para pedir puntos de extensión.** Si la API no cubre tu caso,
  ábrelo como incidencia. Ver
  [Reportar incidencias](../open-source/reporting-issues.md).

---

## Lo que Homlity no hace

Para que no haya expectativas equivocadas:

- No revisa ni certifica extensiones de terceros.
- No da soporte a extensiones de terceros. Ese soporte es tuyo.
- No garantiza que dos extensiones cualesquiera convivan.
- No mantiene un directorio de extensiones compatibles. Si eso cambia, se
  anunciará.

---

## Si crees que alguien incumple

Escribe a través de <https://homlity.com/>. Describe el producto, dónde está
publicado y qué condición crees que no se cumple.

Los problemas de **seguridad** —de Homlity o de una extensión— no van por ahí:
ver [Seguridad](../open-source/security.md) y el archivo
[`SECURITY.md`](../../../SECURITY.md) del repositorio.
