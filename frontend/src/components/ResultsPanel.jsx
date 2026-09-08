import { useState, useMemo } from 'react'
import { descargarArchivo, descargarZip, exportarNexusColegio, exportarMatriculaColegio } from '../services/api'

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

  const handleZip = async () => {
    setDescargando('zip')
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

  return (
    <div className="results-panel">
      {/* Fila de descarga masiva ZIP */}
      <div className="zip-row">
        <button
          className="btn-zip"
          onClick={handleZip}
          disabled={descargando === 'zip'}
        >
          {descargando === 'zip' ? 'Generando ZIP...' : 'Descargar paquete completo ZIP'}
        </button>
        <span className="zip-hint">
          {archivos.length} archivos agrupados por distrito y modalidad
        </span>
      </div>

      {/* Sección Agregada: Filtrar y Descargar por Colegio Individual */}
      <section style={{
        background: '#ffffff',
        border: '1px solid #e2e8f0',
        borderRadius: '10px',
        padding: '1.25rem',
        margin: '1.25rem 0',
        boxShadow: '0 1px 3px rgba(0,0,0,0.05)'
      }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem', marginBottom: '0.85rem' }}>
          <strong style={{ fontSize: '1rem', color: '#0f172a' }}>
            🏫 Descargar Reporte Individual por Colegio (I.E.)
          </strong>
          <span style={{ fontSize: '0.75rem', padding: '0.2rem 0.6rem', borderRadius: '4px', background: tipoExcel === 'NEXUS' ? '#1b365d' : '#ca8a04', color: '#ffffff', fontWeight: 'bold' }}>
            Formato: {tipoExcel === 'NEXUS' ? 'NEXUS (Banner Azul)' : 'REPORTE MATRÍCULA (5 Colores)'}
          </span>
        </div>

        <div style={{ display: 'flex', gap: '0.8rem', flexWrap: 'wrap', alignItems: 'center' }}>
          <input
            type="text"
            placeholder="🔍 Buscar por colegio o código (ej. 070, 15255)..."
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
              background: tipoExcel === 'NEXUS' ? '#1b365d' : '#ca8a04',
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

      {/* Grupos de descarga por distrito originales */}
      {publicos.length > 0 && (
        <GrupoArchivos titulo="Gestion publica por Distrito" lista={publicos} colorClass="grupo-publico" descargando={descargando} onDescargar={handleDescargarDistrito} />
      )}

      {privados.length > 0 && (
        <GrupoArchivos titulo="Gestion privada por Distrito" lista={privados} colorClass="grupo-privado" descargando={descargando} onDescargar={handleDescargarDistrito} />
      )}

      {errores && errores.length > 0 && (
        <details className="errores-detail">
          <summary>{errores.length} registro(s) omitido(s) por validacion</summary>
          <ul>
            {errores.map((e, i) => <li key={i}>{e}</li>)}
          </ul>
        </details>
      )}
    </div>
  )
}
