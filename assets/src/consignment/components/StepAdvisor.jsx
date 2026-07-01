import { InputField } from './Field';
import FileUploader from './FileUploader';

export default function StepAdvisor({ data, updateField, errors, config, compact = false }) {
  const uploadCfg = config?.upload || {};

  return (
    <div className="hcf-step hcf-step--advisor">
      {!compact && (
        <>
          <h2 className="hcf-step__title">Datos del asesor</h2>
          <p className="hcf-step__desc">
            Como asesor, inmobiliaria o constructor, puedes registrar los datos del asesor responsable de este inmueble.
            Este paso es opcional.
          </p>
        </>
      )}

      <div className="hcf-row">
        <InputField
          label="Nombre del asesor"
          id="advisor_name"
          value={data.name}
          onChange={(v) => updateField('name', v)}
          error={errors.name}
          placeholder="Nombre completo"
          autoComplete="name"
        />
        <InputField
          label="Cargo / Rol"
          id="advisor_role"
          value={data.role}
          onChange={(v) => updateField('role', v)}
          placeholder="Asesor comercial, Director, etc."
        />
      </div>

      <div className="hcf-row">
        <InputField
          label="Correo del asesor"
          id="advisor_email"
          type="email"
          value={data.email}
          onChange={(v) => updateField('email', v)}
          error={errors.email}
          placeholder="asesor@inmobiliaria.com"
          autoComplete="email"
        />
        <InputField
          label="Teléfono del asesor"
          id="advisor_phone"
          type="tel"
          value={data.phone}
          onChange={(v) => updateField('phone', v)}
          error={errors.phone}
          placeholder="+57 300 000 0000"
          autoComplete="tel"
        />
      </div>

      <InputField
        label="ID externo / Código CRM"
        id="advisor_external_id"
        value={data.external_id}
        onChange={(v) => updateField('external_id', v)}
        placeholder="Identificador en su sistema CRM o inmobiliaria"
      />

      <div className="hcf-field">
        <label className="hcf-label">Foto del asesor</label>
        <FileUploader
          type="image"
          multiple={false}
          value={data.photo}
          onChange={(url) => updateField('photo', url)}
          accept="image/jpeg,image/png,image/webp"
          maxSizeMb={uploadCfg.max_size_mb || 5}
          label="Subir foto del asesor"
        />
      </div>
    </div>
  );
}
