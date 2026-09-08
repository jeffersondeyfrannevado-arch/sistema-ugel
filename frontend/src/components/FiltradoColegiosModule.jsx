import { useState, useMemo } from 'react'
import { procesarFiltradoColegio, exportarFiltradoColegio, exportarZipColegios } from '../services/api'

export default function FiltradoColegiosModule() {
  const [tabActiva, setTabActiva] = useState('nexus') // 'nexus' | 'matricula'

  const [archivo, setArchivo] = useState(null)
  const [cargando, setCargando] = useState(false)
  const [errorMsg, setErrorMsg] = useState(null)
  const [resultado, setResultado] = useState(null)

  const [busqueda, setBusqueda] = useState('')
  const [colegioSeleccionado, setColegioSeleccionado] = useState('')
  const [descargando, setDescargando] = useState(false)
  const [descargandoZip, setDescargandoZip] = useState(false)
  const [exitoMsg, setExitoMsg] = useState(null)

  const handleCambiarTab = (nuevaTab) => {
    setTabActiva(nuevaTab)
    setArchivo(null)
    setCargando(false)
    setErrorMsg(null)
    setResultado(null)
    setBusqueda('')
    setColegioSeleccionado('')
    setExitoMsg(null)
  }

  const handleFileChange = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return

    setArchivo(file)
    setCargando(true)
    setErrorMsg(null)
    setResultado(null)
    setColegioSeleccionado('')
    setExitoMsg(null)

    try {
      const res = await procesarFiltradoColegio(file)
      if (res.success && res.data) {
        setResultado(res.data)

        // Sincronizar la pestaña si se detectó el otro tipo
        if (res.data.tipo_excel === 'NEXUS' && tabActiva !== 'nexus') {
          setTabActiva('nexus')
        } else if (res.data.tipo_excel === 'MATRICULA' && tabActiva !== 'matricula') {
          setTabActiva('matricula')
        }

        if (res.data.colegios && res.data.colegios.length > 0) {
          const primerColegio = res.data.colegios[0]
          setColegioSeleccionado(primerColegio.nombre || primerColegio.codigo_ie || '')
        }
      } else {
        throw new Error(res.mensaje || 'Error al procesar el archivo')
      }
    } catch (err) {
      setErrorMsg(err.message || 'No se pudo analizar el archivo subido')
    } finally {
      setCargando(false)
    }
  }

  const handleReset = () => {
    setArchivo(null)
    setCargando(false)
    setErrorMsg(null)
    setResultado(null)
    setBusqueda('')
    setColegioSeleccionado('')
    setExitoMsg(null)
  }

  const colegiosFiltrados = useMemo(() => {
    if (!resultado || !resultado.colegios) return []
    if (!busqueda.trim()) return resultado.colegios

    const q = busqueda.toUpperCase().trim()
    return resultado.colegios.filter((c) => {
      const nom = (c.nombre || '').toUpperCase()
      const cod = (c.codigo_ie || '').toUpperCase()
      const mod = (c.codmod || '').toUpperCase()
      return nom.includes(q) || cod.includes(q) || mod.includes(q)
    })
  }, [resultado, busqueda])

  const handleDescargar = async () => {
    if (!colegioSeleccionado || !resultado) return

    setDescargando(true)
    setErrorMsg(null)
    setExitoMsg(null)

    try {
      await exportarFiltradoColegio(colegioSeleccionado, resultado.tipo_excel)
      setExitoMsg(`¡Archivo para "${colegioSeleccionado}" generado y descargado con éxito!`)
    } catch (err) {
      setErrorMsg(err.message || 'Error al generar la descarga del colegio seleccionado')
    } finally {
      setDescargando(false)
    }
  }

  const handleDescargarZip = async () => {
    if (!resultado) return

    setDescargandoZip(true)
    setErrorMsg(null)
    setExitoMsg(null)

    try {
      await exportarZipColegios(resultado.tipo_excel)
      setExitoMsg(`¡Paquete ZIP con los ${resultado.total_colegios} colegios generado y descargado con éxito!`)
    } catch (err) {
      setErrorMsg(err.message || 'Error al generar el paquete ZIP de colegios')
    } finally {
      setDescargandoZip(false)
    }
  }

  const esNexus = tabActiva === 'nexus'

  return (
    <section className="step-card" style={{ maxWidth: '1000px', margin: '0 auto' }}>
      <div className="step-head">
        <div className="step-label" style={{
          background: esNexus ? '#1b365d' : '#15803d',
          color: '#fff',
          padding: '4px 14px',
          borderRadius: '6px',
          display: 'inline-block',
          fontWeight: 'bold'
        }}>
          Módulo de Filtrado por Colegio ({esNexus ? 'NEXUS - Plazas' : 'REPORTE - Matrícula'})
        </div>
        <p style={{ marginTop: '8px', color: '#64748b' }}>
          Selecciona el tipo de Excel que deseas filtrar. Cada pestaña genera su formato oficial independiente sin mezclar información.
        </p>
      </div>

      {/* Pestañas Principales para los 2 Tipos de Excel */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: '1fr 1fr',
        gap: '12px',
        marginTop: '20px',
        marginBottom: '20px'
      }}>
        {/* Pestaña 1: NEXUS */}
        <button
          type="button"
          onClick={() => handleCambiarTab('nexus')}
          style={{
            padding: '16px 20px',
            borderRadius: '10px',
            border: tabActiva === 'nexus' ? '2px solid #1b365d' : '1px solid #cbd5e1',
            backgroundColor: tabActiva === 'nexus' ? '#1b365d' : '#ffffff',
            color: tabActiva === 'nexus' ? '#ffffff' : '#334155',
            textAlign: 'left',
            cursor: 'pointer',
            transition: 'all 0.2s ease',
            boxShadow: tabActiva === 'nexus' ? '0 4px 12px rgba(27, 54, 93, 0.25)' : 'none'
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
            <span style={{ fontSize: '1.2rem' }}>📊</span>
            <strong style={{ fontSize: '1rem' }}>Pestaña 1: Excel NEXUS (Plazas)</strong>
          </div>
          <p style={{ fontSize: '0.8rem', margin: 0, opacity: 0.9 }}>
            Banner Azul Oficial UGEL Piura (Filas 1-2), cabecera azul marino y 66 columnas (A-BN).
          </p>
        </button>

        {/* Pestaña 2: REPORTE MATRÍCULA */}
        <button
          type="button"
          onClick={() => handleCambiarTab('matricula')}
          style={{
            padding: '16px 20px',
            borderRadius: '10px',
            border: tabActiva === 'matricula' ? '2px solid #15803d' : '1px solid #cbd5e1',
            backgroundColor: tabActiva === 'matricula' ? '#15803d' : '#ffffff',
            color: tabActiva === 'matricula' ? '#ffffff' : '#334155',
            textAlign: 'left',
            cursor: 'pointer',
            transition: 'all 0.2s ease',
            boxShadow: tabActiva === 'matricula' ? '0 4px 12px rgba(21, 128, 61, 0.25)' : 'none'
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
            <span style={{ fontSize: '1.2rem' }}>📋</span>
            <strong style={{ fontSize: '1rem' }}>Pestaña 2: Reporte Matrícula (3 Niveles)</strong>
          </div>
          <p style={{ fontSize: '0.8rem', margin: 0, opacity: 0.9 }}>
            Formato oficial de 5 secciones coloreadas (Blanco, Amarillo claro, Amarillo brillante, Verde, Rosa).
          </p>
        </button>
      </div>

      {/* Zona de Carga de Archivo específica para la pestaña activa */}
      {!resultado && (
        <div style={{
          border: `2px dashed ${esNexus ? '#94a3b8' : '#86efac'}`,
          borderRadius: '12px',
          padding: '36px 24px',
          textAlign: 'center',
          backgroundColor: esNexus ? '#f8fafc' : '#f0fdf4',
          transition: 'all 0.2s ease'
        }}>
          {cargando ? (
            <div style={{ padding: '20px' }}>
              <div className="spinner" style={{ margin: '0 auto 12px auto' }} />
              <p style={{ fontWeight: '600', color: '#334155' }}>
                Analizando y extrayendo colegios del Excel {esNexus ? 'NEXUS' : 'REPORTE MATRÍCULA'}...
              </p>
              <span style={{ fontSize: '0.85rem', color: '#64748b' }}>Por favor espera un momento</span>
            </div>
          ) : (
            <>
              <svg style={{ width: '48px', height: '48px', margin: '0 auto 12px auto', color: esNexus ? '#1b365d' : '#15803d' }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <h3 style={{ fontSize: '1.1rem', fontWeight: '700', color: '#1e293b', marginBottom: '4px' }}>
                Sube tu archivo Excel para {esNexus ? 'NEXUS (Plazas)' : 'REPORTE DE MATRÍCULA'}
              </h3>
              <p style={{ fontSize: '0.9rem', color: '#64748b', marginBottom: '16px' }}>
                {esNexus
                  ? 'Carga el archivo nexus.xlsx para generar reportes individuales de plazas.'
                  : 'Carga el archivo REPORTE ACTUALIZADO.xls para generar reportes individuales de matrícula (5 colores).'
                }
              </p>
              <label style={{
                display: 'inline-block',
                backgroundColor: esNexus ? '#1b365d' : '#15803d',
                color: '#ffffff',
                padding: '10px 24px',
                borderRadius: '8px',
                fontWeight: '600',
                cursor: 'pointer',
                boxShadow: esNexus ? '0 2px 4px rgba(27, 54, 93, 0.3)' : '0 2px 4px rgba(21, 128, 61, 0.3)'
              }}>
                Elegir archivo Excel {esNexus ? 'NEXUS' : 'REPORTE'}
                <input type="file" accept=".xlsx,.xls" onChange={handleFileChange} hidden />
              </label>
            </>
          )}
        </div>
      )}

      {/* Banner de Error */}
      {errorMsg && (
        <div style={{
          backgroundColor: '#fef2f2',
          border: '1px solid #fecaca',
          color: '#991b1b',
          padding: '12px 16px',
          borderRadius: '8px',
          marginTop: '16px',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center'
        }}>
          <div>
            <strong>Error: </strong> {errorMsg}
          </div>
          <button onClick={() => setErrorMsg(null)} style={{ background: 'transparent', border: 'none', color: '#991b1b', cursor: 'pointer', fontWeight: 'bold' }}>✕</button>
        </div>
      )}

      {/* Banner de Éxito */}
      {exitoMsg && (
        <div style={{
          backgroundColor: '#f0fdf4',
          border: '1px solid #bbf7d0',
          color: '#166534',
          padding: '12px 16px',
          borderRadius: '8px',
          marginTop: '16px',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center'
        }}>
          <div>
            <strong>¡Éxito! </strong> {exitoMsg}
          </div>
          <button onClick={() => setExitoMsg(null)} style={{ background: 'transparent', border: 'none', color: '#166534', cursor: 'pointer', fontWeight: 'bold' }}>✕</button>
        </div>
      )}

      {/* Resultados de Procesamiento de la Pestaña Activa */}
      {resultado && (
        <div style={{ marginTop: '24px' }}>
          {/* Header de Info del Archivo Detectado */}
          <div style={{
            display: 'flex',
            flexWrap: 'wrap',
            justifyContent: 'space-between',
            alignItems: 'center',
            backgroundColor: '#f1f5f9',
            padding: '16px 20px',
            borderRadius: '10px',
            gap: '12px'
          }}>
            <div>
              <span style={{ fontSize: '0.8rem', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.5px', color: '#64748b' }}>
                Tipo Detectado:
              </span>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginTop: '4px' }}>
                <span style={{
                  backgroundColor: resultado.tipo_excel === 'NEXUS' ? '#1b365d' : '#15803d',
                  color: '#fff',
                  padding: '4px 10px',
                  borderRadius: '6px',
                  fontWeight: '700',
                  fontSize: '0.85rem'
                }}>
                  {resultado.tipo_excel}
                </span>
                <span style={{ fontWeight: '600', color: '#1e293b', fontSize: '1rem' }}>
                  {resultado.nombre_tipo}
                </span>
              </div>
            </div>

            <div style={{ display: 'flex', gap: '16px', alignItems: 'center' }}>
              <div style={{ textAlign: 'right' }}>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>Total Colegios:</span>
                <div style={{ fontWeight: '700', fontSize: '1.2rem', color: '#0f172a' }}>
                  {resultado.total_colegios}
                </div>
              </div>
              <button
                onClick={handleReset}
                style={{
                  backgroundColor: '#ffffff',
                  border: '1px solid #cbd5e1',
                  color: '#475569',
                  padding: '8px 16px',
                  borderRadius: '6px',
                  fontWeight: '600',
                  cursor: 'pointer',
                  fontSize: '0.85rem'
                }}
              >
                Cargar otro archivo
              </button>
            </div>
          </div>

          {/* Formato Visual Previa Indicador */}
          <div style={{
            marginTop: '16px',
            padding: '12px 16px',
            backgroundColor: '#ffffff',
            border: '1px solid #e2e8f0',
            borderRadius: '8px',
            fontSize: '0.85rem',
            color: '#475569'
          }}>
            <strong>Formato de Exportación que se aplicará:</strong>
            {resultado.tipo_excel === 'NEXUS' ? (
              <div style={{ marginTop: '6px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <span style={{ backgroundColor: '#1b365d', color: '#fff', padding: '2px 8px', borderRadius: '4px', fontSize: '0.75rem', fontWeight: 'bold' }}>
                  BANNER AZUL NEXUS (Filas 1-2)
                </span>
                <span>Enfocado en UGEL Piura + Cabecera Oscura y 66 columnas completas</span>
              </div>
            ) : (
              <div style={{ marginTop: '6px', display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                <span style={{ backgroundColor: '#ffffff', border: '1px solid #cbd5e1', color: '#000', padding: '2px 6px', borderRadius: '4px', fontSize: '0.75rem' }}>
                  1. Detalle IE (Blanco + CÓDIGO IE Amarillo)
                </span>
                <span style={{ backgroundColor: '#fff2cc', border: '1px solid #fef08a', color: '#854d0e', padding: '2px 6px', borderRadius: '4px', fontSize: '0.75rem' }}>
                  2. Matrícula (Amarillo Claro)
                </span>
                <span style={{ backgroundColor: '#ffff00', color: '#000', padding: '2px 6px', borderRadius: '4px', fontSize: '0.75rem', fontWeight: 'bold' }}>
                  3. Info 2025 + Comentario (Amarillo)
                </span>
                <span style={{ backgroundColor: '#d9ead3', border: '1px solid #bbf7d0', color: '#166534', padding: '2px 6px', borderRadius: '4px', fontSize: '0.75rem' }}>
                  4. Plazas y Bolsa (Verde)
                </span>
                <span style={{ backgroundColor: '#f4cccc', border: '1px solid #fecaca', color: '#991b1b', padding: '2px 6px', borderRadius: '4px', fontSize: '0.75rem' }}>
                  5. Fecha Bases (Rosa)
                </span>
              </div>
            )}
          </div>

          {/* Selector de Colegio & Buscador */}
          <div style={{
            marginTop: '20px',
            padding: '20px',
            backgroundColor: '#ffffff',
            border: '1px solid #e2e8f0',
            borderRadius: '10px',
            boxShadow: '0 1px 3px rgba(0,0,0,0.05)'
          }}>
            <h4 style={{ fontSize: '1rem', fontWeight: '700', color: '#1e293b', marginBottom: '12px' }}>
              Seleccionar Institución Educativa / Colegio ({resultado.tipo_excel})
            </h4>

            {/* Buscador */}
            <div style={{ marginBottom: '16px' }}>
              <input
                type="text"
                placeholder="🔍 Buscar por nombre de colegio, código IE o código modular..."
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                style={{
                  width: '100%',
                  padding: '10px 14px',
                  borderRadius: '6px',
                  border: '1px solid #cbd5e1',
                  fontSize: '0.9rem',
                  outline: 'none'
                }}
              />
            </div>

            {/* Select Dropdown */}
            <div style={{ marginBottom: '20px' }}>
              <label style={{ display: 'block', fontSize: '0.85rem', fontWeight: '600', color: '#475569', marginBottom: '6px' }}>
                Colegio a exportar ({colegiosFiltrados.length} encontrados):
              </label>
              <select
                value={colegioSeleccionado}
                onChange={(e) => setColegioSeleccionado(e.target.value)}
                style={{
                  width: '100%',
                  padding: '10px 14px',
                  borderRadius: '6px',
                  border: '1px solid #cbd5e1',
                  fontSize: '0.95rem',
                  fontWeight: '600',
                  color: '#0f172a',
                  backgroundColor: '#f8fafc',
                  cursor: 'pointer'
                }}
              >
                {colegiosFiltrados.map((item, idx) => {
                  const val = item.nombre || item.codigo_ie || item.codmod
                  const label = item.nombre
                    ? `${item.nombre} ${item.codigo_ie ? `(Código: ${item.codigo_ie})` : ''}`
                    : `CÓDIGO: ${item.codigo_ie || item.codmod}`

                  return (
                    <option key={idx} value={val}>
                      {label}
                    </option>
                  )
                })}
              </select>
            </div>

            {/* Botones de Descarga: Individual y Masiva ZIP */}
            <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end', flexWrap: 'wrap' }}>
              <button
                onClick={handleDescargarZip}
                disabled={descargandoZip || descargando}
                style={{
                  backgroundColor: descargandoZip ? '#94a3b8' : (esNexus ? '#1b365d' : '#15803d'),
                  color: '#ffffff',
                  padding: '12px 24px',
                  borderRadius: '8px',
                  fontWeight: '700',
                  fontSize: '0.95rem',
                  border: 'none',
                  cursor: descargandoZip ? 'not-allowed' : 'pointer',
                  boxShadow: '0 2px 4px rgba(0,0,0,0.15)',
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '8px'
                }}
              >
                {descargandoZip ? (
                  <>
                    <span className="spinner" style={{ width: '16px', height: '16px', borderWidth: '2px' }} />
                    Empaquetando ZIP con {resultado.total_colegios} colegios...
                  </>
                ) : (
                  <>
                    <svg style={{ width: '20px', height: '20px' }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    Descargar Paquete ZIP ({resultado.total_colegios} Colegios)
                  </>
                )}
              </button>

              <button
                onClick={handleDescargar}
                disabled={descargando || descargandoZip || !colegioSeleccionado}
                style={{
                  backgroundColor: descargando || !colegioSeleccionado ? '#94a3b8' : '#16a34a',
                  color: '#ffffff',
                  padding: '12px 28px',
                  borderRadius: '8px',
                  fontWeight: '700',
                  fontSize: '0.95rem',
                  border: 'none',
                  cursor: descargando || !colegioSeleccionado ? 'not-allowed' : 'pointer',
                  boxShadow: '0 2px 4px rgba(22, 163, 74, 0.2)',
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '8px'
                }}
              >
                {descargando ? (
                  <>
                    <span className="spinner" style={{ width: '16px', height: '16px', borderWidth: '2px' }} />
                    Generando Excel del Colegio...
                  </>
                ) : (
                  <>
                    <svg style={{ width: '20px', height: '20px' }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar Excel de {colegioSeleccionado || 'Colegio'}
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </section>
  )
}
