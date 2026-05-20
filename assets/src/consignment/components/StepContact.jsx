import { InputField, SelectField, CheckboxField } from './Field';

const CONSIGNANT_TYPES = [
  { value: 'owner',      label: 'Propietario' },
  { value: 'authorized', label: 'Persona autorizada por el propietario' },
  { value: 'advisor',    label: 'Asesor inmobiliario independiente' },
  { value: 'agency',     label: 'Inmobiliaria' },
  { value: 'builder',    label: 'Constructor / Proyecto' },
];

export default function StepContact({ data, updateField, errors, config }) {
  const texts = config?.texts || {};

  return (
    <div className="hcf-step hcf-step--contact">
      <h2 className="hcf-step__title">Datos de contacto</h2>
      <p className="hcf-step__desc">
        Cuéntanos quién eres para que podamos validar y publicar tu inmueble.
      </p>

      <SelectField
        label="Tipo de consignante"
        id="consignant_type"
        value={data.consignant_type}
        onChange={(v) => updateField('consignant_type', v)}
        error={errors.consignant_type}
        required
        placeholder="Selecciona una opción…"
      >
        {CONSIGNANT_TYPES.map((t) => (
          <option key={t.value} value={t.value}>{t.label}</option>
        ))}
      </SelectField>

      <InputField
        label="Nombre completo"
        id="name"
        value={data.name}
        onChange={(v) => updateField('name', v)}
        error={errors.name}
        required
        autoComplete="name"
        placeholder="Tu nombre completo"
      />

      <InputField
        label="Número de identificación"
        id="document"
        value={data.document}
        onChange={(v) => updateField('document', v)}
        error={errors.document}
        placeholder="CC / NIT / Pasaporte"
      />

      <div className="hcf-row">
        <InputField
          label="Teléfono"
          id="phone"
          type="tel"
          value={data.phone}
          onChange={(v) => updateField('phone', v)}
          error={errors.phone}
          autoComplete="tel"
          placeholder="+57 300 000 0000"
          hint="Al menos teléfono o WhatsApp es obligatorio."
        />
        <InputField
          label="WhatsApp"
          id="whatsapp"
          type="tel"
          value={data.whatsapp}
          onChange={(v) => updateField('whatsapp', v)}
          error={errors.whatsapp}
          autoComplete="tel"
          placeholder="+57 300 000 0000"
        />
      </div>

      <InputField
        label="Correo electrónico"
        id="email"
        type="email"
        value={data.email}
        onChange={(v) => updateField('email', v)}
        error={errors.email}
        required
        autoComplete="email"
        placeholder="tu@correo.com"
      />

      <div className="hcf-consents">
        <CheckboxField
          label={
            texts.data_consent_label ||
            'Acepto la política de tratamiento de datos personales.'
          }
          id="data_consent"
          checked={data.data_consent}
          onChange={(v) => updateField('data_consent', v)}
          error={errors.data_consent}
          required
        />

        <CheckboxField
          label={
            texts.authorization_consent_label ||
            'Confirmo que tengo autorización del propietario para consignar este inmueble.'
          }
          id="authorization_consent"
          checked={data.authorization_consent}
          onChange={(v) => updateField('authorization_consent', v)}
          error={errors.authorization_consent}
          required
        />
      </div>
    </div>
  );
}
