import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  actualizarUsuarioAdmin,
  alternarEstadoUsuarioAdmin,
  crearUsuarioAdmin,
  eliminarUsuarioAdmin,
  listarUsuariosAdmin,
} from '../services/api'

const EMPTY_CREATE_FORM = {
  name: '',
  email: '',
  password: '',
  role: 'user',
  is_active: true,
}

function buildEditForm(user) {
  return {
    name: user.name,
    email: user.email,
    password: '',
    role: user.role,
    is_active: Boolean(user.is_active),
  }
}

export default function UserManagementPanel({ currentUser, onUnauthorized, onCurrentUserChange }) {
  const [usuarios, setUsuarios] = useState([])
  const [loading, setLoading] = useState(true)
  const [savingCreate, setSavingCreate] = useState(false)
  const [savingEdit, setSavingEdit] = useState(false)
  const [busyUserId, setBusyUserId] = useState(null)
  const [error, setError] = useState('')
  const [mensaje, setMensaje] = useState('')
  const [createForm, setCreateForm] = useState(EMPTY_CREATE_FORM)
  const [editingId, setEditingId] = useState(null)
  const [editForm, setEditForm] = useState(null)

  const resumen = useMemo(() => ({
    total: usuarios.length,
    activos: usuarios.filter(user => user.is_active).length,
    admins: usuarios.filter(user => user.role === 'admin').length,
  }), [usuarios])

  const loadUsuarios = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const data = await listarUsuariosAdmin()
      setUsuarios(data)
    } catch (e) {
      if (e.message === 'No autorizado') {
        onUnauthorized?.()
      } else {
        setError(e.message || 'No se pudo cargar la lista de usuarios')
      }
    } finally {
      setLoading(false)
    }
  }, [onUnauthorized])

  useEffect(() => {
    const run = async () => {
      await loadUsuarios()
    }

    void run()
  }, [loadUsuarios])

  const updateCreateField = (field, value) => {
    setCreateForm(prev => ({ ...prev, [field]: value }))
  }

  const updateEditField = (field, value) => {
    setEditForm(prev => ({ ...prev, [field]: value }))
  }

  const resetCreateForm = () => {
    setCreateForm(EMPTY_CREATE_FORM)
  }

  const handleCrear = async (e) => {
    e.preventDefault()
    setSavingCreate(true)
    setError('')
    setMensaje('')

    try {
      const nuevoUsuario = await crearUsuarioAdmin(createForm)
      setUsuarios(prev => [nuevoUsuario, ...prev].sort(sortUsuarios))
      resetCreateForm()
      setMensaje('Usuario creado correctamente.')
    } catch (e) {
      if (e.message === 'No autorizado') {
        onUnauthorized?.()
      } else {
        setError(e.message || 'No se pudo crear el usuario')
      }
    } finally {
      setSavingCreate(false)
    }
  }

  const handleEditar = (user) => {
    setEditingId(user.id)
    setEditForm(buildEditForm(user))
    setError('')
    setMensaje('')
  }

  const cancelEdit = () => {
    setEditingId(null)
    setEditForm(null)
  }

  const handleGuardarEdicion = async (userId) => {
    setSavingEdit(true)
    setError('')
    setMensaje('')

    const payload = { ...editForm }
    if (!payload.password) {
      delete payload.password
    }

    try {
      const usuarioActualizado = await actualizarUsuarioAdmin(userId, payload)
      setUsuarios(prev => prev.map(item => (item.id === userId ? usuarioActualizado : item)).sort(sortUsuarios))

      if (usuarioActualizado.id === currentUser?.id) {
        onCurrentUserChange?.(usuarioActualizado)
      }

      cancelEdit()
      setMensaje('Usuario actualizado correctamente.')
    } catch (e) {
      if (e.message === 'No autorizado') {
        onUnauthorized?.()
      } else {
        setError(e.message || 'No se pudo actualizar el usuario')
      }
    } finally {
      setSavingEdit(false)
    }
  }

  const handleToggle = async (user) => {
    const actionLabel = user.is_active ? 'desactivar' : 'activar'
    if (!window.confirm(`¿Deseas ${actionLabel} a ${user.name}?`)) return

    setBusyUserId(user.id)
    setError('')
    setMensaje('')

    try {
      const actualizado = await alternarEstadoUsuarioAdmin(user.id)
      setUsuarios(prev => prev.map(item => (item.id === user.id ? actualizado : item)).sort(sortUsuarios))
      setMensaje(actualizado.is_active ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.')
    } catch (e) {
      if (e.message === 'No autorizado') {
        onUnauthorized?.()
      } else {
        setError(e.message || 'No se pudo cambiar el estado del usuario')
      }
    } finally {
      setBusyUserId(null)
    }
  }

  const handleEliminar = async (user) => {
    if (!window.confirm(`¿Seguro que deseas eliminar a ${user.name}? Esta accion no se puede deshacer.`)) return

    setBusyUserId(user.id)
    setError('')
    setMensaje('')

    try {
      await eliminarUsuarioAdmin(user.id)
      setUsuarios(prev => prev.filter(item => item.id !== user.id))
      if (editingId === user.id) {
        cancelEdit()
      }
      setMensaje('Usuario eliminado correctamente.')
    } catch (e) {
      if (e.message === 'No autorizado') {
        onUnauthorized?.()
      } else {
        setError(e.message || 'No se pudo eliminar el usuario')
      }
    } finally {
      setBusyUserId(null)
    }
  }

  return (
    <div className="admin-layout">
      <section className="step-card">
        <div className="step-head">
          <div className="step-label"><span>10</span> Administracion de usuarios</div>
          <p>Controla altas, roles, activacion y eliminacion de cuentas desde un solo modulo.</p>
        </div>

        <div className="admin-summary-grid">
          <article className="admin-summary-card">
            <span>Total de usuarios</span>
            <strong>{resumen.total}</strong>
          </article>
          <article className="admin-summary-card">
            <span>Usuarios activos</span>
            <strong>{resumen.activos}</strong>
          </article>
          <article className="admin-summary-card">
            <span>Administradores</span>
            <strong>{resumen.admins}</strong>
          </article>
        </div>

        {mensaje && <div className="training-banner training-banner-success">{mensaje}</div>}
        {error && <div className="training-banner training-banner-error">{error}</div>}
      </section>

      <section className="step-card">
        <div className="step-head">
          <div className="step-label"><span>11</span> Crear usuario</div>
          <p>El administrador puede crear cuentas nuevas y asignar el rol inicial.</p>
        </div>

        <form className="admin-form-grid" onSubmit={handleCrear}>
          <label className="training-field">
            <span>Nombre</span>
            <input value={createForm.name} onChange={e => updateCreateField('name', e.target.value)} required />
          </label>
          <label className="training-field">
            <span>Correo</span>
            <input type="email" value={createForm.email} onChange={e => updateCreateField('email', e.target.value)} required />
          </label>
          <label className="training-field">
            <span>Contrasena</span>
            <input type="password" value={createForm.password} onChange={e => updateCreateField('password', e.target.value)} minLength={8} required />
          </label>
          <label className="training-field">
            <span>Rol</span>
            <select value={createForm.role} onChange={e => updateCreateField('role', e.target.value)}>
              <option value="user">Usuario</option>
              <option value="admin">Administrador</option>
            </select>
          </label>
          <label className="admin-checkbox">
            <input
              type="checkbox"
              checked={createForm.is_active}
              onChange={e => updateCreateField('is_active', e.target.checked)}
            />
            <span>Crear usuario activo</span>
          </label>

          <div className="training-actions">
            <button className={`btn-procesar ${savingCreate ? 'loading' : ''}`} disabled={savingCreate}>
              {savingCreate ? <><span className="spinner" /> Guardando...</> : 'Crear usuario'}
            </button>
          </div>
        </form>
      </section>

      <section className="step-card">
        <div className="step-head">
          <div className="step-label"><span>12</span> Usuarios registrados</div>
          <p>Administra el estado y los permisos de las cuentas existentes.</p>
        </div>

        {loading ? (
          <div className="preview-loading">
            <span className="spinner spinner-dark" />
            <span>Cargando usuarios...</span>
          </div>
        ) : usuarios.length === 0 ? (
          <div className="empty-state">
            <div>
              <strong>No hay usuarios registrados</strong>
              <p>Cuando crees usuarios desde este modulo apareceran aqui.</p>
            </div>
          </div>
        ) : (
          <div className="admin-users-list">
            {usuarios.map(user => {
              const isEditing = editingId === user.id && editForm
              const isBusy = busyUserId === user.id

              return (
                <article key={user.id} className="admin-user-card">
                  <div className="admin-user-head">
                    <div>
                      <strong>{user.name}</strong>
                      <p>{user.email}</p>
                    </div>
                    <div className="admin-badges">
                      <span className={`admin-badge ${user.role === 'admin' ? 'admin-badge-admin' : ''}`}>
                        {user.role === 'admin' ? 'Administrador' : 'Usuario'}
                      </span>
                      <span className={`admin-badge ${user.is_active ? 'admin-badge-active' : 'admin-badge-inactive'}`}>
                        {user.is_active ? 'Activo' : 'Inactivo'}
                      </span>
                      {currentUser?.id === user.id && (
                        <span className="admin-badge">Tu cuenta</span>
                      )}
                    </div>
                  </div>

                  {isEditing ? (
                    <div className="admin-edit-grid">
                      <label className="training-field">
                        <span>Nombre</span>
                        <input value={editForm.name} onChange={e => updateEditField('name', e.target.value)} />
                      </label>
                      <label className="training-field">
                        <span>Correo</span>
                        <input type="email" value={editForm.email} onChange={e => updateEditField('email', e.target.value)} />
                      </label>
                      <label className="training-field">
                        <span>Rol</span>
                        <select value={editForm.role} onChange={e => updateEditField('role', e.target.value)}>
                          <option value="user">Usuario</option>
                          <option value="admin">Administrador</option>
                        </select>
                      </label>
                      <label className="training-field">
                        <span>Nueva contrasena</span>
                        <input
                          type="password"
                          value={editForm.password}
                          onChange={e => updateEditField('password', e.target.value)}
                          placeholder="Opcional"
                        />
                      </label>
                      <label className="admin-checkbox">
                        <input
                          type="checkbox"
                          checked={editForm.is_active}
                          onChange={e => updateEditField('is_active', e.target.checked)}
                        />
                        <span>Cuenta activa</span>
                      </label>
                      <div className="admin-actions-row">
                        <button
                          type="button"
                          className={`btn-procesar ${savingEdit ? 'loading' : ''}`}
                          onClick={() => handleGuardarEdicion(user.id)}
                          disabled={savingEdit}
                        >
                          {savingEdit ? <><span className="spinner" /> Guardando...</> : 'Guardar cambios'}
                        </button>
                        <button type="button" className="btn-remove" onClick={cancelEdit}>
                          Cancelar
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="admin-actions-row">
                      <button type="button" className="btn-remove" onClick={() => handleEditar(user)}>
                        Editar
                      </button>
                      <button type="button" className="btn-remove" onClick={() => handleToggle(user)} disabled={isBusy}>
                        {user.is_active ? 'Desactivar' : 'Activar'}
                      </button>
                      <button type="button" className="btn-remove admin-danger-btn" onClick={() => handleEliminar(user)} disabled={isBusy}>
                        Eliminar
                      </button>
                    </div>
                  )}
                </article>
              )
            })}
          </div>
        )}
      </section>
    </div>
  )
}

function sortUsuarios(a, b) {
  if (a.role !== b.role) {
    return a.role === 'admin' ? -1 : 1
  }

  return a.name.localeCompare(b.name)
}
