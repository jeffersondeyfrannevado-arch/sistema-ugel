const BASE_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'

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

export async function login(email, password) {
  const res = await fetch(`${BASE_URL}/login`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ email, password }),
  })
  const data = await res.json()
  if (!res.ok) throw new Error(data.message || 'Error en login')
  return data
}

export async function registerUser(name, email, password) {
  const res = await fetch(`${BASE_URL}/register`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ name, email, password }),
  })
  const data = await res.json()
  if (!res.ok) throw new Error(data.message || 'Error en registro')
  return data
}

export async function logout() {
  const res = await fetch(`${BASE_URL}/logout`, {
    method: 'POST',
    headers: getHeaders(),
  })
  if (res.ok) {
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user')
  }
}

export async function previewArchivo(archivo) {
  const formData = new FormData()
  formData.append('archivo', archivo)

  const res = await fetch(`${BASE_URL}/matricula/preview`, {
    method: 'POST',
    headers: getHeaders(true),
    body: formData,
  })

  const data = await res.json()
  if (!res.ok || !data.success) {
    if (res.status === 401) throw new Error('No autorizado')
    throw new Error(data?.mensaje || 'No se pudo generar la vista previa')
  }

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

export async function descargarArchivo(rutaBase64, nombreArchivo) {
  const res = await fetch(`${BASE_URL}/matricula/descargar/${rutaBase64}`, { headers: getHeaders() })
  if (!res.ok) throw new Error('No se pudo descargar el archivo')

  const blob = await res.blob()
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = nombreArchivo
  a.click()
  URL.revokeObjectURL(url)
}

export async function descargarPdf(rutaBase64, nombreArchivo) {
  const res = await fetch(`${BASE_URL}/matricula/descargar-pdf/${rutaBase64}`, { headers: getHeaders() })
  if (!res.ok) throw new Error('No se pudo descargar el PDF')

  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = nombreArchivo.replace(/\.xlsx$/i, '.pdf')
  a.click()
  URL.revokeObjectURL(url)
}

export async function descargarZip() {
  const res = await fetch(`${BASE_URL}/matricula/descargar-zip`, { headers: getHeaders() })
  if (!res.ok) throw new Error('No se pudo generar el ZIP')

  const blob = await res.blob()
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = `matricula_${new Date().toISOString().slice(0, 10)}.zip`
  a.click()
  a.click()
  URL.revokeObjectURL(url)
}
