/**
 * El estado visible mientras se genera la ficha técnica en PDF.
 *
 * Lo que se comprueba aquí es justo lo que las pruebas de PHP no alcanzan: la
 * plantilla solo pinta las clases y los `data-`, y quien las enciende es este
 * script. Sin estas pruebas, un botón que vuelve a quedarse mudo —el fallo que
 * esto vino a arreglar— no rompería nada.
 *
 * El script se engancha a `document` al cargarse, así que se pide una sola vez
 * y cada prueba repinta el cuerpo del documento; el oyente sigue en su sitio.
 */

const PDF_URL = 'https://example.test/ficha-tecnica/apartamento-guatape/?download=1';

require('../../assets/js/technical-sheet-download.js');

/** Una respuesta de fetch() con lo poco que el script le mira. */
function respuesta({ ok = true, status = 200, type = 'application/pdf', disposition = '' } = {}) {
  const headers = new Map();
  if (type !== null) headers.set('content-type', type);
  if (disposition !== '') headers.set('content-disposition', disposition);

  return {
    ok,
    status,
    headers: { get: (name) => headers.get(name.toLowerCase()) ?? null },
    blob: () => Promise.resolve(new Blob(['%PDF-1.4'], { type: 'application/pdf' })),
  };
}

function pintarBoton({ async = true } = {}) {
  document.body.innerHTML = `
    <div class="property-tech-sheet-btn-wrap">
      <a
        class="property-tech-sheet-btn${async ? ' property-tech-sheet-btn--async' : ''}"
        href="${PDF_URL}"
        download
        data-filename="apartamento-en-guatape.pdf"
        data-loading-text="Generando ficha…"
        data-ready-text="Ficha descargada."
        data-error-text="No se pudo generar la ficha. Vuelve a intentarlo."
      >
        <span class="property-tech-sheet-btn__spinner" aria-hidden="true"></span>
        <span class="property-tech-sheet-btn__label">Descargar ficha técnica</span>
      </a>
      <p class="property-tech-sheet-btn__status" role="status" aria-live="polite"></p>
    </div>
  `;

  return document.querySelector('.property-tech-sheet-btn');
}

const boton = () => document.querySelector('.property-tech-sheet-btn');
const etiqueta = () => document.querySelector('.property-tech-sheet-btn__label').textContent;
const aviso = () => document.querySelector('.property-tech-sheet-btn__status').textContent;

function clic(elemento, opciones = {}) {
  const evento = new window.MouseEvent('click', { bubbles: true, cancelable: true, ...opciones });
  elemento.dispatchEvent(evento);

  return evento;
}

/**
 * Deja correr la cadena de promesas del script sin adelantar los
 * temporizadores —el reloj lo mueve cada prueba cuando le toca.
 *
 * Vaciar la cola de microtareas a base de `await` y no con `setImmediate`
 * porque los temporizadores falsos de Jest sustituyen también a ese, y con el
 * sustituto puesto la espera no terminaría nunca. Nada de lo que hay detrás
 * toca la red ni el disco, así que unas cuantas vueltas bastan y sobran.
 */
async function asentar() {
  for (let vuelta = 0; vuelta < 20; vuelta += 1) {
    await Promise.resolve();
  }
}

let descargas;
let clickReal;

beforeEach(() => {
  jest.useFakeTimers();

  descargas = [];
  // Interceptar el clic del enlace sintético evita que jsdom intente navegar
  // —y de paso es la única forma de ver que la descarga llegó a dispararse.
  clickReal = window.HTMLAnchorElement.prototype.click;
  window.HTMLAnchorElement.prototype.click = function () {
    if (this.download) descargas.push({ nombre: this.download, href: this.href });
  };

  window.URL.createObjectURL = jest.fn(() => 'blob:https://example.test/1234');
  window.URL.revokeObjectURL = jest.fn();
  global.fetch = jest.fn(() => Promise.resolve(respuesta()));
});

afterEach(() => {
  window.HTMLAnchorElement.prototype.click = clickReal;
  jest.useRealTimers();
});

// ── Mientras se compone la ficha ────────────────────────────────────────────

describe('mientras el servidor compone la ficha', () => {
  test('el botón anuncia que está trabajando', async () => {
    pintarBoton();
    clic(boton());

    expect(boton().classList.contains('is-loading')).toBe(true);
    expect(etiqueta()).toBe('Generando ficha…');
    expect(aviso()).toBe('Generando ficha…');

    await asentar();
  });

  /** Lo que hace que la animación tenga tiempo de verse. */
  test('se cancela la navegación del enlace', async () => {
    pintarBoton();

    expect(clic(boton()).defaultPrevented).toBe(true);

    await asentar();
  });

  test('el lector de pantalla se entera', async () => {
    pintarBoton();
    clic(boton());

    expect(boton().getAttribute('aria-busy')).toBe('true');
    expect(boton().getAttribute('aria-disabled')).toBe('true');

    await asentar();
  });

  /**
   * Cada clic vuelve a componer la ficha entera con Dompdf. Repetirlo mientras
   * la anterior sigue en marcha solo duplica el trabajo del servidor.
   */
  test('un segundo clic no lanza una segunda petición', async () => {
    pintarBoton();
    clic(boton());
    clic(boton());
    clic(boton());

    expect(global.fetch).toHaveBeenCalledTimes(1);

    await asentar();
  });

  /**
   * El servidor apunta la descarga contra la cookie del visitante y resuelve
   * el idioma con la sesión; sin las credenciales, la ficha saldría de otro
   * visitante y la analítica de descargas no contaría nada.
   */
  test('el PDF se pide con las cookies de la sesión', async () => {
    pintarBoton();
    clic(boton());
    await asentar();

    expect(global.fetch).toHaveBeenCalledWith(PDF_URL, expect.objectContaining({
      credentials: 'same-origin',
    }));
  });
});

// ── Cuando la ficha llega ───────────────────────────────────────────────────

describe('cuando la ficha llega', () => {
  test('el botón vuelve a su estado normal', async () => {
    pintarBoton();
    clic(boton());
    await asentar();

    expect(boton().classList.contains('is-loading')).toBe(false);
    expect(etiqueta()).toBe('Descargar ficha técnica');
    expect(boton().getAttribute('aria-busy')).toBe('false');
  });

  test('el archivo se guarda con el nombre que manda el servidor', async () => {
    pintarBoton();
    global.fetch.mockResolvedValue(respuesta({
      disposition: 'attachment; filename="casa-en-el-retiro.pdf"',
    }));

    clic(boton());
    await asentar();

    expect(descargas).toEqual([
      { nombre: 'casa-en-el-retiro.pdf', href: 'blob:https://example.test/1234' },
    ]);
  });

  test('un nombre con acentos llega entero', async () => {
    pintarBoton();
    global.fetch.mockResolvedValue(respuesta({
      disposition: "attachment; filename*=UTF-8''apartamento-en-guatap%C3%A9.pdf",
    }));

    clic(boton());
    await asentar();

    expect(descargas[0].nombre).toBe('apartamento-en-guatapé.pdf');
  });

  /** Hay proxys que recortan la cabecera; el respaldo lo pone la plantilla. */
  test('sin cabecera se usa el nombre que trae el botón', async () => {
    pintarBoton();
    clic(boton());
    await asentar();

    expect(descargas[0].nombre).toBe('apartamento-en-guatape.pdf');
  });

  test('el aviso de éxito se borra solo', async () => {
    pintarBoton();
    clic(boton());
    await asentar();

    expect(aviso()).toBe('Ficha descargada.');

    jest.advanceTimersByTime(5000);
    expect(aviso()).toBe('');
  });

  /** Una URL de blob viva retiene el PDF entero en memoria. */
  test('la URL del blob se libera después', async () => {
    pintarBoton();
    clic(boton());
    await asentar();

    expect(window.URL.revokeObjectURL).not.toHaveBeenCalled();

    jest.advanceTimersByTime(60000);
    expect(window.URL.revokeObjectURL).toHaveBeenCalledWith('blob:https://example.test/1234');
  });
});

// ── Cuando algo sale mal ────────────────────────────────────────────────────

describe('cuando algo sale mal', () => {
  test('un error del servidor se dice y el botón se recupera', async () => {
    pintarBoton();
    global.fetch.mockResolvedValue(respuesta({ ok: false, status: 500 }));

    clic(boton());
    await asentar();

    expect(aviso()).toBe('No se pudo generar la ficha. Vuelve a intentarlo.');
    expect(boton().classList.contains('is-loading')).toBe(false);
    expect(etiqueta()).toBe('Descargar ficha técnica');
    expect(descargas).toEqual([]);
  });

  test('la red caída se dice igual', async () => {
    pintarBoton();
    global.fetch.mockRejectedValue(new Error('sin red'));

    clic(boton());
    await asentar();

    expect(aviso()).toBe('No se pudo generar la ficha. Vuelve a intentarlo.');
    expect(boton().classList.contains('is-loading')).toBe(false);
  });

  /**
   * Cuando PHP muere a medio componer, lo que llega es la página de error con
   * estado 200. Guardarla como .pdf le deja al visitante un archivo que no
   * abre y la sensación de que la descarga funcionó.
   */
  test('una página de error no se guarda como si fuera el PDF', async () => {
    pintarBoton();
    global.fetch.mockResolvedValue(respuesta({ type: 'text/html; charset=UTF-8' }));

    clic(boton());
    await asentar();

    expect(descargas).toEqual([]);
    expect(aviso()).toBe('No se pudo generar la ficha. Vuelve a intentarlo.');
  });

  /** Sin cabecera de tipo no hay motivo para desconfiar. */
  test('una respuesta sin tipo declarado sí se guarda', async () => {
    pintarBoton();
    global.fetch.mockResolvedValue(respuesta({ type: null }));

    clic(boton());
    await asentar();

    expect(descargas).toHaveLength(1);
  });

  /**
   * El aviso de éxito se borra solo a los cinco segundos. Si dentro de esa
   * ventana el visitante vuelve a pulsar y esta vez falla, el temporizador
   * pendiente del acuse anterior llegaría tarde y se llevaría por delante el
   * mensaje de error, dejando el botón mudo justo cuando hay algo que contar.
   */
  test('un error dentro de la ventana del acuse anterior no se borra', async () => {
    pintarBoton();
    clic(boton());
    await asentar();
    expect(aviso()).toBe('Ficha descargada.');

    jest.advanceTimersByTime(3000);

    global.fetch.mockRejectedValue(new Error('sin red'));
    clic(boton());
    await asentar();

    jest.advanceTimersByTime(3000);

    expect(aviso()).toBe('No se pudo generar la ficha. Vuelve a intentarlo.');
  });

  /** El de error se queda hasta el siguiente intento; no es un acuse de recibo. */
  test('el aviso de error no se borra solo', async () => {
    pintarBoton();
    global.fetch.mockRejectedValue(new Error('sin red'));

    clic(boton());
    await asentar();
    jest.advanceTimersByTime(60000);

    expect(aviso()).toBe('No se pudo generar la ficha. Vuelve a intentarlo.');
  });

  /**
   * Si PHP muere sin cerrar la conexión, fetch() no rechaza nunca y el aro
   * giraría para siempre.
   */
  test('una espera eterna acaba cortándose', async () => {
    pintarBoton();
    global.fetch.mockImplementation((url, opciones) => new Promise((resolve, reject) => {
      opciones.signal.addEventListener('abort', () => reject(new Error('abortada')));
    }));

    clic(boton());
    expect(boton().classList.contains('is-loading')).toBe(true);

    jest.advanceTimersByTime(120000);
    await asentar();

    expect(boton().classList.contains('is-loading')).toBe(false);
    expect(aviso()).toBe('No se pudo generar la ficha. Vuelve a intentarlo.');
  });

  test('un intento fallido no impide reintentar', async () => {
    pintarBoton();
    global.fetch.mockRejectedValueOnce(new Error('sin red'));

    clic(boton());
    await asentar();

    global.fetch.mockResolvedValue(respuesta());
    clic(boton());
    await asentar();

    expect(descargas).toHaveLength(1);
    expect(aviso()).toBe('Ficha descargada.');
  });
});

// ── Lo que el script no debe tocar ──────────────────────────────────────────

describe('lo que el script deja en paz', () => {
  /** Abrir la ficha en el sitio navega, y de eso ya avisa el navegador. */
  test('un enlace sin el modificador no se intercepta', () => {
    pintarBoton({ async: false });

    expect(clic(boton()).defaultPrevented).toBe(false);
    expect(global.fetch).not.toHaveBeenCalled();
  });

  test.each([
    ['ctrl', { ctrlKey: true }],
    ['cmd', { metaKey: true }],
    ['mayúsculas', { shiftKey: true }],
    ['alt', { altKey: true }],
    ['el botón central', { button: 1 }],
  ])('con %s el navegador hace lo suyo', (_nombre, opciones) => {
    pintarBoton();

    expect(clic(boton(), opciones).defaultPrevented).toBe(false);
    expect(global.fetch).not.toHaveBeenCalled();
  });

  /**
   * Vale más un botón sin animación que un botón que no descarga: sin fetch,
   * el enlace se deja navegar y el navegador baja el PDF como siempre.
   */
  test('sin fetch el enlace descarga como toda la vida', () => {
    pintarBoton();
    const original = window.fetch;
    delete window.fetch;

    expect(clic(boton()).defaultPrevented).toBe(false);

    window.fetch = original;
  });
});
