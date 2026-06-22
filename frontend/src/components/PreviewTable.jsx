import { useMemo } from 'react'

const getColumnStyle = (header, index, rows) => {
  const h = (header || '').toLowerCase();
  
  // Default alignment and min-width
  let align = 'left';
  let minWidth = '130px';
  
  if (
    h.includes('cód') || 
    h.includes('anexo') || 
    h.includes('código') || 
    h.includes('id') || 
    h.includes('nro') || 
    h.includes('número') ||
    h.includes('telefono') ||
    h.includes('teléfono')
  ) {
    align = 'center';
    minWidth = '110px';
  } else {
    // Check if the values in this column are mostly numeric (empty/dashes/numbers)
    let numericCount = 0;
    let totalCount = 0;
    
    for (let i = 0; i < Math.min(rows.length, 15); i++) {
      const val = rows[i][index];
      if (val !== undefined && val !== null) {
        const strVal = String(val).trim();
        if (strVal !== '') {
          totalCount++;
          if (strVal === '-' || !isNaN(strVal.replace(/[,.%]/g, ''))) {
            numericCount++;
          }
        }
      }
    }
    
    if (totalCount > 0 && numericCount / totalCount > 0.8) {
      align = 'right';
      minWidth = '140px';
    }
  }

  // Adjust min-width based on specific header names for better spacing
  if (h.includes('nombre') || h.includes('ie') || h.includes('institucion') || h.includes('institución')) {
    minWidth = '280px';
  } else if (h.includes('tipo') || h.includes('modalidad')) {
    minWidth = '220px';
  } else if (h.includes('dre') || h.includes('ugel')) {
    minWidth = '110px';
  } else if (h.includes('departamento') || h.includes('provincia') || h.includes('distrito') || h.includes('centro poblado')) {
    minWidth = '160px';
  } else if (h.includes('estudiantes') || h.includes('matriculados') || h.includes('matrícula') || h.includes('definitiva') || h.includes('proceso')) {
    minWidth = '160px';
  }

  return { 
    textAlign: align, 
    minWidth: minWidth,
    width: minWidth
  };
};

export default function PreviewTable({ preview, cargando }) {
  const columnStyles = useMemo(() => {
    if (!preview || !preview.headers) return [];
    return preview.headers.map((header, index) => getColumnStyle(header, index, preview.rows));
  }, [preview]);

  const tableMinWidth = useMemo(() => {
    if (!columnStyles || columnStyles.length === 0) return '100%';
    const total = columnStyles.reduce((acc, style) => {
      const val = parseInt(style.minWidth, 10) || 0;
      return acc + val;
    }, 0);
    return `${total}px`;
  }, [columnStyles]);

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
        <table className="preview-table" style={{ minWidth: tableMinWidth }}>
          <thead>
            <tr>
              {preview.headers.map((header, index) => (
                <th key={`${header}-${index}`} style={columnStyles[index]}>
                  {header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {preview.rows.map((row, rowIndex) => (
              <tr key={rowIndex}>
                {preview.headers.map((_, cellIndex) => (
                  <td key={`${rowIndex}-${cellIndex}`} style={columnStyles[cellIndex]}>
                    {row[cellIndex] || '-'}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}

