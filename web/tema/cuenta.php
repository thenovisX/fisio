<?php
/*
 * Cuenta del cliente. Variables: $cliente (null si no ha entrado), $reservas (upcoming/past), $activada,
 * $enlace_caducado, $registro. El comportamiento lo pone el SDK con atributos data-*: ver TEMA.md.
 */
require __DIR__ . '/partes.php';
$estados = ['pendiente' => t('Petición enviada: esperando al centro'), 'esperando' => t('Falta que la confirmes'), 'confirmada' => t('Confirmada')];
abrir(($cliente ? t('Mi cuenta') : t('Entrar')) . ' · ' . $s['name'], 'pagina');
?>
<main id="main" class="cont cuenta">
<?php if (!$cliente): ?>
  <div class="dos">
    <div>
      <h1 class="titulo"><?= t('Tu cuenta') ?></h1>
      <?php if ($enlace_caducado): ?><p class="aviso"><?= t('El enlace de activación no es válido o ha caducado. Vuelve a crear la cuenta o pide otro enlace.') ?></p><?php endif; ?>
      <p class="texto-grande"><?= t('Pide cita sin escribir tus datos cada vez, mira tus próximas citas y cancélalas tú mismo cuando quieras, sin coste.') ?></p>
    </div>
    <div class="caja">
      <div class="pestanas" data-tabs role="tablist">
        <button type="button" role="tab" aria-controls="f-entrar" aria-selected="<?= $registro ? 'false' : 'true' ?>"><?= t('Entrar') ?></button>
        <button type="button" role="tab" aria-controls="f-registro" aria-selected="<?= $registro ? 'true' : 'false' ?>"><?= t('Crear cuenta') ?></button>
      </div>
      <form class="form" id="f-entrar" data-api="c_login" <?= $registro ? 'hidden' : '' ?> novalidate>
        <label>Email <input name="email" type="email" autocomplete="email" required></label>
        <label><?= t('Contraseña') ?> <input name="password" type="password" autocomplete="current-password" required></label>
        <p class="error" data-error role="alert"></p>
        <button class="boton boton-acento" type="submit"><?= t('Entrar') ?></button>
        <p class="nota"><a href="recuperar.php"><?= t('¿Has olvidado la contraseña?') ?></a></p>
      </form>
      <form class="form" id="f-registro" data-api="c_register" <?= $registro ? '' : 'hidden' ?> novalidate>
        <label><?= t('Nombre') ?> <input name="name" autocomplete="name" maxlength="80" required></label>
        <label>Email <input name="email" type="email" autocomplete="email" maxlength="120" required></label>
        <label><?= t('Teléfono') ?> <span><?= t('opcional, para avisarte si hay algún cambio') ?></span><input name="phone" type="tel" autocomplete="tel" maxlength="30"></label>
        <label><?= t('Contraseña') ?> <span><?= t('mínimo 8 caracteres') ?></span><input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
        <label class="trampa" aria-hidden="true"><?= t('No rellenar') ?> <input name="website" tabindex="-1" autocomplete="off"></label>
        <label class="casilla"><input type="checkbox" name="privacy" value="1"><span><?= t('Acepto las <a href="legal.php#condiciones" target="_blank">condiciones de uso</a> y he leído la <a href="legal.php#privacidad" target="_blank">política de privacidad</a>.') ?></span></label>
        <p class="error" data-error role="alert"></p>
        <button class="boton boton-acento" type="submit"><?= t('Crear cuenta') ?></button>
        <p class="nota"><?= t('Te enviaremos un email para activar la cuenta. Las citas que hayas pedido con este mismo email aparecerán en tu cuenta al activarla; las que pediste sin email o con otro no se pueden añadir. No te enviaremos publicidad.') ?></p>
        <p class="nota pequena"><?= t('<strong>Protección de datos.</strong> Responsable: %s. Finalidad: gestionar tu cuenta y tus citas. No cedemos tus datos a nadie. Puedes descargarlos o borrar la cuenta desde aquí mismo, y ejercer el resto de derechos escribiendo a %s.', h($s['owner_name'] ?: $s['name']), h($s['email'] ?: t('nuestro contacto'))) ?> <a href="legal.php#privacidad" target="_blank"><?= t('Más información') ?></a>.</p>
      </form>
      <div data-registro-ok hidden>
        <h2><?= t('Revisa tu email') ?></h2>
        <p><?= t('Te hemos enviado un enlace a %s para activar tu cuenta. Caduca en 48 horas. Si no lo ves, mira en la carpeta de spam.', '<strong data-registro-email></strong>') ?></p>
        <button class="boton" type="button" data-resend><?= t('Enviar el email otra vez') ?></button>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="cuenta-cab">
    <h1 class="titulo"><?= t('Hola, %s', h(explode(' ', $cliente['name'])[0])) ?></h1>
    <?php if ($activada): ?><p class="aviso aviso-ok"><?= t('Cuenta activada. Ya puedes pedir cita sin escribir tus datos.') ?></p><?php endif; ?>
    <a class="boton boton-acento" href="#reservar" data-book><?= t('Pedir cita') ?></a>
  </div>

  <section class="cuenta-sec">
    <h2><?= t('Próximas citas') ?></h2>
    <?php if (!$reservas['upcoming']): ?><p><?= t('No tienes citas pendientes. <a href="#reservar" data-book>Pide una</a>.') ?></p><?php endif; ?>
    <ul class="lista-res">
      <?php foreach ($reservas['upcoming'] as $r): ?>
        <li>
          <div>
            <p class="res-cuando"><?= h(ucfirst($r['when'])) ?></p>
            <p class="nota"><?= h(($r['service'] ?? '') !== '' ? tr($r['service']) : t('Cita')) ?> · <span class="estado estado-<?= $r['status'] ?>"><?= $estados[$r['status']] ?? $r['status'] ?></span></p>
            <?php if ($r['notes']): ?><p class="nota"><?= h($r['notes']) ?></p><?php endif; ?>
          </div>
          <div class="botones">
            <?php if ($r['status'] === 'esperando'): ?><button class="boton boton-acento" type="button" data-confirm-res="<?= $r['id'] ?>"><?= t('Confirmar') ?></button><?php endif; ?>
            <?php if ($r['can_cancel']): ?><button class="boton" type="button" data-cancel="<?= $r['id'] ?>" data-when="<?= h($r['when']) ?>"><?= t('Cancelar') ?></button><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="nota"><?= t('Aquí salen las citas pedidas con tu cuenta o con el email %s. Las que pediste sin email o con otro email no aparecen.', h($cliente['email'])) ?></p>
  </section>

  <?php if ($reservas['past']): ?>
    <section class="cuenta-sec">
      <h2><?= t('Historial') ?></h2>
      <ul class="lista-res lista-pasadas">
        <?php foreach (array_slice($reservas['past'], 0, 20) as $r): ?>
          <li><div><p class="res-cuando"><?= h(ucfirst($r['when'])) ?></p><p class="nota"><?= h(($r['service'] ?? '') !== '' ? tr($r['service']) : t('Cita')) ?> · <?= [ 'rechazada' => t('No pudimos atenderla'), 'cancelada' => t('Cancelada')][$r['status']] ?? t('Pasada') ?></p></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="cuenta-sec">
    <h2><?= t('Mis datos') ?></h2>
    <form class="form" data-api="c_profile" novalidate>
      <div class="fila">
        <label><?= t('Nombre') ?> <input name="name" value="<?= h($cliente['name']) ?>" autocomplete="name" required></label>
        <label><?= t('Teléfono') ?> <input name="phone" type="tel" value="<?= h($cliente['phone']) ?>" autocomplete="tel"></label>
      </div>
      <label>Email <input name="email" type="email" value="<?= h($cliente['email']) ?>" autocomplete="email" required></label>
      <p class="nota"><?= t('Para cambiar el email, escribe también tu contraseña actual en «Cambiar contraseña».') ?></p>
      <details>
        <summary><?= t('Cambiar contraseña') ?></summary>
        <div class="fila">
          <label><?= t('Contraseña actual') ?> <input name="password" type="password" autocomplete="current-password"></label>
          <label><?= t('Nueva contraseña') ?> <input name="new_password" type="password" autocomplete="new-password" minlength="8"></label>
        </div>
      </details>
      <p class="error" data-error role="alert"></p>
      <button class="boton" type="submit"><?= t('Guardar cambios') ?></button>
    </form>
  </section>

  <section class="cuenta-sec">
    <h2><?= t('Tus datos y tu sesión') ?></h2>
    <div class="botones">
      <a class="boton" href="api/?r=c_export" download="mis-datos.json"><?= t('Descargar mis datos') ?></a>
      <button class="boton" type="button" data-logout><?= t('Cerrar sesión') ?></button>
    </div>
    <details class="borrar">
      <summary><?= t('Borrar mi cuenta') ?></summary>
      <form class="form" data-api="c_delete" data-confirm="<?= h(t('¿Seguro que quieres borrar tu cuenta? No se puede deshacer.')) ?>" novalidate>
        <p class="nota"><?= t('Borramos tus datos de acceso. Tus citas pendientes siguen en pie: si quieres anularlas, cancélalas antes.') ?></p>
        <label><?= t('Escribe tu contraseña para confirmar') ?> <input name="password" type="password" autocomplete="current-password"></label>
        <p class="error" data-error role="alert"></p>
        <button class="boton boton-peligro" type="submit"><?= t('Borrar mi cuenta') ?></button>
      </form>
    </details>
  </section>
<?php endif; ?>
</main>
<?php cerrar();
