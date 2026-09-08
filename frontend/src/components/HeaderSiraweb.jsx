import { useRef } from 'react'

export default function HeaderSiraweb({
  user,
  tabActiva,
  onTabChange,
  onArchivoSeleccionado,
  onExportarZip,
  onLogout,
}) {
  const fileInputRef = useRef(null)

  const handleFileChange = (e) => {
    if (e.target.files && e.target.files[0]) {
      onArchivoSeleccionado(e.target.files[0])
    }
  }

  return (
    <header className="siraweb-header" style={{ background: '#0b1329', color: '#ffffff', borderBottom: '1px solid #1e293b' }}>
      {/* Barra Superior */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.85rem 1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <div style={{ background: '#2563eb', width: '40px', height: '40px', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold', fontSize: '1.4rem' }}>
            🏢
          </div>
          <div>
            <h1 style={{ margin: 0, fontSize: '1.2rem', fontWeight: '800', letterSpacing: '0.5px' }}>
              SIRAWEB 2026 - UGEL Piura
            </h1>
            <p style={{ margin: 0, fontSize: '0.75rem', opacity: 0.7 }}>
              Sistema Integrado de Consultas, Fichas por Colegio (3 Niveles) y Reportes Oficiales
            </p>
          </div>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flexWrap: 'wrap' }}>
          <input
            type="file"
            ref={fileInputRef}
            onChange={handleFileChange}
            accept=".xlsx,.xls"
            style={{ display: 'none' }}
          />

          <button
            onClick={() => fileInputRef.current?.click()}
            style={{
              background: 'rgba(255, 255, 255, 0.1)',
              color: '#ffffff',
              border: '1px solid rgba(255, 255, 255, 0.2)',
              padding: '0.55rem 1rem',
              borderRadius: '6px',
              fontWeight: 'bold',
              cursor: 'pointer',
              fontSize: '0.85rem',
              display: 'flex',
              alignItems: 'center',
              gap: '0.4rem',
            }}
          >
            📄 Cargar nuevo Excel
          </button>

          <button
            onClick={onExportarZip}
            style={{
              background: '#7c3aed',
              color: '#ffffff',
              border: 'none',
              padding: '0.55rem 1rem',
              borderRadius: '6px',
              fontWeight: 'bold',
              cursor: 'pointer',
              fontSize: '0.85rem',
              display: 'flex',
              alignItems: 'center',
              gap: '0.4rem',
            }}
          >
            🗜️ ZIP (.zip)
          </button>

          {user ? (
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', background: '#1e293b', padding: '0.4rem 0.8rem', borderRadius: '6px' }}>
              <span style={{ fontSize: '0.8rem', fontWeight: 'bold' }}>👤 {user.name}</span>
              <button
                onClick={onLogout}
                style={{ background: '#ef4444', color: '#fff', border: 'none', padding: '0.2rem 0.5rem', borderRadius: '4px', cursor: 'pointer', fontSize: '0.75rem' }}
              >
                Salir
              </button>
            </div>
          ) : (
            <button
              style={{
                background: '#2563eb',
                color: '#ffffff',
                border: 'none',
                padding: '0.55rem 1.2rem',
                borderRadius: '6px',
                fontWeight: 'bold',
                cursor: 'pointer',
                fontSize: '0.85rem',
              }}
            >
              👤 Iniciar Sesión
            </button>
          )}
        </div>
      </div>

      {/* Menú de Pestañas Principal SIRAWEB */}
      <nav style={{ display: 'flex', background: '#0f172a', padding: '0 1.5rem', borderTop: '1px solid #1e293b', overflowX: 'auto' }}>
        <button
          onClick={() => onTabChange('consultor')}
          style={{
            padding: '0.85rem 1.2rem',
            background: tabActiva === 'consultor' ? '#1e293b' : 'transparent',
            color: tabActiva === 'consultor' ? '#38bdf8' : '#94a3b8',
            border: 'none',
            borderBottom: tabActiva === 'consultor' ? '3px solid #38bdf8' : '3px solid transparent',
            fontWeight: 'bold',
            fontSize: '0.88rem',
            cursor: 'pointer',
            whiteSpace: 'nowrap',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
          }}
        >
          🔍 Consultor y Fichas de IIEE
        </button>

        <button
          onClick={() => onTabChange('nexus')}
          style={{
            padding: '0.85rem 1.2rem',
            background: tabActiva === 'nexus' ? '#1e293b' : 'transparent',
            color: tabActiva === 'nexus' ? '#38bdf8' : '#94a3b8',
            border: 'none',
            borderBottom: tabActiva === 'nexus' ? '3px solid #38bdf8' : '3px solid transparent',
            fontWeight: 'bold',
            fontSize: '0.88rem',
            cursor: 'pointer',
            whiteSpace: 'nowrap',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
          }}
        >
          🎴 Padrón Plazas NEXUS
        </button>

        <button
          onClick={() => onTabChange('dashboard')}
          style={{
            padding: '0.85rem 1.2rem',
            background: tabActiva === 'dashboard' ? '#1e293b' : 'transparent',
            color: tabActiva === 'dashboard' ? '#38bdf8' : '#94a3b8',
            border: 'none',
            borderBottom: tabActiva === 'dashboard' ? '3px solid #38bdf8' : '3px solid transparent',
            fontWeight: 'bold',
            fontSize: '0.88rem',
            cursor: 'pointer',
            whiteSpace: 'nowrap',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
          }}
        >
          📊 Dashboard Estadístico
        </button>

        <button
          onClick={() => onTabChange('auditoria')}
          style={{
            padding: '0.85rem 1.2rem',
            background: 'transparent',
            color: '#94a3b8',
            border: 'none',
            fontWeight: 'bold',
            fontSize: '0.88rem',
            cursor: 'pointer',
            whiteSpace: 'nowrap',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
          }}
        >
          📑 Bitácora de Auditoría
        </button>

        <button
          onClick={() => onTabChange('usuarios')}
          style={{
            padding: '0.85rem 1.2rem',
            background: 'transparent',
            color: '#94a3b8',
            border: 'none',
            fontWeight: 'bold',
            fontSize: '0.88rem',
            cursor: 'pointer',
            whiteSpace: 'nowrap',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
          }}
        >
          👥 Gestión de Usuarios
        </button>

        <button
          onClick={() => onTabChange('alertas')}
          style={{
            padding: '0.85rem 1.2rem',
            background: 'transparent',
            color: '#94a3b8',
            border: 'none',
            fontWeight: 'bold',
            fontSize: '0.88rem',
            cursor: 'pointer',
            whiteSpace: 'nowrap',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
          }}
        >
          ⚠️ Alertas e Incidencias
        </button>
      </nav>
    </header>
  )
}
