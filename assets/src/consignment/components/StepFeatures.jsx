import { useState } from '@wordpress/element';
import { InputField } from './Field';

export default function StepFeatures({ data, updateField, config }) {
  const [customInput, setCustomInput] = useState('');

  const groups        = config?.terms?.features_groups || [];
  const allGlobalTags = groups.flatMap((g) => (g.features || g.items || []));

  const selected = data.selected || [];
  const custom   = data.custom   || [];

  const toggle = (tag) => {
    const next = selected.includes(tag)
      ? selected.filter((t) => t !== tag)
      : [...selected, tag];
    updateField('selected', next);
  };

  const addCustom = () => {
    const trimmed = customInput.trim();
    if (!trimmed || custom.includes(trimmed)) return;
    updateField('custom', [...custom, trimmed]);
    setCustomInput('');
  };

  const removeCustom = (tag) => {
    updateField('custom', custom.filter((t) => t !== tag));
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter') { e.preventDefault(); addCustom(); }
  };

  return (
    <div className="hcf-step hcf-step--features">
      <h2 className="hcf-step__title">Características</h2>
      <p className="hcf-step__desc">Selecciona las características del inmueble. Puedes agregar características adicionales.</p>

      {groups.length > 0 ? (
        groups.map((group) => (
          <div key={group.label || group.name} className="hcf-features-group">
            <h3 className="hcf-features-group__title">{group.label || group.name}</h3>
            <div className="hcf-chip-list" role="group" aria-label={group.label || group.name}>
              {(group.features || group.items || []).map((tag) => (
                <button
                  key={tag}
                  type="button"
                  className={`hcf-chip${selected.includes(tag) ? ' hcf-chip--active' : ''}`}
                  onClick={() => toggle(tag)}
                  aria-pressed={selected.includes(tag)}
                >
                  {tag}
                </button>
              ))}
            </div>
          </div>
        ))
      ) : allGlobalTags.length === 0 ? (
        <p className="hcf-note">No hay características configuradas. Agrega las tuyas a continuación.</p>
      ) : (
        <div className="hcf-chip-list" role="group" aria-label="Características disponibles">
          {allGlobalTags.map((tag) => (
            <button
              key={tag}
              type="button"
              className={`hcf-chip${selected.includes(tag) ? ' hcf-chip--active' : ''}`}
              onClick={() => toggle(tag)}
              aria-pressed={selected.includes(tag)}
            >
              {tag}
            </button>
          ))}
        </div>
      )}

      {/* Custom features */}
      <div className="hcf-features-custom">
        <h3 className="hcf-features-group__title">Características adicionales</h3>
        <div className="hcf-row hcf-row--inline">
          <input
            type="text"
            className="hcf-input"
            value={customInput}
            onChange={(e) => setCustomInput(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Escribe y presiona Enter o Agregar"
            aria-label="Agregar característica personalizada"
            maxLength={80}
          />
          <button type="button" className="hcf-btn hcf-btn--secondary hcf-btn--sm" onClick={addCustom}>
            Agregar
          </button>
        </div>
        {custom.length > 0 && (
          <div className="hcf-chip-list hcf-chip-list--custom">
            {custom.map((tag) => (
              <span key={tag} className="hcf-chip hcf-chip--custom hcf-chip--active">
                {tag}
                <button
                  type="button"
                  className="hcf-chip__remove"
                  onClick={() => removeCustom(tag)}
                  aria-label={`Eliminar ${tag}`}
                >
                  ×
                </button>
              </span>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
