import { InputField, SelectField, CheckboxField } from './Field';

const STRATA = ['1', '2', '3', '4', '5', '6'];

export default function StepAreas({ data, updateField, errors, compact = false }) {
  return (
    <div className="hcf-step hcf-step--areas">
      {!compact && (
        <>
          <h2 className="hcf-step__title">Áreas y distribución</h2>
          <p className="hcf-step__desc">Indica las áreas y distribución del inmueble.</p>
        </>
      )}

      {/* Areas */}
      <fieldset className="hcf-fieldset">
        <legend className="hcf-fieldset__legend">Áreas (m²)</legend>
        <div className="hcf-row">
          <InputField
            label="Área total"
            id="area"
            type="number"
            value={data.area}
            onChange={(v) => updateField('area', v)}
            error={errors.area}
            required
            min={1}
            step="0.01"
            placeholder="0"
          />
          <InputField
            label="Área construida"
            id="area_built"
            type="number"
            value={data.area_built}
            onChange={(v) => updateField('area_built', v)}
            min={0}
            step="0.01"
            placeholder="0"
          />
        </div>
        <div className="hcf-row">
          <InputField
            label="Área privada"
            id="area_private"
            type="number"
            value={data.area_private}
            onChange={(v) => updateField('area_private', v)}
            min={0}
            step="0.01"
            placeholder="0"
          />
          <InputField
            label="Área de lote"
            id="area_lot"
            type="number"
            value={data.area_lot}
            onChange={(v) => updateField('area_lot', v)}
            min={0}
            step="0.01"
            placeholder="0"
          />
        </div>
      </fieldset>

      {/* Rooms */}
      <fieldset className="hcf-fieldset">
        <legend className="hcf-fieldset__legend">Habitaciones</legend>
        <div className="hcf-row">
          <InputField
            label="Habitaciones"
            id="bedrooms"
            type="number"
            value={data.bedrooms}
            onChange={(v) => updateField('bedrooms', v)}
            error={errors.bedrooms}
            min={0}
            placeholder="0"
          />
          <InputField
            label="Baños"
            id="bathrooms"
            type="number"
            value={data.bathrooms}
            onChange={(v) => updateField('bathrooms', v)}
            error={errors.bathrooms}
            min={0}
            placeholder="0"
          />
          <InputField
            label="Garajes / Parqueaderos"
            id="parking"
            type="number"
            value={data.parking}
            onChange={(v) => updateField('parking', v)}
            error={errors.parking}
            min={0}
            placeholder="0"
          />
        </div>

        <div className="hcf-checkbox-group">
          <CheckboxField
            label="Parqueadero comunal"
            id="communal_parking"
            checked={data.communal_parking}
            onChange={(v) => updateField('communal_parking', v)}
          />
          <CheckboxField
            label="Parqueadero de visitantes"
            id="visitor_parking"
            checked={data.visitor_parking}
            onChange={(v) => updateField('visitor_parking', v)}
          />
          <CheckboxField
            label="Parqueadero de motos"
            id="motorcycle_parking"
            checked={data.motorcycle_parking}
            onChange={(v) => updateField('motorcycle_parking', v)}
          />
          <CheckboxField
            label="Parqueadero de carros"
            id="car_parking"
            checked={data.car_parking}
            onChange={(v) => updateField('car_parking', v)}
          />
        </div>
      </fieldset>

      {/* Building */}
      <fieldset className="hcf-fieldset">
        <legend className="hcf-fieldset__legend">Edificio / Proyecto</legend>
        <div className="hcf-row">
          <InputField
            label="Piso"
            id="floor"
            type="number"
            value={data.floor}
            onChange={(v) => updateField('floor', v)}
            min={0}
            placeholder="0"
          />
          <InputField
            label="Número de niveles"
            id="levels"
            type="number"
            value={data.levels}
            onChange={(v) => updateField('levels', v)}
            min={0}
            placeholder="0"
          />
          <InputField
            label="Ascensores"
            id="elevators"
            type="number"
            value={data.elevators}
            onChange={(v) => updateField('elevators', v)}
            min={0}
            placeholder="0"
          />
        </div>
        <div style={{ maxWidth: '200px' }}>
          <SelectField
            label="Estrato socioeconómico"
            id="stratum"
            value={data.stratum}
            onChange={(v) => updateField('stratum', v)}
            placeholder="Selecciona…"
          >
            {STRATA.map((s) => <option key={s} value={s}>{s}</option>)}
          </SelectField>
        </div>
      </fieldset>
    </div>
  );
}
