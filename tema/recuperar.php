<?php
/*
 * Recuperar la contraseña del cliente. Variables: $estado (pedir, nueva, invalido) y $token (solo en «nueva»).
 * El SDK envía los formularios data-api="c_forgot" (muestra [data-forgot-ok]) y data-api="c_reset" (entra en la cuenta).
 */
require __DIR__ . '/partes.php';
abrir(t('Recuperar la contraseña') . ' · ' . $s['name'], 'pagina');
?>
<main id="main" class="cont confirmar">
<?php if ($estado === 'nueva'): ?>
  <h1 class="titulo"><?= t('Elige una contraseña nueva') ?></h1>
  <form class="form" data-api="c_reset" novalidate>
    <input type="hidden" name="t" value="<?= h($token) ?>">
    <label><?= t('Nueva contraseña') ?> <span><?= t('mínimo 8 caracteres') ?></span><input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
    <p class="error" data-error role="alert"></p>
    <button class="boton boton-acento" type="submit"><?= t('Guardar y entrar') ?></button>
  </form>
<?php else: ?>
  <?php if ($estado === 'invalido'): ?>
    <h1 class="titulo"><?= t('Este enlace ya no funciona') ?></h1>
    <p class="texto-grande"><?= t('Puede que ya lo hayas usado o que haya caducado. Puedes pedir otro aquí abajo.') ?></p>
  <?php else: ?>
    <h1 class="titulo"><?= t('Recuperar la contraseña') ?></h1>
    <p class="texto-grande"><?= t('Escribe el email de tu cuenta y te enviaremos un enlace para elegir una contraseña nueva.') ?></p>
  <?php endif; ?>
  <form class="form" data-api="c_forgot" novalidate>
    <label>Email <input name="email" type="email" autocomplete="email" maxlength="120" required></label>
    <p class="error" data-error role="alert"></p>
    <button class="boton boton-acento" type="submit"><?= t('Enviar el enlace') ?></button>
  </form>
  <p class="aviso aviso-ok" data-forgot-ok hidden role="status"><?= t('Si hay una cuenta con ese email, te hemos enviado un enlace. Caduca en 1 hora. Si no lo ves, mira en la carpeta de spam.') ?></p>
  <div class="botones"><a class="boton" href="cuenta.php"><?= t('Volver a entrar') ?></a></div>
<?php endif; ?>
</main>
<?php cerrar();
