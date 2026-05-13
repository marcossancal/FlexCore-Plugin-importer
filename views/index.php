<?php partial('layout/header', [
    'page_title'  => $page_title,
    'active_page' => $active_page,
    'breadcrumbs' => $breadcrumbs,
]) ?>

<div class="sec-head">
  <div>
    <div class="sec-title">📥 FlexCore Data Importer</div>
    <div class="sec-sub">Importe dados de CSV direto para qualquer entidade</div>
  </div>
</div>

<div class="row2" style="gap:24px;align-items:start">

  <!-- Formulário principal -->
  <div style="flex:2">
    <div class="card">
      <div class="card-title">📁 Passo 1 — Selecionar arquivo e configurar</div>

      <form method="POST" action="<?= url('/importer/upload') ?>" enctype="multipart/form-data">

        <div class="field">
          <label>Entidade de destino *</label>
          <select name="entity_id" required>
            <option value="">— Selecione —</option>
            <?php foreach ($entities as $e): ?>
            <option value="<?= $e['id'] ?>"><?= h($e['icon'] . ' ' . $e['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Os dados serão importados como registros desta entidade.</div>
        </div>

        <div class="field">
          <label>Arquivo CSV *</label>
          <input type="file" name="csv_file" accept=".csv,text/csv" required
                 style="background:var(--sf2);border:1px solid var(--bd2);border-radius:var(--r2);color:var(--tx);padding:9px 12px;width:100%">
        </div>

        <div class="row2" style="gap:16px">
          <div class="field" style="flex:1">
            <label>Delimitador</label>
            <select name="delimiter">
              <option value="comma">Vírgula  ( , )</option>
              <option value="semicolon" selected>Ponto e vírgula  ( ; )</option>
              <option value="tab">Tabulação  ( TAB )</option>
              <option value="pipe">Pipe  ( | )</option>
            </select>
          </div>
          <div class="field" style="flex:1">
            <label>Delimitador de texto</label>
            <select name="enclosure">
              <option value='"' selected>Aspas duplas ( " )</option>
              <option value="'">Aspas simples ( ' )</option>
            </select>
          </div>
        </div>

        <div class="row2" style="gap:16px">
          <div class="field" style="flex:1">
            <label>Encoding</label>
            <select name="encoding">
              <option value="utf-8" selected>UTF-8</option>
              <option value="iso-8859-1">ISO-8859-1 (Latin-1)</option>
              <option value="windows-1252">Windows-1252 (ANSI)</option>
            </select>
          </div>
          <div class="field" style="flex:1;padding-top:24px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="has_header" value="1" checked style="width:16px;height:16px">
              Primeira linha é cabeçalho
            </label>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Continuar →</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Instruções -->
  <div style="flex:1">
    <div class="card" style="margin:0">
      <div class="card-title">💡 Como funciona</div>
      <div style="display:flex;flex-direction:column;gap:14px;font-size:.85rem;color:var(--mt2)">
        <div style="display:flex;gap:10px">
          <span style="background:var(--ac);color:#000;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;flex-shrink:0">1</span>
          <div><strong style="color:var(--tx)">Upload</strong><br>Selecione a entidade de destino e envie o CSV.</div>
        </div>
        <div style="display:flex;gap:10px">
          <span style="background:var(--ac);color:#000;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;flex-shrink:0">2</span>
          <div><strong style="color:var(--tx)">Mapeamento</strong><br>Veja as colunas detectadas, defina o tipo de cada campo e escolha o nome.</div>
        </div>
        <div style="display:flex;gap:10px">
          <span style="background:var(--ac);color:#000;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;flex-shrink:0">3</span>
          <div><strong style="color:var(--tx)">Importação</strong><br>Campos novos são criados automaticamente e os dados são importados.</div>
        </div>
      </div>

      <div style="margin-top:20px;padding:12px;background:var(--sf2);border-radius:var(--r2);font-size:.8rem;color:var(--mt)">
        <strong style="color:var(--am)">⚠ Atenção:</strong> A importação cria novos registros. Não sobrescreve dados existentes.
      </div>
    </div>
  </div>

</div>

<?php partial('layout/footer') ?>
