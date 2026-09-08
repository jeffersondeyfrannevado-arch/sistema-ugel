import { useState, useMemo } from 'react'
import { exportarMatriculaColegio } from '../services/api'

export default function MatriculaNivelesPanel({ resultado }) {
  const colegios = resultado?.colegios || []
  const estadisticas = resultado?.estadisticas || {}

  const [busqueda, setBusqueda] = useState('')
  const [colegioSeleccionado, setColegioSeleccionado] = useState(colegios[0]?.codigo_ie || colegios[0]?.nombre || '')
  const [descargando, setDescargando] = useState(false)
  const [mensaje, setMensaje] = useState(null)

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
      setMensaje({ tipo: 'success', texto: `Excel de Matrícula generado y descargado correctamente.` })
    } catch (err) {
      setMensaje({ tipo: 'error', texto: `Error al descargar: ${err.message}` })
    } finally {
      setDescargando(false)
    }
  }

  return (
    <section className="step-card dashboard-card matricula-theme">
      <div className="step-head" style={{ borderBottom: '2px solid #ca8a04', paddingBottom: '1rem' }}>
        <div className="step-label" style={{ background: '#ca8a04', color: '#fff', padding: '0.2rem 0.8rem', borderRadius: '4px', display: 'inline-block' }}>
          <span>MATRÍCULA</span> Módulo de Niveles Educativos
        </div>
        <h2 style={{ marginTop: '0.5rem', color: '#854d0e' }}>Detección Automática: Reporte de Matrícula</h2>
        <p>Filtrado de matrícula por colegio respetando sus niveles ofertados (Inicial, Primaria, Secundaria).</p>
      </div>

      {/* Tarjetas de Estadísticas Matrícula */}
      <div className="stats-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem', margin: '1.5rem 0' }}>
        <div className="stat-card" style={{ background: '#fefce8', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #ca8a04' }}>
          <span style={{ fontSize: '0.85rem', color: '#854d0e' }}>Total Registros Validados</span>
          <h3 style={{ fontSize: '1.5rem', color: '#854d0e', margin: '0.2rem 0' }}>{estadisticas.total || 0}</h3>
        </div>
        <div className="stat-card" style={{ background: '#eff6ff', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #2563eb' }}>
          <span style={{ fontSize: '0.85rem', color: '#1e40af' }}>Instituciones Públicas</span>
          <h3 style={{ fontSize: '1.5rem', color: '#2563eb', margin: '0.2rem 0' }}>{estadisticas.publicos || 0}</h3>
        </div>
        <div className="stat-card" style={{ background: '#f5f3ff', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #7c3aed' }}>
          <span style={{ fontSize: '0.85rem', color: '#5b21b6' }}>Instituciones Privadas</span>
          <h3 style={{ fontSize: '1.5rem', color: '#7c3aed', margin: '0.2rem 0' }}>{estadisticas.privados || 0}</h3>
        </div>
        <div className="stat-card" style={{ background: '#fcfaef', padding: '1rem', borderRadius: '8px', borderLeft: '4px solid #eab308' }}>
          <span style={{ fontSize: '0.85rem', color: '#713f12' }}>Colegios Identificados</span>
          <h3 style={{ fontSize: '1.5rem', color: '#a16207', margin: '0.2rem 0' }}>{colegios.length}</h3>
        </div>
      </div>

      {/* Selector y Buscador de Colegio */}
      <div style={{ background: '#ffffff', padding: '1.25rem', borderRadius: '8px', border: '1px solid #e2e8f0', marginBottom: '1.5rem' }}>
        <h3 style={{ margin: '0 0 1rem 0', color: '#1e293b' }}>Filtrar y Descargar Reporte de Colegio (I.E.)</h3>
        <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', alignItems: 'center' }}>
          <input
            type="text"
            placeholder="🔍 Buscar código de colegio (ej. 070)..."
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
            📥 {descargando ? 'Generando...' : `Descargar REPORTE - I.E. ${colegioActual.nombre || colegioActual.codigo_ie || '070'}`}
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

      {/* Resumen del Colegio Seleccionado con Niveles Dinámicos */}
      {(colegioActual.codigo_ie || colegioActual.nombre) && (
        <div style={{ background: '#fefce8', padding: '1.25rem', borderRadius: '8px', border: '1px solid #fef08a' }}>
          <div style={{ background: '#ca8a04', color: '#ffffff', padding: '0.75rem 1rem', borderRadius: '6px', marginBottom: '1rem' }}>
            <h4 style={{ margin: 0, textTransform: 'uppercase' }}>
              REPORTE DE MATRÍCULA - I.E. {colegioActual.nombre || colegioActual.codigo_ie}
            </h4>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem' }}>
            <div style={{ background: '#ffffff', padding: '0.85rem', borderRadius: '6px', border: '1px solid #e2e8f0' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Código I.E.</span>
              <div style={{ fontWeight: 'bold', fontSize: '1.1rem', color: '#854d0e' }}>{colegioActual.codigo_ie || '070'}</div>
            </div>

            <div style={{ background: '#ffffff', padding: '0.85rem', borderRadius: '6px', border: '1px solid #e2e8f0' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Niveles Ofertados ({colegioActual.niveles?.length || 1})</span>
              <div style={{ fontWeight: 'bold', fontSize: '1.05rem', color: '#16a34a', marginTop: '0.2rem' }}>
                {colegioActual.niveles?.length ? colegioActual.niveles.join(' • ') : 'INICIAL / PRIMARIA / SECUNDARIA'}
              </div>
            </div>

            <div style={{ background: '#ffffff', padding: '0.85rem', borderRadius: '6px', border: '1px solid #e2e8f0' }}>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Secciones con Colores</span>
              <div style={{ fontSize: '0.85rem', marginTop: '0.2rem' }}>
                <span style={{ display: 'inline-block', padding: '2px 6px', background: '#fff', border: '1px solid #ccc', borderRadius: '3px', marginRight: '4px' }}>Blanco</span>
                <span style={{ display: 'inline-block', padding: '2px 6px', background: '#fff2cc', borderRadius: '3px', marginRight: '4px' }}>Amarillo Claro</span>
                <span style={{ display: 'inline-block', padding: '2px 6px', background: '#d9ead3', borderRadius: '3px', marginRight: '4px' }}>Verde</span>
                <span style={{ display: 'inline-block', padding: '2px 6px', background: '#f4cccc', borderRadius: '3px' }}>Rosa</span>
              </div>
            </div>
          </div>
        </div>
      )}
    </section>
  )
}
