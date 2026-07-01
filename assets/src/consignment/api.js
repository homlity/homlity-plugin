/**
 * Homlity Consignment Form — API client.
 *
 * All requests go to the plugin's own consignment endpoints.
 * The nonce is sent as X-WP-Nonce header (WordPress REST API standard).
 * No tokens, HMAC secrets, or CRM credentials are ever exposed.
 */

let runtimeConfig = window.homlityConsignmentConfig || {};

const getBase = () =>
  runtimeConfig.restBase ||
  `${window.location.origin}/wp-json/homlity/v1/consignment`;

const getNonce = () => runtimeConfig.nonce || '';

const headers = (extra = {}) => ({
  'Content-Type': 'application/json',
  'X-WP-Nonce': getNonce(),
  ...extra,
});

/** Fetch the form configuration from the server. */
export async function fetchConfig() {
  const res = await fetch(`${getBase()}/config`, {
    method: 'GET',
    headers: { 'X-WP-Nonce': getNonce() },
    credentials: 'same-origin',
  });
  if (!res.ok) throw new Error('No se pudo cargar la configuración del formulario.');
  const json = await res.json();
  runtimeConfig = {
    ...runtimeConfig,
    nonce: json.nonce || runtimeConfig.nonce || '',
  };
  return json;
}

export async function geoSearch(query) {
  const url = new URL(`${window.location.origin}/wp-json/homlity/v1/geo/search`);
  url.searchParams.set('q', query);

  const res = await fetch(url.toString(), {
    method: 'GET',
    headers: { 'X-WP-Nonce': getNonce() },
    credentials: 'same-origin',
  });

  if (!res.ok) {
    return [];
  }

  return res.json();
}

/** Validate a single step without submitting. */
export async function validateStep(step, data) {
  const res = await fetch(`${getBase()}/validate-step`, {
    method: 'POST',
    headers: headers(),
    credentials: 'same-origin',
    body: JSON.stringify({ step, data }),
  });
  const json = await res.json();
  return json; // { ok, errors }
}

/** Submit the full form. */
export async function submitForm(formData, provider) {
  const res = await fetch(`${getBase()}/submit`, {
    method: 'POST',
    headers: headers(),
    credentials: 'same-origin',
    body: JSON.stringify({ ...formData, _provider: provider }),
  });
  const json = await res.json();
  if (!res.ok && res.status !== 422) {
    throw new Error(json.message || 'Error al enviar el formulario.');
  }
  return json; // { ok, post_id, message } | { ok: false, errors }
}

/** Upload a single file and return { ok, url, id }. */
export async function uploadFile(file, type = 'image', onProgress) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('type', type);

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', `${getBase()}/upload`);
    xhr.setRequestHeader('X-WP-Nonce', getNonce());

    if (onProgress) {
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          onProgress(Math.round((e.loaded / e.total) * 100));
        }
      });
    }

    xhr.onload = () => {
      try {
        const json = JSON.parse(xhr.responseText);
        if (json.ok) {
          resolve(json);
        } else {
          reject(new Error(json.message || 'Error al subir el archivo.'));
        }
      } catch {
        reject(new Error('Respuesta inesperada del servidor.'));
      }
    };

    xhr.onerror = () => reject(new Error('Error de red al subir el archivo.'));
    xhr.send(formData);
  });
}
