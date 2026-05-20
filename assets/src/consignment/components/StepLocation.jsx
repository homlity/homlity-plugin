import { useState, useEffect } from '@wordpress/element';
import { InputField, SelectField, CheckboxField, TextareaField } from './Field';

function parseGeoTree(config) {
  return config?.geo_tree || {};
}

export default function StepLocation({ data, updateField, updateStep, errors, config }) {
  const geoTree = parseGeoTree(config);

  // Derived lists
  const countries     = Object.keys(geoTree);
  const states        = data.country ? Object.keys(geoTree[data.country] || {}) : [];
  const cities        = data.state   ? Object.keys((geoTree[data.country] || {})[data.state] || {}) : [];
  const neighborhoods = data.city
    ? ((geoTree[data.country] || {})[data.state] || {})[data.city] || []
    : [];

  const resetBelow = (level) => {
    const patch = {};
    if (level <= 1) { patch.state = ''; patch.city = ''; patch.neighborhood = ''; }
    if (level <= 2) { patch.city = ''; patch.neighborhood = ''; }
    if (level <= 3) { patch.neighborhood = ''; }
    updateStep(patch);
  };

  const handleCountry = (v) => { updateField('country', v); resetBelow(1); };
  const handleState   = (v) => { updateField('state',   v); resetBelow(2); };
  const handleCity    = (v) => { updateField('city',    v); resetBelow(3); };

  // If server provided only string lists (no geo_tree), fall back to plain inputs
  const useSelects = countries.length > 0;

  return (
    <div className="hcf-step hcf-step--location">
      <h2 className="hcf-step__title">Ubicación del inmueble</h2>
      <p className="hcf-step__desc">Indica dónde se encuentra el inmueble.</p>

      <div className="hcf-row">
        {useSelects ? (
          <SelectField
            label="País"
            id="country"
            value={data.country}
            onChange={handleCountry}
            error={errors.country}
            required
            placeholder="Selecciona país…"
          >
            {countries.map((c) => <option key={c} value={c}>{c}</option>)}
          </SelectField>
        ) : (
          <InputField
            label="País"
            id="country"
            value={data.country}
            onChange={(v) => updateField('country', v)}
            error={errors.country}
            required
          />
        )}

        {useSelects ? (
          <SelectField
            label="Departamento / Estado"
            id="state"
            value={data.state}
            onChange={handleState}
            error={errors.state}
            required
            placeholder="Selecciona…"
          >
            {states.map((s) => <option key={s} value={s}>{s}</option>)}
          </SelectField>
        ) : (
          <InputField
            label="Departamento / Estado"
            id="state"
            value={data.state}
            onChange={(v) => updateField('state', v)}
            error={errors.state}
            required
          />
        )}
      </div>

      <div className="hcf-row">
        {useSelects && cities.length > 0 ? (
          <SelectField
            label="Ciudad / Municipio"
            id="city"
            value={data.city}
            onChange={handleCity}
            error={errors.city}
            required
            placeholder="Selecciona…"
          >
            {cities.map((c) => <option key={c} value={c}>{c}</option>)}
          </SelectField>
        ) : (
          <InputField
            label="Ciudad / Municipio"
            id="city"
            value={data.city}
            onChange={(v) => updateField('city', v)}
            error={errors.city}
            required
          />
        )}

        {useSelects && neighborhoods.length > 0 ? (
          <SelectField
            label="Barrio / Sector"
            id="neighborhood"
            value={data.neighborhood}
            onChange={(v) => updateField('neighborhood', v)}
            error={errors.neighborhood}
            placeholder="Selecciona…"
          >
            {neighborhoods.map((n) => <option key={n} value={n}>{n}</option>)}
          </SelectField>
        ) : (
          <InputField
            label="Barrio / Sector"
            id="neighborhood"
            value={data.neighborhood}
            onChange={(v) => updateField('neighborhood', v)}
            error={errors.neighborhood}
            placeholder="Opcional"
          />
        )}
      </div>

      <InputField
        label="Dirección"
        id="address"
        value={data.address}
        onChange={(v) => updateField('address', v)}
        error={errors.address}
        required
        placeholder="Calle 10 # 5-20"
        autoComplete="street-address"
      />

      <InputField
        label="Complemento de dirección"
        id="address_complement"
        value={data.address_complement}
        onChange={(v) => updateField('address_complement', v)}
        placeholder="Apto 301, Torre B, etc."
      />

      <CheckboxField
        label="Mostrar dirección exacta en el portal"
        id="show_exact_address"
        checked={data.show_exact_address}
        onChange={(v) => updateField('show_exact_address', v)}
        hint="Si lo desactivas, solo se mostrará el barrio o sector."
      />

      <div className="hcf-row">
        <InputField
          label="Latitud"
          id="latitude"
          type="text"
          inputMode="decimal"
          value={data.latitude}
          onChange={(v) => updateField('latitude', v)}
          error={errors.latitude}
          placeholder="4.710989"
        />
        <InputField
          label="Longitud"
          id="longitude"
          type="text"
          inputMode="decimal"
          value={data.longitude}
          onChange={(v) => updateField('longitude', v)}
          error={errors.longitude}
          placeholder="-74.072090"
        />
      </div>

      <InputField
        label="Referencia de ubicación"
        id="location_reference"
        value={data.location_reference}
        onChange={(v) => updateField('location_reference', v)}
        placeholder="Frente al parque, cerca al centro comercial…"
      />

      <InputField
        label="URL de Google Maps"
        id="maps_url"
        type="url"
        value={data.maps_url}
        onChange={(v) => updateField('maps_url', v)}
        placeholder="https://maps.google.com/..."
        hint="Pega el enlace de la ubicación en Google Maps."
      />
    </div>
  );
}
