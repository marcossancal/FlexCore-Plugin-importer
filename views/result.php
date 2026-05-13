<?php partial('layout/header', [
    'page_title'  => $page_title,
    'active_page' => $active_page,
    'breadcrumbs' => $breadcrumbs,
]) ?>

<div class="sec-head">
  <div>
    <div class="sec-title">📥 FlexCore Data Importer</div>
    <div class="sec-sub">Resultado da importação</div>
  </div>
</div>

<div style="max-width:600px;margin:0 auto">
  <div class="card" style="text-align:center;padding:40px 32px">

    <?php if ($result['imported'] > 0 && empty($result['errors'])): ?>
      <!-- Sucesso total -->
      <div style="font-size:3rem;margin-bottom:16px">✅</div>
      <div style="font-family:var(--fd);font-weight:700;font-size:1.2rem;color:var(--gn);margin-bottom:8px">
        Importação concluída!
      </div>
      <div style="color:var(--mt);margin-bottom:28px">
        <strong style="color:var(--tx)"><?= number_format($result['imported']) ?></strong> de
        <strong style="color:var(--tx)"><?= number_format($result['total']) ?></strong> registros
        importados com sucesso para
        <strong style="color:var(--ac)"><?= h($session['entity_name']) ?></strong>.
      </div>

    <?php elseif ($result['imported'] > 0): ?>
      <!-- Sucesso parcial -->
      <div style="font-size:3rem;margin-bottom:16px">⚠️</div>
      <div style="font-family:var(--fd);font-weight:700;font-size:1.2rem;color:var(--am);margin-bottom:8px">
        Importação parcial
      </div>
      <div style="color:var(--mt);margin-bottom:20px">
        <strong style="color:var(--gn)"><?= number_format($result['imported']) ?></strong> registros importados,
        <strong style="color:var(--rd)"><?= number_format(count($result['errors'])) ?></strong> com erro.
      </div>

    <?php else: ?>
      <!-- Falha total -->
      <div style="font-size:3rem;margin-bottom:16px">❌</div>
      <div style="font-family:var(--fd);font-weight:700;font-size:1.2rem;color:var(--rd);margin-bottom:8px">
        Importação falhou
      </div>
      <div style="color:var(--mt);margin-bottom:20px">
        Nenhum registro foi importado. Verifique os erros abaixo.
      </div>
    <?php endif; ?>

    <!-- Resumo em números -->
    <div style="display:flex;gap:16px;justify-content:center;margin-bottom:28px">
      <div style="background:var(--sf2);border-radius:var(--r2);padding:14px 24px;flex:1">
        <div style="font-size:1.6rem;font-weight:700;color:var(--gn)"><?= number_format($result['imported']) ?></div>
        <div style="font-size:.75rem;color:var(--mt)">Importados</div>
      </div>
      <div style="background:var(--sf2);border-radius:var(--r2);padding:14px 24px;flex:1">
        <div style="font-size:1.6rem;font-weight:700;color:<?= count($result['errors']) > 0 ? 'var(--rd)' : 'var(--mt)' ?>"><?= number_format(count($result['errors'])) ?></div>
        <div style="font-size:.75rem;color:var(--mt)">Erros</div>
      </div>
      <div style="background:var(--sf2);border-radius:var(--r2);padding:14px 24px;flex:1">
        <div style="font-size:1.6rem;font-weight:700;color:var(--tx)"><?= number_format($result['total']) ?></div>
        <div style="font-size:.75rem;color:var(--mt)">Total</div>
      </div>
    </div>

    <!-- Erros detalhados -->
    <?php if (!empty($result['errors'])): ?>
    <div style="background:color-mix(in srgb,var(--rd) 8%,transparent);border:1px solid color-mix(in srgb,var(--rd) 25%,transparent);border-radius:var(--r2);padding:16px;text-align:left;margin-bottom:24px;max-height:200px;overflow-y:auto">
      <div style="font-size:.75rem;font-weight:700;color:var(--rd);margin-bottom:8px;text-transform:uppercase;letter-spacing:.07em">Erros</div>
      <?php foreach ($result['errors'] as $err): ?>
      <div style="font-size:.8rem;color:var(--mt2);padding:3px 0;border-bottom:1px solid var(--bd)"><?= h($err) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Ações -->
    <div style="display:flex;gap:12px;justify-content:center">
      <a href="<?= url('/importer') ?>" class="btn btn-ghost">📥 Nova importação</a>
      <a href="<?= url('/e/' . h($session['entity_slug'])) ?>" class="btn btn-primary">
        Ver registros de <?= h($session['entity_name']) ?> →
      </a>
    </div>

  </div>
</div>

<?php partial('layout/footer') ?>
