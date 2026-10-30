<?php
/* Información legal. Variables: $secciones (id, titulo, html). Los textos los genera el backend. */
require __DIR__ . '/partes.php';
abrir(t('Información legal') . ' · ' . $s['name'], 'pagina');
?>
<main id="main" class="cont legal">
  <h1 class="titulo"><?= t('Información legal') ?></h1>
  <?php if (legal_aviso_traduccion()): ?><p class="nota"><?= h(legal_aviso_traduccion()) ?></p><?php endif; ?>
  <nav class="legal-indice" aria-label="<?= t('Apartados') ?>"><?php foreach ($secciones as $sec): ?><a href="#<?= $sec['id'] ?>"><?= h($sec['titulo']) ?></a><?php endforeach; ?></nav>
  <?php foreach ($secciones as $sec): ?>
    <section id="<?= $sec['id'] ?>">
      <h2><?= h($sec['titulo']) ?></h2>
      <?= $sec['html'] ?>
    </section>
  <?php endforeach; ?>
  <section id="borrar-datos">
    <h2><?= t('Borrar todos mis datos') ?></h2>
    <?= borrar_form('boton boton-acento') ?>
  </section>
  <p class="nota"><?= t('Última actualización: %s.', h(date('j/n/Y', strtotime(LEGAL_VERSION)))) ?></p>
</main>
<?php cerrar();
