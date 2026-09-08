import { useState, useMemo } from 'react'
import { descargarArchivo, descargarZip, exportarNexusColegio, exportarMatriculaColegio, exportarZipColegios } from '../services/api'

function GrupoArchivos({ titulo, lista, colorClass, descargando, onDescargar }) {
  return (
    <section className={`results-group ${colorClass}`}>
      <div className="group-title">
        <strong>{titulo}</strong>
        <span>{lista.length} archivo(s)</span>
      </div>

      <div className="files-list">
        {lista.map((a, i) => (
          <article key={i} className="file-row">
            <div className="file-info">
              <span className="file-icon">{a.nivel?.slice(0, 1) || 'R'}</span>
              <div>
                <strong>{a.distrito}</strong>
                <span>{a.registros} institucion(es) - {a.archivo}</span>
              </div>
            </div>
            <button
              className="btn-download"
              onClick={() => onDescargar(a)}
              disabled={descargando === a.ruta}
            >
              {descargando === a.ruta ? 'Descargando...' : 'Excel'}
            </button>
          </article>
        ))}
      </div>
    </section>
  )
}

export default function ResultsPanel({ archivos = [], errores = [], colegios = [], tipoExcel = 'MATRICULA' }) {
  const [subTab, setSubTab] = useState('distritos') // 'distritos' | 'colegios'
  const [descargando, setDescargando] = useState(null)
  const [busquedaColegio, setBusquedaColegio] = useState('')
  const [colegioSeleccionado, setColegioSeleccionado] = useState(colegios[0]?.codigo_ie || colegios[0]?.nombre || '')
  const [mensajeColegio, setMensajeColegio] = useState(null)

  const publicos = archivos.filter(a => a.modalidad === 'PUBLICO')
  const privados = archivos.filter(a => a.modalidad === 'PRIVADO')

  const colegiosFiltrados = useMemo(() => {
    if (!busquedaColegio.trim()) return colegios
    const q = busquedaColegio.toLowerCase()
    return colegios.filter(
      c =>
        c.nombre?.toLowerCase().includes(q) ||
        c.codigo_ie?.toLowerCase().includes(q) ||
        c.codmod?.toLowerCase().includes(q)
    )
  }, [colegios, busquedaColegio])

  const colegioActual = useMemo(() => {
    return colegios.find(c => (c.codigo_ie || c.nombre) === colegioSeleccionado) || colegiosFiltrados[0] || {}
  }, [colegios, colegioSeleccionado, colegiosFiltrados])

  const handleDescargarDistrito = async (archivo) => {
    setDescargando(archivo.ruta)
    try {
      await descargarArchivo(archivo.ruta, archivo.archivo)
    } finally {
      setDescargando(null)
    }
  }

  const handleZipDistritos = async () => {
    setDescargando('zip_distritos')
    try {
      await descargarZip()
    } finally {
      setDescargando(null)
    }
  }

  const handleDescargarColegio = async () => {
    const target = colegioActual.codigo_ie || colegioActual.nombre || colegioSeleccionado
    if (!target) return

    try {
      setDescargando(`colegio_${target}`)
      setMensajeColegio({ tipo: 'info', texto: `Generando archivo para la I.E. ${target}...` })

      if (tipoExcel === 'NEXUS') {
        await exportarNexusColegio(target)
        setMensajeColegio({ tipo: 'success', texto: `Excel NEXUS - ${target}.xlsx descargado correctamente con su banner azul.` })
      } else {
        await exportarMatriculaColegio(target)
        setMensajeColegio({ tipo: 'success', texto: `Excel REPORTE - I.E. ${target}.xlsx descargado correctamente con las 5 secciones coloreadas.` })
      }
    } catch (err) {
      setMensajeColegio({ tipo: 'error', texto: `Error al generar reporte: ${err.message}` })
    } finally {
      setDescargando(null)
    }
  }

  const handleZipColegios = async () => {
    try {
      setDescargando('zip_colegios')
      setMensajeColegio({ tipo: 'info', texto: `Empaquetando paquete ZIP con los ${colegios.length} colegios...` })

      await exportarZipColegios(tipoExcel)
      setMensajeColegio({ tipo: 'success', texto: `¡Paquete ZIP con los ${colegios.length} colegios descargado con éxito!` })
    } catch (err) {
      setMensajeColegio({ tipo: 'error', texto: `Error al generar ZIP por colegios: ${err.message}` })
    } finally {
      setDescargando(null)
    }
  }

  return (
    <div className="results-panel">
      {/* Pestañas de Navegación dentro de Descargas */}
      <div style={{
        display: 'flex',
        gap: '8px',
        borderBottom: '2px solid #e2e8f0',
        marginBottom: '20px',
        paddingBottom: '4px'
      }}>
        <button
          onClick={() => setSubTab('distritos')}
          style={{
            padding: '10px 20px',
            border: 'none',
            borderRadius: '6px 6px 0 0',
            backgroundColor: subTab === 'distritos' ? '#4f46e5' : '#f1f5f9',
            color: subTab === 'distritos' ? '#ffffff' : '#475569',
            fontWeight: 'bold',
            cursor: 'pointer',
            fontSize: '0.9rem'
          }}
        >
          📂 Archivos por Distrito ({archivos.length})
        </button>

        <button
          onClick={() => setSubTab('colegios')}
          style={{
            padding: '10px 20px',
            border: 'none',
            borderRadius: '6px 6px 0 0',
            backgroundColor: subTab === 'colegios' ? '#4f46e5' : '#f1f5f9',
            color: subTab === 'colegios' ? '#ffffff' : '#475569',
            fontWeight: 'bold',
            cursor: 'pointer',
            fontSize: '0.9rem',
            display: 'flex',
            alignItems: 'center',
            gap: '6px'
          }}
        >
          🏫 Filtrado por Colegio / IE ({colegios.length})
        </button>
      </div>

      {/* Sub-Pestaña 1: Archivos por Distrito */}
      {subTab === 'distritos' && (
        <>
          <div className="zip-row">
            <button
              className="btn-zip"
              onClick={handleZipDistritos}
              disabled={descargando === 'zip_distritos'}
            >
              {descargando === 'zip_distritos' ? 'Generando ZIP...' : 'Descargar paquete completo ZIP (Distritos)'}
            </button>
            <span className="zip-hint">
              {archivos.length} archivos agrupados por distrito y modalidad
            </span>
          </div>

          {publicos.length > 0 && (
            <GrupoArchivos titulo="Gestion publica por Distrito" lista={publicos} colorClass="grupo-publico" descargando={descargando} onDescargar={handleDescargarDistrito} />
          )}

          {privados.length > 0 && (
            <GrupoArchivos titulo="Gestion privada por Distrito" lista={privados} colorClass="grupo-privado" descargando={descargando} onDescargar={handleDescargarDistrito} />
          )}
        </>
      )}

      {/* Sub-Pestaña 2: Filtrado e Individualización por Colegio */}
      {subTab === 'colegios' && (
        <section style={{
          background: '#ffffff',
          border: '1px solid #e2e8f0',
          borderRadius: '10px',
          padding: '1.25rem',
          boxShadow: '0 1px 3px rgba(0,0,0,0.05)'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem', marginBottom: '1rem' }}>
            <div>
              <strong style={{ fontSize: '1.05rem', color: '#0f172a', display: 'block' }}>
                🏫 Descarga por Colegio / Institución Educativa
              </strong>
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>
                Descarga un colegio individual o el paquete comprimido ZIP con todos los colegios.
              </span>
            </div>

            <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
              <span style={{
                fontSize: '0.75rem',
                padding: '4px 10px',
                borderRadius: '6px',
                background: tipoExcel === 'NEXUS' ? '#1b365d' : '#16803d',
                color: '#ffffff',
                fontWeight: 'bold'
              }}>
                Formato: {tipoExcel === 'NEXUS' ? 'NEXUS (Banner Azul)' : 'REPORTE MATRÍCULA (5 Colores)'}
              </span>

              <button
                onClick={handleZipColegios}
                disabled={descargando === 'zip_colegios' || colegios.length === 0}
                style={{
                  backgroundColor: descargando === 'zip_colegios' ? '#94a3b8' : '#4f46e5',
                  color: '#ffffff',
                  padding: '8px 16px',
                  borderRadius: '6px',
                  border: 'none',
                  fontWeight: 'bold',
                  fontSize: '0.85rem',
                  cursor: descargando === 'zip_colegios' ? 'not-allowed' : 'pointer',
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '6px'
                }}
              >
                {descargando === 'zip_colegios' ? 'Generando ZIP Colegios...' : '📦 Descargar ZIP (Todos los Colegios)'}
              </button>
            </div>
          </div>

          <div style={{ display: 'flex', gap: '0.8rem', flexWrap: 'wrap', alignItems: 'center' }}>
            <input
              type="text"
              placeholder="🔍 Buscar por colegio o código..."
              value={busquedaColegio}
              onChange={(e) => setBusquedaColegio(e.target.value)}
              style={{ padding: '0.6rem 0.9rem', borderRadius: '6px', border: '1px solid #cbd5e1', flex: '1', minWidth: '220px', fontSize: '0.9rem' }}
            />

            <select
              value={colegioSeleccionado}
              onChange={(e) => setColegioSeleccionado(e.target.value)}
              style={{ padding: '0.6rem 0.9rem', borderRadius: '6px', border: '1px solid #cbd5e1', flex: '2', minWidth: '240px', fontWeight: 'bold', fontSize: '0.9rem' }}
            >
              {colegiosFiltrados.length === 0 ? (
                <option value="">No hay colegios encontrados</option>
              ) : (
                colegiosFiltrados.map((c, i) => (
                  <option key={i} value={c.codigo_ie || c.nombre}>
                    I.E. {c.nombre || c.codigo_ie} {c.codmod ? `(Cód. Mod. ${c.codmod})` : ''}
                  </option>
                ))
              )}
            </select>

            <button
              onClick={handleDescargarColegio}
              disabled={descargando?.startsWith('colegio_') || (!colegioActual.nombre && !colegioActual.codigo_ie)}
              style={{
                background: tipoExcel === 'NEXUS' ? '#1b365d' : '#16a34a',
                color: '#ffffff',
                padding: '0.6rem 1.2rem',
                borderRadius: '6px',
                border: 'none',
                fontWeight: 'bold',
                fontSize: '0.9rem',
                cursor: 'pointer',
                whiteSpace: 'nowrap'
              }}
            >
              {descargando?.startsWith('colegio_') ? 'Generando Excel...' : `Descargar ${tipoExcel === 'NEXUS' ? 'NEXUS' : 'REPORTE'} - ${colegioActual.nombre || colegioActual.codigo_ie || 'I.E.'}`}
            </button>
          </div>

          {mensajeColegio && (
            <div style={{
              marginTop: '0.85rem',
              padding: '0.65rem 0.85rem',
              borderRadius: '6px',
              fontSize: '0.85rem',
              background: mensajeColegio.tipo === 'error' ? '#fef2f2' : (mensajeColegio.tipo === 'success' ? '#f0fdf4' : '#eff6ff'),
              color: mensajeColegio.tipo === 'error' ? '#991b1b' : (mensajeColegio.tipo === 'success' ? '#166534' : '#1e40af'),
              border: '1px solid currentColor'
            }}>
              {mensajeColegio.texto}
            </div>
          )}
        </section>
      )}

      {errores && errores.length > 0 && (
        <details className="errores-detail" style={{ marginTop: '16px' }}>
          <summary>{errores.length} registro(s) omitido(s) por validacion</summary>
          <ul>
            {errores.map((e, i) => <li key={i}>{e}</li>)}
          </ul>
        </details>
      )}
    </div>
  )
}
