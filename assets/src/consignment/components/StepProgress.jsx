export default function StepProgress({ steps, currentStep, onStepClick, completedSteps = 0 }) {
  const current = steps.findIndex((s) => s.id === currentStep);
  const progressPercent = steps.length > 0
    ? Math.round((completedSteps / steps.length) * 100)
    : 0;

  return (
    <nav className="hcf-progress" aria-label="Progreso del formulario">
      <div className="hcf-progress__panel">
        <div className="hcf-progress__panel-head">
          <h3 className="hcf-progress__panel-title">Resumen del inmueble</h3>
        </div>

        <ol className="hcf-progress__list">
          {steps.map((step, idx) => {
            const done = idx < current;
            const active = idx === current;
            const canClick = idx < current;

            return (
              <li
                key={step.id}
                className={[
                  'hcf-progress__item',
                  done ? 'hcf-progress__item--done' : '',
                  active ? 'hcf-progress__item--active' : '',
                ].join(' ').trim()}
              >
                <button
                  type="button"
                  className="hcf-progress__btn"
                  onClick={() => canClick && onStepClick(step.id)}
                  disabled={!canClick}
                  aria-current={active ? 'step' : undefined}
                  aria-label={`Paso ${idx + 1}: ${step.label}${done ? ' (completado)' : ''}`}
                >
                  <span className="hcf-progress__circle">
                    {done ? '✓' : idx + 1}
                  </span>
                  <span className="hcf-progress__content">
                    <span className="hcf-progress__label">{step.label}</span>
                    {step.description && (
                      <span className="hcf-progress__desc">{step.description}</span>
                    )}
                  </span>
                </button>
              </li>
            );
          })}
        </ol>

        <div className="hcf-progress__panel-foot">
          <div className="hcf-progress__panel-meta">
            <span>Completado</span>
            <strong>{completedSteps} / {steps.length}</strong>
          </div>
          <div className="hcf-progress__panel-bar" aria-hidden="true">
            <span
              className="hcf-progress__panel-fill"
              style={{ width: `${progressPercent}%` }}
            />
          </div>
        </div>
      </div>
    </nav>
  );
}
