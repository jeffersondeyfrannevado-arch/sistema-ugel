import { useState, useMemo, useRef } from 'react'
import { exportarNexusColegio, procesarArchivo } from '../services/api'

export default function NexusPanel({ resultado, onResultadoChange }) {
  const [localResultado, setLocalResultado] = useState(resultado)
  const [cargando, setCargando] = useState(false)

  const activeResultado = resultado || localResultado
  const estadisticas = activeResultado?.estadisticas || {}
  const colegios = activeResultado?.colegios || []

  const [busqueda, setBusqueda] = useState('')
  const [colegioSeleccionado, setColegioSeleccionado] = useState(colegios[0]?.nombre || '')
  const [descargando, setDescargando] = useState(false)
  const [mensaje, setMensaje] = useState(null)
  const fileInputRef = useRef(null)

  const handleSubirExcelNexus = async (e) => {
    if (!e.target.files || !e.target.files[0]) return
    const file = e.target.files[0]

    try {
      setCargando(true)
      setMensaje({ tipo: 'info', texto: 'Procesando archivo NEXUS y extrayendo colegios...' })
      const res = await procesarArchivo(file, [])
      setLocalResultado(res)
      if (onResultadoChange) onResultadoChange(res)
      if (res.colegios && res.colegios.length > 0) {
        setColegioSeleccionado(res.colegios[0].nombre)
      }
      setMensaje({ tipo: 'success', texto: `Archivo NEXUS procesado correctamente. ${res.colegios?.length || 0} colegios identificados.` })
    } catch (err) {
      setMensaje({ tipo: 'error', texto: `Error al procesar NEXUS: ${err.message}` })
    } finally {
      setCargando(false)
    }
  }

  const colegiosFiltrados = useMemo(() => {
    if (!busqueda.trim()) return colegios
    const query = busqueda.toLowerCase()
    return colegios.filter(
      (c) =>
        c.nombre?.toLowerCase().includes(query) ||
        c.codmod?.toLowerCase().includes(query)
    )
  }, [colegios, busqueda])

  const colegioActual = useMemo(() => {
    return colegios.find((c) => c.nombre === colegioSeleccionado) || colegiosFiltrados[0] || {}
  }, [colegios, colegioSeleccionado, colegiosFiltrados])

  const handleDescargar = async () => {
    const targetColegio = colegioActual.nombre || colegioSeleccionado
    if (!targetColegio) return

    try {
      setDescargando(true)
      setMensaje({ tipo: 'info', texto: `Generando archivo NEXUS - ${targetColegio}...` })
      await exportarNexusColegio(targetColegio)
      setMensaje({ tipo: 'success', texto: `Excel NEXUS - ${targetColegio}.xlsx descargado correctamente con el banner azul oficial.` })
    } catch (err) {
      setMensaje({ tipo: 'error', texto: `Error al descargar: ${err.message}` })
    } finally {
      setDescargando(false)
    }
  }

  return (
    <section className="step-card dashboard-card nexus-theme">
      <div className="step-head" style={{ borderBottom: '2px solid #1b365d', paddingBottom: '1rem' }}>
        <div className="step-label" style={{ background: '#1b365d', color: '#fff', padding: '0.3rem 0.8rem', borderRadius: '4px', display: 'inline-block', fontWeight: 'bold' }}>
          MÓDULO NEXUS
        </div>
        <h2 style={{ marginTop: '0.5rem', color: '#1b365d' }}>Filtrar y Exportar Archivo NEXUS por Colegio</h2>
        <p>Sube o selecciona el Excel de NEXUS para separar la información por colegio en el formato oficial `NEXUS - [COLEGIO].xlsx`.</p>
      </div>

      <input
        type="file"
        ref={fileInputRef}
        onChange={handleSubirExcelNexus}
        accept=".xlsx,.xls"
        style={{ display: 'none' }}
      />

      {/* Zona de Carga Directa si aún no hay un archivo cargado */}
      {(!colegios || colegios.length === 0) && (
        <div style={{ background: '#f8fafc', border: '2px dashed #cbd5e1', borderRadius: '10px', padding: '2.5rem', textAlign: 'center', margin: '1.5rem 0' }}>
          <div style={{ fontSize: '2.5rem', marginBottom: '0.5rem' }}>📁</div>
          <h3 style={{ margin: '0 0 0.5rem 0', color: '#1e293b' }}>Subir Excel NEXUS (ej. nexus.xlsx)</h3>
          <p style={{ margin: '0 0 1.5rem 0', color: '#64748b', fontSize: '0.9rem' }}>
            Selecciona el reporte maestro NEXUS sin filtrar para extraer los colegios y generar sus archivos individuales.
          </p>
          <button
            onClick={() => fileInputRef.current?.click()}
            disabled={cargando}
            style={{
              background: '#1b365d',
              color: '#ffffff',
              padding: '0.75rem 1.8rem',
              borderRadius: '8px',
              border: 'none',
              fontWeight: 'bold',
              fontSize: '1rem',
              cursor: cargando ? 'not-allowed' : 'pointer',
            }}
          >
            {cargando ? 'Procesando NEXUS...' : 'Elegir Excel NEXUS'}
          </button>
        </div>
      )}

      {/* Botón para cambiar o subir otro archivo NEXUS si ya hay uno */}
      {colegios && colegios.length > 0 && (
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f1f5f9', padding: '0.75rem 1rem', borderRadius: '8px', margin: '1rem 0' }}>
          <span style={{ fontSize: '0.85rem', color: '#334155', fontWeight: 'bold' }}>
            ✅ Archivo NEXUS activo con {colegios.length} colegios detectados.
          </span>
          <button
            onClick={() => fileInputRef.current?.click()}
            disabled={cargando}
            style={{ background: '#334155', color: '#fff', border: 'none', padding: '0.4rem 0.8rem', borderRadius: '6px', cursor: 'pointer', fontSize: '0.8rem', fontWeight: 'bold' }}
          >
            🔄 Cambiar archivo NEXUS
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

      {/* Si hay colegios, mostrar el panel de estadísticas y descarga por colegio */}
      {colegios && colegios.length > 0 && (
        <>
          <div className="stats-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem', margin: '1.5rem 0' }}>
            <div className="stat-card" style={{ background: '#ffffff', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #1b365d', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Total Instituciones</span>
              <h3 style={{ fontSize: '1.5rem', color: '#0f172a', margin: '0.2rem 0' }}>{estadisticas.total_colegios || colegios.length}</h3>
            </div>
            <div className="stat-card" style={{ background: '#ffffff', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #3b82f6', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Total Plazas</span>
              <h3 style={{ fontSize: '1.5rem', color: '#3b82f6', margin: '0.2rem 0' }}>{estadisticas.total_plazas || 0}</h3>
            </div>
            <div className="stat-card" style={{ background: '#ffffff', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #10b981', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Nombrados</span>
              <h3 style={{ fontSize: '1.5rem', color: '#10b981', margin: '0.2rem 0' }}>{estadisticas.nombrados || 0}</h3>
            </div>
            <div className="stat-card" style={{ background: '#ffffff', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #8b5cf6', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Contratados</span>
              <h3 style={{ fontSize: '1.5rem', color: '#8b5cf6', margin: '0.2rem 0' }}>{estadisticas.contratados || 0}</h3>
            </div>
          </div>

          <div style={{ background: '#ffffff', padding: '1.25rem', borderRadius: '8px', border: '1px solid #e2e8f0', marginBottom: '1.5rem' }}>
            <h3 style={{ margin: '0 0 1rem 0', color: '#1e293b' }}>Seleccionar Colegio para Filtrar Excel NEXUS</h3>
            <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', alignItems: 'center' }}>
              <input
                type="text"
                placeholder="🔍 Buscar por colegio (ej. 15255)..."
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
                  <option key={c.nombre || c.codmod} value={c.nombre}>
                    I.E. {c.nombre || c.codmod} - ({c.total_plazas} plazas)
                  </option>
                ))}
              </select>

              <button
                onClick={handleDescargar}
                disabled={descargando || !colegioActual.nombre}
                style={{
                  background: '#1b365d',
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
                📥 {descargando ? 'Generando...' : `Descargar NEXUS - ${colegioActual.nombre || '15255'}`}
              </button>
            </div>
          </div>
        </>
      )}
    </section>
  )
}
