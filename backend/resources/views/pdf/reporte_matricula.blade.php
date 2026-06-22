<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 40px 30px 40px 30px;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9pt;
            color: #333333;
            line-height: 1.4;
        }
        /* Header */
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid {{ $modalidad === 'PUBLICO' ? '#1A56DB' : '#7E22CE' }};
            padding-bottom: 10px;
        }
        .header-title {
            font-size: 16pt;
            font-weight: bold;
            color: {{ $modalidad === 'PUBLICO' ? '#1E40AF' : '#6B21A8' }};
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }
        .header-meta {
            font-size: 9pt;
            color: #666666;
            margin: 0;
        }
        
        /* Summary Grid */
        .summary-box {
            background-color: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 4px 10px;
            vertical-align: top;
        }
        .summary-label {
            font-weight: bold;
            color: #4B5563;
        }
        .summary-value {
            color: #111827;
        }

        /* Main Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th {
            background-color: {{ $modalidad === 'PUBLICO' ? '#1A56DB' : '#7E22CE' }};
            color: #FFFFFF;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8pt;
            padding: 8px 10px;
            border: 1px solid {{ $modalidad === 'PUBLICO' ? '#1E40AF' : '#6B21A8' }};
            text-align: left;
        }
        .data-table th.num-col {
            text-align: right;
        }
        .data-table td {
            padding: 6px 10px;
            border: 1px solid #E5E7EB;
            font-size: 8.5pt;
        }
        .data-table tr:nth-child(even) {
            background-color: #F9FAFB;
        }
        .data-table tr.total-row {
            background-color: {{ $modalidad === 'PUBLICO' ? '#EFF6FF' : '#F5F3FF' }} !important;
            font-weight: bold;
            border-top: 2px solid {{ $modalidad === 'PUBLICO' ? '#1E40AF' : '#6B21A8' }};
        }
        .data-table tr.total-row td {
            border-top: 2px solid {{ $modalidad === 'PUBLICO' ? '#1E40AF' : '#6B21A8' }};
        }
        
        .num-val {
            text-align: right;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 20px;
            font-size: 8pt;
            color: #9CA3AF;
            text-align: center;
            border-top: 1px solid #E5E7EB;
            padding-top: 5px;
        }
        .footer .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="header-title">{{ $title }}</h1>
        <p class="header-meta">
            Plataforma de Matrícula Institucional UGEL | Reporte Operativo de Control
        </p>
    </div>

    <div class="summary-box">
        <table class="summary-table">
            <tr>
                <td style="width: 15%;" class="summary-label">Generado:</td>
                <td style="width: 35%;" class="summary-value">{{ $fecha }}</td>
                <td style="width: 20%;" class="summary-label">Archivo origen:</td>
                <td style="width: 30%;" class="summary-value">{{ $archivo_origen }}</td>
            </tr>
            <tr>
                <td class="summary-label">Modalidad:</td>
                <td class="summary-value">{{ $modalidad }}</td>
                <td class="summary-label">Total Instituciones:</td>
                <td class="summary-value">{{ $total_registros }}</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">Cod. Mod.</th>
                <th style="width: 53%;">Institución Educativa</th>
                <th style="width: 11%;" class="num-col">Matriculados</th>
                <th style="width: 12%;" class="num-col">En Proceso</th>
                <th style="width: 12%;" class="num-col">Secciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item['cod_mod'] }}</td>
                    <td>{{ $item['nombre_ie'] }}</td>
                    <td class="num-val">{{ number_format($item['matriculados']) }}</td>
                    <td class="num-val">{{ number_format($item['en_proceso']) }}</td>
                    <td class="num-val">{{ number_format($item['secciones']) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">TOTALES GENERALES</td>
                <td class="num-val">{{ number_format($totales['matriculados']) }}</td>
                <td class="num-val">{{ number_format($totales['en_proceso']) }}</td>
                <td class="num-val">{{ number_format($totales['secciones']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <span>Reporte generado automáticamente — Página </span><span class="page-number"></span>
    </div>

</body>
</html>
