export default function PreviewTable({ preview, cargando }) {
  if (cargando) {
    return (
      <div className="preview-loading">
        <span className="spinner spinner-dark" />
        <span>Generando vista previa del Excel...</span>
      </div>
    )
  }

  if (!preview) return null

  return (
    <section className="step-card preview-card">
      <div className="step-head">
        <div className="step-label"><span>02</span> Vista previa</div>
        <p>Hoja: {preview.sheet} · Nivel detectado: {preview.nivel} · Datos desde fila {preview.fila_inicio_datos}</p>
      </div>

      <div className="preview-meta">
        <span>{preview.total_filas_excel} fila(s) en el Excel</span>
        <span>{preview.rows.length} fila(s) mostradas</span>
      </div>

      <div className="preview-table-wrap">
        <table className="preview-table">
          <thead>
            <tr>
              {preview.headers.map((header, index) => (
                <th key={`${header}-${index}`}>{header}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {preview.rows.map((row, rowIndex) => (
              <tr key={rowIndex}>
                {preview.headers.map((_, cellIndex) => (
                  <td key={`${rowIndex}-${cellIndex}`}>{row[cellIndex] || '-'}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}
