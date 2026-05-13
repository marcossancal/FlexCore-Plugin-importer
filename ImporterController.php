<?php

declare(strict_types=1);

namespace FlexCoreDataImporter;

/**
 * ImporterController — lógica do FlexCore Data Importer.
 *
 * Fluxo em 3 etapas:
 *   STEP 1 — Upload do CSV + configuração do delimitador
 *   STEP 2 — Preview das colunas detectadas + mapeamento de tipos de campo
 *   STEP 3 — Execução da importação + relatório de resultado
 *
 * Rotas (registradas em config/routes.php):
 *   GET  /importer                  → index()   — tela inicial
 *   POST /importer/upload           → upload()  — step 1 → processa CSV
 *   GET  /importer/map/{token}      → map()     → step 2 — mapeamento de campos
 *   POST /importer/map/{token}      → saveMap() → step 2 — salva mapeamento + cria campos
 *   POST /importer/run/{token}      → run()     → step 3 — executa importação
 *   GET  /importer/result/{token}   → result()  → step 3 — relatório
 */
class ImporterController
{
    // Tipos de campo disponíveis para mapeamento
    private const FIELD_TYPES = [
        'text'        => 'Texto',
        'textarea'    => 'Texto longo',
        'number'      => 'Número',
        'currency'    => 'Moeda (R$)',
        'email'       => 'E-mail',
        'url'         => 'URL',
        'phone'       => 'Telefone',
        'date'        => 'Data',
        'datetime'    => 'Data e hora',
        'select'      => 'Seleção única',
        'checkbox'    => 'Checkbox',
        '_skip'       => '— Ignorar coluna —',
    ];

    // ── STEP 1 — Tela inicial ─────────────────────────────────────────

    public function index(): void
    {
        \Auth::require(['admin', 'editor']);
        $entities = \DB::q('SELECT id, name, slug, icon FROM entities WHERE active = 1 ORDER BY name ASC');
        $this->view('index', compact('entities'));
    }

    // ── STEP 1 → STEP 2 — Upload + parse CSV ─────────────────────────

    public function upload(): void
    {
        \Auth::require(['admin', 'editor']);

        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flash('err', 'Erro no upload. Verifique o arquivo e tente novamente.');
            redirect('/importer');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            flash('err', 'Apenas arquivos .csv são aceitos.');
            redirect('/importer');
        }

        $entityId   = (int) ($_POST['entity_id'] ?? 0);
        $delimiter  = $_POST['delimiter']  ?? ',';
        $enclosure  = $_POST['enclosure']  ?? '"';
        $hasHeader  = ($_POST['has_header'] ?? '1') === '1';
        $encoding   = $_POST['encoding']   ?? 'utf-8';

        // Validar entidade
        $entity = \DB::one('SELECT * FROM entities WHERE id = ? AND active = 1', [$entityId]);
        if (!$entity) {
            flash('err', 'Entidade não encontrada.');
            redirect('/importer');
        }

        // Normalizar delimitador
        $delimiters = [
            'comma'     => ',',
            'semicolon' => ';',
            'tab'       => "\t",
            'pipe'      => '|',
        ];
        $sep = $delimiters[$delimiter] ?? $delimiter;
        if (strlen($sep) !== 1) $sep = ',';

        // Ler e converter encoding se necessário
        $rawContent = file_get_contents($file['tmp_name']);
        if (strtolower($encoding) !== 'utf-8') {
            $rawContent = mb_convert_encoding($rawContent, 'UTF-8', $encoding);
        }
        // Remove BOM UTF-8 se presente
        $rawContent = ltrim($rawContent, "\xEF\xBB\xBF");

        // Parse CSV
        $lines  = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $rawContent);
        rewind($handle);

        while (($row = fgetcsv($handle, 0, $sep, $enclosure)) !== false) {
            $lines[] = $row;
        }
        fclose($handle);

        if (empty($lines)) {
            flash('err', 'O arquivo CSV está vazio.');
            redirect('/importer');
        }

        // Extrair cabeçalhos
        if ($hasHeader) {
            $headers  = array_shift($lines);
            $headers  = array_map('trim', $headers);
        } else {
            // Gerar nomes automáticos col_1, col_2...
            $colCount = count($lines[0] ?? []);
            $headers  = array_map(fn($i) => "col_{$i}", range(1, $colCount));
        }

        // Preview: até 5 linhas
        $preview = array_slice($lines, 0, 5);

        // Detectar tipo sugerido para cada coluna
        $suggested = $this->detectTypes($headers, $lines);

        // Campos já existentes na entidade
        $existingFields = \DB::q(
            'SELECT slug FROM entity_fields WHERE entity_id = ?',
            [$entityId]
        );
        $existingSlugs = array_column($existingFields, 'slug');

        // Salvar sessão de importação
        $token   = bin2hex(random_bytes(16));
        $session = [
            'token'          => $token,
            'entity_id'      => $entityId,
            'entity_name'    => $entity['name'],
            'entity_slug'    => $entity['slug'],
            'delimiter'      => $sep,
            'enclosure'      => $enclosure,
            'headers'        => $headers,
            'suggested'      => $suggested,
            'existing_slugs' => $existingSlugs,
            'total_rows'     => count($lines),
            'created_at'     => time(),
        ];

        // Guardar dados brutos no arquivo temporário (evita sessão gigante)
        $tmpFile = sys_get_temp_dir() . "/flexcore_import_{$token}.php";
        file_put_contents($tmpFile, '<?php return ' . var_export([
            'rows'    => $lines,
            'session' => $session,
        ], true) . ';');

        $_SESSION['importer_token'] = $token;
        $_SESSION['importer_file']  = $tmpFile;

        redirect("/importer/map/{$token}");
    }

    // ── STEP 2 — Mapeamento de campos ─────────────────────────────────

    public function map(string $token): void
    {
        \Auth::require(['admin', 'editor']);
        $data = $this->loadSession($token);
        if (!$data) { flash('err', 'Sessão expirada. Faça o upload novamente.'); redirect('/importer'); }

        $session        = $data['session'];
        $preview        = array_slice($data['rows'], 0, 5);
        $fieldTypes     = self::FIELD_TYPES;
        $entities       = \DB::q('SELECT id, name FROM entities WHERE active = 1 ORDER BY name ASC');

        // Pré-calcular slugs para usar na view sem expor o método
        $headerSlugs = array_map([$this, 'makeSlugPublic'], $session['headers']);

        $this->view('map', compact('session', 'preview', 'fieldTypes', 'entities', 'token', 'headerSlugs'));
    }

    // ── STEP 2 POST — Salvar mapeamento + criar campos ────────────────

    public function saveMap(string $token): void
    {
        \Auth::require(['admin', 'editor']);
        $data = $this->loadSession($token);
        if (!$data) { flash('err', 'Sessão expirada.'); redirect('/importer'); }

        $session  = $data['session'];
        $entityId = $session['entity_id'];
        $mapping  = $_POST['mapping'] ?? [];  // [colIndex => field_type]
        $names    = $_POST['name']    ?? [];  // [colIndex => field_name]
        $slugs    = $_POST['slug']    ?? [];  // [colIndex => field_slug]
        $required = $_POST['required'] ?? []; // [colIndex => '1']

        // Cria campos novos que ainda não existem
        $created = 0;
        $skipped = 0;

        foreach ($session['headers'] as $i => $header) {
            $type = $mapping[$i] ?? '_skip';
            if ($type === '_skip') { $skipped++; continue; }

            $fieldName = trim($names[$i] ?? $header);
            $fieldSlug = $this->makeSlug(trim($slugs[$i] ?? $fieldName));

            // Evitar duplicata de slug na entidade
            if (in_array($fieldSlug, $session['existing_slugs'], true)) {
                continue; // já existe — vai mapear para esse campo
            }

            // Posição = próxima disponível
            $maxPos = \DB::one(
                'SELECT COALESCE(MAX(position),0)+1 AS p FROM entity_fields WHERE entity_id = ?',
                [$entityId]
            )['p'] ?? 1;

            \DB::exec(
                'INSERT INTO entity_fields
                    (entity_id, name, slug, field_type, required, show_in_list, position)
                 VALUES (?, ?, ?, ?, ?, 1, ?)',
                [$entityId, $fieldName, $fieldSlug, $type, isset($required[$i]) ? 1 : 0, $maxPos]
            );

            $session['existing_slugs'][] = $fieldSlug;
            $created++;
        }

        // Persistir mapeamento na sessão
        $session['mapping'] = $mapping;
        $session['names']   = $names;
        $session['slugs']   = array_map([$this, 'makeSlug'], $slugs);

        $data['session'] = $session;
        file_put_contents(
            $_SESSION['importer_file'],
            '<?php return ' . var_export($data, true) . ';'
        );
        $_SESSION['importer_token'] = $token;

        flash('ok', "{$created} campo(s) criado(s). Pronto para importar!");
        redirect("/importer/run/{$token}");
    }

    // ── STEP 3 — Confirmar e executar importação ──────────────────────

    public function run(string $token): void
    {
        \Auth::require(['admin', 'editor']);
        $data = $this->loadSession($token);
        if (!$data) { flash('err', 'Sessão expirada.'); redirect('/importer'); }

        // Se GET — mostra tela de confirmação
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $session = $data['session'];
            $this->view('run', compact('session', 'token'));
            return;
        }

        // POST — executa a importação
        $session  = $data['session'];
        $rows     = $data['rows'];
        $entityId = $session['entity_id'];
        $mapping  = $session['mapping'] ?? [];
        $slugMap  = $session['slugs']   ?? [];

        // Carregar campos da entidade (com IDs)
        $fields = \DB::q(
            'SELECT id, slug, field_type FROM entity_fields WHERE entity_id = ?',
            [$entityId]
        );
        $fieldsBySlug = array_column($fields, null, 'slug');

        $imported = 0;
        $errors   = [];
        $userId   = \Auth::id();

        foreach ($rows as $lineNum => $row) {
            try {
                // Criar o registro pai
                $recordId = \DB::exec(
                    'INSERT INTO entity_records (entity_id, created_by, created_at, updated_at)
                     VALUES (?, ?, NOW(), NOW())',
                    [$entityId, $userId]
                );

                // Salvar cada valor
                foreach ($session['headers'] as $i => $header) {
                    $type = $mapping[$i] ?? '_skip';
                    if ($type === '_skip') continue;

                    $slug  = $this->makeSlug(trim($slugMap[$i] ?? $session['names'][$i] ?? $header));
                    $field = $fieldsBySlug[$slug] ?? null;
                    if (!$field) continue;

                    $raw = trim($row[$i] ?? '');
                    if ($raw === '') continue;

                    [$valText, $valNum, $valDate] = [null, null, null];

                    if (in_array($field['field_type'], ['number', 'currency'], true)) {
                        // Aceita vírgula como decimal (formato BR)
                        $valNum = (float) str_replace(['.', ','], ['', '.'], $raw);
                    } elseif (in_array($field['field_type'], ['date', 'datetime'], true)) {
                        $valDate = $this->parseDate($raw);
                    } else {
                        $valText = $raw;
                    }

                    \DB::run(
                        'INSERT INTO record_values (record_id, field_id, val_text, val_num, val_date)
                         VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                             val_text = VALUES(val_text),
                             val_num  = VALUES(val_num),
                             val_date = VALUES(val_date)',
                        [$recordId, $field['id'], $valText, $valNum, $valDate]
                    );
                }

                $imported++;

            } catch (\Throwable $e) {
                $errors[] = "Linha " . ($lineNum + 1) . ": " . $e->getMessage();
            }
        }

        // Audit log
        \DB::exec(
            'INSERT INTO audit_log (user_id, action, entity_id, description, ip, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [
                $userId,
                'import_records',
                $entityId,
                "Importação CSV: {$imported} registro(s) importado(s).",
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );

        // Salvar resultado na sessão
        $session['result'] = [
            'imported' => $imported,
            'errors'   => $errors,
            'total'    => count($rows),
        ];
        $data['session'] = $session;
        file_put_contents(
            $_SESSION['importer_file'],
            '<?php return ' . var_export($data, true) . ';'
        );

        redirect("/importer/result/{$token}");
    }

    // ── STEP 3 — Resultado ────────────────────────────────────────────

    public function result(string $token): void
    {
        \Auth::require(['admin', 'editor']);
        $data = $this->loadSession($token);
        if (!$data) { flash('err', 'Sessão não encontrada.'); redirect('/importer'); }

        $session = $data['session'];
        $result  = $session['result'] ?? null;
        if (!$result) { redirect("/importer/run/{$token}"); }

        // Limpar arquivo temporário
        if (isset($_SESSION['importer_file']) && file_exists($_SESSION['importer_file'])) {
            unlink($_SESSION['importer_file']);
        }
        unset($_SESSION['importer_token'], $_SESSION['importer_file']);

        $this->view('result', compact('session', 'result'));
    }

    // ── Internals ─────────────────────────────────────────────────────

    private function loadSession(string $token): ?array
    {
        $file = $_SESSION['importer_file'] ?? null;
        if (!$file || !file_exists($file)) return null;

        $data = include $file;
        if (!is_array($data) || ($data['session']['token'] ?? '') !== $token) return null;

        // Sessão expira em 2 horas
        if (time() - ($data['session']['created_at'] ?? 0) > 7200) {
            unlink($file);
            return null;
        }

        return $data;
    }

    /**
     * Detecta tipos sugeridos para cada coluna analisando até 50 linhas.
     */
    private function detectTypes(array $headers, array $rows): array
    {
        $sample  = array_slice($rows, 0, 50);
        $suggest = [];

        foreach ($headers as $i => $header) {
            $values = array_filter(array_map(fn($r) => trim($r[$i] ?? ''), $sample));
            $values = array_values($values);

            if (empty($values)) { $suggest[$i] = 'text'; continue; }

            // Email
            if (str_contains(strtolower($header), 'email') || str_contains(strtolower($header), 'e-mail')) {
                $suggest[$i] = 'email'; continue;
            }
            // Telefone / fone / celular
            if (preg_match('/fone|phone|celular|tel\b/i', $header)) {
                $suggest[$i] = 'phone'; continue;
            }
            // URL / site / link
            if (preg_match('/url|site|link|http/i', $header)) {
                $suggest[$i] = 'url'; continue;
            }
            // Data
            $dateSamples = count(array_filter($values, fn($v) => $this->parseDate($v) !== null));
            if ($dateSamples / count($values) >= 0.7) {
                $suggest[$i] = 'date'; continue;
            }
            // Número / moeda
            $numSamples = count(array_filter($values, function ($v) {
                return is_numeric(str_replace(['.', ',', 'R$', ' '], ['', '.', '', ''], $v));
            }));
            if ($numSamples / count($values) >= 0.8) {
                $suggest[$i] = preg_match('/valor|preco|preço|total|custo|salario|salário|r\$/i', $header)
                    ? 'currency'
                    : 'number';
                continue;
            }
            // Texto longo (> 80 chars em média)
            $avgLen = array_sum(array_map('strlen', $values)) / count($values);
            if ($avgLen > 80) { $suggest[$i] = 'textarea'; continue; }

            $suggest[$i] = 'text';
        }

        return $suggest;
    }

    private function parseDate(string $value): ?string
    {
        if (!$value) return null;

        // Formatos BR: dd/mm/yyyy, dd-mm-yyyy
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        // ISO: yyyy-mm-dd
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return $value;
        }
        // Tenta strtotime como fallback
        $ts = strtotime($value);
        if ($ts && $ts > 0) return date('Y-m-d', $ts);

        return null;
    }

    private function makeSlug(string $value): string
    {
        return $this->makeSlugPublic($value);
    }

    public function makeSlugPublic(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim($value, '_');
    }

    private function view(string $tpl, array $data = []): void
    {
        $data['page_title']  = 'Data Importer';
        $data['active_page'] = 'plugins';
        $data['breadcrumbs'] = [
            ['label' => 'Plugins', 'url' => '/plugins'],
            ['label' => '📥 Data Importer'],
        ];
        extract($data);
        include __DIR__ . "/views/{$tpl}.php";
    }
}
