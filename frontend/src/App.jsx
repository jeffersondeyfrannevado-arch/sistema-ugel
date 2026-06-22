import { useEffect, useMemo, useRef, useState } from 'react'
import UploadZone from './components/UploadZone'
import ColumnSelector from './components/ColumnSelector'
import PreviewTable from './components/PreviewTable'
import ResultsPanel from './components/ResultsPanel'
import StatsCard from './components/StatsCard'
import Login from './components/Login'
import TrainingPanel from './components/TrainingPanel'
import AdminControlPanel from './components/AdminControlPanel'
import { previewArchivo, procesarArchivo, logout, getCurrentUser, refreshToken } from './services/api'
import './App.css'

function DashboardPanel({ resultado }) {
  const estadisticas = resultado.estadisticas
  const total = Math.max(estadisticas.total, 1)
  const publicos = estadisticas.publicos
  const privados = estadisticas.privados
  const errores = estadisticas.errores

  const donutStyle = {
    background: `conic-gradient(
      #4f46e5 0 ${(publicos / total) * 100}%,
      #8b5cf6 ${(publicos / total) * 100}% ${((publicos + privados) / total) * 100}%,
      #cbd5e1 ${((publicos + privados) / total) * 100}% 100%
    )`,
  }

  const barras = [
    { label: 'Publicas', value: publicos, colorClass: 'chart-fill-blue' },
    { label: 'Privadas', value: privados, colorClass: 'chart-fill-indigo' },
    { label: 'Omitidos', value: errores, colorClass: 'chart-fill-muted' },
  ]

  const maxBar = Math.max(...barras.map(item => item.value), 1)

  const topDistritos = Object.values(
    resultado.archivos.reduce((acc, item) => {
      if (!acc[item.distrito]) {
        acc[item.distrito] = { distrito: item.distrito, registros: 0 }
      }
      acc[item.distrito].registros += item.registros
      return acc
    }, {})
  )
    .sort((a, b) => b.registros - a.registros)
    .slice(0, 5)

  const maxDistrito = Math.max(...topDistritos.map(item => item.registros), 1)

  return (
    <section className="step-card dashboard-card">
      <div className="step-head">
        <div className="step-label"><span>03</span> Dashboard</div>
        <p>Visualizacion resumida del archivo procesado con datos exactos del proceso actual.</p>
      </div>

      <div className="dashboard-grid">
        <article className="dashboard-panel">
          <div className="panel-title-row">
            <h3>Distribucion general</h3>
            <span>{estadisticas.total} registros validos</span>
          </div>

          <div className="donut-layout">
            <div className="donut-chart" style={donutStyle}>
              <div className="donut-center">
                <strong>{estadisticas.total}</strong>
                <span>Total</span>
              </div>
            </div>

            <div className="chart-legend">
              <div className="legend-item">
                <span className="legend-dot legend-blue" />
                <div>
                  <strong>{publicos}</strong>
                  <span>Instituciones publicas</span>
                </div>
              </div>
              <div className="legend-item">
                <span className="legend-dot legend-indigo" />
                <div>
                  <strong>{privados}</strong>
                  <span>Instituciones privadas</span>
                </div>
              </div>
              <div className="legend-item">
                <span className="legend-dot legend-muted" />
                <div>
                  <strong>{errores}</strong>
                  <span>Registros omitidos</span>
                </div>
              </div>
            </div>
          </div>
        </article>

        <article className="dashboard-panel">
          <div className="panel-title-row">
            <h3>Comparativo por categoria</h3>
            <span>Valores absolutos</span>
          </div>

          <div className="bar-list">
            {barras.map(item => (
              <div key={item.label} className="bar-item">
                <div className="bar-meta">
                  <span>{item.label}</span>
                  <strong>{item.value}</strong>
                </div>
                <div className="bar-track">
                  <div
                    className={`bar-fill ${item.colorClass}`}
                    style={{ width: `${(item.value / maxBar) * 100}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </article>

        <article className="dashboard-panel dashboard-panel-wide">
          <div className="panel-title-row">
            <h3>Distritos con mayor volumen</h3>
            <span>Top 5 por instituciones agrupadas</span>
          </div>

          <div className="district-list">
            {topDistritos.map(item => (
              <div key={item.distrito} className="district-item">
                <div className="district-head">
                  <strong>{item.distrito}</strong>
                  <span>{item.registros}</span>
                </div>
                <div className="bar-track">
                  <div
                    className="bar-fill chart-fill-blue"
                    style={{ width: `${(item.registros / maxDistrito) * 100}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </article>
      </div>
    </section>
  )
}

export default function App() {
  const [user, setUser] = useState(() => {
    const saved = localStorage.getItem('user')
    return saved ? JSON.parse(saved) : null
  })

  const [archivo, setArchivo] = useState(null)
  const [columnasResaltadas, setColumnasResaltadas] = useState(['Matricula En Proceso'])
  const [estado, setEstado] = useState('idle')
  const [resultado, setResultado] = useState(null)
  const [errMsg, setErrMsg] = useState('')
  const [preview, setPreview] = useState(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [navActivo, setNavActivo] = useState('procesamiento')

  const heroRef = useRef(null)
  const procesoRef = useRef(null)
  const dashboardRef = useRef(null)
  const descargasRef = useRef(null)
  const previewRef = useRef(null)

  const nivelProcesado = resultado?.nivel || 'Inicial / Primaria / Secundaria'
  const isAdmin = (user?.permissions || []).includes('admin.dashboard.view')
  const canManageFormats = (user?.permissions || []).includes('admin.formats.manage')

  const dashboardDisponible = estado === 'listo' && resultado
  const previewDisponible = estado === 'listo' && preview

  const resumenTarjetas = useMemo(() => {
    if (!resultado) return []

    return [
      { label: 'Total de registros', value: resultado.estadisticas.total, icon: 'RG', color: 'blue' },
      { label: 'Instituciones publicas', value: resultado.estadisticas.publicos, icon: 'PU', color: 'blue' },
      { label: 'Instituciones privadas', value: resultado.estadisticas.privados, icon: 'PR', color: 'sand' },
      { label: 'Distritos detectados', value: resultado.estadisticas.distritos, icon: 'DT', color: 'green' },
      { label: 'Registros omitidos', value: resultado.estadisticas.errores, icon: 'ER', color: 'red' },
    ]
  }, [resultado])

  const handleProcesar = async () => {
    if (!archivo) return

    setEstado('procesando')
    setErrMsg('')

    try {
      const data = await procesarArchivo(archivo, columnasResaltadas)
      setResultado(data)
      setEstado('listo')
      setNavActivo('dashboard')
      setTimeout(() => dashboardRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50)
    } catch (e) {
      if (e.message === 'No autorizado') {
        handleLogout()
      } else {
        setErrMsg(e.message || 'Error desconocido')
        setEstado('error')
      }
    }
  }

  const handleReset = () => {
    setArchivo(null)
    setResultado(null)
    setPreview(null)
    setPreviewLoading(false)
    setEstado('idle')
    setErrMsg('')
    setNavActivo('procesamiento')
    setTimeout(() => heroRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50)
  }

  const handleLogout = async () => {
    try {
      await logout()
    } catch (e) {
      console.error(e)
    } finally {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      setUser(null)
    }
  }

  const handleCurrentUserChange = (updatedUser) => {
    setUser(updatedUser)
    localStorage.setItem('user', JSON.stringify(updatedUser))
  }

  useEffect(() => {
    let cancelled = false

    async function refreshUser() {
      const token = localStorage.getItem('auth_token')
      if (!token) return

      try {
        const current = await getCurrentUser()
        if (!cancelled) {
          setUser(current)
          localStorage.setItem('user', JSON.stringify(current))
        }
      } catch {
        if (!cancelled) {
          localStorage.removeItem('auth_token')
          localStorage.removeItem('user')
          setUser(null)
        }
      }
    }

    refreshUser()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    const expiresAt = localStorage.getItem('auth_expires_at')
    const token = localStorage.getItem('auth_token')
    if (!token || !expiresAt) return undefined

    const msUntilRefresh = new Date(expiresAt).getTime() - Date.now() - (5 * 60 * 1000)
    const timeout = window.setTimeout(async () => {
      try {
        const data = await refreshToken()
        localStorage.setItem('auth_token', data.access_token)
        if (data.expires_at) {
          localStorage.setItem('auth_expires_at', data.expires_at)
        }
      } catch {
        handleLogout()
      }
    }, Math.max(msUntilRefresh, 1000))

    return () => window.clearTimeout(timeout)
  }, [user])

  useEffect(() => {
    let cancelled = false

    async function loadPreview() {
      if (!archivo) {
        setPreview(null)
        setPreviewLoading(false)
        return
      }

      setPreviewLoading(true)
      try {
        const data = await previewArchivo(archivo)
        if (!cancelled) {
          setPreview(data)
        }
      } catch (error) {
        if (!cancelled) {
          setPreview(null)
          if (error.message === 'No autorizado') {
            handleLogout()
          } else {
            setErrMsg(error.message || 'No se pudo cargar la vista previa')
          }
        }
      } finally {
        if (!cancelled) {
          setPreviewLoading(false)
        }
      }
    }

    if (user) {
      loadPreview()
    }

    return () => {
      cancelled = true
    }
  }, [archivo, user])

  const scrollTo = (ref, key) => {
    setNavActivo(key)
    ref.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  if (!user) {
    return <Login onLogin={setUser} />
  }

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="sidebar-brand">
          <strong>Matricula</strong>
          <span>Panel institucional</span>
        </div>

        <nav className="sidebar-nav">
          <button
            className={`nav-item ${navActivo === 'procesamiento' ? 'nav-item-active' : ''}`}
            onClick={() => scrollTo(heroRef, 'procesamiento')}
          >
            Procesamiento de Matrícula
          </button>
          <button
            className={`nav-item ${navActivo === 'dashboard' ? 'nav-item-active' : ''}`}
            onClick={() => dashboardDisponible && scrollTo(dashboardRef, 'dashboard')}
            disabled={!dashboardDisponible}
          >
            Dashboard
          </button>
          <button
            className={`nav-item ${navActivo === 'descargas' ? 'nav-item-active' : ''}`}
            onClick={() => dashboardDisponible && scrollTo(descargasRef, 'descargas')}
            disabled={!dashboardDisponible}
          >
            Descargas
          </button>
          <button
            className={`nav-item ${navActivo === 'preview' ? 'nav-item-active' : ''}`}
            onClick={() => previewDisponible && scrollTo(previewRef, 'preview')}
            disabled={!previewDisponible}
          >
            Vista previa Excel
          </button>
          <button
            className={`nav-item ${navActivo === 'entrenamiento' ? 'nav-item-active' : ''}`}
            onClick={() => setNavActivo('entrenamiento')}
            disabled={!canManageFormats}
          >
            Entrenar formatos
          </button>
          <button
            className={`nav-item ${navActivo === 'administracion' ? 'nav-item-active' : ''}`}
            onClick={() => setNavActivo('administracion')}
            disabled={!isAdmin}
          >
            Administracion
          </button>
          <button
            className="nav-item nav-item-secondary"
            onClick={handleReset}
            disabled={!archivo && !resultado}
          >
            Procesar otro archivo
          </button>
        </nav>

        <div className="sidebar-foot">
          <div style={{ marginBottom: '10px' }}>
            <span style={{ display: 'block', fontWeight: '700', color: '#fff' }}>{user.name}</span>
            <span style={{ display: 'block', fontSize: '0.75rem', color: 'rgba(0, 240, 255, 0.7)' }}>{user.email}</span>
          </div>
          <button 
            onClick={handleLogout}
            className="btn-remove"
            style={{ width: '100%', padding: '8px 12px', fontSize: '0.75rem' }}
          >
            Cerrar Sesión
          </button>
        </div>
      </aside>

      <div className="content-shell">
        <header className="hero-panel" ref={heroRef}>
          <div className="hero-bar">
            <span className="hero-topline">Sistema de gestion de matricula</span>
            <span className="hero-link">Catalogo de procesos</span>
          </div>

          <div className="hero-main">
            <div className="hero-copy">
              <h1>Procesamiento de archivos</h1>
              <p className="hero-text">
                Carga reportes de Inicial, Primaria o Secundaria, revisa el resumen del proceso
                y descarga archivos organizados por modalidad y distrito.
              </p>
            </div>

            <div className="hero-summary">
              <div className="summary-item">
                <span className="summary-label">Cobertura</span>
                <strong>Inicial, Primaria y Secundaria</strong>
              </div>
              <div className="summary-item">
                <span className="summary-label">Nivel actual</span>
                <strong>{nivelProcesado}</strong>
              </div>
            </div>
          </div>
        </header>

        <main className="main-content">
          {navActivo === 'procesamiento' && (
            <>
              <section className="step-card" ref={procesoRef}>
                <div className="step-head">
                  <div className="step-label"><span>01</span> Cargar archivo</div>
                  <p>Admite archivos Excel exportados desde los reportes oficiales.</p>
                </div>
                <UploadZone
                  archivo={archivo}
                  onArchivo={setArchivo}
                  disabled={estado === 'procesando'}
                />
              </section>

              {archivo && (
                <>
                  <section className="step-card">
                    <div className="step-head">
                      <div className="step-label"><span>02</span> Columnas destacadas</div>
                      <p>Selecciona los campos que deseas remarcar en los archivos generados.</p>
                    </div>
                    <ColumnSelector
                      seleccionadas={columnasResaltadas}
                      onChange={setColumnasResaltadas}
                    />
                  </section>
                </>
              )}

              {archivo && estado !== 'listo' && (
                <div className="action-row">
                  <button
                    className={`btn-procesar ${estado === 'procesando' ? 'loading' : ''}`}
                    onClick={handleProcesar}
                    disabled={estado === 'procesando'}
                  >
                    {estado === 'procesando' ? (
                      <><span className="spinner" /> Procesando archivo...</>
                    ) : (
                      'Procesar archivo'
                    )}
                  </button>
                </div>
              )}

              {estado === 'error' && (
                <div className="error-banner">
                  <div>
                    <strong>No se pudo completar el procesamiento</strong>
                    <p>{errMsg}</p>
                  </div>
                  <button onClick={handleReset}>Reintentar</button>
                </div>
              )}
            </>
          )}

          {navActivo === 'dashboard' && dashboardDisponible && (
            <>
              <div ref={dashboardRef}>
                <DashboardPanel resultado={resultado} />
              </div>

              <section className="step-card">
                <div className="step-head">
                  <div className="step-label"><span>04</span> Resumen</div>
                  <p>Indicadores numericos del archivo procesado.</p>
                </div>
                <div className="stats-grid">
                  {resumenTarjetas.map(item => (
                    <StatsCard key={item.label} {...item} />
                  ))}
                </div>
              </section>
            </>
          )}

          {navActivo === 'dashboard' && !dashboardDisponible && (
            <section className="step-card empty-state-card">
              <div className="step-head">
                <div className="step-label"><span>03</span> Dashboard</div>
                <p>Este modulo se habilita despues de procesar un archivo.</p>
              </div>
              <div className="empty-state">
                <strong>No hay datos para mostrar</strong>
                <p>Procesa un archivo en el modulo de Procesamiento para ver graficas y resumenes exactos.</p>
              </div>
            </section>
          )}

          {navActivo === 'descargas' && dashboardDisponible && (
              <section className="step-card" ref={descargasRef}>
                <div className="step-head">
                  <div className="step-label"><span>05</span> Descargas</div>
                <p>Archivos disponibles para revision y entrega.</p>
              </div>
              <ResultsPanel archivos={resultado.archivos} errores={resultado.errores} />
              </section>
            )}

          {navActivo === 'descargas' && !dashboardDisponible && (
            <section className="step-card empty-state-card">
              <div className="step-head">
                <div className="step-label"><span>05</span> Descargas</div>
                <p>Las descargas aparecen despues del procesamiento.</p>
              </div>
              <div className="empty-state">
                <strong>No hay archivos generados</strong>
                <p>Procesa un archivo para habilitar las descargas individuales y el paquete ZIP.</p>
              </div>
            </section>
          )}

          {navActivo === 'preview' && previewDisponible && (
            <div ref={previewRef} className="preview-section-container">
              <PreviewTable preview={preview} cargando={previewLoading} />
            </div>
          )}

          {navActivo === 'preview' && !previewDisponible && (
            <section className="step-card empty-state-card">
              <div className="step-head">
                <div className="step-label"><span>06</span> Vista previa Excel</div>
                <p>La vista previa del archivo original se habilita despues del procesamiento.</p>
              </div>
              <div className="empty-state">
                <strong>No hay vista previa disponible</strong>
                <p>Procesa un archivo en el modulo de Procesamiento para revisar aqui la tabla original del Excel cargado.</p>
              </div>
            </section>
          )}

          {navActivo === 'entrenamiento' && (
            canManageFormats ? (
              <TrainingPanel onUnauthorized={handleLogout} />
            ) : (
              <section className="step-card empty-state-card">
                <div className="step-head">
                  <div className="step-label"><span>07</span> Entrenamiento</div>
                  <p>Este módulo requiere permisos administrativos de formatos.</p>
                </div>
                <div className="empty-state">
                  <strong>Acceso restringido</strong>
                  <p>Solicita el permiso de gestión de formatos para entrenar nuevas plantillas Excel.</p>
                </div>
              </section>
            )
          )}

          {navActivo === 'administracion' && isAdmin && (
            <AdminControlPanel
              currentUser={user}
              onUnauthorized={handleLogout}
              onCurrentUserChange={handleCurrentUserChange}
            />
          )}

          {navActivo === 'administracion' && !isAdmin && (
            <section className="step-card empty-state-card">
              <div className="step-head">
                <div className="step-label"><span>10</span> Administracion</div>
                <p>Este modulo es exclusivo para administradores.</p>
              </div>
              <div className="empty-state">
                <strong>Sin permisos</strong>
                <p>Inicia sesion con una cuenta administrador para gestionar usuarios.</p>
              </div>
            </section>
          )}
        </main>

        <footer className="app-footer">
          <span>Plataforma de Matricula</span>
          <span>{nivelProcesado}</span>
          <span>{new Date().getFullYear()}</span>
        </footer>
      </div>
    </div>
  )
}
