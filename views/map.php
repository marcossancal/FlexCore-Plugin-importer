<?php partial('layout/header', [
    'page_title'  => $page_title,
    'active_page' => $active_page,
    'breadcrumbs' => $breadcrumbs,
]) ?>

<div class="sec-head">
  <div>
    <div class="sec-title">📥 FlexCore Data Importer</div>
    <div class="sec-sub">
      Passo 2 — Mapeamento de campos &rarr;
      <strong><?= h($session['entity_name']) ?></strong>
      <span style="color:var(--mt);margin-left:8px"><?= number_format($session['total_rows']) ?> linhas detectadas</span>
    </div>
  </div>
  <div class="sec-actions">
    <a href="<?= url('/importer') ?>" class="btn btn-ghost btn-sm">← Recomeçar</a>
  </div>
</div>

<!-- Preview do CSV -->
<div class="card" style="margin-bottom:20px">
  <div class="card-title" style="margin-bottom:12px">👁 Preview (primeiras <?= count($preview) ?> linhas)</div>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.8rem">
      <thead>
        <tr>
          <?php foreach ($session['headers'] as $h): ?>
          <th style="padding:6px 10px;background:var(--sf2);border:1px solid var(--bd);text-align:left;white-space:nowrap;color:var(--ac)"><?= h($h) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($preview as $row): ?>
        <tr>
          <?php foreach ($session['headers'] as $i => $_): ?>
          <td style="padding:5px 10px;border:1px solid var(--bd);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--mt2)"
              title="<?= h($row[$i] ?? '') ?>">
            <?= h(mb_strimwidth($row[$i] ?? '', 0, 40, '…')) ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Formulário de mapeamento -->
<form method="POST" action="<?= url("/importer/map/{$token}") ?>">
  <div class="card">
    <div class="card-title" style="margin-bottom:4px">🔧 Configurar campos</div>
    <div style="font-size:.82rem;color:var(--mt);margin-bottom:20px">
      Colunas marcadas como <strong style="color:var(--am)">existentes</strong> já têm campo criado na entidade — apenas mapeie o tipo.
      Colunas novas serão criadas automaticamente.
    </div>

    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="font-size:.75rem;color:var(--mt);text-transform:uppercase;letter-spacing:.06em">
            <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--bd)">Coluna CSV</th>
            <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--bd)">Nome do campo</th>
            <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--bd)">Slug</th>
            <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--bd)">Tipo</th>
            <th style="padding:8px 12px;text-align:center;border-bottom:1px solid var(--bd)">Obrigatório</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($session['headers'] as $i => $header):
            $slug         = $headerSlugs[$i];
            $existingSlug = in_array($slug, $session['existing_slugs'], true);
            $suggested    = $session['suggested'][$i] ?? 'text';
          ?>
          <tr style="border-bottom:1px solid var(--bd);<?= $existingSlug ? 'background:color-mix(in srgb,var(--am) 6%,transparent)' : '' ?>">
            <!-- Coluna original -->
            <td style="padding:10px 12px;font-size:.83rem">
              <code style="background:var(--sf2);padding:2px 7px;border-radius:4px;color:var(--ac);font-size:.78rem"><?= h($header) ?></code>
              <?php if ($existingSlug): ?>
              <span style="font-size:.7rem;color:var(--am);margin-left:4px">existente</span>
              <?php endif; ?>
            </td>

            <!-- Nome do campo -->
            <td style="padding:10px 12px">
              <input type="text" name="name[<?= $i ?>]"
                     value="<?= h(ucwords(str_replace(['_','-'], ' ', $header))) ?>"
                     style="width:100%;min-width:120px"
                     <?= $existingSlug ? 'readonly style="opacity:.6;width:100%;min-width:120px"' : '' ?>>
            </td>

            <!-- Slug -->
            <td style="padding:10px 12px">
              <input type="text" name="slug[<?= $i ?>]"
                     value="<?= h($slug) ?>"
                     style="width:100%;min-width:100px;font-family:monospace;font-size:.82rem"
                     <?= $existingSlug ? 'readonly style="opacity:.6;width:100%;min-width:100px;font-family:monospace;font-size:.82rem"' : '' ?>>
            </td>

            <!-- Tipo de campo -->
            <td style="padding:10px 12px">
              <select name="mapping[<?= $i ?>]" style="width:100%;min-width:140px">
                <?php foreach ($fieldTypes as $val => $label): ?>
                <option value="<?= h($val) ?>" <?= $suggested === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </td>

            <!-- Obrigatório -->
            <td style="padding:10px 12px;text-align:center">
              <input type="checkbox" name="required[<?= $i ?>]" value="1" style="width:16px;height:16px">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="form-actions" style="margin-top:16px">
    <a href="<?= url('/importer') ?>" class="btn btn-ghost">← Cancelar</a>
    <button type="submit" class="btn btn-primary">Criar campos e continuar →</button>
  </div>
</form>

<script>
// Auto-gera slug a partir do nome
document.querySelectorAll('input[name^="name["]').forEach(function(input) {
  input.addEventListener('input', function() {
    const i = this.name.match(/\[(\d+)\]/)[1];
    const slugInput = document.querySelector('input[name="slug[' + i + ']"]');
    if (slugInput && !slugInput.readOnly) {
      slugInput.value = this.value
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_|_$/g, '');
    }
  });
});

// Ao escolher "Ignorar", desabilita os outros campos da linha
document.querySelectorAll('select[name^="mapping["]').forEach(function(sel) {
  sel.addEventListener('change', function() {
    const i   = this.name.match(/\[(\d+)\]/)[1];
    const row = this.closest('tr');
    const dis = this.value === '_skip';
    row.querySelectorAll('input[type="text"]').forEach(function(inp) {
      inp.disabled = dis;
      inp.style.opacity = dis ? '.35' : '1';
    });
  });
});
</script>

<?php partial('layout/footer') ?>
