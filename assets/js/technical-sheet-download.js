/**
 * Estado visible mientras se genera la ficha técnica en PDF.
 *
 * El botón es un enlace normal a `?download=1`. El problema es que ese enlace
 * no navega: la respuesta llega con `Content-Disposition: attachment`, así que
 * el navegador se queda en la página y no pinta ningún indicador de carga. Y
 * componer la ficha no es inmediato —Dompdf arma el HTML, descarga las fotos
 * del inmueble y rasteriza—, de modo que entre el clic y el archivo pasan
 * varios segundos en los que el botón parece roto.
 *
 * Por eso aquí se pide el PDF con fetch(): así hay un momento exacto en el que
 * empieza la espera y otro en el que termina, que es lo que la promesa de
 * fetch() da y una navegación normal no. Con el archivo ya en memoria se
 * dispara la descarga desde un blob.
 *
 * Todo lo que sigue degrada a la navegación de siempre: sin fetch, con una
 * tecla modificadora pulsada o con el botón central, el enlace se deja en paz.
 */
(function () {
  var SELECTOR = '.property-tech-sheet-btn--async';
  var LOADING_CLASS = 'is-loading';

  // Si el PHP muere a medio componer, fetch() no rechaza: la conexión se
  // queda abierta y el botón giraría para siempre. Este tope lo corta.
  var TIMEOUT_MS = 120000;

  function statusNode(link) {
    var wrap = link.closest('.property-tech-sheet-btn-wrap');

    return wrap ? wrap.querySelector('.property-tech-sheet-btn__status') : null;
  }

  function announce(link, message, transient) {
    var node = statusNode(link);
    if (!node) return;

    if (node.homlityClearTimer) {
      clearTimeout(node.homlityClearTimer);
      node.homlityClearTimer = 0;
    }

    node.textContent = message;
    node.classList.toggle('is-visible', message !== '');

    // El aviso de éxito acompaña a la descarga del navegador y sobra en
    // cuanto esta aparece; el de error se queda hasta el siguiente intento.
    if (transient && message !== '') {
      node.homlityClearTimer = setTimeout(function () {
        node.textContent = '';
        node.classList.remove('is-visible');
      }, 5000);
    }
  }

  function setLoading(link, loading) {
    link.classList.toggle(LOADING_CLASS, loading);
    // `aria-disabled` y no `disabled`: un <a> no admite el segundo, y quitarle
    // el href para desactivarlo lo sacaría del orden de tabulación.
    link.setAttribute('aria-disabled', loading ? 'true' : 'false');
    link.setAttribute('aria-busy', loading ? 'true' : 'false');

    var label = link.querySelector('.property-tech-sheet-btn__label');
    if (!label) return;

    if (loading) {
      label.dataset.idleText = label.textContent;
      label.textContent = link.dataset.loadingText || label.textContent;
      return;
    }

    if (label.dataset.idleText) {
      label.textContent = label.dataset.idleText;
      delete label.dataset.idleText;
    }
  }

  /**
   * El nombre del archivo lo manda el servidor en la cabecera; el del enlace
   * es el respaldo para cuando un proxy la recorta.
   */
  function filenameFrom(response, fallback) {
    var header = response.headers.get('Content-Disposition') || '';
    var encoded = /filename\*=\s*UTF-8''([^;]+)/i.exec(header);
    if (encoded) {
      try {
        return decodeURIComponent(encoded[1]);
      } catch (err) {
        // Un porcentaje suelto en la cabecera; seguimos con el respaldo.
      }
    }

    var plain = /filename\s*=\s*"?([^";]+)"?/i.exec(header);

    return plain ? plain[1].trim() : fallback;
  }

  /**
   * Un PDF servido como HTML es la página de error de PHP con otro nombre;
   * guardarla con extensión .pdf deja al visitante con un archivo ilegible y
   * sin saber que algo falló. Un tipo vacío sí pasa: hay proxys que lo quitan.
   */
  function looksLikePdf(response) {
    var type = (response.headers.get('Content-Type') || '').toLowerCase();

    return type === '' || type.indexOf('pdf') !== -1 || type.indexOf('octet-stream') !== -1;
  }

  function saveBlob(blob, filename) {
    // Edge/IE heredado en algunos escritorios corporativos.
    if (window.navigator && window.navigator.msSaveOrOpenBlob) {
      window.navigator.msSaveOrOpenBlob(blob, filename);
      return;
    }

    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.rel = 'noopener';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Safari aborta la descarga si la URL del blob se revoca en el mismo
    // ciclo; hay que dejarla viva hasta que el navegador la haya leído.
    setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
  }

  function download(link) {
    var controller = window.AbortController ? new AbortController() : null;
    var timer = controller
      ? setTimeout(function () { controller.abort(); }, TIMEOUT_MS)
      : 0;

    setLoading(link, true);
    announce(link, link.dataset.loadingText || '');

    fetch(link.href, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      signal: controller ? controller.signal : undefined
    })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        if (!looksLikePdf(response)) throw new Error('respuesta que no es un PDF');

        return response.blob().then(function (blob) {
          saveBlob(blob, filenameFrom(response, link.dataset.filename || 'ficha-tecnica.pdf'));
        });
      })
      .then(function () {
        announce(link, link.dataset.readyText || '', true);
      })
      .catch(function () {
        announce(link, link.dataset.errorText || '');
      })
      .then(function () {
        if (timer) clearTimeout(timer);
        setLoading(link, false);
      });
  }

  function handleClick(event) {
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    var link = event.target.closest ? event.target.closest(SELECTOR) : null;
    if (!link || !link.href) return;

    // Sin estas piezas la descarga desde un blob no se puede armar, y vale
    // más un botón sin animación que un botón que no descarga nada.
    if (!window.fetch || !window.URL || !window.URL.createObjectURL) return;

    event.preventDefault();

    // Cada clic vuelve a componer la ficha entera en el servidor; repetirlo
    // mientras la anterior sigue en marcha solo duplica el trabajo.
    if (link.classList.contains(LOADING_CLASS)) return;

    download(link);
  }

  document.addEventListener('click', handleClick);
})();
