<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelFormatTrainingService
{
    private const PROFILE_VERSION = 1;

    private const COMMON_FIELD_LABELS = [
        'DRE' => 'DRE',
        'UGEL' => 'UGEL',
        'DEPARTAMENTO' => 'Departamento',
        'PROVINCIA' => 'Provincia',
        'DISTRITO' => 'Distrito',
        'CENTRO_POBLADO' => 'Centro Poblado',
        'COD_MOD' => 'Cod. Mod.',
        'ANEXO' => 'Anexo',
        'NOMBRE_IE' => 'Nombre de IE',
        'NIVEL' => 'Nivel',
        'MODALIDAD' => 'Modalidad',
        'TIPO_IE' => 'Tipo IE',
        'TOTAL_MATRICULADOS' => 'Total Matriculados',
        'MATRICULA_DEFINITIVA' => 'Matricula Definitiva',
        'MATRICULA_EN_PROCESO' => 'Matricula En Proceso',
        'DNI_VALIDADO' => 'DNI Validado',
        'DNI_SIN_VALIDAR' => 'DNI sin Validar',
        'SIN_DNI' => 'Sin DNI',
        'TOTAL_GRADOS' => 'Total Grados',
        'TOTAL_SECCIONES' => 'Total Secciones',
        'NOM_GENERADAS' => 'Nominas Generadas',
        'NOM_APROBADAS' => 'Nominas Aprobadas',
        'NOM_RECTIFICAR' => 'Nominas por Rectificar',
    ];

    private const FIELD_ALIASES = [
        'DRE' => ['DRE'],
        'UGEL' => ['UGEL'],
        'DEPARTAMENTO' => ['DEPARTAMENTO'],
        'PROVINCIA' => ['PROVINCIA'],
        'DISTRITO' => ['DISTRITO'],
        'CENTRO_POBLADO' => ['CENTRO POBLADO'],
        'COD_MOD' => ['COD MOD', 'COD. MOD.', 'COD MOD.', 'CODIGO MODULAR', 'CODIGO MOD'],
        'ANEXO' => ['ANEXO'],
        'NOMBRE_IE' => ['NOMBRE DE IE', 'INSTITUCION EDUCATIVA', 'NOMBRE IE', 'IE'],
        'NIVEL' => ['NIVEL'],
        'MODALIDAD' => ['MODALIDAD'],
        'TIPO_IE' => ['TIPO IE', 'TIPO DE IE'],
        'TOTAL_MATRICULADOS' => ['TOTAL MATRICULADOS', 'TOTAL DE ESTUDIANTES MATRICULADOS', 'TOTAL DE ESTUDIANTES MATRICULADOS (*)'],
        'MATRICULA_DEFINITIVA' => ['MATRICULA DEFINITIVA'],
        'MATRICULA_EN_PROCESO' => ['MATRICULA EN PROCESO', 'EN PROCESO', 'MATRICULA PROCESO'],
        'DNI_VALIDADO' => ['DNI VALIDADO'],
        'DNI_SIN_VALIDAR' => ['DNI SIN VALIDAR'],
        'SIN_DNI' => ['SIN DNI', 'REGISTRADO SIN DNI'],
        'TOTAL_GRADOS' => ['TOTAL GRADOS'],
        'TOTAL_SECCIONES' => ['TOTAL SECCIONES'],
        'NOM_GENERADAS' => ['NOMINAS GENERADAS', 'NOMINAS DE MATRICULA GENERADAS', 'GENERADAS'],
        'NOM_APROBADAS' => ['NOMINAS APROBADAS', 'APROBADAS'],
        'NOM_RECTIFICAR' => ['NOMINAS POR RECTIFICAR', 'POR RECTIFICAR'],
    ];

    private const LEVEL_FIELDS = [
        'INICIAL' => ['E0H', 'E0M', 'E1H', 'E1M', 'E2H', 'E2M', 'E3H', 'E3M', 'E4H', 'E4M', 'E5H', 'E5M', 'EM5H', 'EM5M'],
        'PRIMARIA' => ['PR1H', 'PR1M', 'PR2H', 'PR2M', 'PR3H', 'PR3M', 'PR4H', 'PR4M', 'PR5H', 'PR5M', 'PR6H', 'PR6M'],
        'SECUNDARIA' => ['P1H', 'P1M', 'P2H', 'P2M', 'P3H', 'P3M', 'P4H', 'P4M', 'P5H', 'P5M'],
    ];

    public function __construct(private ?string $profilesPath = null)
    {
        $this->profilesPath ??= storage_path('app/excel_format_profiles.json');
    }

    public function listProfiles(): array
    {
        return $this->readProfiles();
    }

    public function findBestProfile(array $headerRow, array $subHeaderRow, string $sheetTitle, mixed $nivelMuestra, ?string $nivelEsperado = null): ?array
    {
        $profiles = $this->readProfiles();
        if (empty($profiles)) {
            return null;
        }

        $combined = $this->normalizeHeaderText(implode(' ', array_filter(
            array_merge($headerRow, $subHeaderRow, [$sheetTitle, (string) $nivelMuestra]),
            fn ($value) => $value !== null && $value !== ''
        )));

        $bestProfile = null;
        $bestScore = 0;

        foreach ($profiles as $profile) {
            if (
                $nivelEsperado !== null &&
                strtoupper((string) ($profile['nivel'] ?? '')) !== strtoupper($nivelEsperado)
            ) {
                continue;
            }

            $score = 0;

            foreach (($profile['match_keywords'] ?? []) as $keyword) {
                $keywordNorm = $this->normalizeHeaderText((string) $keyword);
                if ($keywordNorm !== '' && str_contains($combined, $keywordNorm)) {
                    $score += 3;
                }
            }

            foreach (($profile['columns'] ?? []) as $field => $index) {
                if (!is_int($index) && !ctype_digit((string) $index)) {
                    continue;
                }

                $idx = (int) $index;
                $main = $this->normalizeHeaderText((string) ($headerRow[$idx] ?? ''));
                $sub = $this->normalizeHeaderText((string) ($subHeaderRow[$idx] ?? ''));
                $combo = trim($main . ' ' . $sub);

                foreach (self::FIELD_ALIASES[$field] ?? [] as $alias) {
                    $aliasNorm = $this->normalizeHeaderText($alias);
                    if ($main === $aliasNorm || $sub === $aliasNorm || str_contains($combo, $aliasNorm)) {
                        $score += 2;
                        break;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProfile = $profile;
            }
        }

        return $bestScore >= 6 ? $bestProfile : null;
    }

    public function analyzeWorkbook(UploadedFile $archivo): array
    {
        $spreadsheet = IOFactory::load($archivo->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray('', true, true, false);

        $dataStartRow = $this->findDataStartRow($rows);
        $headerRowIndex = max(0, $dataStartRow - 2);
        $subHeaderRowIndex = max(0, $dataStartRow - 1);

        $headerRow = $rows[$headerRowIndex] ?? [];
        $subHeaderRow = $rows[$subHeaderRowIndex] ?? [];
        $headers = $this->buildHeaderInventory($headerRow, $subHeaderRow);
        $nivel = $this->inferLevel($headerRow, $subHeaderRow, $sheet->getTitle(), $rows[$dataStartRow][9] ?? '');
        $suggestedColumns = $this->suggestColumnMapping($headerRow, $subHeaderRow, $nivel);
        $matchKeywords = $this->suggestMatchKeywords($sheet->getTitle(), $nivel, $headerRow, $subHeaderRow);

        return [
            'sheet' => $sheet->getTitle(),
            'nivel_sugerido' => $nivel,
            'fila_inicio_datos' => $dataStartRow + 1,
            'fila_headers' => $headerRowIndex + 1,
            'fila_subheaders' => $subHeaderRowIndex + 1,
            'headers_detectados' => $headers,
            'columnas_sugeridas' => $suggestedColumns,
            'campos_requeridos' => array_keys(self::COMMON_FIELD_LABELS),
            'campos_grado_requeridos' => self::LEVEL_FIELDS[$nivel] ?? [],
            'keywords_sugeridas' => $matchKeywords,
        ];
    }

    public function saveProfile(array $payload): array
    {
        $nombre = trim((string) ($payload['nombre'] ?? ''));
        $nivel = strtoupper(trim((string) ($payload['nivel'] ?? '')));
        $columns = $payload['columns'] ?? [];

        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del formato es obligatorio.');
        }

        if (!isset(self::LEVEL_FIELDS[$nivel])) {
            throw new InvalidArgumentException('El nivel del formato no es valido.');
        }

        if (!is_array($columns) || empty($columns)) {
            throw new InvalidArgumentException('Debes enviar el mapeo de columnas del formato.');
        }

        $normalizedColumns = [];
        foreach ($columns as $field => $index) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            if (!is_int($index) && !ctype_digit((string) $index)) {
                continue;
            }

            $normalizedColumns[strtoupper($field)] = (int) $index;
        }

        foreach (array_keys(self::COMMON_FIELD_LABELS) as $field) {
            if (!array_key_exists($field, $normalizedColumns)) {
                throw new InvalidArgumentException("Falta mapear el campo requerido {$field}.");
            }
        }

        foreach (self::LEVEL_FIELDS[$nivel] as $field) {
            if (!array_key_exists($field, $normalizedColumns)) {
                throw new InvalidArgumentException("Falta mapear la columna de grado {$field}.");
            }
        }

        $profiles = $this->readProfiles();
        $profileId = (string) ($payload['id'] ?? Str::uuid());
        $nombre = $this->resolveProfileName($nombre, $nivel, $normalizedColumns, $profiles, $profileId);

        $profile = [
            'id' => $profileId,
            'version' => self::PROFILE_VERSION,
            'nombre' => $nombre,
            'nivel' => $nivel,
            'match_keywords' => array_values(array_filter(array_map(
                fn ($value) => trim((string) $value),
                is_array($payload['match_keywords'] ?? null) ? $payload['match_keywords'] : []
            ))),
            'data_start_row' => (int) ($payload['data_start_row'] ?? 0),
            'header_row_index' => max(0, ((int) ($payload['header_row_index'] ?? 1)) - 1),
            'subheader_row_index' => max(0, ((int) ($payload['subheader_row_index'] ?? 1)) - 1),
            'columns' => $normalizedColumns,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $replaced = false;

        foreach ($profiles as $idx => $existing) {
            if (($existing['id'] ?? '') === $profile['id']) {
                $profile['created_at'] = $existing['created_at'] ?? $profile['created_at'];
                $profiles[$idx] = $profile;
                $replaced = true;
                break;
            }
        }

        if (!$replaced) {
            $profiles[] = $profile;
        }

        $this->writeProfiles($profiles);

        return $profile;
    }

    private function resolveProfileName(string $nombre, string $nivel, array $columns, array $profiles, string $profileId): string
    {
        $sameNameProfiles = array_values(array_filter(
            $profiles,
            fn (array $profile) =>
                ($profile['id'] ?? '') !== $profileId &&
                strtoupper((string) ($profile['nivel'] ?? '')) === $nivel &&
                trim((string) ($profile['nombre'] ?? '')) === $nombre
        ));

        if ($sameNameProfiles === []) {
            return $nombre;
        }

        foreach ($sameNameProfiles as $profile) {
            if (($profile['columns'] ?? []) === $columns) {
                return $nombre;
            }
        }

        return $this->generateUniqueVariantName($nombre, $nivel, $profiles);
    }

    private function generateUniqueVariantName(string $nombre, string $nivel, array $profiles): string
    {
        $usedNames = array_map(
            fn (array $profile) => trim((string) ($profile['nombre'] ?? '')),
            array_filter(
                $profiles,
                fn (array $profile) => strtoupper((string) ($profile['nivel'] ?? '')) === $nivel
            )
        );

        $variant = 2;
        do {
            $candidate = "{$nombre} - Variante {$variant}";
            $variant++;
        } while (in_array($candidate, $usedNames, true));

        return $candidate;
    }

    private function readProfiles(): array
    {
        if (!is_file($this->profilesPath)) {
            return [];
        }

        $content = file_get_contents($this->profilesPath);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeProfiles(array $profiles): void
    {
        $dir = dirname($this->profilesPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->profilesPath,
            json_encode(array_values($profiles), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function findDataStartRow(array $rows): int
    {
        foreach ($rows as $idx => $row) {
            if (isset($row[0]) && strtoupper(trim((string) $row[0])) === 'DRE') {
                return $idx + 2;
            }
        }

        return 8;
    }

    private function inferLevel(array $headerRow, array $subHeaderRow, string $sheetTitle, mixed $nivelMuestra): string
    {
        $text = $this->normalizeHeaderText(implode(' ', array_filter(
            array_merge($headerRow, $subHeaderRow, [$sheetTitle, (string) $nivelMuestra]),
            fn ($value) => $value !== null && $value !== ''
        )));

        if (
            str_contains($text, 'INICIAL') ||
            str_contains($text, '0 ANOS') ||
            str_contains($text, '1 ANO') ||
            str_contains($text, 'MAS DE 5 ANOS')
        ) {
            return 'INICIAL';
        }

        if (
            str_contains($text, 'PRIMARIA') ||
            str_contains($text, 'B0 - PRIMARIA') ||
            str_contains($text, 'SEXTO')
        ) {
            return 'PRIMARIA';
        }

        return 'SECUNDARIA';
    }

    private function buildHeaderInventory(array $headerRow, array $subHeaderRow): array
    {
        $headers = [];
        $limit = max(count($headerRow), count($subHeaderRow));

        for ($i = 0; $i < $limit; $i++) {
            $main = $this->cleanText((string) ($headerRow[$i] ?? ''));
            $sub = $this->cleanText((string) ($subHeaderRow[$i] ?? ''));
            $composed = $main !== '' && $sub !== '' ? "{$main} - {$sub}" : ($main !== '' ? $main : $sub);

            $headers[] = [
                'index' => $i,
                'header' => $main,
                'subheader' => $sub,
                'label' => $composed !== '' ? $composed : 'Columna ' . ($i + 1),
            ];
        }

        return $headers;
    }

    private function suggestColumnMapping(array $headerRow, array $subHeaderRow, string $nivel): array
    {
        $map = [];
        $limit = max(count($headerRow), count($subHeaderRow));

        for ($i = 0; $i < $limit; $i++) {
            $main = $this->normalizeHeaderText((string) ($headerRow[$i] ?? ''));
            $sub = $this->normalizeHeaderText((string) ($subHeaderRow[$i] ?? ''));
            $combo = trim($main . ' ' . $sub);

            foreach (self::FIELD_ALIASES as $field => $aliases) {
                if (isset($map[$field])) {
                    continue;
                }

                foreach ($aliases as $alias) {
                    $aliasNorm = $this->normalizeHeaderText($alias);
                    if ($main === $aliasNorm || $sub === $aliasNorm || str_contains($combo, $aliasNorm)) {
                        $map[$field] = $i;
                        break;
                    }
                }
            }
        }

        foreach ($this->suggestGradeColumns($headerRow, $subHeaderRow, $nivel) as $field => $index) {
            $map[$field] = $index;
        }

        return $map;
    }

    private function suggestGradeColumns(array $headerRow, array $subHeaderRow, string $nivel): array
    {
        $indices = [];
        $start = null;
        $limit = max(count($headerRow), count($subHeaderRow));

        for ($i = 0; $i < $limit; $i++) {
            $main = $this->normalizeHeaderText((string) ($headerRow[$i] ?? ''));
            $sub = $this->normalizeHeaderText((string) ($subHeaderRow[$i] ?? ''));

            if ($start === null && in_array($sub, ['HOMBRES', 'MUJERES'], true) && $main !== '') {
                $start = $i;
            }

            if ($start !== null && in_array($sub, ['HOMBRES', 'MUJERES'], true)) {
                $indices[] = $i;
            }
        }

        $fields = self::LEVEL_FIELDS[$nivel] ?? [];
        $mapping = [];

        foreach ($fields as $position => $field) {
            if (isset($indices[$position])) {
                $mapping[$field] = $indices[$position];
            }
        }

        return $mapping;
    }

    private function suggestMatchKeywords(string $sheetTitle, string $nivel, array $headerRow, array $subHeaderRow): array
    {
        $keywords = [$nivel, $sheetTitle];

        foreach (array_merge($headerRow, $subHeaderRow) as $value) {
            $text = $this->cleanText((string) $value);
            if ($text !== '' && strlen($text) > 4) {
                $keywords[] = $text;
            }
        }

        return array_values(array_slice(array_unique(array_filter($keywords)), 0, 8));
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function normalizeHeaderText(string $text): string
    {
        $text = $this->cleanText($text);
        $text = Str::ascii($text);
        $text = strtoupper($text);
        $text = str_replace(["'", "`", "´", "’"], '', $text);

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }
}
