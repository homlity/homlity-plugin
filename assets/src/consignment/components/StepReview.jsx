import { CheckboxField } from './Field';

function SummaryRow({ label, value }) {
  if (!value && value !== 0) return null;
  return (
    <tr className="hcf-review__row">
      <td className="hcf-review__key">{label}</td>
      <td className="hcf-review__val">{String(value)}</td>
    </tr>
  );
}

export default function StepReview({ data, updateField, errors, config, formData }) {
  const texts = config?.texts || {};

  const contact = formData.contact || {};
  const op      = formData.operation || {};
  const loc     = formData.location || {};
  const det     = formData.property_details || {};
  const areas   = formData.areas || {};
  const media   = formData.media || {};

  const typeMap = {
    owner:      'Propietario',
    authorized: 'Persona autorizada',
    advisor:    'Asesor inmobiliario',
    agency:     'Inmobiliaria',
    builder:    'Constructor / Proyecto',
  };

  const locationStr = [loc.address, loc.neighborhood, loc.city, loc.state, loc.country]
    .filter(Boolean).join(', ');

  const priceStr = (() => {
    const parts = [];
    if (op.operation?.includes('Venta') && op.sale_price) {
      parts.push(`Venta: ${op.sale_price} ${op.sale_currency || ''}`);
    }
    if (op.operation?.includes('Arriendo') && op.rent_price) {
      parts.push(`Arriendo: ${op.rent_price} ${op.rent_currency || ''}`);
    }
    return parts.join(' · ') || '—';
  })();

  return (
    <div className="hcf-step hcf-step--review">
      <h2 className="hcf-step__title">Revisión y envío</h2>
      <p className="hcf-step__desc">
        Revisa el resumen de la información antes de enviar. Puedes regresar a cualquier paso para corregir datos.
      </p>

      {/* Summary */}
      <div className="hcf-review-card">
        <h3 className="hcf-review-card__title">Resumen</h3>
        <table className="hcf-review__table">
          <tbody>
            <SummaryRow label="Consignante"    value={`${contact.name || '—'} (${typeMap[contact.consignant_type] || contact.consignant_type || '—'})`} />
            <SummaryRow label="Contacto"       value={[contact.email, contact.phone || contact.whatsapp].filter(Boolean).join(' · ')} />
            <SummaryRow label="Inmueble"       value={det.title || '—'} />
            <SummaryRow label="Tipo"           value={det.property_type || '—'} />
            <SummaryRow label="Operación"      value={op.operation || '—'} />
            <SummaryRow label="Precio"         value={priceStr} />
            <SummaryRow label="Ubicación"      value={locationStr || '—'} />
            <SummaryRow label="Área total"     value={areas.area ? `${areas.area} m²` : null} />
            <SummaryRow label="Habitaciones"   value={areas.bedrooms} />
            <SummaryRow label="Baños"          value={areas.bathrooms} />
            <SummaryRow label="Fotos"          value={media.gallery?.length ? `${media.gallery.length} imagen(es)` : null} />
          </tbody>
        </table>
      </div>

      {/* Declarations */}
      <div className="hcf-review-declarations">
        <CheckboxField
          label={
            texts.truth_declaration_label ||
            'Declaro bajo la gravedad del juramento que la información suministrada es verídica y que tengo autorización para publicar este inmueble.'
          }
          id="truth_declaration"
          checked={data.truth_declaration}
          onChange={(v) => updateField('truth_declaration', v)}
          error={errors.truth_declaration}
          required
        />

        <CheckboxField
          label={
            texts.contact_consent_label ||
            'Acepto que el equipo de la plataforma se comunique conmigo para validar los datos del inmueble antes de su publicación.'
          }
          id="contact_consent"
          checked={data.contact_consent}
          onChange={(v) => updateField('contact_consent', v)}
          error={errors.contact_consent}
          required
        />
      </div>

      <div className="hcf-review-notice" role="note">
        <p>
          Tu inmueble quedará en estado <strong>pendiente de revisión</strong>. El equipo lo
          verificará y se pondrá en contacto contigo antes de publicarlo.
        </p>
      </div>
    </div>
  );
}
