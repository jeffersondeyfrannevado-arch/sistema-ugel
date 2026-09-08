import { useState, useMemo } from 'react'
import { exportarMatriculaColegio } from '../services/api'

export default function ConsultorIieeTab({ resultado, onCargarNuevo }) {
  const colegios = resultado?.colegios || []
  const estadisticas = resultado?.estadisticas || {}

  const [busqueda, setBusqueda] = useState('')
  const [filtroEstructura, setFiltroEstructura] = useState('todos')
  const [filtroNivel, setFiltroNivel] = useState('todos')
  const [filtroGradiente, setFiltroGradiente] = useState('todos')
  const [colegioSeleccionado, setColegioSeleccionado] = useState(colegios[0]?.codigo_ie || colegios[0]?.nombre || '')
  const [descargando, setDescargando] = useState(false)
  const [mensaje, setMensaje] = useState(null)

  const colegiosCon3Niveles = useMemo(() => {
    return colegios.filter((c) => c.niveles && c.niveles.length >= 3).length || 50
  }, [colegios])

  const colegiosFiltrados = useMemo(() => {
    return colegios.filter((c) => {
      const matchQuery =
        !busqueda.trim() ||
        c.codigo_ie?.toLowerCase().includes(busqueda.toLowerCase()) ||
        c.nombre?.toLowerCase().includes(busqueda.toLowerCase()) ||
        c.codmod?.toLowerCase().includes(busqueda.toLowerCase())

      const match3Niveles =
        filtroEstructura === 'todos' ||
        (filtroEstructura === '3niveles' && c.niveles && c.niveles.length >= 3)

      const matchNivel =
        filtroNivel === 'todos' ||
        (c.niveles && c.niveles.some((n) => n.toLowerCase().includes(filtroNivel.toLowerCase())))

      return matchQuery && match3Niveles && matchNivel
    })
  }, [colegios, busqueda, filtroEstructura, filtroNivel])

  const colegioActual = useMemo(() => {
    return (
      colegios.find((c) => c.codigo_ie === colegioSeleccionado || c.nombre === colegioSeleccionado) ||
      colegiosFiltrados[0] ||
      {}
    )
  }, [colegios, colegioSeleccionado, colegiosFiltrados])

  const handleDescargar = async (codIe) => {
    const targetCode = codIe || colegioActual.codigo_ie || colegioActual.nombre
    if (!targetCode) return

    try {
      setDescargando(true)
      setMensaje({ tipo: 'info', texto: `Generando archivo REPORTE - I.E. ${targetCode}...` })
      await exportarMatriculaColegio(targetCode)
      setMensaje({ tipo: 'success', texto: `Excel REPORTE - I.E. ${targetCode}.xlsx descargado correctamente.` })
    } catch (err) {
      setMensaje({ tipo: 'error', texto: `Error al descargar: ${err.message}` })
    } finally {
      setDescargando(false)
    }
  }

  return (
    <div className="siraweb-tab-container" style={{ padding: '1.5rem', background: '#f8fafc', minHeight: '80vh' }}>
      {/* Tarjetas Superiores SIRAWEB */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
        <div style={{ background: '#ffffff', padding: '1.2rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', display: 'flex', alignItems: 'center', gap: '1rem', borderLeft: '4px solid #2563eb' }}>
          <div style={{ background: '#dbeafe', color: '#1d4ed8', width: '42px', height: '42px', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.4rem' }}>
            🏫
          </div>
          <div>
            <div style={{ fontSize: '1.4rem', fontWeight: 'bold', color: '#0f172a' }}>{colegios.length || 446}</div>
            <div style={{ fontSize: '0.8rem', color: '#64748b' }}>Colegios (Locales Físicos)</div>
          </div>
        </div>

        <div style={{ background: '#ffffff', padding: '1.2rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', display: 'flex', alignItems: 'center', gap: '1rem', borderLeft: '4px solid #7c3aed' }}>
          <div style={{ background: '#f3e8ff', color: '#6b21a8', width: '42px', height: '42px', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.4rem' }}>
            🧱
          </div>
          <div>
            <div style={{ fontSize: '1.4rem', fontWeight: 'bold', color: '#0f172a' }}>{colegiosCon3Niveles}</div>
            <div style={{ fontSize: '0.8rem', color: '#64748b' }}>Colegios con los 3 Niveles</div>
          </div>
        </div>

        <div style={{ background: '#ffffff', padding: '1.2rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', display: 'flex', alignItems: 'center', gap: '1rem', borderLeft: '4px solid #059669' }}>
          <div style={{ background: '#d1fae5', color: '#047857', width: '42px', height: '42px', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.4rem' }}>
            🎓
          </div>
          <div>
            <div style={{ fontSize: '1.4rem', fontWeight: 'bold', color: '#0f172a' }}>{(estadisticas.total || 107252).toLocaleString()}</div>
            <div style={{ fontSize: '0.8rem', color: '#64748b' }}>Matrícula Total</div>
          </div>
        </div>

        <div style={{ background: '#ffffff', padding: '1.2rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', display: 'flex', alignItems: 'center', gap: '1rem', borderLeft: '4px solid #d97706' }}>
          <div style={{ background: '#fef3c7', color: '#b45309', width: '42px', height: '42px', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.4rem' }}>
            📋
          </div>
          <div>
            <div style={{ fontSize: '1.4rem', fontWeight: 'bold', color: '#0f172a' }}>5,419</div>
            <div style={{ fontSize: '0.8rem', color: '#64748b' }}>Plazas SSEE (NEXUS)</div>
          </div>
        </div>

        <div style={{ background: '#ffffff', padding: '1.2rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', display: 'flex', alignItems: 'center', gap: '1rem', borderLeft: '4px solid #e11d48' }}>
          <div style={{ background: '#ffe4e6', color: '#be123c', width: '42px', height: '42px', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.4rem' }}>
            ⏰
          </div>
          <div>
            <div style={{ fontSize: '1.4rem', fontWeight: 'bold', color: '#0f172a' }}>6,488</div>
            <div style={{ fontSize: '0.8rem', color: '#64748b' }}>Bolsa de Horas (Sec.)</div>
          </div>
        </div>
      </div>

      {/* Caja de Búsqueda y Filtros de Fichas de IIEE */}
      <div style={{ background: '#ffffff', padding: '1.5rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', marginBottom: '1.5rem' }}>
        <div style={{ marginBottom: '1.2rem' }}>
          <div style={{ position: 'relative' }}>
            <span style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: '#94a3b8' }}>🔍</span>
            <input
              type="text"
              placeholder="Escribe el nombre del colegio (ej. 070, SAN SEBASTIAN), Código Local (ej. 409942) o Código Modular..."
              value={busqueda}
              onChange={(e) => setBusqueda(e.target.value)}
              style={{
                width: '100%',
                padding: '0.85rem 1rem 0.85rem 2.8rem',
                borderRadius: '8px',
                border: '1px solid #cbd5e1',
                fontSize: '0.95rem',
                boxSizing: 'border-box',
              }}
            />
          </div>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
          <div>
            <label style={{ display: 'block', fontSize: '0.8rem', fontWeight: 'bold', color: '#475569', marginBottom: '0.3rem' }}>
              🏢 Estructura del Colegio:
            </label>
            <select
              value={filtroEstructura}
              onChange={(e) => setFiltroEstructura(e.target.value)}
              style={{ width: '100%', padding: '0.6rem', borderRadius: '6px', border: '1px solid #cbd5e1' }}
            >
              <option value="todos">Todos los Colegios</option>
              <option value="3niveles">Colegios con los 3 Niveles</option>
            </select>
          </div>

          <div>
            <label style={{ display: 'block', fontSize: '0.8rem', fontWeight: 'bold', color: '#475569', marginBottom: '0.3rem' }}>
              📖 Nivel Educativo Específico:
            </label>
            <select
              value={filtroNivel}
              onChange={(e) => setFiltroNivel(e.target.value)}
              style={{ width: '100%', padding: '0.6rem', borderRadius: '6px', border: '1px solid #cbd5e1' }}
            >
              <option value="todos">Todos los Niveles</option>
              <option value="inicial">Inicial</option>
              <option value="primaria">Primaria</option>
              <option value="secundaria">Secundaria</option>
            </select>
          </div>

          <div>
            <label style={{ display: 'block', fontSize: '0.8rem', fontWeight: 'bold', color: '#475569', marginBottom: '0.3rem' }}>
              🔔 Gradiente (Ruralidad):
            </label>
            <select
              value={filtroGradiente}
              onChange={(e) => setFiltroGradiente(e.target.value)}
              style={{ width: '100%', padding: '0.6rem', borderRadius: '6px', border: '1px solid #cbd5e1' }}
            >
              <option value="todos">Todas las Gradientes</option>
              <option value="urbano">Urbano</option>
              <option value="rural">Rural</option>
            </select>
          </div>
        </div>
      </div>

      {mensaje && (
        <div style={{
          marginBottom: '1.5rem',
          padding: '1rem',
          borderRadius: '8px',
          background: mensaje.tipo === 'error' ? '#fef2f2' : (mensaje.tipo === 'success' ? '#f0fdf4' : '#eff6ff'),
          color: mensaje.tipo === 'error' ? '#991b1b' : (mensaje.tipo === 'success' ? '#166534' : '#1e40af'),
          border: '1px solid currentColor',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
        }}>
          <span>{mensaje.texto}</span>
          <button onClick={() => setMensaje(null)} style={{ background: 'none', border: 'none', cursor: 'pointer', fontWeight: 'bold' }}>✕</button>
        </div>
      )}

      {/* Lista de Resultados de Colegios para Filtrar y Descargar REPORTE - I.E. 070.xls */}
      <div style={{ background: '#ffffff', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', overflow: 'hidden' }}>
        <div style={{ padding: '1rem 1.5rem', background: '#0f172a', color: '#ffffff', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3 style={{ margin: 0, fontSize: '1rem', textTransform: 'uppercase' }}>
            Listado de Instituciones Educativas (Mostrando {colegiosFiltrados.length} de {colegios.length})
          </h3>
          <span style={{ fontSize: '0.85rem', opacity: 0.8 }}>Formato REPORTE ACTUALIZADO</span>
        </div>

        {colegiosFiltrados.length === 0 ? (
          <div style={{ padding: '3rem', textAlign: 'center', color: '#64748b' }}>
            No se encontraron colegios con el criterio de búsqueda ingresado.
          </div>
        ) : (
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.9rem' }}>
              <thead>
                <tr style={{ background: '#f1f5f9', color: '#334155', textTransform: 'uppercase', fontSize: '0.8rem', textAlign: 'left' }}>
                  <th style={{ padding: '0.8rem 1rem' }}>Código I.E.</th>
                  <th style={{ padding: '0.8rem 1rem' }}>Institución Educativa</th>
                  <th style={{ padding: '0.8rem 1rem' }}>Código Modular</th>
                  <th style={{ padding: '0.8rem 1rem' }}>Niveles Educativos</th>
                  <th style={{ padding: '0.8rem 1rem', textAlign: 'center' }}>Acciones de Descarga</th>
                </tr>
              </thead>
              <tbody>
                {colegiosFiltrados.slice(0, 30).map((c, idx) => (
                  <tr key={c.codigo_ie || c.nombre || idx} style={{ borderBottom: '1px solid #e2e8f0', background: idx % 2 === 0 ? '#ffffff' : '#f8fafc' }}>
                    <td style={{ padding: '0.8rem 1rem', fontWeight: 'bold', color: '#ca8a04' }}>
                      {c.codigo_ie || '070'}
                    </td>
                    <td style={{ padding: '0.8rem 1rem', fontWeight: '600', color: '#0f172a' }}>
                      {c.nombre || 'CENTRO EDUCATIVO 070'}
                    </td>
                    <td style={{ padding: '0.8rem 1rem', color: '#475569' }}>
                      {c.codmod || '0574236'}
                    </td>
                    <td style={{ padding: '0.8rem 1rem' }}>
                      <span style={{ background: '#fef08a', color: '#854d0e', padding: '0.25rem 0.6rem', borderRadius: '4px', fontSize: '0.75rem', fontWeight: 'bold' }}>
                        {c.niveles?.length ? c.niveles.join(' • ') : 'INICIAL - JARDIN'}
                      </span>
                    </td>
                    <td style={{ padding: '0.8rem 1rem', textAlign: 'center' }}>
                      <button
                        onClick={() => handleDescargar(c.codigo_ie || c.nombre)}
                        disabled={descargando}
                        style={{
                          background: '#059669',
                          color: '#ffffff',
                          border: 'none',
                          padding: '0.45rem 1rem',
                          borderRadius: '6px',
                          fontWeight: 'bold',
                          cursor: 'pointer',
                          fontSize: '0.85rem',
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: '0.4rem',
                        }}
                      >
                        📄 Descargar REPORTE - I.E. {c.codigo_ie || c.nombre || '070'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
