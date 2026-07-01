/**
 * Homlity Consignment Form — client-side validators.
 *
 * These are UX helpers only. The authoritative validation is always server-side.
 * Each function returns an object of fieldName → errorMessage.
 * An empty object means the step is valid.
 */

// ── Contact ───────────────────────────────────────────────────────────────

export function validateContact(data, config) {
  const errors = {};
  const allowed = config?.required_fields || {};

  if (!data.consignant_type) {
    errors.consignant_type = 'Selecciona el tipo de consignante.';
  }

  if (!data.name || data.name.trim().length < 2) {
    errors.name = 'El nombre debe tener al menos 2 caracteres.';
  }

  if (!data.email || !isValidEmail(data.email)) {
    errors.email = 'Ingresa un correo electrónico válido.';
  }

  if (!data.phone?.trim() && !data.whatsapp?.trim()) {
    errors.phone = 'Ingresa al menos un número de teléfono o WhatsApp.';
  }

  if (!data.data_consent) {
    errors.data_consent = 'Debes aceptar la política de tratamiento de datos.';
  }

  if (!data.authorization_consent) {
    errors.authorization_consent = 'Debes confirmar que tienes autorización para consignar este inmueble.';
  }

  return errors;
}

// ── Operation ─────────────────────────────────────────────────────────────

export function validateOperation(data) {
  const errors = {};
  const op = data.operation || '';

  if (!op) {
    errors.operation = 'Selecciona el tipo de operación.';
    return errors;
  }

  if (op.includes('Venta')) {
    const price = parseFloat((data.sale_price || '').replace(/[^0-9.]/g, ''));
    if (!price || price <= 0) {
      errors.sale_price = 'El precio de venta es obligatorio.';
    } else if (price < 1_000_000) {
      errors._sale_price_warn = 'El precio parece muy bajo. Verifica que sea correcto.';
    } else if (price > 100_000_000_000) {
      errors._sale_price_warn = 'El precio parece muy alto. Verifica que sea correcto.';
    }
  }

  if (op.includes('Arriendo')) {
    const price = parseFloat((data.rent_price || '').replace(/[^0-9.]/g, ''));
    if (!price || price <= 0) {
      errors.rent_price = 'El precio de arriendo es obligatorio.';
    }
  }

  return errors;
}

// ── Location ──────────────────────────────────────────────────────────────

export function validateLocation(data, config) {
  const errors = {};
  const requireCoords = config?.required_fields?.require_coordinates !== false;

  if (!data.country?.trim()) errors.country = 'El país es obligatorio.';
  if (!data.state?.trim())   errors.state   = 'El departamento/estado es obligatorio.';
  if (!data.city?.trim())    errors.city    = 'La ciudad es obligatoria.';
  if (!data.address?.trim()) errors.address = 'La dirección es obligatoria.';

  if (requireCoords) {
    if (!data.latitude || isNaN(parseFloat(data.latitude))) {
      errors.latitude = 'La latitud es obligatoria.';
    }
    if (!data.longitude || isNaN(parseFloat(data.longitude))) {
      errors.longitude = 'La longitud es obligatoria.';
    }
  }

  return errors;
}

// ── Property Details ──────────────────────────────────────────────────────

export function validatePropertyDetails(data) {
  const errors = {};

  if (data.title?.trim() && data.title.length > 200) {
    errors.title = 'El título no debe superar los 200 caracteres.';
  }

  if (!data.property_type?.trim()) {
    errors.property_type = 'El tipo de inmueble es obligatorio.';
  }

  if (data.year_built) {
    const year = parseInt(data.year_built, 10);
    const currentYear = new Date().getFullYear();
    if (year < 1800 || year > currentYear + 10) {
      errors.year_built = 'Ingresa un año de construcción válido.';
    }
  }

  return errors;
}

// ── Areas ─────────────────────────────────────────────────────────────────

export function validateAreas(data) {
  const errors = {};

  const area = parseFloat((data.area || '').replace(/[^0-9.]/g, ''));
  if (!area || area <= 0) {
    errors.area = 'El área del inmueble es obligatoria.';
  }

  ['bedrooms', 'bathrooms', 'parking'].forEach((field) => {
    if (data[field] !== '' && data[field] !== undefined) {
      const val = parseInt(data[field], 10);
      if (isNaN(val) || val < 0) {
        const labels = { bedrooms: 'Habitaciones', bathrooms: 'Baños', parking: 'Parqueaderos' };
        errors[field] = `${labels[field]} debe ser un número mayor o igual a cero.`;
      }
    }
  });

  return errors;
}

// ── Media ─────────────────────────────────────────────────────────────────

export function validateMedia(data, config) {
  const errors = {};
  const requireImage = config?.required_fields?.require_image !== false;

  if (requireImage && (!data.gallery || data.gallery.length === 0) && !data.featured_image) {
    errors.gallery = 'Debes subir al menos una imagen del inmueble.';
  }

  return errors;
}

// ── Review ────────────────────────────────────────────────────────────────

export function validateReview(data) {
  const errors = {};

  if (!data.truth_declaration) {
    errors.truth_declaration = 'Debes confirmar que la información suministrada es verídica.';
  }
  if (!data.contact_consent) {
    errors.contact_consent = 'Debes aceptar ser contactado para validar la consignación.';
  }

  return errors;
}

// ── Per-step dispatcher ───────────────────────────────────────────────────

export function validateStep(step, data, config) {
  switch (step) {
    case 'contact':          return validateContact(data, config);
    case 'operation':        return validateOperation(data);
    case 'location':         return validateLocation(data, config);
    case 'property_details': return validatePropertyDetails(data);
    case 'areas':            return validateAreas(data);
    case 'features':         return {};
    case 'media':            return validateMedia(data, config);
    case 'advisor':          return {};
    case 'review':           return validateReview(data);
    default:                 return {};
  }
}

// ── Utils ─────────────────────────────────────────────────────────────────

export function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

export function hasErrors(errors) {
  return Object.values(errors).some((v) => v && !String(v).startsWith('_'));
}

export function getWarnings(errors) {
  return Object.fromEntries(
    Object.entries(errors).filter(([k]) => k.startsWith('_'))
  );
}
