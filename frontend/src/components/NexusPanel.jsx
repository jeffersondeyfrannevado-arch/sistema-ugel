import { useState, useMemo } from 'react'
import { exportarNexusColegio } from '../services/api'

export default function NexusPanel({ resultado }) {
  const estadisticas = resultado?.estadisticas || {}
  const colegios = resultado?.colegios || []

  const [busqueda, setBusqueda] = useState('')
  const [colegioSeleccionado, setColegioSeleccionado] = useState(colegios[0]?.nombre || '')
  const [descargando, setDescargando] = useState(false)
  const [mensaje, setMensaje] = useState(null)

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
      setMensaje({ tipo: 'success', texto: `Excel NEXUS generado y descargado correctamente.` })
    } catch (err) {
      setMensaje({ tipo: 'error', texto: `Error al descargar: ${err.message}` })
    } finally {
      setDescargando(false)
    }
  }

  return (
    <section className="step-card dashboard-card nexus-theme">
      <div className="step-head" style={{ borderBottom: '2px solid #1b365d', paddingBottom: '1rem' }}>
        <div className="step-label" style={{ background: '#1b365d', color: '#fff', padding: '0.2rem 0.8rem', borderRadius: '4px', display: 'inline-block' }}>
          <span>NEXUS</span> Módulo Cuadro de Plazas
        </div>
        <h2 style={{ marginTop: '0.5rem', color: '#1b365d' }}>Detección Automática: Formato NEXUS</h2>
        <p>Procesamiento y segregación de plazas por Institución Educativa (I.E.) con formato oficial UGEL.</p>
      </div>

      {/* Tarjetas de Estadísticas NEXUS */}
      <div className="stats-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem', margin: '1.5rem 0' }}>
        <div className="stat-card" style={{ background: '#f8fafc', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #1b365d' }}>
          <span style={{ fontSize: '0.85rem', color: '#64748b' }}>Total Instituciones</span>
          <h3 style={{ fontSize: '1.5rem', color: '#0f172a', margin: '0.2rem 0' }}>{estadisticas.total_colegios || 0}</h3>
        </div>
        <div className="stat-card" style={{ background: '#f8fafc', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #3b82f6' }}>
          <span style={{ fontSize: '0.85rem', color: '#64748b' }}>Total Plazas</span>
          <h3 style={{ fontSize: '1.5rem', color: '#3b82f6', margin: '0.2rem 0' }}>{estadisticas.total_plazas || 0}</h3>
        </div>
        <div className="stat-card" style={{ background: '#f8fafc', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #10b981' }}>
          <span style={{ fontSize: '0.85rem', color: '#64748b' }}>Plazas Nombradas</span>
          <h3 style={{ fontSize: '1.5rem', color: '#10b981', margin: '0.2rem 0' }}>{estadisticas.nombrados || 0}</h3>
        </div>
        <div className="stat-card" style={{ background: '#f8fafc', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #8b5cf6' }}>
          <span style={{ fontSize: '0.85rem', color: '#64748b' }}>Plazas Contratadas</span>
          <h3 style={{ fontSize: '1.5rem', color: '#8b5cf6', margin: '0.2rem 0' }}>{estadisticas.contratados || 0}</h3>
        </div>
      </div>

      {/* Selector y Buscador de Colegio */}
      <div style={{ background: '#ffffff', padding: '1.25rem', borderRadius: '8px', border: '1px solid #e2e8f0', marginBottom: '1.5rem' }}>
        <h3 style={{ margin: '0 0 1rem 0', color: '#1e293b' }}>Seleccionar Colegio / Institución Educativa</h3>
        <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', alignItems: 'center' }}>
          <input
            type="text"
            placeholder="🔍 Buscar por colegio o código modular..."
            value={busqueda}
            onChange={(e) => setBusqueda(e.target.value)}
            style={{ padding: '0.6rem 1rem', borderRadius: '6px', border: '1px solid #cbd5e1', flex: '1', minWidth: '240px' }}
          />

          <select
            value={colegioSeleccionado}
            onChange={(e) => setColegioSeleccionado(e.target.value)}
            style={{ padding: '0.6rem 1rem', borderRadius: '6px', border: '1px solid #cbd5e1', flex: '2', minWidth: '280px', fontWeight: 'bold' }}
          >
            {colegiosFiltrados.map((c) => (
              <option key={c.nombre || c.codmod} value={c.nombre}>
                {c.nombre || c.codmod} - ({c.total_plazas} plazas)
              </option>
            ))}
          </select>

          <button
            onClick={handleDescargar}
            disabled={descargando || !colegioActual.nombre}
            style={{
              background: '#1b365d',
              color: '#ffffff',
              padding: '0.6rem 1.4rem',
              borderRadius: '6px',
              border: 'none',
              fontWeight: 'bold',
              cursor: descargando ? 'not-allowed' : 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '0.5rem',
              transition: 'background 0.2s',
            }}
          >
            📥 {descargando ? 'Generando...' : `Descargar NEXUS - ${colegioActual.nombre || 'I.E.'}`}
          </button>
        </div>

        {mensaje && (
          <div style={{
            marginTop: '1rem',
            padding: '0.75rem',
            borderRadius: '6px',
            background: mensaje.tipo === 'error' ? '#fef2f2' : (mensaje.tipo === 'success' ? '#f0fdf4' : '#eff6ff'),
            color: mensaje.tipo === 'error' ? '#991b1b' : (mensaje.tipo === 'success' ? '#166534' : '#1e40af'),
            border: '1px solid currentColor',
          }}>
            {mensaje.texto}
          </div>
        )}
      </div>

      {/* Resumen del Colegio Seleccionado */}
      {colegioActual.nombre && (
        <div style={{ background: '#f1f5f9', padding: '1.25rem', borderRadius: '8px', border: '1px solid #cbd5e1' }}>
          <div style={{ background: '#1b365d', color: '#ffffff', padding: '0.75rem 1rem', borderRadius: '6px', marginBottom: '1rem' }}>
            <h4 style={{ margin: 0, textTransform: 'uppercase' }}>
              CUADRO DE PLAZAS NEXUS - I.E. {colegioActual.nombre}
            </h4>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '1rem', textAlign: 'center' }}>
            <div style={{ background: '#ffffff', padding: '0.75rem', borderRadius: '6px' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Código Modular</span>
              <div style={{ fontWeight: 'bold', fontSize: '1.1rem', color: '#0f172a' }}>{colegioActual.codmod || 'N/A'}</div>
            </div>
            <div style={{ background: '#ffffff', padding: '0.75rem', borderRadius: '6px' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Plazas Totales</span>
              <div style={{ fontWeight: 'bold', fontSize: '1.1rem', color: '#3b82f6' }}>{colegioActual.total_plazas}</div>
            </div>
            <div style={{ background: '#ffffff', padding: '0.75rem', borderRadius: '6px' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Nombrados</span>
              <div style={{ fontWeight: 'bold', fontSize: '1.1rem', color: '#10b981' }}>{colegioActual.nombrados}</div>
            </div>
            <div style={{ background: '#ffffff', padding: '0.75rem', borderRadius: '6px' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Contratados</span>
              <div style={{ fontWeight: 'bold', fontSize: '1.1rem', color: '#8b5cf6' }}>{colegioActual.contratados}</div>
            </div>
            <div style={{ background: '#ffffff', padding: '0.75rem', borderRadius: '6px' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Vacantes</span>
              <div style={{ fontWeight: 'bold', fontSize: '1.1rem', color: '#f59e0b' }}>{colegioActual.vacantes}</div>
            </div>
          </div>
        </div>
      )}
    </section>
  )
}
