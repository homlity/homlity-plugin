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
import { fetchConfig, submitForm } from './api';
import {
  StepContactOperation,
  StepListingDetails,
  StepPropertySetup,
  StepAssetsReview,
} from './components/CombinedSteps';
import {
  validateStep,
  hasErrors,
} from './validators';

// ── Step definitions ──────────────────────────────────────────────────────

const ALL_STEPS = [
  {
    id: 'contact_operation',
    label: 'Contacto y negocio',
    description: 'Datos del consignante y condiciones comerciales del inmueble.',
    component: StepContactOperation,
  },
  {
    id: 'listing_details',
    label: 'Ubicación e inmueble',
    description: 'Ubicación, tipología y descripción general del inmueble.',
    component: StepListingDetails,
  },
  {
    id: 'property_setup',
    label: 'Distribución y extras',
    description: 'Áreas, distribución, características y datos complementarios.',
    component: StepPropertySetup,
  },
  {
    id: 'assets_review',
    label: 'Multimedia y envío',
    description: 'Archivos, revisión final y confirmación antes del envío.',
    component: StepAssetsReview,
  },
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

function getStorage() {
  try {
    if (typeof window !== 'undefined' && window.localStorage) {
      return window.localStorage;
    }
  } catch {}

  try {
    if (typeof window !== 'undefined' && window.sessionStorage) {
      return window.sessionStorage;
    }
  } catch {}

  return null;
}

function loadDraft(initial) {
  const storage = getStorage();
  if (!storage) {
    return { formData: initial, currentStep: 'contact_operation' };
  }

  try {
    const saved = storage.getItem(STORAGE_KEY);
    if (!saved) {
      return { formData: initial, currentStep: 'contact_operation' };
    }

    const parsed = JSON.parse(saved);

    if (parsed && typeof parsed === 'object' && parsed.formData) {
      return {
        formData: parsed.formData,
        currentStep: parsed.currentStep || 'contact_operation',
      };
    }

    return {
      formData: parsed,
      currentStep: 'contact_operation',
    };
  } catch {}

  return { formData: initial, currentStep: 'contact_operation' };
}

function saveDraft(formData, currentStep) {
  const storage = getStorage();
  if (!storage) {
    return;
  }

  try {
    storage.setItem(STORAGE_KEY, JSON.stringify({ formData, currentStep }));
  } catch {}
}

function clearDraft() {
  const storage = getStorage();
  if (!storage) {
    return;
  }

  try {
    storage.removeItem(STORAGE_KEY);
  } catch {}
}

function getStepFromErrorKey(key) {
  if (!key || key === '_server') {
    return null;
  }

  const normalized = String(key).trim();

  if (
    normalized.startsWith('contact.') ||
    normalized.startsWith('operation.') ||
    [
      'consignant_type',
      'name',
      'document',
      'phone',
      'whatsapp',
      'email',
      'data_consent',
      'authorization_consent',
      'operation',
      'sale_price',
      'sale_currency',
      'rent_price',
      'rent_currency',
      'admin_price',
      'admin_currency',
      'admin_included',
      'negotiable',
      'commercial_note',
    ].includes(normalized)
  ) {
    return 'contact_operation';
  }

  if (
    normalized.startsWith('location.') ||
    normalized.startsWith('property_details.') ||
    [
      'country',
      'state',
      'city',
      'neighborhood',
      'address',
      'address_complement',
      'show_exact_address',
      'latitude',
      'longitude',
      'location_reference',
      'maps_url',
      'title',
      'property_type',
      'category',
      'condition',
      'year_built',
      'description',
      'short_description',
      'code',
    ].includes(normalized)
  ) {
    return 'listing_details';
  }

  if (
    normalized.startsWith('areas.') ||
    normalized.startsWith('features.') ||
    normalized.startsWith('advisor.') ||
    [
      'area',
      'area_built',
      'area_private',
      'area_lot',
      'bedrooms',
      'bathrooms',
      'parking',
      'communal_parking',
      'visitor_parking',
      'motorcycle_parking',
      'car_parking',
      'floor',
      'levels',
      'stratum',
      'elevators',
      'selected',
      'custom',
      'advisor_name',
      'advisor_email',
      'advisor_phone',
      'advisor_photo',
      'advisor_role',
      'advisor_external_id',
    ].includes(normalized)
  ) {
    return 'property_setup';
  }

  if (
    normalized.startsWith('media.') ||
    normalized.startsWith('review.') ||
    [
      'gallery',
      'featured_image',
      'videos',
      'tour_360',
      'brochure',
      'photo_note',
      'truth_declaration',
      'contact_consent',
    ].includes(normalized)
  ) {
    return 'assets_review';
  }

  return null;
}

function getFirstErrorStep(errorBag = {}) {
  const firstKey = Object.keys(errorBag).find((key) => key !== '_server' && errorBag[key]);
  return getStepFromErrorKey(firstKey);
}

// ── App ───────────────────────────────────────────────────────────────────

export default function App({ hostElement = null, rootConfig = {} }) {

  const [config, setConfig]             = useState(null);
  const [isLoading, setIsLoading]       = useState(true);
  const [loadError, setLoadError]       = useState(null);
  const [formData, setFormData]         = useState(() => buildInitialData());
  const [currentStep, setCurrentStep]   = useState('contact_operation');
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
        const draft = loadDraft(initial);
        setFormData(draft.formData || initial);
        setCurrentStep(draft.currentStep || 'contact_operation');
        setIsLoading(false);
      })
      .catch((err) => {
        setLoadError(err.message);
        setIsLoading(false);
      });
  }, []);

  // Persist draft on every change
  useEffect(() => {
    if (!isLoading && formData) {
      saveDraft(formData, currentStep);
    }
  }, [formData, currentStep, isLoading]);

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

  const visibleSteps = useCallback(() => ALL_STEPS, []);

  const stepIndex = () => visibleSteps().findIndex((s) => s.id === currentStep);

  // ── Field update ──────────────────────────────────────────────────────

  const updateSectionField = useCallback((step, field, value) => {
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

  const updateSection = useCallback((step, values) => {
    setFormData((prev) => ({
      ...prev,
      [step]: { ...prev[step], ...values },
    }));
  }, []);

  const updateMetaField = useCallback((field, value) => {
    setFormData((prev) => ({
      ...prev,
      [field]: value,
    }));
  }, []);

  const validateWizardStep = useCallback((stepId) => {
    switch (stepId) {
      case 'contact_operation':
        return {
          ...validateStep('contact', formData.contact || {}, config),
          ...validateStep('operation', formData.operation || {}, config),
        };
      case 'listing_details':
        return {
          ...validateStep('location', formData.location || {}, config),
          ...validateStep('property_details', formData.property_details || {}, config),
        };
      case 'property_setup':
        return {
          ...validateStep('areas', formData.areas || {}, config),
        };
      case 'assets_review':
        return {
          ...validateStep('media', formData.media || {}, config),
          ...validateStep('review', formData.review || {}, config),
        };
      default:
        return {};
    }
  }, [config, formData]);

  // ── Navigation ────────────────────────────────────────────────────────

  const goNext = useCallback(() => {
    const stepErrors = validateWizardStep(currentStep);

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
    }
  }, [currentStep, validateWizardStep, visibleSteps]);

  const goPrev = useCallback(() => {
    setErrors({});
    const steps = visibleSteps();
    const idx   = steps.findIndex((s) => s.id === currentStep);
    if (idx > 0) {
      setCurrentStep(steps[idx - 1].id);
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
    }
  }, [currentStep, visibleSteps]);

  // ── Submit ────────────────────────────────────────────────────────────

  const handleSubmit = useCallback(async () => {
    const finalErrors = validateWizardStep('assets_review');
    if (hasErrors(finalErrors)) {
      setErrors(finalErrors);
      const errorStep = getFirstErrorStep(finalErrors);
      if (errorStep && errorStep !== currentStep) {
        setCurrentStep(errorStep);
      }
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
        const nextErrors = {
          ...(result.errors || {}),
          _server: result.message || 'No se pudo enviar el inmueble. Revisa los campos marcados e inténtalo de nuevo.',
        };
        setServerErrors(result.errors || {});
        setErrors(nextErrors);
        const errorStep = getFirstErrorStep(result.errors || {});
        if (errorStep && errorStep !== currentStep) {
          setCurrentStep(errorStep);
        }
      }
    } catch (err) {
      setErrors({ _server: err.message || config?.texts?.error || 'Error al enviar.' });
    } finally {
      setIsSubmitting(false);
    }
  }, [currentStep, formData, config, rootConfig, validateWizardStep]);

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
  const progressPercent = steps.length > 0
    ? Math.round(((idx + 1) / steps.length) * 100)
    : 0;
  const completedSteps = Math.max(0, idx);

  const stepProps = {
    updateSectionField,
    updateSection,
    errors,
    serverErrors,
    config,
    formData,
  };

  const StepComponent = step?.component;

  return (
    <div id="homlity-consignment-form-root" className="hcf-wrapper">
      {/* Header */}
      {(config?.texts?.form_title || config?.texts?.form_subtitle) && (
        <div className="hcf-header">
          {config.texts.form_title    && <h1 className="hcf-title">{config.texts.form_title}</h1>}
          {config.texts.form_subtitle && <p className="hcf-subtitle">{config.texts.form_subtitle}</p>}
        </div>
      )}

      <div className="hcf-progress-overview" role="status" aria-live="polite">
        <div className="hcf-progress-overview__meta">
          <div className="hcf-progress-overview__lead">
            <div className="hcf-progress-overview__dots" aria-hidden="true">
              {steps.map((item, itemIdx) => (
                <span
                  key={item.id}
                  className={[
                    'hcf-progress-overview__dot',
                    itemIdx < idx ? 'is-complete' : '',
                    itemIdx === idx ? 'is-active' : '',
                  ].join(' ').trim()}
                />
              ))}
            </div>
            <p className="hcf-progress-overview__eyebrow">Paso {idx + 1} / {steps.length}</p>
            <p className="hcf-progress-overview__text">{step?.label}</p>
          </div>
          <strong className="hcf-progress-overview__percent">{progressPercent}%</strong>
        </div>
        <div className="hcf-progress-overview__bar" aria-hidden="true">
          <span
            className="hcf-progress-overview__fill"
            style={{ width: `${progressPercent}%` }}
          />
        </div>
      </div>

      {/* Progress */}
      <StepProgress
        steps={steps}
        currentStep={currentStep}
        onStepClick={goToStep}
        completedSteps={completedSteps}
      />

      {/* Honeypot — hidden, must stay empty */}
      <div aria-hidden="true" style={{ display: 'none' }}>
        <input
          type="text"
          name="_hp"
          tabIndex={-1}
          autoComplete="off"
          value={formData._hp || ''}
          onChange={(e) => updateMetaField('_hp', e.target.value)}
        />
      </div>

      <div className="hcf-main">
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
    </div>
  );
}
