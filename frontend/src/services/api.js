const BASE_URL = import.meta.env.VITE_API_URL || (import.meta.env.PROD ? 'https://sistema-ugel-api.onrender.com/api' : 'http://127.0.0.1:8000/api')

function getHeaders(isFormData = false) {
  const token = localStorage.getItem('auth_token')
  const headers = {}
  if (token) headers['Authorization'] = `Bearer ${token}`
  if (!isFormData) {
    headers['Content-Type'] = 'application/json'
    headers['Accept'] = 'application/json'
  }
  return headers
}

async function parseJsonResponse(res, defaultMessage) {
  const data = await res.json()

  if (!res.ok || data?.success === false) {
    if (res.status === 401) throw new Error('No autorizado')
    throw new Error(data?.mensaje || data?.message || defaultMessage)
  }

  return data
}

export async function login(email, password) {
  const res = await fetch(`${BASE_URL}/login`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ email, password }),
  })
  return parseJsonResponse(res, 'Error en login')
}

export async function registerUser(name, email, password) {
  const res = await fetch(`${BASE_URL}/register`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ name, email, password }),
  })
  return parseJsonResponse(res, 'Error en registro')
}

export async function getCurrentUser() {
  const res = await fetch(`${BASE_URL}/user`, {
    headers: getHeaders(),
  })
  return parseJsonResponse(res, 'No se pudo obtener el usuario')
}

export async function verifyMfaLogin(challengeId, code) {
  const res = await fetch(`${BASE_URL}/login/mfa/verify`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ challenge_id: challengeId, code }),
  })
  return parseJsonResponse(res, 'No se pudo validar el codigo MFA')
}

export async function resendMfaCode(challengeId) {
  const res = await fetch(`${BASE_URL}/login/mfa/resend`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ challenge_id: challengeId }),
  })
  return parseJsonResponse(res, 'No se pudo reenviar el codigo MFA')
}

export async function logout() {
  const res = await fetch(`${BASE_URL}/logout`, {
    method: 'POST',
    headers: getHeaders(),
  })
  if (res.ok) {
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user')
    localStorage.removeItem('auth_expires_at')
  }
}

export async function refreshToken() {
  const res = await fetch(`${BASE_URL}/token/refresh`, {
    method: 'POST',
    headers: getHeaders(),
  })
  return parseJsonResponse(res, 'No se pudo renovar la sesion')
}

export async function previewArchivo(archivo) {
  const formData = new FormData()
  formData.append('archivo', archivo)

  const res = await fetch(`${BASE_URL}/matricula/preview`, {
    method: 'POST',
    headers: getHeaders(true),
    body: formData,
  })

  const data = await parseJsonResponse(res, 'No se pudo generar la vista previa')
  return data.preview
}

export async function procesarArchivo(archivo, columnasResaltadas) {
  const formData = new FormData()
  formData.append('archivo', archivo)
  columnasResaltadas.forEach(col => formData.append('columnas_resaltadas[]', col))

  const res = await fetch(`${BASE_URL}/matricula/procesar`, {
    method: 'POST',
    headers: getHeaders(true),
    body: formData,
  })

  const contentType = res.headers.get('content-type') || ''
  const isJson = contentType.includes('application/json')
  const data = isJson ? await res.json() : null

  if (!res.ok || !data.success) {
    if (res.status === 401) throw new Error('No autorizado')
    const fallbackMessage = !isJson
      ? `Error en el servidor (${res.status}). La respuesta no fue JSON.`
      : 'Error en el servidor'

    throw new Error(data?.mensaje || fallbackMessage)
  }
  return data
}

export async function listarFormatosExcel() {
  const res = await fetch(`${BASE_URL}/matricula/formatos`, {
    headers: getHeaders(),
  })
  const data = await parseJsonResponse(res, 'No se pudieron listar los formatos')
  return data.formatos
}

export async function analizarFormatoExcel(archivo) {
  const formData = new FormData()
  formData.append('archivo', archivo)

  const res = await fetch(`${BASE_URL}/matricula/formatos/analizar`, {
    method: 'POST',
    headers: getHeaders(true),
    body: formData,
  })

  const data = await parseJsonResponse(res, 'No se pudo analizar el formato')
  return data.analisis
}

export async function guardarFormatoExcel(payload) {
  const res = await fetch(`${BASE_URL}/matricula/formatos`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify(payload),
  })

  const data = await parseJsonResponse(res, 'No se pudo guardar el formato')
  return data.formato
}

export async function listarUsuariosAdmin(filters = {}) {
  const query = new URLSearchParams(filters)
  const res = await fetch(`${BASE_URL}/admin/users${query.toString() ? `?${query.toString()}` : ''}`, {
    headers: getHeaders(),
  })
  const data = await parseJsonResponse(res, 'No se pudieron cargar los usuarios')
  return data.usuarios
}

export async function crearUsuarioAdmin(payload) {
  const res = await fetch(`${BASE_URL}/admin/users`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify(payload),
  })
  const data = await parseJsonResponse(res, 'No se pudo crear el usuario')
  return data.usuario
}

export async function actualizarUsuarioAdmin(userId, payload) {
  const res = await fetch(`${BASE_URL}/admin/users/${userId}`, {
    method: 'PUT',
    headers: getHeaders(),
    body: JSON.stringify(payload),
  })
  const data = await parseJsonResponse(res, 'No se pudo actualizar el usuario')
  return data.usuario
}

export async function alternarEstadoUsuarioAdmin(userId) {
  const res = await fetch(`${BASE_URL}/admin/users/${userId}/toggle-status`, {
    method: 'PATCH',
    headers: getHeaders(),
  })
  const data = await parseJsonResponse(res, 'No se pudo cambiar el estado del usuario')
  return data.usuario
}

export async function eliminarUsuarioAdmin(userId) {
  const res = await fetch(`${BASE_URL}/admin/users/${userId}`, {
    method: 'DELETE',
    headers: getHeaders(),
  })
  await parseJsonResponse(res, 'No se pudo eliminar el usuario')
}

export async function obtenerDashboardAdmin(filters = {}) {
  const query = new URLSearchParams(filters)
  const res = await fetch(`${BASE_URL}/admin/dashboard${query.toString() ? `?${query.toString()}` : ''}`, {
    headers: getHeaders(),
  })
  const data = await parseJsonResponse(res, 'No se pudo cargar el dashboard administrativo')
  return data.dashboard
}

export async function exportarDashboardAdmin(filters = {}, format = 'excel') {
  const query = new URLSearchParams({ ...filters, format })
  const res = await fetch(`${BASE_URL}/admin/dashboard/export?${query.toString()}`, {
    headers: getHeaders(),
  })
  if (!res.ok) throw new Error('No se pudo exportar el dashboard')

  const blob = await res.blob()
  const extension = format === 'pdf' ? 'pdf' : 'xlsx'
  downloadBlob(blob, `dashboard_admin_${new Date().toISOString().slice(0, 10)}.${extension}`)
}

export async function listarContenidosAdmin(filters = {}) {
  const query = new URLSearchParams(filters)
  const res = await fetch(`${BASE_URL}/admin/contents${query.toString() ? `?${query.toString()}` : ''}`, {
    headers: getHeaders(),
  })
  const data = await parseJsonResponse(res, 'No se pudieron cargar los contenidos')
  return data.contenidos
}

export async function crearContenidoAdmin(payload) {
  const res = await fetch(`${BASE_URL}/admin/contents`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify(payload),
  })
  const data = await parseJsonResponse(res, 'No se pudo crear el contenido')
  return data.contenido
}

export async function actualizarContenidoAdmin(contentId, payload) {
  const res = await fetch(`${BASE_URL}/admin/contents/${contentId}`, {
    method: 'PUT',
    headers: getHeaders(),
    body: JSON.stringify(payload),
  })
  const data = await parseJsonResponse(res, 'No se pudo actualizar el contenido')
  return data.contenido
}

export async function moderarContenidoAdmin(contentId, payload) {
  const res = await fetch(`${BASE_URL}/admin/contents/${contentId}/moderate`, {
    method: 'PATCH',
    headers: getHeaders(),
    body: JSON.stringify(payload),
  })
  const data = await parseJsonResponse(res, 'No se pudo moderar el contenido')
  return data.contenido
}

export async function eliminarContenidoAdmin(contentId) {
  const res = await fetch(`${BASE_URL}/admin/contents/${contentId}`, {
    method: 'DELETE',
    headers: getHeaders(),
  })
  await parseJsonResponse(res, 'No se pudo eliminar el contenido')
}

export async function listarAuditoriaAdmin(filters = {}) {
  const query = new URLSearchParams(filters)
  const res = await fetch(`${BASE_URL}/admin/audit-logs${query.toString() ? `?${query.toString()}` : ''}`, {
    headers: getHeaders(),
  })
  const data = await parseJsonResponse(res, 'No se pudo cargar la auditoria')
  return data.logs
}

export async function listarRespaldosAdmin() {
  const res = await fetch(`${BASE_URL}/admin/backups`, {
    headers: getHeaders(),
  })
  const data = await parseJsonResponse(res, 'No se pudieron cargar los respaldos')
  return data.respaldos
}

export async function crearRespaldoAdmin() {
  const res = await fetch(`${BASE_URL}/admin/backups`, {
    method: 'POST',
    headers: getHeaders(),
  })
  const data = await parseJsonResponse(res, 'No se pudo generar el respaldo')
  return data.respaldo
}

export async function restaurarRespaldoAdmin(backupId) {
  const res = await fetch(`${BASE_URL}/admin/backups/${backupId}/restore`, {
    method: 'POST',
    headers: getHeaders(),
  })
  const data = await parseJsonResponse(res, 'No se pudo restaurar el respaldo')
  return data
}

export async function descargarArchivo(rutaBase64, nombreArchivo) {
  const res = await fetch(`${BASE_URL}/matricula/descargar/${rutaBase64}`, { headers: getHeaders() })
  if (!res.ok) throw new Error('No se pudo descargar el archivo')

  const blob = await res.blob()
  downloadBlob(blob, nombreArchivo)
}

export async function descargarPdf(rutaBase64, nombreArchivo) {
  const res = await fetch(`${BASE_URL}/matricula/descargar-pdf/${rutaBase64}`, { headers: getHeaders() })
  if (!res.ok) throw new Error('No se pudo descargar el PDF')

  const blob = await res.blob()
  downloadBlob(blob, nombreArchivo.replace(/\.xlsx$/i, '.pdf'))
}

export async function descargarZip() {
  const res = await fetch(`${BASE_URL}/matricula/descargar-zip`, { headers: getHeaders() })
  if (!res.ok) throw new Error('No se pudo generar el ZIP')

  const blob = await res.blob()
  downloadBlob(blob, `matricula_${new Date().toISOString().slice(0, 10)}.zip`)
}

function downloadBlob(blob, fileName) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = fileName
  a.click()
  URL.revokeObjectURL(url)
}

export async function sendForgotPasswordCode(email) {
  const res = await fetch(`${BASE_URL}/password/forgot`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ email }),
  })
  return parseJsonResponse(res, 'No se pudo enviar el código de recuperación')
}

export async function resetPassword(email, code, password) {
  const res = await fetch(`${BASE_URL}/password/reset`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ email, code, password }),
  })
  return parseJsonResponse(res, 'No se pudo restablecer la contraseña')
}

export async function exportarNexusColegio(colegioNombre) {
  const res = await fetch(`${BASE_URL}/matricula/nexus/exportar-colegio`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ colegio: colegioNombre }),
  })
  if (!res.ok) throw new Error('No se pudo exportar el colegio NEXUS')
  const blob = await res.blob()
  const safeName = colegioNombre.replace(/[^A-Za-z0-9_-]/g, '_')
  downloadBlob(blob, `NEXUS - ${safeName}.xlsx`)
}

export async function exportarMatriculaColegio(colegioCodigo) {
  const res = await fetch(`${BASE_URL}/matricula/colegio/exportar`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ colegio: colegioCodigo }),
  })
  if (!res.ok) throw new Error('No se pudo exportar el reporte de matrícula')
  const blob = await res.blob()
  const safeCode = colegioCodigo.replace(/[^A-Za-z0-9_-]/g, '_')
  downloadBlob(blob, `REPORTE - I.E. ${safeCode}.xlsx`)
}

export async function procesarFiltradoColegio(archivo) {
  const formData = new FormData()
  formData.append('archivo', archivo)

  const res = await fetch(`${BASE_URL}/matricula/filtrar-colegio/procesar`, {
    method: 'POST',
    headers: getHeaders(true),
    body: formData,
  })

  return parseJsonResponse(res, 'Error al procesar el archivo para filtrado de colegio')
}

export async function exportarFiltradoColegio(colegio, tipoExcel) {
  const res = await fetch(`${BASE_URL}/matricula/filtrar-colegio/exportar`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ colegio, tipo_excel: tipoExcel }),
  })

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}))
    throw new Error(errorData.error || errorData.mensaje || 'Error al exportar el archivo del colegio')
  }

  const blob = await res.blob()
  const safeName = colegio.replace(/[^A-Za-z0-9_-]/g, '_')
  const defaultFileName = tipoExcel === 'NEXUS' ? `NEXUS - ${safeName}.xlsx` : `REPORTE - I.E. ${safeName}.xlsx`

  // Obtener filename de Content-Disposition si existe
  const disposition = res.headers.get('content-disposition')
  let fileName = defaultFileName
  if (disposition && disposition.indexOf('filename=') !== -1) {
    const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/
    const matches = filenameRegex.exec(disposition)
    if (matches != null && matches[1]) {
      fileName = matches[1].replace(/['"]/g, '')
    }
  }

  downloadBlob(blob, fileName)
}

export async function exportarZipColegios(tipoExcel) {
  const res = await fetch(`${BASE_URL}/matricula/filtrar-colegio/exportar-zip`, {
    method: 'POST',
    headers: getHeaders(),
  })

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}))
    throw new Error(errorData.error || errorData.mensaje || 'Error al exportar el paquete ZIP de colegios')
  }

  const blob = await res.blob()
  const defaultFileName = tipoExcel === 'NEXUS' ? 'NEXUS_Todos_Los_Colegios.zip' : 'REPORTE_Todos_Los_Colegios.zip'

  downloadBlob(blob, defaultFileName)
}



