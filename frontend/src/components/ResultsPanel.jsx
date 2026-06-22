import { useState } from 'react'
import { descargarArchivo, descargarZip } from '../services/api'

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

export default function ResultsPanel({ archivos, errores }) {
  const [descargando, setDescargando] = useState(null)

  const publicos = archivos.filter(a => a.modalidad === 'PUBLICO')
  const privados = archivos.filter(a => a.modalidad === 'PRIVADO')

  const handleDescargar = async (archivo) => {
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

  return (
    <div className="results-panel">
      <div className="zip-row">
        <button
          className="btn-zip"
          onClick={handleZip}
          disabled={descargando === 'zip'}
        >
          {descargando === 'zip' ? 'Generando ZIP...' : 'Descargar carpeta ZIP'}
        </button>
        <span className="zip-hint">
          {archivos.length} archivos - {publicos.length} publicos - {privados.length} privados
        </span>
      </div>

      {publicos.length > 0 && (
        <GrupoArchivos titulo="Gestion publica" lista={publicos} colorClass="grupo-publico" descargando={descargando} onDescargar={handleDescargar} />
      )}

      {privados.length > 0 && (
        <GrupoArchivos titulo="Gestion privada" lista={privados} colorClass="grupo-privado" descargando={descargando} onDescargar={handleDescargar} />
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
