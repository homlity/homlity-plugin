import { useState, useRef, useCallback } from '@wordpress/element';
import { uploadFile } from '../api';

/**
 * Single or multi-file uploader with drag & drop, progress, and preview.
 *
 * Props:
 *   type         'image' | 'brochure'
 *   multiple     bool
 *   value        string (single) | string[] (multiple) — URL(s)
 *   onChange     (value) => void
 *   accept       MIME types string
 *   maxSizeMb    number
 *   label        string
 *   error        string
 */
export default function FileUploader({
  type = 'image',
  multiple = false,
  value,
  onChange,
  accept = 'image/jpeg,image/png,image/webp',
  maxSizeMb = 10,
  label = 'Subir archivo',
  error,
}) {
  const [dragging,   setDragging]   = useState(false);
  const [uploading,  setUploading]  = useState(false);
  const [progress,   setProgress]   = useState(0);
  const [uploadError, setUploadError] = useState('');
  const inputRef = useRef();

  const files   = multiple ? (Array.isArray(value) ? value : []) : [];
  const single  = !multiple ? (value || '') : '';

  const handleFiles = useCallback(async (fileList) => {
    const file = fileList[0];
    if (!file) return;

    if (file.size > maxSizeMb * 1024 * 1024) {
      setUploadError(`El archivo no debe superar ${maxSizeMb} MB.`);
      return;
    }

    setUploadError('');
    setUploading(true);
    setProgress(0);

    try {
      const res = await uploadFile(file, type, (pct) => setProgress(pct));
      if (multiple) {
        onChange([...files, res.url]);
      } else {
        onChange(res.url);
      }
    } catch (err) {
      setUploadError(err.message || 'Error al subir el archivo.');
    } finally {
      setUploading(false);
      setProgress(0);
    }
  }, [files, multiple, onChange, type, maxSizeMb]);

  const onInputChange = (e) => handleFiles(e.target.files);

  const onDrop = (e) => {
    e.preventDefault();
    setDragging(false);
    handleFiles(e.dataTransfer.files);
  };

  const removeFile = (url) => {
    if (multiple) {
      onChange(files.filter((f) => f !== url));
    } else {
      onChange('');
    }
  };

  const displayUrls = multiple ? files : (single ? [single] : []);

  return (
    <div className={`hcf-uploader${error || uploadError ? ' hcf-uploader--error' : ''}`}>
      <div
        className={`hcf-dropzone${dragging ? ' hcf-dropzone--active' : ''}${uploading ? ' hcf-dropzone--uploading' : ''}`}
        onDragEnter={(e) => { e.preventDefault(); setDragging(true); }}
        onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
        onDragLeave={() => setDragging(false)}
        onDrop={onDrop}
        onClick={() => !uploading && inputRef.current?.click()}
        role="button"
        tabIndex={0}
        aria-label={label}
        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') inputRef.current?.click(); }}
      >
        <input
          ref={inputRef}
          type="file"
          accept={accept}
          multiple={multiple}
          className="hcf-uploader__input"
          onChange={onInputChange}
          tabIndex={-1}
          aria-hidden="true"
        />
        {uploading ? (
          <div className="hcf-uploader__progress">
            <div className="hcf-progress-bar">
              <div className="hcf-progress-bar__fill" style={{ width: `${progress}%` }} />
            </div>
            <p>{progress}%</p>
          </div>
        ) : (
          <div className="hcf-uploader__prompt">
            <span className="hcf-uploader__icon" aria-hidden="true">📎</span>
            <p>{label}</p>
            <p className="hcf-hint">Arrastra aquí o haz clic · máx {maxSizeMb} MB</p>
          </div>
        )}
      </div>

      {(uploadError || error) && (
        <p className="hcf-error-msg" role="alert">{uploadError || error}</p>
      )}

      {displayUrls.length > 0 && (
        <ul className="hcf-uploader__list">
          {displayUrls.map((url) => (
            <li key={url} className="hcf-uploader__item">
              {type === 'image' ? (
                <img src={url} alt="" className="hcf-uploader__thumb" loading="lazy" />
              ) : (
                <a href={url} target="_blank" rel="noreferrer" className="hcf-uploader__link">
                  {decodeURIComponent(url.split('/').pop())}
                </a>
              )}
              <button
                type="button"
                className="hcf-uploader__remove"
                onClick={() => removeFile(url)}
                aria-label="Eliminar archivo"
              >
                ×
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
