import { SelectField, InputField, CheckboxField, TextareaField } from './Field';

const CURRENCIES = ['COP', 'CRC', 'USD', 'EUR'];

function normalizeOperations(config) {
  const configured = Array.isArray(config?.operations) ? config.operations : [];
  const base = configured.length > 0 ? configured : ['Venta', 'Arriendo'];
  const values = base
    .map((item) => {
      if (typeof item === 'string') {
        return item.trim();
      }

      if (item && typeof item === 'object') {
        return String(item.name || item.label || item.value || '').trim();
      }

      return '';
    })
    .filter(Boolean);

  const unique = Array.from(new Set(values));

  if (unique.includes('Venta') && unique.includes('Arriendo') && !unique.includes('Venta y Arriendo')) {
    unique.push('Venta y Arriendo');
  }

  return unique.map((value) => ({ value, label: value }));
}

function PriceRow({ label, idAmount, idCurrency, valueAmount, valueCurrency, onChangeAmount, onChangeCurrency, error }) {
  return (
    <div className="hcf-row hcf-row--price">
      <InputField
        label={label}
        id={idAmount}
        type="text"
        inputMode="numeric"
        value={valueAmount}
        onChange={onChangeAmount}
        error={error}
        required
        placeholder="Ej: 250.000.000"
      />
      <SelectField
        label="Moneda"
        id={idCurrency}
        value={valueCurrency}
        onChange={onChangeCurrency}
      >
        {CURRENCIES.map((c) => <option key={c} value={c}>{c}</option>)}
      </SelectField>
    </div>
  );
}

export default function StepOperation({ data, updateField, errors, config, compact = false }) {
  const op = data.operation || '';
  const showSale  = op.includes('Venta');
  const showRent  = op.includes('Arriendo');
  const operations = normalizeOperations(config);

  return (
    <div className="hcf-step hcf-step--operation">
      {!compact && (
        <>
          <h2 className="hcf-step__title">Tipo de operación</h2>
          <p className="hcf-step__desc">Define cómo quieres ofrecer tu inmueble.</p>
        </>
      )}

      <SelectField
        label="Operación"
        id="operation"
        value={data.operation}
        onChange={(v) => updateField('operation', v)}
        error={errors.operation}
        required
        placeholder="Selecciona…"
      >
        {operations.map((o) => (
          <option key={o.value} value={o.value}>{o.label}</option>
        ))}
      </SelectField>

      {showSale && (
        <PriceRow
          label="Precio de venta"
          idAmount="sale_price"
          idCurrency="sale_currency"
          valueAmount={data.sale_price}
          valueCurrency={data.sale_currency}
          onChangeAmount={(v) => updateField('sale_price', v)}
          onChangeCurrency={(v) => updateField('sale_currency', v)}
          error={errors.sale_price}
        />
      )}

      {showRent && (
        <>
          <PriceRow
            label="Canon de arriendo mensual"
            idAmount="rent_price"
            idCurrency="rent_currency"
            valueAmount={data.rent_price}
            valueCurrency={data.rent_currency}
            onChangeAmount={(v) => updateField('rent_price', v)}
            onChangeCurrency={(v) => updateField('rent_currency', v)}
            error={errors.rent_price}
          />

          <PriceRow
            label="Administración (mensual)"
            idAmount="admin_price"
            idCurrency="admin_currency"
            valueAmount={data.admin_price}
            valueCurrency={data.admin_currency}
            onChangeAmount={(v) => updateField('admin_price', v)}
            onChangeCurrency={(v) => updateField('admin_currency', v)}
            error={errors.admin_price}
          />

          <CheckboxField
            label="Administración incluida en el canon"
            id="admin_included"
            checked={data.admin_included}
            onChange={(v) => updateField('admin_included', v)}
          />
        </>
      )}

      <CheckboxField
        label="Precio negociable"
        id="negotiable"
        checked={data.negotiable}
        onChange={(v) => updateField('negotiable', v)}
      />

      <TextareaField
        label="Nota comercial (opcional)"
        id="commercial_note"
        value={data.commercial_note}
        onChange={(v) => updateField('commercial_note', v)}
        placeholder="Información adicional sobre condiciones de precio, financiación, etc."
        rows={3}
      />
    </div>
  );
}
