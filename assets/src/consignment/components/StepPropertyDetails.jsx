import { InputField, SelectField, TextareaField } from './Field';

export default function StepPropertyDetails({ data, updateField, errors, config }) {
  const terms     = config?.terms || {};
  const types     = terms.property_type     || [];
  const categories = terms.property_category || [];
  const conditions = terms.property_condition || [];

  const currentYear = new Date().getFullYear();

  return (
    <div className="hcf-step hcf-step--property-details">
      <h2 className="hcf-step__title">Datos del inmueble</h2>
      <p className="hcf-step__desc">Describe las características principales del inmueble.</p>

      <InputField
        label="Título del inmueble"
        id="title"
        value={data.title}
        onChange={(v) => updateField('title', v)}
        error={errors.title}
        required
        placeholder="Ej: Apartamento moderno con vista a la montaña"
        hint="Máximo 200 caracteres. Sé descriptivo y conciso."
      />

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
            <option key={t.slug || t.name} value={t.name || t}>{t.name || t}</option>
          ))}
        </SelectField>

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
              <option key={c.slug || c.name} value={c.name || c}>{c.name || c}</option>
            ))}
          </SelectField>
        )}
      </div>

      <div className="hcf-row">
        {conditions.length > 0 && (
          <SelectField
            label="Estado del inmueble"
            id="condition"
            value={data.condition}
            onChange={(v) => updateField('condition', v)}
            placeholder="Selecciona…"
          >
            {conditions.map((c) => (
              <option key={c.slug || c.name} value={c.name || c}>{c.name || c}</option>
            ))}
          </SelectField>
        )}

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

      <InputField
        label="Código interno (opcional)"
        id="code"
        value={data.code}
        onChange={(v) => updateField('code', v)}
        placeholder="Ref. de tu sistema CRM o inmobiliaria"
      />

      <TextareaField
        label="Descripción completa"
        id="description"
        value={data.description}
        onChange={(v) => updateField('description', v)}
        rows={6}
        placeholder="Describe el inmueble, sus acabados, zona, accesos, puntos de interés…"
      />

      <TextareaField
        label="Descripción corta"
        id="short_description"
        value={data.short_description}
        onChange={(v) => updateField('short_description', v)}
        rows={2}
        placeholder="Resumen en 1-2 oraciones para listados y tarjetas."
      />
    </div>
  );
}
