import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  actualizarContenidoAdmin,
  actualizarUsuarioAdmin,
  alternarEstadoUsuarioAdmin,
  crearContenidoAdmin,
  crearRespaldoAdmin,
  crearUsuarioAdmin,
  eliminarContenidoAdmin,
  eliminarUsuarioAdmin,
  exportarDashboardAdmin,
  listarAuditoriaAdmin,
  listarContenidosAdmin,
  listarRespaldosAdmin,
  listarUsuariosAdmin,
  moderarContenidoAdmin,
  obtenerDashboardAdmin,
  restaurarRespaldoAdmin,
} from '../services/api'

const EMPTY_USER_FORM = {
  name: '',
  email: '',
  password: '',
  role: 'sub_admin',
  is_active: true,
  mfa_enabled: true,
  custom_permissions: [],
}

const EMPTY_CONTENT_FORM = {
  title: '',
  body: '',
  type: 'announcement',
  status: 'draft',
}

const ROLE_OPTIONS = [
  { value: 'super_admin', label: 'Administrador principal' },
  { value: 'sub_admin', label: 'Subadministrador' },
  { value: 'custom', label: 'Rol personalizado' },
  { value: 'user', label: 'Usuario estándar' },
]

const PERMISSION_OPTIONS = [
  'admin.dashboard.view',
  'admin.reports.export',
  'admin.users.view',
  'admin.users.create',
  'admin.users.update',
  'admin.users.suspend',
  'admin.users.delete',
  'admin.content.view',
  'admin.content.create',
  'admin.content.update',
  'admin.content.moderate',
  'admin.audit.view',
  'admin.audit.export',
  'admin.backups.view',
  'admin.backups.create',
  'admin.backups.restore',
  'admin.formats.manage',
]

function buildUserForm(user) {
  return {
    name: user.name,
    email: user.email,
    password: '',
    role: user.role,
    is_active: Boolean(user.is_active),
    mfa_enabled: Boolean(user.mfa_enabled),
    custom_permissions: user.custom_permissions || [],
  }
}

function buildDateRange() {
  const now = new Date()
  const start = new Date()
  start.setDate(now.getDate() - 30)
  return {
    from: start.toISOString().slice(0, 10),
    to: now.toISOString().slice(0, 10),
  }
}

export default function AdminControlPanel({ currentUser, onUnauthorized, onCurrentUserChange }) {
  const [activeTab, setActiveTab] = useState('overview')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  const [dashboardFilters, setDashboardFilters] = useState(buildDateRange)
  const [dashboard, setDashboard] = useState(null)

  const [userFilters, setUserFilters] = useState({ q: '', role: '', status: '', mfa: '' })
  const [usuarios, setUsuarios] = useState([])
  const [userForm, setUserForm] = useState(EMPTY_USER_FORM)
  const [editingUserId, setEditingUserId] = useState(null)

  const [contentFilters, setContentFilters] = useState({ q: '', status: '', type: '' })
  const [contents, setContents] = useState([])
  const [contentForm, setContentForm] = useState(EMPTY_CONTENT_FORM)
  const [editingContentId, setEditingContentId] = useState(null)

  const [auditFilters, setAuditFilters] = useState({ q: '', action: '', from: '', to: '' })
  const [auditLogs, setAuditLogs] = useState([])

  const [backups, setBackups] = useState([])

  const permissions = useMemo(() => currentUser?.permissions || [], [currentUser])
  const can = (permission) => permissions.includes(permission)

  const tabs = [
    can('admin.dashboard.view') && { key: 'overview', label: 'Resumen operativo' },
    can('admin.users.view') && { key: 'users', label: 'Usuarios' },
    can('admin.content.view') && { key: 'content', label: 'Contenido' },
    can('admin.audit.view') && { key: 'audit', label: 'Auditoría' },
    can('admin.backups.view') && { key: 'backups', label: 'Respaldos' },
  ].filter(Boolean)

  const handleApiError = useCallback((e, fallback) => {
    if (e.message === 'No autorizado') {
      onUnauthorized?.()
      return
    }
    setError(e.message || fallback)
  }, [onUnauthorized])

  const loadDashboard = useCallback(async () => {
    if (!permissions.includes('admin.dashboard.view')) return
    try {
      const data = await obtenerDashboardAdmin(dashboardFilters)
      setDashboard(data)
    } catch (e) {
      handleApiError(e, 'No se pudo cargar el dashboard administrativo')
    }
  }, [dashboardFilters, handleApiError, permissions])

  const loadUsuarios = useCallback(async () => {
    if (!permissions.includes('admin.users.view')) return
    try {
      const data = await listarUsuariosAdmin(compactFilters(userFilters))
      setUsuarios(data)
    } catch (e) {
      handleApiError(e, 'No se pudo cargar la lista de usuarios')
    }
  }, [handleApiError, permissions, userFilters])

  const loadContents = useCallback(async () => {
    if (!permissions.includes('admin.content.view')) return
    try {
      const data = await listarContenidosAdmin(compactFilters(contentFilters))
      setContents(data)
    } catch (e) {
      handleApiError(e, 'No se pudo cargar el contenido administrable')
    }
  }, [contentFilters, handleApiError, permissions])

  const loadAuditLogs = useCallback(async () => {
    if (!permissions.includes('admin.audit.view')) return
    try {
      const data = await listarAuditoriaAdmin(compactFilters(auditFilters))
      setAuditLogs(data)
    } catch (e) {
      handleApiError(e, 'No se pudo cargar la auditoría')
    }
  }, [auditFilters, handleApiError, permissions])

  const loadBackups = useCallback(async () => {
    if (!permissions.includes('admin.backups.view')) return
    try {
      const data = await listarRespaldosAdmin()
      setBackups(data)
    } catch (e) {
      handleApiError(e, 'No se pudieron cargar los respaldos')
    }
  }, [handleApiError, permissions])

  useEffect(() => {
    const loadAll = async () => {
      await Promise.all([
        loadDashboard(),
        loadUsuarios(),
        loadContents(),
        loadAuditLogs(),
        loadBackups(),
      ])
    }

    void loadAll()
  }, [loadAuditLogs, loadBackups, loadContents, loadDashboard, loadUsuarios])

  const saveUser = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError('')
    setMessage('')

    try {
      const payload = {
        ...userForm,
        custom_permissions: userForm.role === 'custom' ? userForm.custom_permissions : [],
      }

      const user = editingUserId
        ? await actualizarUsuarioAdmin(editingUserId, payload)
        : await crearUsuarioAdmin(payload)

      setUsuarios(prev => replaceById(prev, user).sort(sortByName))
      if (!editingUserId) {
        setUserForm(EMPTY_USER_FORM)
      }
      if (user.id === currentUser?.id) {
        onCurrentUserChange?.(user)
      }
      setEditingUserId(null)
      setMessage(editingUserId ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.')
      await loadDashboard()
      await loadUsuarios()
    } catch (e) {
      handleApiError(e, 'No se pudo guardar el usuario')
    } finally {
      setBusy(false)
    }
  }

  const editUser = (user) => {
    setEditingUserId(user.id)
    setUserForm(buildUserForm(user))
    setMessage('')
    setError('')
  }

  const toggleUser = async (user) => {
    if (!window.confirm(`¿Deseas ${user.is_active ? 'suspender' : 'activar'} a ${user.name}?`)) return
    setBusy(true)
    setError('')
    setMessage('')
    try {
      const updated = await alternarEstadoUsuarioAdmin(user.id)
      setUsuarios(prev => replaceById(prev, updated).sort(sortByName))
      if (updated.id === currentUser?.id) {
        onCurrentUserChange?.(updated)
      }
      setMessage(updated.is_active ? 'Usuario activado correctamente.' : 'Usuario suspendido correctamente.')
      await loadDashboard()
    } catch (e) {
      handleApiError(e, 'No se pudo cambiar el estado del usuario')
    } finally {
      setBusy(false)
    }
  }

  const removeUser = async (user) => {
    if (!window.confirm(`¿Eliminar a ${user.name}? Esta acción no se puede deshacer.`)) return
    setBusy(true)
    setError('')
    setMessage('')
    try {
      await eliminarUsuarioAdmin(user.id)
      setUsuarios(prev => prev.filter(item => item.id !== user.id))
      setMessage('Usuario eliminado correctamente.')
      await loadDashboard()
    } catch (e) {
      handleApiError(e, 'No se pudo eliminar el usuario')
    } finally {
      setBusy(false)
    }
  }

  const saveContent = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError('')
    setMessage('')
    try {
      const content = editingContentId
        ? await actualizarContenidoAdmin(editingContentId, contentForm)
        : await crearContenidoAdmin(contentForm)
      setContents(prev => replaceById(prev, content).sort(sortByDateDesc))
      setEditingContentId(null)
      setContentForm(EMPTY_CONTENT_FORM)
      setMessage(editingContentId ? 'Contenido actualizado correctamente.' : 'Contenido creado correctamente.')
      await loadDashboard()
    } catch (e) {
      handleApiError(e, 'No se pudo guardar el contenido')
    } finally {
      setBusy(false)
    }
  }

  const moderateContent = async (content, status) => {
    const review_notes = window.prompt('Notas de revisión (opcional):', '') ?? ''
    const flagged_reason = status === 'flagged'
      ? (window.prompt('Motivo del marcado:', content.flagged_reason || '') ?? '')
      : ''

    setBusy(true)
    setError('')
    setMessage('')
    try {
      const updated = await moderarContenidoAdmin(content.id, { status, review_notes, flagged_reason })
      setContents(prev => replaceById(prev, updated).sort(sortByDateDesc))
      setMessage('Contenido moderado correctamente.')
      await loadDashboard()
      await loadAuditLogs()
    } catch (e) {
      handleApiError(e, 'No se pudo moderar el contenido')
    } finally {
      setBusy(false)
    }
  }

  const removeContent = async (content) => {
    if (!window.confirm(`¿Eliminar "${content.title}"?`)) return
    setBusy(true)
    setError('')
    setMessage('')
    try {
      await eliminarContenidoAdmin(content.id)
      setContents(prev => prev.filter(item => item.id !== content.id))
      setMessage('Contenido eliminado correctamente.')
      await loadDashboard()
    } catch (e) {
      handleApiError(e, 'No se pudo eliminar el contenido')
    } finally {
      setBusy(false)
    }
  }

  const createBackup = async () => {
    setBusy(true)
    setError('')
    setMessage('')
    try {
      const backup = await crearRespaldoAdmin()
      setBackups(prev => [backup, ...prev])
      setMessage('Respaldo generado correctamente.')
      await loadDashboard()
    } catch (e) {
      handleApiError(e, 'No se pudo generar el respaldo')
    } finally {
      setBusy(false)
    }
  }

  const restoreBackup = async (backup) => {
    if (!window.confirm(`¿Restaurar el respaldo ${backup.file_name}? Se revocarán las sesiones activas.`)) return
    setBusy(true)
    setError('')
    setMessage('')
    try {
      await restaurarRespaldoAdmin(backup.id)
      setMessage('Respaldo restaurado correctamente. Vuelve a iniciar sesión si la sesión se revoca.')
      await Promise.all([loadDashboard(), loadUsuarios(), loadContents(), loadAuditLogs(), loadBackups()])
    } catch (e) {
      handleApiError(e, 'No se pudo restaurar el respaldo')
    } finally {
      setBusy(false)
    }
  }

  const exportDashboard = async (format) => {
    setBusy(true)
    setError('')
    try {
      await exportarDashboardAdmin(dashboardFilters, format)
    } catch (e) {
      handleApiError(e, 'No se pudo exportar el dashboard')
    } finally {
      setBusy(false)
    }
  }

  const chartMax = Math.max(...((dashboard?.charts?.acciones_por_dia || []).map(item => Number(item.total))), 1)
  const roleMax = Math.max(...((dashboard?.charts?.usuarios_por_rol || []).map(item => Number(item.total))), 1)

  return (
    <div className="admin-layout">
      <section className="step-card">
        <div className="step-head">
          <div className="step-label"><span>10</span> Módulo administrador</div>
          <p>Centro de control con seguridad reforzada, MFA, auditoría, respaldo y gestión avanzada del sistema.</p>
        </div>

        <div className="admin-tabs">
          {tabs.map(tab => (
            <button
              key={tab.key}
              type="button"
              className={`chip ${activeTab === tab.key ? 'active' : ''}`}
              onClick={() => setActiveTab(tab.key)}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {message && <div className="training-banner training-banner-success">{message}</div>}
        {error && <div className="training-banner training-banner-error">{error}</div>}
      </section>

      {activeTab === 'overview' && can('admin.dashboard.view') && (
        <>
          <section className="step-card">
            <div className="step-head">
              <div className="step-label"><span>11</span> Dashboard centralizado</div>
              <p>Métricas en tiempo real, alertas recientes y exportación de reportes.</p>
            </div>

            <div className="admin-filter-row">
              <label className="training-field">
                <span>Desde</span>
                <input type="date" value={dashboardFilters.from} onChange={e => setDashboardFilters(prev => ({ ...prev, from: e.target.value }))} />
              </label>
              <label className="training-field">
                <span>Hasta</span>
                <input type="date" value={dashboardFilters.to} onChange={e => setDashboardFilters(prev => ({ ...prev, to: e.target.value }))} />
              </label>
              <div className="admin-actions-row">
                <button type="button" className="btn-remove" onClick={loadDashboard} disabled={busy}>Actualizar</button>
                {can('admin.reports.export') && <button type="button" className="btn-procesar" onClick={() => exportDashboard('excel')} disabled={busy}>Exportar Excel</button>}
                {can('admin.reports.export') && <button type="button" className="btn-remove" onClick={() => exportDashboard('pdf')} disabled={busy}>Exportar PDF</button>}
              </div>
            </div>

            {dashboard && (
              <>
                <div className="admin-summary-grid admin-summary-grid-wide">
                  <MetricCard label="Usuarios activos" value={dashboard.metrics.usuarios_activos} />
                  <MetricCard label="Usuarios bloqueados" value={dashboard.metrics.usuarios_bloqueados} />
                  <MetricCard label="MFA habilitado" value={dashboard.metrics.mfa_habilitado} />
                  <MetricCard label="Alertas de seguridad" value={dashboard.metrics.intentos_fallidos} />
                </div>

                <div className="dashboard-grid">
                  <article className="dashboard-panel">
                    <div className="panel-title-row">
                      <h3>Actividad por día</h3>
                      <span>{dashboard.range.from} a {dashboard.range.to}</span>
                    </div>
                    <div className="bar-list">
                      {(dashboard.charts.acciones_por_dia || []).map(item => (
                        <div key={item.fecha} className="bar-item">
                          <div className="bar-meta">
                            <span>{item.fecha}</span>
                            <strong>{item.total}</strong>
                          </div>
                          <div className="bar-track">
                            <div className="bar-fill chart-fill-blue" style={{ width: `${(Number(item.total) / chartMax) * 100}%` }} />
                          </div>
                        </div>
                      ))}
                    </div>
                  </article>

                  <article className="dashboard-panel">
                    <div className="panel-title-row">
                      <h3>Usuarios por rol</h3>
                      <span>Distribución actual</span>
                    </div>
                    <div className="bar-list">
                      {(dashboard.charts.usuarios_por_rol || []).map(item => (
                        <div key={item.role} className="bar-item">
                          <div className="bar-meta">
                            <span>{item.role}</span>
                            <strong>{item.total}</strong>
                          </div>
                          <div className="bar-track">
                            <div className="bar-fill chart-fill-indigo" style={{ width: `${(Number(item.total) / roleMax) * 100}%` }} />
                          </div>
                        </div>
                      ))}
                    </div>
                  </article>
                </div>

                <div className="admin-two-column">
                  <article className="dashboard-panel">
                    <div className="panel-title-row">
                      <h3>Acciones recientes</h3>
                      <span>Últimos eventos</span>
                    </div>
                    <div className="admin-list">
                      {(dashboard.recent_actions || []).map(item => (
                        <div key={item.id} className="admin-list-item">
                          <strong>{item.action}</strong>
                          <span>{item.actor?.email || 'Sistema'} · {formatDateTime(item.created_at)}</span>
                        </div>
                      ))}
                    </div>
                  </article>

                  <article className="dashboard-panel">
                    <div className="panel-title-row">
                      <h3>Alertas</h3>
                      <span>Bloqueos y contenido marcado</span>
                    </div>
                    <div className="admin-list">
                      {(dashboard.alerts.bloqueos_recientes || []).map(item => (
                        <div key={`lock-${item.id}`} className="admin-list-item">
                          <strong>{item.target_label || 'Cuenta bloqueada'}</strong>
                          <span>{formatDateTime(item.created_at)}</span>
                        </div>
                      ))}
                      {(dashboard.alerts.contenido_marcado || []).map(item => (
                        <div key={`flag-${item.id}`} className="admin-list-item">
                          <strong>{item.title}</strong>
                          <span>{item.flagged_reason || 'Marcado para revisión'}</span>
                        </div>
                      ))}
                    </div>
                  </article>
                </div>
              </>
            )}
          </section>
        </>
      )}

      {activeTab === 'users' && can('admin.users.view') && (
        <>
          <section className="step-card">
            <div className="step-head">
              <div className="step-label"><span>12</span> Búsqueda y filtros</div>
              <p>Localiza cuentas por nombre, correo, rol, estado o MFA.</p>
            </div>
            <div className="admin-filter-grid">
              <label className="training-field"><span>Buscar</span><input value={userFilters.q} onChange={e => setUserFilters(prev => ({ ...prev, q: e.target.value }))} placeholder="Nombre o correo" /></label>
              <label className="training-field"><span>Rol</span><select value={userFilters.role} onChange={e => setUserFilters(prev => ({ ...prev, role: e.target.value }))}><option value="">Todos</option>{ROLE_OPTIONS.map(role => <option key={role.value} value={role.value}>{role.label}</option>)}</select></label>
              <label className="training-field"><span>Estado</span><select value={userFilters.status} onChange={e => setUserFilters(prev => ({ ...prev, status: e.target.value }))}><option value="">Todos</option><option value="active">Activos</option><option value="inactive">Inactivos</option><option value="locked">Bloqueados</option></select></label>
              <label className="training-field"><span>MFA</span><select value={userFilters.mfa} onChange={e => setUserFilters(prev => ({ ...prev, mfa: e.target.value }))}><option value="">Todos</option><option value="enabled">Habilitado</option><option value="disabled">Deshabilitado</option></select></label>
            </div>
            <div className="training-actions">
              <button type="button" className="btn-remove" onClick={loadUsuarios} disabled={busy}>Aplicar filtros</button>
            </div>
          </section>

          {can(editingUserId ? 'admin.users.update' : 'admin.users.create') && (
            <section className="step-card">
              <div className="step-head">
                <div className="step-label"><span>13</span> {editingUserId ? 'Editar usuario' : 'Crear usuario'}</div>
                <p>Asigna rol, MFA, suspensión y permisos personalizados cuando el rol sea configurable.</p>
              </div>
              <form className="admin-form-grid" onSubmit={saveUser}>
                <label className="training-field"><span>Nombre</span><input value={userForm.name} onChange={e => setUserForm(prev => ({ ...prev, name: e.target.value }))} required /></label>
                <label className="training-field"><span>Correo</span><input type="email" value={userForm.email} onChange={e => setUserForm(prev => ({ ...prev, email: e.target.value }))} required /></label>
                <label className="training-field"><span>Contraseña</span><input type="password" value={userForm.password} onChange={e => setUserForm(prev => ({ ...prev, password: e.target.value }))} minLength={editingUserId ? 0 : 8} required={!editingUserId} /></label>
                <label className="training-field"><span>Rol</span><select value={userForm.role} onChange={e => setUserForm(prev => ({ ...prev, role: e.target.value }))}>{ROLE_OPTIONS.map(role => <option key={role.value} value={role.value}>{role.label}</option>)}</select></label>
                <label className="admin-checkbox"><input type="checkbox" checked={userForm.is_active} onChange={e => setUserForm(prev => ({ ...prev, is_active: e.target.checked }))} /><span>Cuenta activa</span></label>
                <label className="admin-checkbox"><input type="checkbox" checked={userForm.mfa_enabled} onChange={e => setUserForm(prev => ({ ...prev, mfa_enabled: e.target.checked }))} /><span>MFA habilitado</span></label>
                {userForm.role === 'custom' && (
                  <label className="training-field training-field-wide">
                    <span>Permisos personalizados</span>
                    <select multiple value={userForm.custom_permissions} onChange={e => setUserForm(prev => ({ ...prev, custom_permissions: Array.from(e.target.selectedOptions).map(option => option.value) }))}>
                      {PERMISSION_OPTIONS.map(permission => <option key={permission} value={permission}>{permission}</option>)}
                    </select>
                  </label>
                )}
                <div className="admin-actions-row">
                  <button className={`btn-procesar ${busy ? 'loading' : ''}`} disabled={busy}>{editingUserId ? 'Guardar cambios' : 'Crear cuenta'}</button>
                  {editingUserId && <button type="button" className="btn-remove" onClick={() => { setEditingUserId(null); setUserForm(EMPTY_USER_FORM) }}>Cancelar edición</button>}
                </div>
              </form>
            </section>
          )}

          <section className="step-card">
            <div className="step-head">
              <div className="step-label"><span>14</span> Cuentas del sistema</div>
              <p>Gestión avanzada con MFA, bloqueo de acceso, permisos y última actividad.</p>
            </div>
            <div className="admin-users-list">
              {usuarios.map(user => (
                <article key={user.id} className="admin-user-card">
                  <div className="admin-user-head">
                    <div>
                      <strong>{user.name}</strong>
                      <p>{user.email}</p>
                    </div>
                    <div className="admin-badges">
                      <span className={`admin-badge ${user.role === 'super_admin' ? 'admin-badge-admin' : ''}`}>{user.role}</span>
                      <span className={`admin-badge ${user.is_active ? 'admin-badge-active' : 'admin-badge-inactive'}`}>{user.is_active ? 'Activo' : 'Suspendido'}</span>
                      <span className="admin-badge">{user.mfa_enabled ? 'MFA activo' : 'MFA apagado'}</span>
                      {user.locked_until && <span className="admin-badge admin-badge-inactive">Bloqueado</span>}
                    </div>
                  </div>
                  <div className="admin-user-meta-grid">
                    <span>Último acceso: {user.last_login_at ? formatDateTime(user.last_login_at) : 'Sin datos'}</span>
                    <span>IP: {user.last_login_ip || 'Sin registro'}</span>
                    <span>Intentos fallidos: {user.failed_login_attempts}</span>
                    <span>Permisos: {(user.permissions || []).length}</span>
                  </div>
                  <div className="admin-actions-row">
                    {can('admin.users.update') && <button type="button" className="btn-remove" onClick={() => editUser(user)}>Editar</button>}
                    {can('admin.users.suspend') && <button type="button" className="btn-remove" onClick={() => toggleUser(user)} disabled={busy}>{user.is_active ? 'Suspender' : 'Activar'}</button>}
                    {can('admin.users.delete') && currentUser?.id !== user.id && <button type="button" className="btn-remove admin-danger-btn" onClick={() => removeUser(user)} disabled={busy}>Eliminar</button>}
                  </div>
                </article>
              ))}
            </div>
          </section>
        </>
      )}

      {activeTab === 'content' && can('admin.content.view') && (
        <>
          <section className="step-card">
            <div className="step-head">
              <div className="step-label"><span>15</span> Moderación de contenido</div>
              <p>Revisa, publica, marca o retira contenido del sistema con trazabilidad completa.</p>
            </div>
            <div className="admin-filter-grid">
              <label className="training-field"><span>Buscar</span><input value={contentFilters.q} onChange={e => setContentFilters(prev => ({ ...prev, q: e.target.value }))} placeholder="Título o texto" /></label>
              <label className="training-field"><span>Estado</span><select value={contentFilters.status} onChange={e => setContentFilters(prev => ({ ...prev, status: e.target.value }))}><option value="">Todos</option><option value="draft">Borrador</option><option value="published">Publicado</option><option value="flagged">Marcado</option><option value="removed">Retirado</option></select></label>
              <label className="training-field"><span>Tipo</span><input value={contentFilters.type} onChange={e => setContentFilters(prev => ({ ...prev, type: e.target.value }))} placeholder="announcement, note..." /></label>
            </div>
            <div className="training-actions"><button type="button" className="btn-remove" onClick={loadContents} disabled={busy}>Aplicar filtros</button></div>
          </section>

          {(can('admin.content.create') || can('admin.content.update')) && (
            <section className="step-card">
              <div className="step-head">
                <div className="step-label"><span>16</span> {editingContentId ? 'Editar contenido' : 'Nuevo contenido'}</div>
                <p>Crea anuncios, notas operativas o piezas de contenido moderables.</p>
              </div>
              <form className="admin-content-form" onSubmit={saveContent}>
                <label className="training-field"><span>Título</span><input value={contentForm.title} onChange={e => setContentForm(prev => ({ ...prev, title: e.target.value }))} required /></label>
                <label className="training-field"><span>Tipo</span><input value={contentForm.type} onChange={e => setContentForm(prev => ({ ...prev, type: e.target.value }))} required /></label>
                <label className="training-field"><span>Estado inicial</span><select value={contentForm.status} onChange={e => setContentForm(prev => ({ ...prev, status: e.target.value }))}><option value="draft">Borrador</option><option value="published">Publicado</option><option value="flagged">Marcado</option><option value="removed">Retirado</option></select></label>
                <label className="training-field training-field-wide"><span>Contenido</span><textarea rows="5" value={contentForm.body} onChange={e => setContentForm(prev => ({ ...prev, body: e.target.value }))} required /></label>
                <div className="admin-actions-row">
                  <button className="btn-procesar" disabled={busy}>{editingContentId ? 'Guardar contenido' : 'Crear contenido'}</button>
                  {editingContentId && <button type="button" className="btn-remove" onClick={() => { setEditingContentId(null); setContentForm(EMPTY_CONTENT_FORM) }}>Cancelar</button>}
                </div>
              </form>
            </section>
          )}

          <section className="step-card">
            <div className="step-head">
              <div className="step-label"><span>17</span> Revisión y acciones</div>
              <p>Marca contenido inapropiado, documenta revisiones y elimina elementos problemáticos.</p>
            </div>
            <div className="admin-users-list">
              {contents.map(content => (
                <article key={content.id} className="admin-user-card">
                  <div className="admin-user-head">
                    <div>
                      <strong>{content.title}</strong>
                      <p>{content.type} · {content.creator?.email || 'Sistema'}</p>
                    </div>
                    <div className="admin-badges">
                      <span className={`admin-badge ${content.status === 'published' ? 'admin-badge-active' : ''}`}>{content.status}</span>
                      {content.is_flagged && <span className="admin-badge admin-badge-inactive">Marcado</span>}
                    </div>
                  </div>
                  <p className="admin-content-preview">{content.body}</p>
                  <div className="admin-user-meta-grid">
                    <span>Creado: {formatDateTime(content.created_at)}</span>
                    <span>Revisado por: {content.reviewer?.email || 'Pendiente'}</span>
                    <span>Motivo: {content.flagged_reason || 'Sin observaciones'}</span>
                    <span>Notas: {content.review_notes || 'Sin notas'}</span>
                  </div>
                  <div className="admin-actions-row">
                    {can('admin.content.update') && <button type="button" className="btn-remove" onClick={() => { setEditingContentId(content.id); setContentForm({ title: content.title, body: content.body, type: content.type, status: content.status }) }}>Editar</button>}
                    {can('admin.content.moderate') && <button type="button" className="btn-remove" onClick={() => moderateContent(content, 'published')} disabled={busy}>Publicar</button>}
                    {can('admin.content.moderate') && <button type="button" className="btn-remove" onClick={() => moderateContent(content, 'flagged')} disabled={busy}>Marcar</button>}
                    {can('admin.content.moderate') && <button type="button" className="btn-remove admin-danger-btn" onClick={() => moderateContent(content, 'removed')} disabled={busy}>Retirar</button>}
                    {can('admin.content.moderate') && <button type="button" className="btn-remove admin-danger-btn" onClick={() => removeContent(content)} disabled={busy}>Eliminar</button>}
                  </div>
                </article>
              ))}
            </div>
          </section>
        </>
      )}

      {activeTab === 'audit' && can('admin.audit.view') && (
        <>
          <section className="step-card">
            <div className="step-head">
              <div className="step-label"><span>18</span> Log de auditoría</div>
              <p>Registro inmutable de acciones administrativas y eventos de seguridad.</p>
            </div>
            <div className="admin-filter-grid">
              <label className="training-field"><span>Buscar</span><input value={auditFilters.q} onChange={e => setAuditFilters(prev => ({ ...prev, q: e.target.value }))} placeholder="Acción o objetivo" /></label>
              <label className="training-field"><span>Acción</span><input value={auditFilters.action} onChange={e => setAuditFilters(prev => ({ ...prev, action: e.target.value }))} placeholder="auth.login.failed" /></label>
              <label className="training-field"><span>Desde</span><input type="date" value={auditFilters.from} onChange={e => setAuditFilters(prev => ({ ...prev, from: e.target.value }))} /></label>
              <label className="training-field"><span>Hasta</span><input type="date" value={auditFilters.to} onChange={e => setAuditFilters(prev => ({ ...prev, to: e.target.value }))} /></label>
            </div>
            <div className="training-actions"><button type="button" className="btn-remove" onClick={loadAuditLogs} disabled={busy}>Consultar auditoría</button></div>
            <div className="audit-log-list">
              {auditLogs.map(log => (
                <article key={log.id} className="audit-log-card">
                  <strong>{log.action}</strong>
                  <span>{log.actor?.email || 'Sistema'} · {formatDateTime(log.created_at)}</span>
                  <span>Objetivo: {log.target_label || `${log.subject_type || 'N/A'} #${log.subject_id || '-'}`}</span>
                  <span>IP: {log.ip_address || 'Sin IP'}</span>
                </article>
              ))}
            </div>
          </section>
        </>
      )}

      {activeTab === 'backups' && can('admin.backups.view') && (
        <>
          <section className="step-card">
            <div className="step-head">
              <div className="step-label"><span>19</span> Respaldo y restauración</div>
              <p>Genera copias automáticas/manuales del módulo administrativo y restaura ante fallos.</p>
            </div>
            <div className="admin-actions-row">
              {can('admin.backups.create') && <button type="button" className="btn-procesar" onClick={createBackup} disabled={busy}>Generar respaldo</button>}
              <button type="button" className="btn-remove" onClick={loadBackups} disabled={busy}>Actualizar listado</button>
            </div>
          </section>

          <section className="step-card">
            <div className="step-head">
              <div className="step-label"><span>20</span> Historial de respaldos</div>
              <p>Control de restauración con registro de fecha, tamaño y última recuperación ejecutada.</p>
            </div>
            <div className="admin-users-list">
              {backups.map(backup => (
                <article key={backup.id} className="admin-user-card">
                  <div className="admin-user-head">
                    <div>
                      <strong>{backup.file_name}</strong>
                      <p>{backup.status}</p>
                    </div>
                    <div className="admin-badges">
                      <span className="admin-badge">{Math.round((backup.size_bytes || 0) / 1024)} KB</span>
                      {backup.restored_at && <span className="admin-badge admin-badge-active">Restaurado</span>}
                    </div>
                  </div>
                  <div className="admin-user-meta-grid">
                    <span>Creado: {formatDateTime(backup.created_at)}</span>
                    <span>Última restauración: {backup.restored_at ? formatDateTime(backup.restored_at) : 'Nunca'}</span>
                  </div>
                  {can('admin.backups.restore') && <button type="button" className="btn-remove" onClick={() => restoreBackup(backup)} disabled={busy}>Restaurar</button>}
                </article>
              ))}
            </div>
          </section>
        </>
      )}
    </div>
  )
}

function MetricCard({ label, value }) {
  return (
    <article className="admin-summary-card">
      <span>{label}</span>
      <strong>{value}</strong>
    </article>
  )
}

function compactFilters(filters) {
  return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined))
}

function replaceById(list, item) {
  const exists = list.some(entry => entry.id === item.id)
  if (!exists) return [item, ...list]
  return list.map(entry => (entry.id === item.id ? item : entry))
}

function sortByName(a, b) {
  return a.name.localeCompare(b.name)
}

function sortByDateDesc(a, b) {
  return new Date(b.updated_at || b.created_at).getTime() - new Date(a.updated_at || a.created_at).getTime()
}

function formatDateTime(value) {
  if (!value) return 'Sin fecha'
  return new Date(value).toLocaleString()
}
