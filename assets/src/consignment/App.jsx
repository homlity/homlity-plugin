/**
 * Homlity Consignment Form — root App component.
 *
 * State shape:
 *   currentStep  — string key of the active step
 *   formData     — object with one key per step
 *   errors       — object with fieldPath → errorMessage
 *   config       — data loaded from /consignment/config endpoint
 *   isLoading    — bool (loading config)
 *   isSubmitting — bool
 *   success      — bool
 *   successMsg   — string
 *   serverErrors — errors returned by the server after submit
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import StepProgress from './components/StepProgress';
import StepContact from './components/StepContact';
import StepOperation from './components/StepOperation';
import StepLocation from './components/StepLocation';
import StepPropertyDetails from './components/StepPropertyDetails';
import StepFeatures from './components/StepFeatures';
import StepMedia from './components/StepMedia';
import StepAreas from './components/StepAreas';
import StepAdvisor from './components/StepAdvisor';
import StepReview from './components/StepReview';
import { fetchConfig, submitForm } from './api';
import { validateStep, hasErrors } from './validators';

// ── Step definitions ──────────────────────────────────────────────────────

const ALL_STEPS = [
  { id: 'contact',          label: 'Contacto',       component: StepContact },
  { id: 'operation',        label: 'Operación',      component: StepOperation },
  { id: 'location',         label: 'Ubicación',      component: StepLocation },
  { id: 'property_details', label: 'Inmueble',       component: StepPropertyDetails },
  { id: 'areas',            label: 'Áreas',          component: StepAreas },
  { id: 'features',         label: 'Características',component: StepFeatures },
  { id: 'media',            label: 'Multimedia',     component: StepMedia },
  { id: 'advisor',          label: 'Asesor',         component: StepAdvisor, conditional: true },
  { id: 'review',           label: 'Revisión',       component: StepReview },
];

// ── Initial form data ─────────────────────────────────────────────────────

const buildInitialData = (config = {}) => ({
  contact: {
    consignant_type: '',
    name: '', document: '', phone: '', whatsapp: '', email: '',
    data_consent: false, authorization_consent: false,
  },
  operation: {
    operation: '',
    sale_price: '', sale_currency: config.default_currency || 'COP',
    rent_price: '', rent_currency: config.default_currency || 'COP',
    admin_price: '', admin_currency: config.default_currency || 'COP',
    admin_included: false, negotiable: false, commercial_note: '',
  },
  location: {
    country: config.default_country || '', state: '', city: '', neighborhood: '',
    address: '', address_complement: '', show_exact_address: true,
    latitude: '', longitude: '', location_reference: '', maps_url: '',
  },
  property_details: {
    title: '', property_type: '', category: '', condition: '',
    year_built: '', code: '', description: '', short_description: '',
  },
  areas: {
    area: '', area_built: '', area_private: '', area_lot: '',
    bedrooms: '', bathrooms: '', parking: '',
    communal_parking: false, visitor_parking: false,
    motorcycle_parking: false, car_parking: false,
    floor: '', levels: '', stratum: '', elevators: '',
  },
  features: { selected: [], custom: [] },
  media: {
    gallery: [], featured_image: '',
    videos: [], tour_360: [], brochure: '', photo_note: '',
  },
  advisor: { name: '', email: '', phone: '', photo: '', role: '', external_id: '' },
  review: { truth_declaration: false, contact_consent: false },
});

const STORAGE_KEY = 'homlity_consignment_draft';

function loadDraft(initial) {
  try {
    const saved = sessionStorage.getItem(STORAGE_KEY);
    if (saved) return JSON.parse(saved);
  } catch {}
  return initial;
}

function saveDraft(data) {
  try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch {}
}

function clearDraft() {
  try { sessionStorage.removeItem(STORAGE_KEY); } catch {}
}

// ── App ───────────────────────────────────────────────────────────────────

export default function App({ hostElement = null, rootConfig = {} }) {

  const [config, setConfig]             = useState(null);
  const [isLoading, setIsLoading]       = useState(true);
  const [loadError, setLoadError]       = useState(null);
  const [formData, setFormData]         = useState(() => buildInitialData());
  const [currentStep, setCurrentStep]   = useState('contact');
  const [errors, setErrors]             = useState({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [success, setSuccess]           = useState(false);
  const [successMsg, setSuccessMsg]     = useState('');
  const [serverErrors, setServerErrors] = useState({});

  // Load config
  useEffect(() => {
    fetchConfig()
      .then((cfg) => {
        setConfig(cfg);
        const initial = buildInitialData(cfg);
        setFormData(loadDraft(initial));
        setIsLoading(false);
      })
      .catch((err) => {
        setLoadError(err.message);
        setIsLoading(false);
      });
  }, []);

  // Persist draft on every change
  useEffect(() => {
    if (!isLoading && formData) saveDraft(formData);
  }, [formData, isLoading]);

  // Apply CSS variables from config styles
  useEffect(() => {
    if (!config?.styles) return;
    const root = hostElement;
    if (!root) return;
    const s = config.styles;
    root.style.setProperty('--hcf-primary',   rootConfig.primaryColor || s.primary_color   || '#2563eb');
    root.style.setProperty('--hcf-secondary',  s.secondary_color || '#1e40af');
    root.style.setProperty('--hcf-background', s.bg_color        || '#ffffff');
    root.style.setProperty('--hcf-text',       rootConfig.textColor || s.text_color || '#1f2937');
    root.style.setProperty('--hcf-radius',     (s.border_radius  || '8') + 'px');
  }, [config, hostElement, rootConfig.primaryColor, rootConfig.textColor]);

  // ── Steps visible for current consignant type ─────────────────────────

  const visibleSteps = useCallback(() => {
    const type = formData.contact?.consignant_type || '';
    const showAdvisor = ['advisor', 'agency', 'builder'].includes(type);
    return ALL_STEPS.filter((s) => !s.conditional || showAdvisor);
  }, [formData.contact?.consignant_type]);

  const stepIndex = () => visibleSteps().findIndex((s) => s.id === currentStep);
  const totalSteps = () => visibleSteps().length;

  // ── Field update ──────────────────────────────────────────────────────

  const updateField = useCallback((step, field, value) => {
    setFormData((prev) => ({
      ...prev,
      [step]: { ...prev[step], [field]: value },
    }));
    // Clear field error on change
    setErrors((prev) => {
      if (!prev[field]) return prev;
      const next = { ...prev };
      delete next[field];
      return next;
    });
  }, []);

  const updateStep = useCallback((step, values) => {
    setFormData((prev) => ({
      ...prev,
      [step]: { ...prev[step], ...values },
    }));
  }, []);

  // ── Navigation ────────────────────────────────────────────────────────

  const goNext = useCallback(() => {
    const stepErrors = validateStep(currentStep, formData[currentStep] || {}, config);

    // Filter out warnings (keys starting with _)
    const realErrors = Object.fromEntries(
      Object.entries(stepErrors).filter(([k]) => !k.startsWith('_'))
    );

    if (hasErrors(realErrors)) {
      setErrors(realErrors);
      return;
    }
    setErrors({});

    const steps = visibleSteps();
    const idx   = steps.findIndex((s) => s.id === currentStep);
    if (idx < steps.length - 1) {
      setCurrentStep(steps[idx + 1].id);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }, [currentStep, formData, config, visibleSteps]);

  const goPrev = useCallback(() => {
    setErrors({});
    const steps = visibleSteps();
    const idx   = steps.findIndex((s) => s.id === currentStep);
    if (idx > 0) {
      setCurrentStep(steps[idx - 1].id);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }, [currentStep, visibleSteps]);

  const goToStep = useCallback((stepId) => {
    // Only allow going back
    const steps  = visibleSteps();
    const target = steps.findIndex((s) => s.id === stepId);
    const current = steps.findIndex((s) => s.id === currentStep);
    if (target < current) {
      setErrors({});
      setCurrentStep(stepId);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }, [currentStep, visibleSteps]);

  // ── Submit ────────────────────────────────────────────────────────────

  const handleSubmit = useCallback(async () => {
    const reviewErrors = validateStep('review', formData.review || {}, config);
    if (hasErrors(reviewErrors)) {
      setErrors(reviewErrors);
      return;
    }

    setIsSubmitting(true);
    setErrors({});
    setServerErrors({});

    try {
      const provider = config?.provider || rootConfig.provider || 'public-consignment';
      const result   = await submitForm(formData, provider);

      if (result.ok) {
        clearDraft();
        setSuccess(true);
        setSuccessMsg(result.message || config?.texts?.success || '¡Enviado con éxito!');
        if (rootConfig.redirectUrl) {
          setTimeout(() => { window.location.href = rootConfig.redirectUrl; }, 2500);
        }
      } else {
        setServerErrors(result.errors || {});
        if (result.message) setErrors({ _server: result.message });
      }
    } catch (err) {
      setErrors({ _server: err.message || config?.texts?.error || 'Error al enviar.' });
    } finally {
      setIsSubmitting(false);
    }
  }, [formData, config, rootConfig]);

  // ── Render ────────────────────────────────────────────────────────────

  if (isLoading) {
    return (
      <div className="hcf-loading" role="status" aria-live="polite">
        <div className="hcf-spinner" aria-hidden="true"></div>
        <p>Cargando formulario…</p>
      </div>
    );
  }

  if (loadError) {
    return (
      <div className="hcf-error-state" role="alert">
        <p>No se pudo cargar el formulario: {loadError}</p>
        <button className="hcf-btn hcf-btn--primary" onClick={() => window.location.reload()}>Reintentar</button>
      </div>
    );
  }

  if (success) {
    return (
      <div className="hcf-success-state" role="status" aria-live="polite">
        <div className="hcf-success-icon" aria-hidden="true">✓</div>
        <h2 className="hcf-success-title">¡Inmueble enviado!</h2>
        <p className="hcf-success-message">{successMsg}</p>
      </div>
    );
  }

  const steps   = visibleSteps();
  const idx     = stepIndex();
  const step    = steps[idx];
  const isFirst = idx === 0;
  const isLast  = idx === steps.length - 1;

  const stepProps = {
    data:       formData[currentStep] || {},
    updateField:(field, val) => updateField(currentStep, field, val),
    updateStep: (vals) => updateStep(currentStep, vals),
    errors,
    serverErrors,
    config,
    formData,
  };

  const StepComponent = step?.component;

  return (
    <div className="hcf-wrapper">
      {/* Header */}
      {(config?.texts?.form_title || config?.texts?.form_subtitle) && (
        <div className="hcf-header">
          {config.texts.form_title    && <h1 className="hcf-title">{config.texts.form_title}</h1>}
          {config.texts.form_subtitle && <p className="hcf-subtitle">{config.texts.form_subtitle}</p>}
        </div>
      )}

      {/* Progress */}
      <StepProgress
        steps={steps}
        currentStep={currentStep}
        onStepClick={goToStep}
      />

      {/* Honeypot — hidden, must stay empty */}
      <div aria-hidden="true" style={{ display: 'none' }}>
        <input
          type="text"
          name="_hp"
          tabIndex={-1}
          autoComplete="off"
          value={formData._hp || ''}
          onChange={(e) => updateField('_hp', '_hp', e.target.value)}
        />
      </div>

      {/* Step card */}
      <div className="hcf-step-card" role="region" aria-label={step?.label}>
        {errors._server && (
          <div className="hcf-alert hcf-alert--error" role="alert">{errors._server}</div>
        )}

        {StepComponent ? (
          <StepComponent {...stepProps} />
        ) : (
          <div className="hcf-step-placeholder">
            <p>Paso no disponible.</p>
          </div>
        )}
      </div>

      {/* Navigation */}
      <div className="hcf-navigation">
        {!isFirst && (
          <button
            type="button"
            className="hcf-btn hcf-btn--secondary"
            onClick={goPrev}
            disabled={isSubmitting}
          >
            {config?.texts?.btn_prev || 'Anterior'}
          </button>
        )}
        <span className="hcf-nav-spacer" />
        {!isLast ? (
          <button
            type="button"
            className="hcf-btn hcf-btn--primary"
            onClick={goNext}
          >
            {config?.texts?.btn_next || 'Siguiente'}
          </button>
        ) : (
          <button
            type="button"
            className="hcf-btn hcf-btn--submit"
            onClick={handleSubmit}
            disabled={isSubmitting}
            aria-busy={isSubmitting}
          >
            {isSubmitting ? 'Enviando…' : (config?.texts?.btn_submit || 'Enviar inmueble para revisión')}
          </button>
        )}
      </div>
    </div>
  );
}
