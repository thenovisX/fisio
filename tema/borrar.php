<?php
/*
 * Borrar mis datos. Variables: $estado (pedir, confirmar, hecho, invalido), $contacto, $resumen (accounts, reservations,
 * future), $hecho (accounts, reservations, cancelled) y $borrar_boton (función que pinta el botón que borra de verdad).
 * En «pedir» e «invalido» pinta borrar_form().
 */
require __DIR__ . '/partes.php';
abrir(t('Borrar mis datos') . ' · ' . $s['name'], 'pagina');
?>
<main id="main" class="cont confirmar">
<?php if ($estado === 'hecho'): ?>
  <h1 class="titulo"><?= t('Datos borrados') ?></h1>
  <p class="texto-grande"><?= t('Hemos borrado todo lo que guardábamos con ese contacto.') ?><?= $hecho['cancelled'] ? ' ' . t('Las reservas que tenías pendientes se han cancelado.') : '' ?></p>
  <div class="botones"><a class="boton" href="./"><?= t('Volver al inicio') ?></a></div>
<?php elseif ($estado === 'confirmar'): ?>
  <h1 class="titulo"><?= t('¿Borrar todos tus datos?') ?></h1>
  <p class="texto-grande"><?= h(t('Con %s guardamos:', $contacto)) ?></p>
  <ul>
    <li><?= $resumen['accounts'] ? t('Tu cuenta de cliente') : t('Ninguna cuenta de cliente') ?></li>
    <li><?= $resumen['reservations'] === 1 ? t('1 reserva') : t('%d reservas', $resumen['reservations']) ?><?= $resumen['future'] ? ' (' . ($resumen['future'] === 1 ? t('1 aún no ha pasado y se cancelará') : t('%d aún no han pasado y se cancelarán', $resumen['future'])) . ')' : '' ?></li>
  </ul>
  <p class="nota"><?= t('No se puede deshacer. De las reservas solo quedarán el día, la hora y el número de personas, sin nada que te identifique.') ?></p>
  <div class="botones"><?= $borrar_boton('', 'boton boton-acento') ?><a class="boton" href="./"><?= t('No, volver') ?></a></div>
<?php else: ?>
  <?php if ($estado === 'invalido'): ?>
    <h1 class="titulo"><?= t('Este enlace ya no funciona') ?></h1>
    <p class="texto-grande"><?= t('Puede que ya lo hayas usado o que haya caducado. Puedes pedir otro aquí abajo.') ?></p>
  <?php else: ?>
    <h1 class="titulo"><?= t('Borrar mis datos') ?></h1>
    <p class="texto-grande"><?= t('Escribe el email o el teléfono con el que reservaste o te registraste.') ?></p>
  <?php endif; ?>
  <?= borrar_form('boton boton-acento') ?>
<?php endif; ?>
</main>
<?php cerrar();
