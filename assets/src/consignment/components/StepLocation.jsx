import { useEffect, useRef } from '@wordpress/element';
import { geoSearch } from '../api';
import { InputField, CheckboxField } from './Field';

function uniqueOptions(values = []) {
  return Array.from(
    new Set(
      values
        .map((value) => String(value || '').trim())
        .filter(Boolean)
    )
  ).sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }));
}

function parseGeoTree(config) {
  return config?.geo_tree || {};
}

function collectCountries(geoTree, config) {
  return uniqueOptions([
    ...Object.keys(geoTree || {}),
    ...(Array.isArray(config?.countries) ? config.countries : []),
  ]);
}

function collectStates(geoTree, config, country) {
  const treeStates = country ? Object.keys(geoTree?.[country] || {}) : [];

  return uniqueOptions([
    ...treeStates,
    ...(Array.isArray(config?.states) ? config.states : []),
  ]);
}

function collectCities(geoTree, config, country, state) {
  const treeCities = country && state
    ? Object.keys((geoTree?.[country] || {})[state] || {})
    : [];

  return uniqueOptions([
    ...treeCities,
    ...(Array.isArray(config?.cities) ? config.cities : []),
  ]);
}

function collectNeighborhoods(geoTree, config, country, state, city) {
  const treeNeighborhoods = country && state && city
    ? (((geoTree?.[country] || {})[state] || {})[city] || [])
    : [];

  return uniqueOptions([
    ...treeNeighborhoods,
    ...(Array.isArray(config?.neighborhoods) ? config.neighborhoods : []),
  ]);
}

function LocationInput({
  id,
  label,
  value,
  onChange,
  error,
  required = false,
  placeholder,
  hint,
  options = [],
}) {
  const listId = `${id}-list`;

  return (
    <>
      <InputField
        label={label}
        id={id}
        value={value}
        onChange={onChange}
        error={error}
        required={required}
        placeholder={placeholder}
        hint={hint}
        list={options.length > 0 ? listId : undefined}
        autoComplete="off"
      />
      {options.length > 0 && (
        <datalist id={listId}>
          {options.map((option) => (
            <option key={option} value={option} />
          ))}
        </datalist>
      )}
    </>
  );
}

function buildGeoQuery(location) {
  return [
    location.address,
    location.neighborhood,
    location.city,
    location.state,
    location.country,
  ]
    .map((value) => String(value || '').trim())
    .filter(Boolean)
    .join(', ');
}

function createLeafletIcon() {
  if (window.L) {
    return window.L.divIcon({
      className: 'hcf-map-marker',
      html: '<span class="hcf-map-marker__dot"></span>',
      iconSize: [24, 24],
      iconAnchor: [12, 12],
    });
  }

  return null;
}

export default function StepLocation({ data, updateField, updateStep, errors, config, compact = false }) {
  const geoTree = parseGeoTree(config);
  const mapRef = useRef(null);
  const markerRef = useRef(null);
  const mapNodeRef = useRef(null);
  const debounceRef = useRef(null);
  const latestQueryRef = useRef('');
  const draggingRef = useRef(false);

  const countries = collectCountries(geoTree, config);
  const states = collectStates(geoTree, config, data.country);
  const cities = collectCities(geoTree, config, data.country, data.state);
  const neighborhoods = collectNeighborhoods(geoTree, config, data.country, data.state, data.city);

  const resetBelow = (level) => {
    const patch = {};
    if (level <= 1) {
      patch.state = '';
      patch.city = '';
      patch.neighborhood = '';
    }
    if (level <= 2) {
      patch.city = '';
      patch.neighborhood = '';
    }
    if (level <= 3) {
      patch.neighborhood = '';
    }
    updateStep(patch);
  };

  const handleCountry = (value) => {
    updateField('country', value);
    resetBelow(1);
  };

  const handleState = (value) => {
    updateField('state', value);
    resetBelow(2);
  };

  const handleCity = (value) => {
    updateField('city', value);
    resetBelow(3);
  };

  useEffect(() => {
    if (typeof window === 'undefined' || typeof window.L === 'undefined' || !mapNodeRef.current || mapRef.current) {
      return;
    }

    const L = window.L;
    const initialLat = parseFloat(data.latitude) || 4.710989;
    const initialLng = parseFloat(data.longitude) || -74.07209;

    mapRef.current = L.map(mapNodeRef.current, {
      scrollWheelZoom: false,
    }).setView([initialLat, initialLng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap',
    }).addTo(mapRef.current);

    const icon = createLeafletIcon();
    markerRef.current = L.marker(
      [initialLat, initialLng],
      icon ? { draggable: true, icon } : { draggable: true }
    ).addTo(mapRef.current);

    markerRef.current.on('dragstart', () => {
      draggingRef.current = true;
    });

    markerRef.current.on('dragend', () => {
      draggingRef.current = false;
      const pos = markerRef.current.getLatLng();
      updateField('latitude', pos.lat.toFixed(6));
      updateField('longitude', pos.lng.toFixed(6));
    });

    mapRef.current.on('click', (event) => {
      const { lat, lng } = event.latlng;
      markerRef.current.setLatLng([lat, lng]);
      updateField('latitude', lat.toFixed(6));
      updateField('longitude', lng.toFixed(6));
    });

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
      if (mapRef.current) {
        mapRef.current.remove();
        mapRef.current = null;
      }
      markerRef.current = null;
    };
  }, []);

  useEffect(() => {
    if (!mapRef.current || !markerRef.current || draggingRef.current) {
      return;
    }

    const lat = parseFloat(data.latitude);
    const lng = parseFloat(data.longitude);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
      return;
    }

    markerRef.current.setLatLng([lat, lng]);
    mapRef.current.setView([lat, lng], Math.max(mapRef.current.getZoom(), 14));
  }, [data.latitude, data.longitude]);

  useEffect(() => {
    const query = buildGeoQuery(data);
    latestQueryRef.current = query;

    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    if (!mapRef.current || query.length < 6) {
      return undefined;
    }

    debounceRef.current = setTimeout(async () => {
      try {
        const results = await geoSearch(query);
        const first = Array.isArray(results) ? results[0] : null;
        const lat = first?.position?.lat;
        const lng = first?.position?.lng;

        if (!first || !Number.isFinite(lat) || !Number.isFinite(lng) || latestQueryRef.current !== query) {
          return;
        }

        updateField('latitude', Number(lat).toFixed(6));
        updateField('longitude', Number(lng).toFixed(6));
      } catch {}
    }, 650);

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, [data.country, data.state, data.city, data.neighborhood, data.address]);

  return (
    <div className="hcf-step hcf-step--location">
      {!compact && (
        <>
          <h2 className="hcf-step__title">Ubicación del inmueble</h2>
          <p className="hcf-step__desc">Indica dónde se encuentra el inmueble.</p>
        </>
      )}

      <div className="hcf-row">
        <LocationInput
          label="País"
          id="country"
          value={data.country}
          onChange={handleCountry}
          error={errors.country}
          required
          placeholder="Selecciona o escribe un país…"
          hint={countries.length > 0 ? 'Puedes seleccionar un país existente o escribir uno nuevo.' : undefined}
          options={countries}
        />

        <LocationInput
          label="Departamento / Estado"
          id="state"
          value={data.state}
          onChange={handleState}
          error={errors.state}
          required
          placeholder="Selecciona o escribe un departamento…"
          hint={states.length > 0 ? 'Puedes elegir uno existente o crear uno nuevo.' : undefined}
          options={states}
        />
      </div>

      <div className="hcf-row">
        <LocationInput
          label="Ciudad / Municipio"
          id="city"
          value={data.city}
          onChange={handleCity}
          error={errors.city}
          required
          placeholder="Selecciona o escribe una ciudad…"
          hint={cities.length > 0 ? 'Si no existe, escríbela y se creará al guardar.' : undefined}
          options={cities}
        />

        <LocationInput
          label="Barrio / Sector"
          id="neighborhood"
          value={data.neighborhood}
          onChange={(value) => updateField('neighborhood', value)}
          error={errors.neighborhood}
          placeholder="Selecciona o escribe un barrio…"
          hint={neighborhoods.length > 0 ? 'También puedes crear un barrio nuevo escribiéndolo aquí.' : 'Opcional'}
          options={neighborhoods}
        />
      </div>

      <InputField
        label="Dirección"
        id="address"
        value={data.address}
        onChange={(value) => updateField('address', value)}
        error={errors.address}
        required
        placeholder="Calle 10 # 5-20"
        autoComplete="street-address"
      />

      <InputField
        label="Complemento de dirección"
        id="address_complement"
        value={data.address_complement}
        onChange={(value) => updateField('address_complement', value)}
        placeholder="Apto 301, Torre B, etc."
      />

      <CheckboxField
        label="Mostrar dirección exacta en el portal"
        id="show_exact_address"
        checked={data.show_exact_address}
        onChange={(value) => updateField('show_exact_address', value)}
        hint="Si lo desactivas, solo se mostrará el barrio o sector."
      />

      <div className="hcf-map-card">
        <div className="hcf-map-card__head">
          <h4 className="hcf-map-card__title">Mapa de ubicación</h4>
          <p className="hcf-map-card__desc">
            El marcador se actualiza automáticamente con la ubicación diligenciada. También puedes hacer clic en el mapa o arrastrar el puntero para ajustar el punto exacto.
          </p>
        </div>
        <div ref={mapNodeRef} className="hcf-location-map" />
      </div>

      <div className="hcf-row">
        <InputField
          label="Latitud"
          id="latitude"
          type="text"
          inputMode="decimal"
          value={data.latitude}
          onChange={(value) => updateField('latitude', value)}
          error={errors.latitude}
          placeholder="4.710989"
        />
        <InputField
          label="Longitud"
          id="longitude"
          type="text"
          inputMode="decimal"
          value={data.longitude}
          onChange={(value) => updateField('longitude', value)}
          error={errors.longitude}
          placeholder="-74.072090"
        />
      </div>

      <InputField
        label="Referencia de ubicación"
        id="location_reference"
        value={data.location_reference}
        onChange={(value) => updateField('location_reference', value)}
        placeholder="Frente al parque, cerca al centro comercial…"
      />

      <InputField
        label="URL de Google Maps"
        id="maps_url"
        type="url"
        value={data.maps_url}
        onChange={(value) => updateField('maps_url', value)}
        placeholder="https://maps.google.com/..."
        hint="Pega el enlace de la ubicación en Google Maps."
      />
    </div>
  );
}
