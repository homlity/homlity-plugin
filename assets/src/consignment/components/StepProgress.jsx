import { Fragment } from '@wordpress/element';

export default function StepProgress({ steps, currentStep, onStepClick }) {
  const current = steps.findIndex((s) => s.id === currentStep);

  return (
    <nav className="hcf-progress" aria-label="Progreso del formulario">
      <ol className="hcf-progress__list">
        {steps.map((step, idx) => {
          const done    = idx < current;
          const active  = idx === current;
          const canClick = idx < current;

          return (
            <li
              key={step.id}
              className={[
                'hcf-progress__item',
                done   ? 'hcf-progress__item--done'   : '',
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
                <span className="hcf-progress__label">{step.label}</span>
              </button>
              {idx < steps.length - 1 && (
                <span className="hcf-progress__connector" aria-hidden="true" />
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
