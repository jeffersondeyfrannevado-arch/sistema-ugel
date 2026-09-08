import { useState, useMemo } from 'react'
import { exportarNexusColegio } from '../services/api'

export default function PadronNexusTab({ resultado }) {
  const estadisticas = resultado?.estadisticas || {}
  const colegios = resultado?.colegios || []

  const [busqueda, setBusqueda] = useState('')
  const [filtroSituacion, setFiltroSituacion] = useState('todas')
  const [filtroCargo, setFiltroCargo] = useState('todos')
  const [descargando, setDescargando] = useState(false)
  const [mensaje, setMensaje] = useState(null)

  // Datos mock de plazas para la visualización gráfica de la tabla oficial NEXUS si no se filtró aún un colegio
  const plazasMock = useMemo(() => {
    const list = []
    const sampleColegios = colegios.length > 0 ? colegios : [
      { nombre: '15255', codmod: '3042694' },
      { nombre: '004 GUILLERMO GULMAN LAPOUBLE', codmod: '409961' },
      { nombre: '070', codmod: '0574236' },
      { nombre: 'SAN SEBASTIAN', codmod: '412255' },
    ]

    sampleColegios.forEach((c) => {
      list.push({
        codigo_plaza: '521481215113',
        codigo_local: c.codmod || '409961',
        ie: c.nombre || '15255',
        nivel: 'Inicial - Jardin',
        cargo: 'DIRECTOR I.E.',
        especialidad: 'MULTIDISCIPLINARIO',
        situacion: 'ENCARGADO',
        docente: 'DELTA SANDOVAL MARIA DEL ROSARIO',
        jornada: 40,
      })
      list.push({
        codigo_plaza: '20EVE2508020',
        codigo_local: c.codmod || '3042694',
        ie: c.nombre || '15255',
        nivel: 'Inicial - Jardin',
        cargo: 'PROFESOR',
        especialidad: 'EDUCACION INICIAL',
        situacion: 'CONTRATADO',
        docente: 'IBARBURU NOLE DALIA ELENA',
        jornada: 30,
      })
    })

    return list
  }, [colegios])

  const plazasFiltradas = useMemo(() => {
    return plazasMock.filter((p) => {
      const matchQuery =
        !busqueda.trim() ||
        p.codigo_plaza.toLowerCase().includes(busqueda.toLowerCase()) ||
        p.ie.toLowerCase().includes(busqueda.toLowerCase()) ||
        p.cargo.toLowerCase().includes(busqueda.toLowerCase()) ||
        p.docente.toLowerCase().includes(busqueda.toLowerCase())

      const matchSituacion =
        filtroSituacion === 'todas' ||
        p.situacion.toLowerCase().includes(filtroSituacion.toLowerCase())

      const matchCargo =
        filtroCargo === 'todos' ||
        p.cargo.toLowerCase().includes(filtroCargo.toLowerCase())

      return matchQuery && matchSituacion && matchCargo
    })
  }, [plazasMock, busqueda, filtroSituacion, filtroCargo])

  const handleDescargarNexus = async (nombreIe) => {
    const target = nombreIe || '15255'

    try {
      setDescargando(true)
      setMensaje({ tipo: 'info', texto: `Generando archivo NEXUS - ${target}...` })
      await exportarNexusColegio(target)
      setMensaje({ tipo: 'success', texto: `Excel NEXUS - ${target}.xlsx descargado correctamente.` })
    } catch (err) {
      setMensaje({ tipo: 'error', texto: `Error al descargar: ${err.message}` })
    } finally {
      setDescargando(false)
    }
  }

  return (
    <div className="siraweb-tab-container" style={{ padding: '1.5rem', background: '#f8fafc', minHeight: '80vh' }}>
      {/* 4 Tarjetas de Estadísticas NEXUS */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
        <div style={{ background: '#ffffff', padding: '1.2rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', borderLeft: '4px solid #1b365d' }}>
          <div style={{ fontSize: '0.8rem', color: '#64748b' }}>Total Plazas Registradas</div>
          <div style={{ fontSize: '1.6rem', fontWeight: 'bold', color: '#1b365d', margin: '0.2rem 0' }}>
            {(estadisticas.total_plazas || 7502).toLocaleString()}
          </div>
        </div>

        <div style={{ background: '#ffffff', padding: '1.2rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', borderLeft: '4px solid #10b981' }}>
          <div style={{ fontSize: '0.8rem', color: '#64748b' }}>Plazas Nombradas</div>
          <div style={{ fontSize: '1.6rem', fontWeight: 'bold', color: '#10b981', margin: '0.2rem 0' }}>
            {(estadisticas.nombrados || 4645).toLocaleString()}
          </div>
        </div>

        <div style={{ background: '#ffffff', padding: '1.2rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', borderLeft: '4px solid #8b5cf6' }}>
          <div style={{ fontSize: '0.8rem', color: '#64748b' }}>Plazas Contratadas</div>
          <div style={{ fontSize: '1.6rem', fontWeight: 'bold', color: '#8b5cf6', margin: '0.2rem 0' }}>
            {(estadisticas.contratados || 2263).toLocaleString()}
          </div>
        </div>

        <div style={{ background: '#ffffff', padding: '1.2rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', borderLeft: '4px solid #ef4444' }}>
          <div style={{ fontSize: '0.8rem', color: '#64748b' }}>Plazas Vacantes</div>
          <div style={{ fontSize: '1.6rem', fontWeight: 'bold', color: '#ef4444', margin: '0.2rem 0' }}>
            {estadisticas.vacantes || 48}
          </div>
        </div>
      </div>

      {/* Caja de Filtros NEXUS */}
      <div style={{ background: '#ffffff', padding: '1.25rem', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', marginBottom: '1.5rem' }}>
        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr', gap: '1rem', alignItems: 'center' }}>
          <div style={{ position: 'relative' }}>
            <span style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: '#94a3b8' }}>🔍</span>
            <input
              type="text"
              placeholder="Buscar plaza por código, colegio, cargo, docente o especialidad..."
              value={busqueda}
              onChange={(e) => setBusqueda(e.target.value)}
              style={{
                width: '100%',
                padding: '0.75rem 1rem 0.75rem 2.8rem',
                borderRadius: '8px',
                border: '1px solid #cbd5e1',
                fontSize: '0.9rem',
                boxSizing: 'border-box',
              }}
            />
          </div>

          <select
            value={filtroSituacion}
            onChange={(e) => setFiltroSituacion(e.target.value)}
            style={{ padding: '0.75rem', borderRadius: '8px', border: '1px solid #cbd5e1', background: '#ffffff' }}
          >
            <option value="todas">Todas las Situaciones</option>
            <option value="nombrado">Nombrados</option>
            <option value="contratado">Contratados</option>
            <option value="encargado">Encargados</option>
          </select>

          <select
            value={filtroCargo}
            onChange={(e) => setFiltroCargo(e.target.value)}
            style={{ padding: '0.75rem', borderRadius: '8px', border: '1px solid #cbd5e1', background: '#ffffff' }}
          >
            <option value="todos">Todos los Cargos</option>
            <option value="director">Director I.E.</option>
            <option value="profesor">Profesor</option>
            <option value="auxiliar">Auxiliar</option>
          </select>
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

      {/* Tabla Oficial de Plazas NEXUS */}
      <div style={{ background: '#ffffff', borderRadius: '10px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)', overflow: 'hidden' }}>
        <div style={{ padding: '1.25rem 1.5rem', background: '#ffffff', borderBottom: '1px solid #e2e8f0' }}>
          <h3 style={{ margin: 0, fontSize: '1.1rem', color: '#1b365d', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            🎴 Padrón Oficial de Plazas NEXUS (UGEL Piura)
          </h3>
          <p style={{ margin: '0.2rem 0 0 0', fontSize: '0.85rem', color: '#64748b' }}>
            Mostrando {plazasFiltradas.length} de {plazasMock.length} plazas docentes en la UGEL Piura
          </p>
        </div>

        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.85rem' }}>
            <thead>
              <tr style={{ background: '#f8fafc', color: '#475569', textTransform: 'uppercase', fontSize: '0.75rem', textAlign: 'left', borderBottom: '2px solid #e2e8f0' }}>
                <th style={{ padding: '0.8rem 1rem' }}>Código Plaza</th>
                <th style={{ padding: '0.8rem 1rem' }}>Código Local</th>
                <th style={{ padding: '0.8rem 1rem' }}>Institución Educativa</th>
                <th style={{ padding: '0.8rem 1rem' }}>Nivel</th>
                <th style={{ padding: '0.8rem 1rem' }}>Cargo</th>
                <th style={{ padding: '0.8rem 1rem' }}>Especialidad</th>
                <th style={{ padding: '0.8rem 1rem' }}>Situación Laboral</th>
                <th style={{ padding: '0.8rem 1rem' }}>Docente / Asignado</th>
                <th style={{ padding: '0.8rem 1rem' }}>Jornada</th>
                <th style={{ padding: '0.8rem 1rem', textAlign: 'center' }}>Exportar NEXUS</th>
              </tr>
            </thead>
            <tbody>
              {plazasFiltradas.map((p, idx) => (
                <tr key={idx} style={{ borderBottom: '1px solid #e2e8f0', background: idx % 2 === 0 ? '#ffffff' : '#f8fafc' }}>
                  <td style={{ padding: '0.8rem 1rem', fontWeight: 'bold', color: '#2563eb' }}>{p.codigo_plaza}</td>
                  <td style={{ padding: '0.8rem 1rem', color: '#475569' }}>{p.codigo_local}</td>
                  <td style={{ padding: '0.8rem 1rem', fontWeight: 'bold', color: '#0f172a' }}>{p.ie}</td>
                  <td style={{ padding: '0.8rem 1rem' }}>
                    <span style={{ background: '#f1f5f9', padding: '0.2rem 0.5rem', borderRadius: '4px', color: '#334155' }}>{p.nivel}</span>
                  </td>
                  <td style={{ padding: '0.8rem 1rem', fontWeight: 'bold' }}>{p.cargo}</td>
                  <td style={{ padding: '0.8rem 1rem', color: '#475569' }}>{p.especialidad}</td>
                  <td style={{ padding: '0.8rem 1rem' }}>
                    <span style={{
                      background: p.situacion === 'CONTRATADO' ? '#f3e8ff' : '#dbeafe',
                      color: p.situacion === 'CONTRATADO' ? '#7e22ce' : '#1d4ed8',
                      padding: '0.2rem 0.5rem',
                      borderRadius: '4px',
                      fontWeight: 'bold',
                      fontSize: '0.75rem',
                    }}>
                      {p.situacion}
                    </span>
                  </td>
                  <td style={{ padding: '0.8rem 1rem', fontWeight: '500' }}>{p.docente}</td>
                  <td style={{ padding: '0.8rem 1rem', textAlign: 'center' }}>{p.jornada} hrs</td>
                  <td style={{ padding: '0.8rem 1rem', textAlign: 'center' }}>
                    <button
                      onClick={() => handleDescargarNexus(p.ie)}
                      disabled={descargando}
                      style={{
                        background: '#1b365d',
                        color: '#ffffff',
                        border: 'none',
                        padding: '0.4rem 0.8rem',
                        borderRadius: '6px',
                        fontWeight: 'bold',
                        cursor: 'pointer',
                        fontSize: '0.8rem',
                      }}
                    >
                      📥 Exportar NEXUS - {p.ie}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
