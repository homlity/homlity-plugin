import { InputField, TextareaField } from './Field';
import FileUploader from './FileUploader';

export default function StepMedia({ data, updateField, errors, config, compact = false }) {
  const uploadCfg = config?.upload || {};
  const maxImages = uploadCfg.max_images  || 20;
  const maxSizeMb = uploadCfg.max_size_mb || 10;

  return (
    <div className="hcf-step hcf-step--media">
      {!compact && (
        <>
          <h2 className="hcf-step__title">Multimedia</h2>
          <p className="hcf-step__desc">
            Sube fotos del inmueble. Las imágenes de buena calidad aumentan el interés de los compradores.
          </p>
        </>
      )}

      {/* Gallery */}
      <div className="hcf-field">
        <label className="hcf-label">
          Galería de fotos <span className="hcf-required" aria-hidden="true"> *</span>
        </label>
        <FileUploader
          type="image"
          multiple
          value={data.gallery}
          onChange={(urls) => updateField('gallery', urls)}
          accept="image/jpeg,image/png,image/webp"
          maxSizeMb={maxSizeMb}
          label={`Arrastra fotos o haz clic (máx ${maxImages} imágenes)`}
          error={errors.gallery}
        />
        {data.gallery?.length > 0 && (
          <p className="hcf-hint">{data.gallery.length} foto(s) cargada(s).</p>
        )}
      </div>

      {/* Brochure */}
      <div className="hcf-field">
        <label className="hcf-label">Documentos legales del inmueble</label>
        <FileUploader
          type="brochure"
          multiple={false}
          value={data.brochure}
          onChange={(url) => updateField('brochure', url)}
          accept="application/pdf"
          maxSizeMb={uploadCfg.max_pdf_mb || 20}
          label="Subir PDF"
        />
      </div>

      {/* Videos */}
      <div className="hcf-field">
        <label className="hcf-label">Videos (URLs de YouTube / Vimeo)</label>
        <VideoList
          values={data.videos || []}
          onChange={(v) => updateField('videos', v)}
        />
      </div>

      {/* Tour 360 */}
      <div className="hcf-field">
        <label className="hcf-label">Tour 360° (URLs)</label>
        <VideoList
          values={data.tour_360 || []}
          onChange={(v) => updateField('tour_360', v)}
          placeholder="https://my.matterport.com/..."
        />
      </div>

      <TextareaField
        label="Nota para el fotógrafo / equipo"
        id="photo_note"
        value={data.photo_note}
        onChange={(v) => updateField('photo_note', v)}
        rows={2}
        placeholder="Indicaciones sobre el horario, acceso, personas de contacto, etc."
      />
    </div>
  );
}

function VideoList({ values, onChange, placeholder = 'https://www.youtube.com/watch?v=...' }) {
  const add    = () => onChange([...values, '']);
  const update = (i, v) => { const n = [...values]; n[i] = v; onChange(n); };
  const remove = (i)    => onChange(values.filter((_, idx) => idx !== i));

  return (
    <div className="hcf-video-list">
      {values.map((url, i) => (
        <div key={i} className="hcf-row hcf-row--inline hcf-video-list__row">
          <input
            type="url"
            className="hcf-input"
            value={url}
            onChange={(e) => update(i, e.target.value)}
            placeholder={placeholder}
            aria-label={`URL de video ${i + 1}`}
          />
          <button
            type="button"
            className="hcf-btn hcf-btn--ghost hcf-btn--sm"
            onClick={() => remove(i)}
            aria-label="Eliminar URL"
          >
            ×
          </button>
        </div>
      ))}
      <button type="button" className="hcf-btn hcf-btn--secondary hcf-btn--sm" onClick={add}>
        + Agregar URL
      </button>
    </div>
  );
}
