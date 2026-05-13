<?php partial('layout/header', [
    'page_title'  => $page_title,
    'active_page' => $active_page,
    'breadcrumbs' => $breadcrumbs,
]) ?>

<div class="sec-head">
  <div>
    <div class="sec-title">📥 FlexCore Data Importer</div>
    <div class="sec-sub">Passo 3 — Confirmar importação</div>
  </div>
</div>

<div style="max-width:560px;margin:0 auto">
  <div class="card" style="text-align:center;padding:40px 32px">
    <div style="font-size:3rem;margin-bottom:16px">📥</div>
    <div style="font-family:var(--fd);font-weight:700;font-size:1.2rem;margin-bottom:8px">
      Pronto para importar
    </div>
    <div style="color:var(--mt);margin-bottom:28px;line-height:1.7">
      <strong style="color:var(--tx)"><?= number_format($session['total_rows']) ?> registros</strong>
      serão criados na entidade
      <strong style="color:var(--ac)"><?= h($session['entity_name']) ?></strong>.
      <br>Esta ação não pode ser desfeita.
    </div>

    <!-- Resumo das colunas -->
    <div style="background:var(--sf2);border-radius:var(--r2);padding:16px;text-align:left;margin-bottom:28px">
      <div style="font-size:.75rem;color:var(--mt);font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px">Colunas que serão importadas</div>
      <?php
        $mapping = $session['mapping'] ?? [];
        $names   = $session['names']   ?? [];
        foreach ($session['headers'] as $i => $header):
          $type = $mapping[$i] ?? '_skip';
          if ($type === '_skip') continue;
          $fieldName = $names[$i] ?? $header;
      ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--bd);font-size:.83rem">
        <span style="color:var(--mt2)"><?= h($fieldName) ?></span>
        <code style="font-size:.75rem;background:var(--sf3);padding:2px 7px;border-radius:4px;color:var(--ac)"><?= h($type) ?></code>
      </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" action="<?= url("/importer/run/{$token}") ?>">
      <div style="display:flex;gap:12px;justify-content:center">
        <a href="<?= url('/importer') ?>" class="btn btn-ghost">Cancelar</a>
        <button type="submit" class="btn btn-primary" id="btn-import">
          ✅ Importar <?= number_format($session['total_rows']) ?> registros
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelector('#btn-import')?.closest('form').addEventListener('submit', function() {
  const btn = document.getElementById('btn-import');
  btn.disabled = true;
  btn.textContent = '⏳ Importando...';
});
</script>

<?php partial('layout/footer') ?>
