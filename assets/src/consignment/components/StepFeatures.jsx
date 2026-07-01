import { useState } from '@wordpress/element';

function normalizeFeatureLabel(item) {
  if (typeof item === 'string') {
    return item.trim();
  }

  if (item && typeof item === 'object') {
    return String(item.name || item.label || item.title || '').trim();
  }

  return '';
}

function normalizeFeatureValueType(item) {
  if (!item || typeof item !== 'object') {
    return 'boolean';
  }

  return String(item.valueType || item.value_type || 'boolean').toLowerCase();
}

function normalizeItems(items = []) {
  return items
    .map((item) => ({
      label: normalizeFeatureLabel(item),
      valueType: normalizeFeatureValueType(item),
    }))
    .filter((item) => item.label);
}

function normalizeGroups(config) {
  const source =
    config?.terms?.features_groups ||
    config?.features_groups ||
    config?.features ||
    [];

  if (!Array.isArray(source) || source.length === 0) {
    return [];
  }

  const looksGrouped = source.some((item) => item && typeof item === 'object' && (item.features || item.items));

  if (looksGrouped) {
    return source
      .map((group) => ({
        label: String(group.label || group.name || 'Características').trim(),
        items: normalizeItems(group.features || group.items || []),
      }))
      .filter((group) => group.items.length > 0);
  }

  return [
    {
      label: 'Características disponibles',
      items: normalizeItems(source),
    },
  ];
}

export default function StepFeatures({ data, updateField, config, compact = false }) {
  const [customInput, setCustomInput] = useState('');
  const groups = normalizeGroups(config);

  const selected = Array.isArray(data.selected) ? data.selected : [];
  const custom = Array.isArray(data.custom) ? data.custom : [];

  const toggle = (tag) => {
    const next = selected.includes(tag)
      ? selected.filter((item) => item !== tag)
      : [...selected, tag];
    updateField('selected', next);
  };

  const addCustom = () => {
    const trimmed = customInput.trim();
    if (!trimmed || custom.includes(trimmed)) {
      return;
    }

    updateField('custom', [...custom, trimmed]);
    setCustomInput('');
  };

  const removeCustom = (tag) => {
    updateField('custom', custom.filter((item) => item !== tag));
  };

  const handleKeyDown = (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      addCustom();
    }
  };

  return (
    <div className="hcf-step hcf-step--features">
      {!compact && (
        <>
          <h2 className="hcf-step__title">Características</h2>
          <p className="hcf-step__desc">
            Selecciona las características del inmueble. Las opciones booleanas se activan con un clic.
          </p>
        </>
      )}

      {groups.length > 0 ? (
        <div className="hcf-features-groups">
          {groups.map((group) => {
            const booleanItems = group.items.filter((item) => item.valueType === 'boolean');

            if (booleanItems.length === 0) {
              return null;
            }

            return (
              <section key={group.label} className="hcf-features-group">
                <h3 className="hcf-features-group__title">{group.label}</h3>
                <div className="hcf-chip-list" role="group" aria-label={group.label}>
                  {booleanItems.map((item) => (
                    <button
                      key={item.label}
                      type="button"
                      className={`hcf-chip${selected.includes(item.label) ? ' hcf-chip--active' : ''}`}
                      onClick={() => toggle(item.label)}
                      aria-pressed={selected.includes(item.label)}
                    >
                      {item.label}
                    </button>
                  ))}
                </div>
              </section>
            );
          })}
        </div>
      ) : (
        <p className="hcf-note">No hay características booleanas configuradas. Puedes agregar las tuyas manualmente.</p>
      )}

      <div className="hcf-features-custom">
        <h3 className="hcf-features-group__title">Características adicionales</h3>
        <div className="hcf-row hcf-row--inline">
          <input
            type="text"
            className="hcf-input"
            value={customInput}
            onChange={(event) => setCustomInput(event.target.value)}
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
