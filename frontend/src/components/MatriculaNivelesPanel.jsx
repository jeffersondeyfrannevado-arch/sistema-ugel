import { useState, useMemo, useRef } from 'react'
import { exportarMatriculaColegio, procesarArchivo } from '../services/api'

export default function MatriculaNivelesPanel({ resultado, onResultadoChange }) {
  const [localResultado, setLocalResultado] = useState(resultado)
  const [cargando, setCargando] = useState(false)

  const activeResultado = resultado || localResultado
  const colegios = activeResultado?.colegios || []
  const estadisticas = activeResultado?.estadisticas || {}

  const [busqueda, setBusqueda] = useState('')
  const [colegioSeleccionado, setColegioSeleccionado] = useState(colegios[0]?.codigo_ie || colegios[0]?.nombre || '')
  const [descargando, setDescargando] = useState(false)
  const [mensaje, setMensaje] = useState(null)
  const fileInputRef = useRef(null)

  const handleSubirExcelMatricula = async (e) => {
    if (!e.target.files || !e.target.files[0]) return
    const file = e.target.files[0]

    try {
      setCargando(true)
      setMensaje({ tipo: 'info', texto: 'Procesando archivo REPORTE ACTUALIZADO y extrayendo colegios...' })
      const res = await procesarArchivo(file, [])
      setLocalResultado(res)
      if (onResultadoChange) onResultadoChange(res)
      if (res.colegios && res.colegios.length > 0) {
        setColegioSeleccionado(res.colegios[0].codigo_ie || res.colegios[0].nombre)
      }
      setMensaje({ tipo: 'success', texto: `Archivo de Matrícula procesado correctamente. ${res.colegios?.length || 0} colegios identificados.` })
    } catch (err) {
      setMensaje({ tipo: 'error', texto: `Error al procesar Matrícula: ${err.message}` })
    } finally {
      setCargando(false)
    }
  }

  const colegiosFiltrados = useMemo(() => {
    if (!busqueda.trim()) return colegios
    const query = busqueda.toLowerCase()
    return colegios.filter(
      (c) =>
        c.codigo_ie?.toLowerCase().includes(query) ||
        c.nombre?.toLowerCase().includes(query) ||
        c.codmod?.toLowerCase().includes(query)
    )
  }, [colegios, busqueda])

  const colegioActual = useMemo(() => {
    return colegios.find((c) => c.codigo_ie === colegioSeleccionado || c.nombre === colegioSeleccionado) || colegiosFiltrados[0] || {}
  }, [colegios, colegioSeleccionado, colegiosFiltrados])

  const handleDescargar = async () => {
    const targetCode = colegioActual.codigo_ie || colegioActual.nombre || colegioSeleccionado
    if (!targetCode) return

    try {
      setDescargando(true)
      setMensaje({ tipo: 'info', texto: `Generando archivo REPORTE - I.E. ${targetCode}...` })
      await exportarMatriculaColegio(targetCode)
      setMensaje({ tipo: 'success', texto: `Excel REPORTE - I.E. ${targetCode}.xlsx descargado correctamente con las 5 secciones coloreadas.` })
    } catch (err) {
      setMensaje({ tipo: 'error', texto: `Error al descargar: ${err.message}` })
    } finally {
      setDescargando(false)
    }
  }

  return (
    <section className="step-card dashboard-card matricula-theme">
      <div className="step-head" style={{ borderBottom: '2px solid #ca8a04', paddingBottom: '1rem' }}>
        <div className="step-label" style={{ background: '#ca8a04', color: '#fff', padding: '0.3rem 0.8rem', borderRadius: '4px', display: 'inline-block', fontWeight: 'bold' }}>
          MÓDULO FICHAS POR COLEGIO
        </div>
        <h2 style={{ marginTop: '0.5rem', color: '#854d0e' }}>Filtrar y Exportar Reporte por Colegio (3 Niveles)</h2>
        <p>Sube o selecciona el Excel REPORTE ACTUALIZADO para generar el archivo individual `REPORTE - I.E. [CODIGO].xlsx`.</p>
      </div>

      <input
        type="file"
        ref={fileInputRef}
        onChange={handleSubirExcelMatricula}
        accept=".xlsx,.xls"
        style={{ display: 'none' }}
      />

      {/* Zona de Carga Directa si aún no hay un archivo cargado */}
      {(!colegios || colegios.length === 0) && (
        <div style={{ background: '#fefce8', border: '2px dashed #fde047', borderRadius: '10px', padding: '2.5rem', textAlign: 'center', margin: '1.5rem 0' }}>
          <div style={{ fontSize: '2.5rem', marginBottom: '0.5rem' }}>📄</div>
          <h3 style={{ margin: '0 0 0.5rem 0', color: '#854d0e' }}>Subir Excel REPORTE ACTUALIZADO (ej. REPORTE ACTUALIZADO.xls)</h3>
          <p style={{ margin: '0 0 1.5rem 0', color: '#a16207', fontSize: '0.9rem' }}>
            Selecciona el informe consolidado de matrícula para extraer los colegios y descargar sus reportes por colegio.
          </p>
          <button
            onClick={() => fileInputRef.current?.click()}
            disabled={cargando}
            style={{
              background: '#ca8a04',
              color: '#ffffff',
              padding: '0.75rem 1.8rem',
              borderRadius: '8px',
              border: 'none',
              fontWeight: 'bold',
              fontSize: '1rem',
              cursor: cargando ? 'not-allowed' : 'pointer',
            }}
          >
            {cargando ? 'Procesando Matrícula...' : 'Elegir REPORTE ACTUALIZADO'}
          </button>
        </div>
      )}

      {/* Botón para cambiar o subir otro archivo de Matrícula si ya hay uno */}
      {colegios && colegios.length > 0 && (
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#fefce8', padding: '0.75rem 1rem', borderRadius: '8px', margin: '1rem 0', border: '1px solid #fef08a' }}>
          <span style={{ fontSize: '0.85rem', color: '#854d0e', fontWeight: 'bold' }}>
            ✅ Archivo REPORTE ACTUALIZADO activo con {colegios.length} colegios detectados.
          </span>
          <button
            onClick={() => fileInputRef.current?.click()}
            disabled={cargando}
            style={{ background: '#ca8a04', color: '#fff', border: 'none', padding: '0.4rem 0.8rem', borderRadius: '6px', cursor: 'pointer', fontSize: '0.8rem', fontWeight: 'bold' }}
          >
            🔄 Cambiar REPORTE ACTUALIZADO
          </button>
        </div>
      )}

      {mensaje && (
        <div style={{
          margin: '1rem 0',
          padding: '0.75rem 1rem',
          borderRadius: '6px',
          background: mensaje.tipo === 'error' ? '#fef2f2' : (mensaje.tipo === 'success' ? '#f0fdf4' : '#eff6ff'),
          color: mensaje.tipo === 'error' ? '#991b1b' : (mensaje.tipo === 'success' ? '#166534' : '#1e40af'),
          border: '1px solid currentColor',
          fontSize: '0.9rem',
        }}>
          {mensaje.texto}
        </div>
      )}

      {/* Si hay colegios, mostrar selector y botón de descarga */}
      {colegios && colegios.length > 0 && (
        <div style={{ background: '#ffffff', padding: '1.25rem', borderRadius: '8px', border: '1px solid #e2e8f0', marginBottom: '1.5rem' }}>
          <h3 style={{ margin: '0 0 1rem 0', color: '#1e293b' }}>Seleccionar Colegio para Filtrar Reporte de Matrícula</h3>
          <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', alignItems: 'center' }}>
            <input
              type="text"
              placeholder="🔍 Buscar código (ej. 070)..."
              value={busqueda}
              onChange={(e) => setBusqueda(e.target.value)}
              style={{ padding: '0.65rem 1rem', borderRadius: '6px', border: '1px solid #cbd5e1', flex: '1', minWidth: '220px' }}
            />

            <select
              value={colegioSeleccionado}
              onChange={(e) => setColegioSeleccionado(e.target.value)}
              style={{ padding: '0.65rem 1rem', borderRadius: '6px', border: '1px solid #cbd5e1', flex: '2', minWidth: '260px', fontWeight: 'bold' }}
            >
              {colegiosFiltrados.map((c) => (
                <option key={c.codigo_ie || c.nombre} value={c.codigo_ie || c.nombre}>
                  I.E. {c.nombre || c.codigo_ie} {c.niveles?.length ? `(${c.niveles.join(', ')})` : ''}
                </option>
              ))}
            </select>

            <button
              onClick={handleDescargar}
              disabled={descargando || (!colegioActual.codigo_ie && !colegioActual.nombre)}
              style={{
                background: '#ca8a04',
                color: '#ffffff',
                padding: '0.65rem 1.4rem',
                borderRadius: '6px',
                border: 'none',
                fontWeight: 'bold',
                cursor: descargando ? 'not-allowed' : 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '0.5rem',
              }}
            >
              📥 {descargando ? 'Generando...' : `Descargar REPORTE - I.E. ${colegioActual.nombre || colegioActual.codigo_ie || '070'}`}
            </button>
          </div>
        </div>
      )}
    </section>
  )
}
