import { useEffect, useMemo, useState } from 'react'
import UploadZone from './UploadZone'
import { analizarFormatoExcel, guardarFormatoExcel, listarFormatosExcel } from '../services/api'

function toExcelColumnNumber(index) {
  return Number.isFinite(index) ? index + 1 : ''
}

function toZeroBasedColumnNumber(index) {
  return Number.isFinite(index) ? Math.max(0, index - 1) : ''
}

function MappingGrid({ title, fields, mappings, onChange }) {
  return (
    <section className="training-map-card">
      <div className="training-map-head">
        <strong>{title}</strong>
        <span>{fields.length} campo(s)</span>
      </div>

      <div className="training-map-grid">
        {fields.map(field => (
          <label key={field} className="training-field">
            <span>{field}</span>
            <input
              type="number"
              min="1"
              value={mappings[field] ?? ''}
              onChange={e => onChange(field, e.target.value)}
            />
          </label>
        ))}
      </div>
    </section>
  )
}

export default function TrainingPanel({ onUnauthorized }) {
  const [archivoEntrenamiento, setArchivoEntrenamiento] = useState(null)
  const [analisis, setAnalisis] = useState(null)
  const [formatos, setFormatos] = useState([])
  const [loadingAnalisis, setLoadingAnalisis] = useState(false)
  const [loadingGuardar, setLoadingGuardar] = useState(false)
  const [loadingFormatos, setLoadingFormatos] = useState(true)
  const [mensaje, setMensaje] = useState('')
  const [error, setError] = useState('')
  const [nombreFormato, setNombreFormato] = useState('')
  const [nivel, setNivel] = useState('SECUNDARIA')
  const [keywords, setKeywords] = useState('')
  const [headerRowIndex, setHeaderRowIndex] = useState(1)
  const [subheaderRowIndex, setSubheaderRowIndex] = useState(1)
  const [dataStartRow, setDataStartRow] = useState(1)
  const [mappings, setMappings] = useState({})

  const requiredFields = analisis?.campos_requeridos ?? []
  const gradeFields = analisis?.campos_grado_requeridos ?? []

  const headersPreview = useMemo(() => (analisis?.headers_detectados ?? []).slice(0, 18), [analisis])

  const loadFormatos = async () => {
    setLoadingFormatos(true)
    try {
      const data = await listarFormatosExcel()
      setFormatos(data)
    } catch (e) {
      if (e.message === 'No autorizado') {
        onUnauthorized?.()
      } else {
        setError(e.message || 'No se pudieron cargar los formatos')
      }
    } finally {
      setLoadingFormatos(false)
    }
  }

  useEffect(() => {
    loadFormatos()
  }, [])

  const handleAnalizar = async () => {
    if (!archivoEntrenamiento) return

    setLoadingAnalisis(true)
    setMensaje('')
    setError('')

    try {
      const data = await analizarFormatoExcel(archivoEntrenamiento)
      setAnalisis(data)
      setNivel(data.nivel_sugerido || 'SECUNDARIA')
      setNombreFormato(`Formato ${data.nivel_sugerido || 'SECUNDARIA'} ${new Date().toLocaleDateString()}`)
      setKeywords((data.keywords_sugeridas || []).join(', '))
      setHeaderRowIndex(data.fila_headers || 1)
      setSubheaderRowIndex(data.fila_subheaders || 1)
      setDataStartRow(data.fila_inicio_datos || 1)
      setMappings(
        Object.fromEntries(
          Object.entries(data.columnas_sugeridas || {}).map(([field, index]) => [field, toExcelColumnNumber(index)])
        )
      )
      setMensaje('Analisis completado. Revisa y ajusta el mapeo antes de guardar.')
    } catch (e) {
      if (e.message === 'No autorizado') {
        onUnauthorized?.()
      } else {
        setError(e.message || 'No se pudo analizar el archivo')
      }
    } finally {
      setLoadingAnalisis(false)
    }
  }

  const handleMappingChange = (field, value) => {
    setMappings(prev => ({
      ...prev,
      [field]: value === '' ? '' : Number(value),
    }))
  }

  const handleGuardar = async () => {
    if (!analisis) return

    setLoadingGuardar(true)
    setMensaje('')
    setError('')

    try {
      const normalizedMappings = Object.fromEntries(
        Object.entries(mappings).map(([field, index]) => [field, toZeroBasedColumnNumber(Number(index))])
      )

      const payload = {
        nombre: nombreFormato,
        nivel,
        match_keywords: keywords.split(',').map(item => item.trim()).filter(Boolean),
        header_row_index: Number(headerRowIndex),
        subheader_row_index: Number(subheaderRowIndex),
        data_start_row: Number(dataStartRow),
        columns: normalizedMappings,
      }

      await guardarFormatoExcel(payload)
      setMensaje('Formato guardado. Desde ahora el sistema intentara reconocer este Excel automaticamente.')
      await loadFormatos()
    } catch (e) {
      if (e.message === 'No autorizado') {
        onUnauthorized?.()
      } else {
        setError(e.message || 'No se pudo guardar el formato')
      }
    } finally {
      setLoadingGuardar(false)
    }
  }

  return (
    <div className="training-layout">
      <section className="step-card">
        <div className="step-head">
          <div className="step-label"><span>07</span> Entrenar formato</div>
          <p>Sube un Excel nuevo para detectar encabezados, corregir el mapeo y guardarlo como plantilla reutilizable.</p>
        </div>

        <UploadZone
          archivo={archivoEntrenamiento}
          onArchivo={setArchivoEntrenamiento}
          disabled={loadingAnalisis || loadingGuardar}
        />

        <div className="training-actions">
          <button
            className={`btn-procesar ${loadingAnalisis ? 'loading' : ''}`}
            onClick={handleAnalizar}
            disabled={!archivoEntrenamiento || loadingAnalisis}
          >
            {loadingAnalisis ? <><span className="spinner" /> Analizando...</> : 'Analizar formato'}
          </button>
        </div>

        {mensaje && <div className="training-banner training-banner-success">{mensaje}</div>}
        {error && <div className="training-banner training-banner-error">{error}</div>}
      </section>

      {analisis && (
        <section className="step-card">
          <div className="step-head">
            <div className="step-label"><span>08</span> Ajuste de plantilla</div>
            <p>El analizador sugiere columnas y filas clave. Puedes corregirlas antes de guardar el formato entrenado.</p>
          </div>

          <div className="training-summary">
            <div className="summary-item">
              <span className="summary-label">Hoja</span>
              <strong>{analisis.sheet}</strong>
            </div>
            <div className="summary-item">
              <span className="summary-label">Nivel sugerido</span>
              <strong>{analisis.nivel_sugerido}</strong>
            </div>
            <div className="summary-item">
              <span className="summary-label">Fila de datos</span>
              <strong>{analisis.fila_inicio_datos}</strong>
            </div>
          </div>

          <div className="training-config-grid">
            <label className="training-field">
              <span>Nombre del formato</span>
              <input value={nombreFormato} onChange={e => setNombreFormato(e.target.value)} />
            </label>
            <label className="training-field">
              <span>Nivel</span>
              <select value={nivel} onChange={e => setNivel(e.target.value)}>
                <option value="INICIAL">INICIAL</option>
                <option value="PRIMARIA">PRIMARIA</option>
                <option value="SECUNDARIA">SECUNDARIA</option>
              </select>
            </label>
            <label className="training-field">
              <span>Fila headers</span>
              <input type="number" min="1" value={headerRowIndex} onChange={e => setHeaderRowIndex(e.target.value)} />
            </label>
            <label className="training-field">
              <span>Fila subheaders</span>
              <input type="number" min="1" value={subheaderRowIndex} onChange={e => setSubheaderRowIndex(e.target.value)} />
            </label>
            <label className="training-field">
              <span>Fila inicio datos</span>
              <input type="number" min="1" value={dataStartRow} onChange={e => setDataStartRow(e.target.value)} />
            </label>
            <label className="training-field training-field-wide">
              <span>Keywords de reconocimiento</span>
              <input value={keywords} onChange={e => setKeywords(e.target.value)} placeholder="SECUNDARIA, MATRICULA, REPORTE..." />
            </label>
          </div>

          <div className="training-headers">
            <div className="training-map-head">
              <strong>Encabezados detectados</strong>
              <span>{analisis.headers_detectados.length} columna(s)</span>
            </div>
            <div className="training-header-list">
              {headersPreview.map(item => (
                <div key={`${item.index}-${item.label}`} className="training-header-chip">
                  <strong>{toExcelColumnNumber(item.index)}</strong>
                  <span>{item.label}</span>
                </div>
              ))}
            </div>
          </div>

          <div className="training-map-layout">
            <MappingGrid title="Campos base" fields={requiredFields} mappings={mappings} onChange={handleMappingChange} />
            <MappingGrid title="Campos por grado" fields={gradeFields} mappings={mappings} onChange={handleMappingChange} />
          </div>

          <div className="training-actions training-actions-end">
            <button
              className={`btn-procesar ${loadingGuardar ? 'loading' : ''}`}
              onClick={handleGuardar}
              disabled={loadingGuardar}
            >
              {loadingGuardar ? <><span className="spinner" /> Guardando...</> : 'Guardar formato entrenado'}
            </button>
          </div>
        </section>
      )}

      <section className="step-card">
        <div className="step-head">
          <div className="step-label"><span>09</span> Formatos guardados</div>
          <p>Listado de plantillas que el sistema intentara reconocer automaticamente.</p>
        </div>

        {loadingFormatos ? (
          <div className="preview-loading">
            <span className="spinner spinner-dark" />
            <span>Cargando formatos entrenados...</span>
          </div>
        ) : formatos.length === 0 ? (
          <div className="empty-state">
            <div>
              <strong>Aun no hay formatos entrenados</strong>
              <p>Cuando guardes uno desde este modulo aparecera aqui para reutilizarlo con futuros Excel.</p>
            </div>
          </div>
        ) : (
          <div className="training-profiles">
            {formatos.map(formato => (
              <article key={formato.id} className="training-profile-card">
                <div className="training-map-head">
                  <strong>{formato.nombre}</strong>
                  <span>{formato.nivel}</span>
                </div>
                <p>Keywords: {(formato.match_keywords || []).join(', ') || 'Sin keywords configuradas'}</p>
                <p>Inicio datos: fila {formato.data_start_row || '-'}</p>
                <p>Columnas mapeadas: {Object.keys(formato.columns || {}).length}</p>
              </article>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}
