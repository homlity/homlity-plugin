/**
 * Generic field wrapper — label + input/select/textarea + error message.
 * Props:
 *   label       string
 *   id          string
 *   error       string|undefined
 *   required    bool
 *   hint        string|undefined
 *   children    React node (the actual input)
 */
export function Field({ label, id, error, required, hint, children }) {
  return (
    <div className={`hcf-field${error ? ' hcf-field--error' : ''}`}>
      {label && (
        <label className="hcf-label" htmlFor={id}>
          {label}
          {required && <span className="hcf-required" aria-hidden="true"> *</span>}
        </label>
      )}
      {children}
      {hint && !error && <p className="hcf-hint">{hint}</p>}
      {error && (
        <p className="hcf-error-msg" role="alert" id={`${id}-error`}>
          {error}
        </p>
      )}
    </div>
  );
}

/** Thin wrapper: <Field> + <input> */
export function InputField({ label, id, type = 'text', value, onChange, error, required, hint, placeholder, min, max, step, autoComplete, ...rest }) {
  return (
    <Field label={label} id={id} error={error} required={required} hint={hint}>
      <input
        id={id}
        type={type}
        className={`hcf-input${error ? ' hcf-input--error' : ''}`}
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={!!error}
        aria-describedby={error ? `${id}-error` : undefined}
        placeholder={placeholder}
        required={required}
        min={min}
        max={max}
        step={step}
        autoComplete={autoComplete}
        {...rest}
      />
    </Field>
  );
}

/** Thin wrapper: <Field> + <select> */
export function SelectField({ label, id, value, onChange, error, required, hint, children, placeholder }) {
  return (
    <Field label={label} id={id} error={error} required={required} hint={hint}>
      <select
        id={id}
        className={`hcf-select${error ? ' hcf-select--error' : ''}`}
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={!!error}
        aria-describedby={error ? `${id}-error` : undefined}
        required={required}
      >
        {placeholder && <option value="">{placeholder}</option>}
        {children}
      </select>
    </Field>
  );
}

/** Thin wrapper: <Field> + <textarea> */
export function TextareaField({ label, id, value, onChange, error, required, hint, placeholder, rows = 4 }) {
  return (
    <Field label={label} id={id} error={error} required={required} hint={hint}>
      <textarea
        id={id}
        className={`hcf-textarea${error ? ' hcf-textarea--error' : ''}`}
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={!!error}
        aria-describedby={error ? `${id}-error` : undefined}
        placeholder={placeholder}
        required={required}
        rows={rows}
      />
    </Field>
  );
}

/** Checkbox with inline label */
export function CheckboxField({ label, id, checked, onChange, error, required, hint }) {
  return (
    <div className={`hcf-field hcf-field--checkbox${error ? ' hcf-field--error' : ''}`}>
      <label className="hcf-checkbox-label" htmlFor={id}>
        <input
          id={id}
          type="checkbox"
          className="hcf-checkbox"
          checked={!!checked}
          onChange={(e) => onChange(e.target.checked)}
          aria-invalid={!!error}
          aria-describedby={error ? `${id}-error` : undefined}
          required={required}
        />
        <span className={`hcf-checkbox-pill${checked ? ' hcf-checkbox-pill--active' : ''}`}>
          <span className="hcf-checkbox-pill__text">
            {label}
            {required && <span className="hcf-required" aria-hidden="true"> *</span>}
          </span>
        </span>
      </label>
      {hint && !error && <p className="hcf-hint">{hint}</p>}
      {error && (
        <p className="hcf-error-msg" role="alert" id={`${id}-error`}>
          {error}
        </p>
      )}
    </div>
  );
}
