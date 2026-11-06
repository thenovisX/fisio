<?php
/*
 * El cliente llega aquí desde el enlace del WhatsApp. Variables: $ok, $pendiente, $estado, $reserva, $fecha
 * (dia_semana, dia, mes, texto), $error (titulo, texto), $ics_url (añadir al calendario) y $confirmar_form
 * (función que pinta el botón «Confirmar mi cita»: abrir el enlace NO confirma, hay que pulsarlo) y
 * $cancelar_form (botón «Cancelar la cita»).
 */
require __DIR__ . '/partes.php';
abrir(($ok ? t('Cita confirmada') : ($pendiente ? t('Confirma tu cita') : t('Cita'))) . ' · ' . $s['name'], 'pagina');
?>
<main id="main" class="cont confirmar">
<?php if ($ok || $pendiente): $nombre = h(explode(' ', trim($reserva['name']))[0]); ?>
  <h1 class="titulo"><?= $pendiente ? t('Confirma tu cita') : ($estado === 'ya' ? t('Ya está confirmada') : t('¡Cita confirmada!')) ?></h1>
  <p class="texto-grande"><?= $pendiente ? t('Hola, %s. Revisa los datos y pulsa el botón para confirmarla.', $nombre) : t('Te esperamos, %s.', $nombre) ?></p>
  <section class="ticket" aria-label="<?= t('Datos de la cita') ?>">
    <p class="ticket-marca"><?= h($s['name']) ?></p>
    <p class="ticket-fecha"><span><?= $fecha['dia_semana'] ?></span><b><?= $fecha['dia'] ?></b><span><?= $fecha['mes'] ?></span></p>
    <dl>
      <div><dt><?= t('Hora') ?></dt><dd><?= h($reserva['time']) ?></dd></div>
      <div><dt><?= t('Duración') ?></dt><dd><?= (int)$reserva['duration'] ?> min</dd></div>
      <?php if ($reserva['service'] !== ''): ?><div class="ancho"><dt><?= t('Tratamiento') ?></dt><dd><?= h(tr($reserva['service'])) ?></dd></div><?php endif; ?>
      <div class="ancho"><dt><?= t('A nombre de') ?></dt><dd><?= h($reserva['name']) ?></dd></div>
    </dl>
    <?php if ($s['address']): ?><p class="nota"><?= h($s['address']) ?><?= $s['city'] ? ', ' . h($s['city']) : '' ?></p><?php endif; ?>
    <p class="nota"><?= t('Ven con ropa cómoda y, si es tu primera visita, trae tus informes. Si no puedes venir, cancelar es gratis.') ?></p>
  </section>
  <?php if ($pendiente): ?>
  <div class="confirmar-boton"><?= $confirmar_form(t('Confirmar mi cita'), 'boton boton-acento') ?></div>
  <p class="nota"><?= t('¿Algún dato está mal? No la confirmes: <a href="./#reservar" data-book>pide una cita nueva</a> con los datos correctos.') ?></p>
  <?php else: ?>
  <div class="botones">
    <a class="boton boton-acento" href="<?= h($ics_url) ?>"><?= t('Añadir al calendario') ?></a>
    <?php if ($s['address']): ?><a class="boton" href="<?= h(maps_href()) ?>" target="_blank" rel="noopener"><?= t('Cómo llegar') ?></a><?php endif; ?>
    <a class="boton" href="./#tratamientos"><?= t('Ver tratamientos') ?></a>
  </div>
  <?php if ($s['phone']): ?><p class="nota"><?= t('¿Algún cambio? Llámanos al %s.', '<a href="' . h(tel_href($s['phone'])) . '">' . h($s['phone']) . '</a>') ?></p><?php endif; ?>
  <?php endif; ?>
  <div class="confirmar-cancelar"><span><?= t('¿No puedes venir?') ?></span> <?= $cancelar_form(t('Cancelar la cita'), 'enlace-boton') ?></div>
<?php else: ?>
  <h1 class="titulo"><?= h($error['titulo']) ?></h1>
  <p class="texto-grande"><?= h($error['texto']) ?></p>
  <div class="botones">
    <a class="boton boton-acento" href="./#reservar" data-book><?= t('Pedir cita') ?></a>
    <?php if ($s['phone']): ?><a class="boton" href="<?= h(tel_href($s['phone'])) ?>"><?= t('Llamar') ?></a><?php endif; ?>
  </div>
<?php endif; ?>
</main>
<?php cerrar();
