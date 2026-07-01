import { InputField, SelectField, TextareaField } from './Field';

export default function StepPropertyDetails({ data, updateField, errors, config, compact = false }) {
  const types = Array.isArray(config?.property_types) ? config.property_types : [];
  const categories = Array.isArray(config?.categories) ? config.categories : [];
  const conditionsMap = config?.conditions && typeof config.conditions === 'object' ? config.conditions : {};
  const conditions = Object.entries(conditionsMap).map(([value, label]) => ({ value, label }));

  const currentYear = new Date().getFullYear();

  return (
    <div className="hcf-step hcf-step--property-details">
      {!compact && (
        <>
          <h2 className="hcf-step__title">Datos del inmueble</h2>
          <p className="hcf-step__desc">Describe las características principales del inmueble.</p>
        </>
      )}

      <div className="hcf-row">
        <SelectField
          label="Tipo de inmueble"
          id="property_type"
          value={data.property_type}
          onChange={(v) => updateField('property_type', v)}
          error={errors.property_type}
          required
          placeholder="Selecciona…"
        >
          {types.map((t) => (
            <option key={t.slug || t.name || t} value={t.name || t}>{t.name || t}</option>
          ))}
        </SelectField>

        <InputField
          label="Año de construcción"
          id="year_built"
          type="number"
          value={data.year_built}
          onChange={(v) => updateField('year_built', v)}
          error={errors.year_built}
          min={1800}
          max={currentYear + 10}
          placeholder={String(currentYear)}
        />
      </div>

      <div className="hcf-row">
        {categories.length > 0 && (
          <SelectField
            label="Categoría"
            id="category"
            value={data.category}
            onChange={(v) => updateField('category', v)}
            error={errors.category}
            placeholder="Selecciona…"
          >
            {categories.map((c) => (
              <option key={c.slug || c.name || c} value={c.name || c}>{c.name || c}</option>
            ))}
          </SelectField>
        )}

        {conditions.length > 0 && (
          <SelectField
            label="Estado del inmueble"
            id="condition"
            value={data.condition}
            onChange={(v) => updateField('condition', v)}
            placeholder="Selecciona…"
          >
            {conditions.map((c) => (
              <option key={c.value} value={c.value}>{c.label}</option>
            ))}
          </SelectField>
        )}
      </div>

      <p className="hcf-hint">
        El título del inmueble se generará automáticamente con la gestión, el tipo de inmueble y la ubicación.
      </p>

      <TextareaField
        label="Descripción completa"
        id="description"
        value={data.description}
        onChange={(v) => updateField('description', v)}
        rows={6}
        placeholder="Describe el inmueble, sus acabados, zona, accesos, puntos de interés…"
      />
    </div>
  );
}
