import { useCallback, useState } from 'react'

export default function UploadZone({ archivo, onArchivo, disabled }) {
  const [dragging, setDragging] = useState(false)

  const handleDrop = useCallback((e) => {
    e.preventDefault()
    setDragging(false)
    const file = e.dataTransfer.files[0]

    if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
      onArchivo(file)
    }
  }, [onArchivo])

  const handleChange = (e) => {
    const file = e.target.files[0]
    if (file) onArchivo(file)
  }

  if (archivo) {
    return (
      <div className="upload-success">
        <div className="upload-stamp">Excel listo</div>
        <div className="upload-info">
          <strong>{archivo.name}</strong>
          <span>{(archivo.size / 1024).toFixed(1)} KB - Archivo seleccionado correctamente</span>
        </div>
        {!disabled && (
          <button className="btn-remove" onClick={() => onArchivo(null)}>Cambiar</button>
        )}
      </div>
    )
  }

  return (
    <div
      className={`upload-zone ${dragging ? 'dragging' : ''}`}
      onDragOver={(e) => { e.preventDefault(); setDragging(true) }}
      onDragLeave={() => setDragging(false)}
      onDrop={handleDrop}
    >
      <div className="upload-illustration">
        <span className="upload-dot" />
        <span className="upload-dot small" />
      </div>
      <p className="upload-zone-title">Sube el reporte de matricula</p>
      <p className="upload-zone-sub">Arrastra el archivo aqui o selecciona el documento desde tu equipo.</p>
      <label className="btn-upload">
        Elegir archivo Excel
        <input type="file" accept=".xlsx,.xls" onChange={handleChange} hidden />
      </label>
    </div>
  )
}
