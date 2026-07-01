import StepContact from './StepContact';
import StepOperation from './StepOperation';
import StepLocation from './StepLocation';
import StepPropertyDetails from './StepPropertyDetails';
import StepAreas from './StepAreas';
import StepFeatures from './StepFeatures';
import StepMedia from './StepMedia';
import StepAdvisor from './StepAdvisor';
import StepReview from './StepReview';

function StepSection({ children, className = '' }) {
  return <section className={`hcf-step-section ${className}`.trim()}>{children}</section>;
}

function SectionHeader({ eyebrow, title, description }) {
  return (
    <header className="hcf-section-header">
      {eyebrow && <p className="hcf-section-header__eyebrow">{eyebrow}</p>}
      <h3 className="hcf-section-header__title">{title}</h3>
      {description && <p className="hcf-section-header__desc">{description}</p>}
    </header>
  );
}

export function StepContactOperation({ formData, updateSectionField, errors, config }) {
  return (
    <div className="hcf-step-group hcf-step-group--contact-operation">
      <StepSection>
        <SectionHeader
          eyebrow="Paso 1"
          title="Quién consigna"
          description="Datos de la persona o empresa responsable del inmueble."
        />
        <StepContact
          data={formData.contact || {}}
          updateField={(field, value) => updateSectionField('contact', field, value)}
          errors={errors}
          config={config}
          compact
        />
      </StepSection>

      <StepSection>
        <SectionHeader
          title="Cómo se va a publicar"
          description="Define operación, precios y condiciones comerciales."
        />
        <StepOperation
          data={formData.operation || {}}
          updateField={(field, value) => updateSectionField('operation', field, value)}
          errors={errors}
          compact
        />
      </StepSection>
    </div>
  );
}

export function StepListingDetails({ formData, updateSectionField, updateSection, errors, config }) {
  return (
    <div className="hcf-step-group hcf-step-group--listing-details">
      <StepSection>
        <SectionHeader
          eyebrow="Paso 2"
          title="Dónde está ubicado"
          description="Ubicación general, dirección y referencia del inmueble."
        />
        <StepLocation
          data={formData.location || {}}
          updateField={(field, value) => updateSectionField('location', field, value)}
          updateStep={(values) => updateSection('location', values)}
          errors={errors}
          config={config}
          compact
        />
      </StepSection>

      <StepSection>
        <SectionHeader
          title="Cómo es el inmueble"
          description="Título, tipo, estado y descripción comercial."
        />
        <StepPropertyDetails
          data={formData.property_details || {}}
          updateField={(field, value) => updateSectionField('property_details', field, value)}
          errors={errors}
          config={config}
          compact
        />
      </StepSection>
    </div>
  );
}

export function StepPropertySetup({ formData, updateSectionField, errors, config }) {
  const type = formData.contact?.consignant_type || '';
  const showAdvisor = ['advisor', 'agency', 'builder'].includes(type);

  return (
    <div className="hcf-step-group hcf-step-group--property-setup">
      <StepSection>
        <SectionHeader
          eyebrow="Paso 3"
          title="Distribución principal"
          description="Áreas, habitaciones, baños, parqueaderos y datos del edificio."
        />
        <StepAreas
          data={formData.areas || {}}
          updateField={(field, value) => updateSectionField('areas', field, value)}
          errors={errors}
          compact
        />
      </StepSection>

      <StepSection>
        <SectionHeader
          title="Características adicionales"
          description="Marca los atributos del inmueble y agrega extras si hace falta."
        />
        <StepFeatures
          data={formData.features || {}}
          updateField={(field, value) => updateSectionField('features', field, value)}
          config={config}
          compact
        />
      </StepSection>

      {showAdvisor && (
        <StepSection>
          <SectionHeader
            title="Datos del asesor"
            description="Opcional para asesores, constructoras o inmobiliarias."
          />
          <StepAdvisor
            data={formData.advisor || {}}
            updateField={(field, value) => updateSectionField('advisor', field, value)}
            errors={errors}
            config={config}
            compact
          />
        </StepSection>
      )}
    </div>
  );
}

export function StepAssetsReview({ formData, updateSectionField, errors, serverErrors, config }) {
  return (
    <div className="hcf-step-group hcf-step-group--assets-review">
      <StepSection>
        <SectionHeader
          eyebrow="Paso 4"
          title="Fotos y material comercial"
          description="Carga imágenes, brochure, videos y recorridos virtuales."
        />
        <StepMedia
          data={formData.media || {}}
          updateField={(field, value) => updateSectionField('media', field, value)}
          errors={errors}
          config={config}
          compact
        />
      </StepSection>

      <StepSection className="hcf-step-section--review">
        <SectionHeader
          title="Revisión final"
          description="Verifica el resumen y confirma autorizaciones antes de enviar."
        />
        <StepReview
          data={formData.review || {}}
          updateField={(field, value) => updateSectionField('review', field, value)}
          errors={errors}
          serverErrors={serverErrors}
          config={config}
          formData={formData}
          compact
        />
      </StepSection>
    </div>
  );
}
