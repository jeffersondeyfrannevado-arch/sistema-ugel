const COLUMNAS_DISPONIBLES = [
  'DRE', 'UGEL', 'Departamento', 'Provincia', 'Distrito',
  'Nombre de IE', 'Tipo IE', 'Total Matriculados',
  'Matricula Definitiva', 'Matricula En Proceso',
  'DNI Validado', 'DNI sin Validar', 'Sin DNI',
  'Total Secciones',
]

export default function ColumnSelector({ seleccionadas, onChange }) {
  const toggle = (col) => {
    if (seleccionadas.includes(col)) {
      onChange(seleccionadas.filter(c => c !== col))
    } else {
      onChange([...seleccionadas, col])
    }
  }

  return (
    <div className="col-selector">
      <p className="col-selector-hint">
        Estas marcas ayudan a que la revision visual del Excel final sea mas rapida para el equipo.
      </p>
      <div className="col-chips">
        {COLUMNAS_DISPONIBLES.map(col => (
          <button
            key={col}
            type="button"
            className={`chip ${seleccionadas.includes(col) ? 'active' : ''}`}
            onClick={() => toggle(col)}
          >
            <span className="chip-mark">{seleccionadas.includes(col) ? 'ON' : 'OFF'}</span>
            {col}
          </button>
        ))}
      </div>
      <p className="col-selector-count">
        {seleccionadas.length} columna(s) seleccionada(s)
      </p>
    </div>
  )
}
