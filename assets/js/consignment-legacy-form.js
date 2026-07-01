(function () {
  const config = window.homlityLegacyConsignmentConfig || {};
  const root = document.querySelector('[data-homlity-legacy-consignment]');

  if (!root || !config.restBase) {
    return;
  }

  const state = {
    images: [],
    documents: [],
  };

  const qs = (selector) => root.querySelector(selector);
  const createOption = (value, label) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = label;
    return option;
  };

  const setStatus = (message, type) => {
    const box = qs('[data-role="status"]');
    if (!box) return;
    box.className = 'homlity-legacy-consignment__status' + (type ? ' is-' + type : '');
    box.textContent = message || '';
    box.hidden = !message;
  };

  const setLoading = (loading) => {
    const submit = qs('[type="submit"]');
    if (!submit) return;
    submit.disabled = loading;
    submit.textContent = loading ? 'Enviando...' : (config.submitLabel || 'Enviar inmueble');
  };

  const requestJson = async (path, options = {}) => {
    const response = await fetch(config.restBase.replace(/\/$/, '') + path, {
      credentials: 'same-origin',
      ...options,
    });

    const json = await response.json().catch(() => ({}));

    if (!response.ok) {
      const message = json.message || 'Ocurrió un error en la solicitud.';
      throw new Error(message);
    }

    return json;
  };

  const fillSelect = (select, items, valueKey, labelKey, placeholder) => {
    if (!select) return;
    select.innerHTML = '';
    select.appendChild(createOption('', placeholder || 'Selecciona...'));
    items.forEach((item) => {
      select.appendChild(createOption(item[valueKey], item[labelKey]));
    });
  };

  const loadCatalogs = async () => {
    const [propertyTypes, operations, states] = await Promise.all([
      requestJson('/v1/tiposInmueblePublicar'),
      requestJson('/v1/tiposGestion'),
      requestJson('/v1/data/geo/firstDivisionLevel'),
    ]);

    fillSelect(qs('[name="id_tipoinmueble"]'), propertyTypes || [], 'codigo', 'nombre', 'Tipo de inmueble');
    fillSelect(qs('[name="id_gestion"]'), operations || [], 'codigo', 'nombre', 'Tipo de gestión');
    fillSelect(qs('[name="id_departamento"]'), states || [], 'id', 'nombre', 'Departamento');
  };

  const loadCities = async (stateId) => {
    const citySelect = qs('[name="id_ciudad"]');
    const neighborhoodSelect = qs('[name="id_barrio"]');
    fillSelect(citySelect, [], 'id', 'nombre', 'Ciudad');
    fillSelect(neighborhoodSelect, [], 'id', 'nombre', 'Barrio');

    if (!stateId) return;

    const cities = await requestJson('/v1/data/geo/secondDivisionLevel?id_firstDivision=' + encodeURIComponent(stateId));
    fillSelect(citySelect, cities || [], 'id', 'nombre', 'Ciudad');
  };

  const loadNeighborhoods = async (cityId) => {
    const neighborhoodSelect = qs('[name="id_barrio"]');
    fillSelect(neighborhoodSelect, [], 'id', 'nombre', 'Barrio');

    if (!cityId) return;

    const neighborhoods = await requestJson('/v1/data/geo/neighborhoods?id_secondDivision=' + encodeURIComponent(cityId));
    fillSelect(neighborhoodSelect, neighborhoods || [], 'id', 'nombre', 'Barrio');
  };

  const uploadFile = async (file, type) => {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(
      config.restBase.replace(/\/$/, '') + (type === 'document' ? '/free/v1/uploads/documento' : '/free/v1/uploads/imagen'),
      {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      }
    );

    const json = await response.json().catch(() => ({}));
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'No se pudo cargar el archivo.');
    }

    return json.url_full || json.url || '';
  };

  const uploadFiles = async (files, type) => {
    const uploaded = [];
    for (const file of files) {
      uploaded.push(await uploadFile(file, type));
    }
    return uploaded.filter(Boolean);
  };

  const serialize = async () => {
    const form = qs('form');
    const data = new FormData(form);

    const payload = {
      propietario: {
        nombre: data.get('propietario_nombre') || '',
        email: data.get('propietario_email') || '',
        telefono: data.get('propietario_telefono') || '',
        identificacion: data.get('propietario_identificacion') || '',
        tipo_indentificacion: data.get('propietario_tipo_identificacion') || '',
        tipo: 'owner',
      },
      id_tipoinmueble: Number(data.get('id_tipoinmueble') || 0),
      id_gestion: Number(data.get('id_gestion') || 0),
      id_departamento: Number(data.get('id_departamento') || 0),
      id_ciudad: Number(data.get('id_ciudad') || 0),
      id_barrio: Number(data.get('id_barrio') || 0),
      barrio_nombre: data.get('barrio_nombre') || '',
      nombre: data.get('nombre') || '',
      descripcion: data.get('descripcion') || '',
      direccion: data.get('direccion') || '',
      latitud: data.get('latitud') || '',
      longitud: data.get('longitud') || '',
      valor_venta: Number(data.get('valor_venta') || 0),
      valor_canon: Number(data.get('valor_canon') || 0),
      valor_admin: Number(data.get('valor_admin') || 0),
      valor_admin_incluida: data.get('valor_admin_incluida') === '1',
      area_construida: Number(data.get('area_construida') || 0),
      area_lote: Number(data.get('area_lote') || 0),
      n_alcobas: Number(data.get('n_alcobas') || 0),
      n_banos: Number(data.get('n_banos') || 0),
      n_garajes: Number(data.get('n_garajes') || 0),
      estrato: Number(data.get('estrato') || 0),
      edad: Number(data.get('edad') || 0),
      imagenes: await uploadFiles(state.images, 'image'),
      documentos: await uploadFiles(state.documents, 'document'),
      caracteristicas: [],
      _hp: '',
    };

    payload.foto_portada = payload.imagenes[0] || '';

    return payload;
  };

  const renderFiles = (input, targetSelector) => {
    const target = qs(targetSelector);
    if (!target) return;
    target.textContent = input.files.length
      ? Array.from(input.files).map((file) => file.name).join(', ')
      : 'Ningún archivo seleccionado';
  };

  qs('[name="id_departamento"]')?.addEventListener('change', async (event) => {
    try {
      await loadCities(event.target.value);
    } catch (error) {
      setStatus(error.message, 'error');
    }
  });

  qs('[name="id_ciudad"]')?.addEventListener('change', async (event) => {
    try {
      await loadNeighborhoods(event.target.value);
    } catch (error) {
      setStatus(error.message, 'error');
    }
  });

  qs('[name="imagenes_files"]')?.addEventListener('change', (event) => {
    state.images = Array.from(event.target.files || []);
    renderFiles(event.target, '[data-role="images-list"]');
  });

  qs('[name="documentos_files"]')?.addEventListener('change', (event) => {
    state.documents = Array.from(event.target.files || []);
    renderFiles(event.target, '[data-role="documents-list"]');
  });

  qs('form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    setStatus('', '');
    setLoading(true);

    try {
      const payload = await serialize();
      const result = await requestJson('/free/v1/inmueble/crear', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!result.ok) {
        throw new Error(result.message || 'No fue posible enviar la consignación.');
      }

      qs('form')?.reset();
      state.images = [];
      state.documents = [];
      renderFiles({ files: [] }, '[data-role="images-list"]');
      renderFiles({ files: [] }, '[data-role="documents-list"]');
      setStatus(result.message || config.successMessage || 'Inmueble enviado correctamente.', 'success');
    } catch (error) {
      setStatus(error.message || config.errorMessage || 'No fue posible enviar el formulario.', 'error');
    } finally {
      setLoading(false);
    }
  });

  loadCatalogs().catch((error) => {
    setStatus(error.message || 'No fue posible cargar los catálogos del formulario.', 'error');
  });
})();
